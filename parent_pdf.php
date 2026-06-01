<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * PDF report generator for student competencies or grades.
 *
 * @package    local_competency_report
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/tcpdf/tcpdf.php');
require_once(__DIR__ . '/ai.php');

$courseid     = required_param('courseid', PARAM_INT);
$userid       = optional_param('userid', $USER->id, PARAM_INT);
$focustype    = optional_param('focus_type', 'competency', PARAM_ALPHA); // 'competency' or 'grades'
$customprompt = optional_param('custom_prompt', '', PARAM_RAW);
$pdfcontent   = optional_param('pdf_content', '', PARAM_RAW);

require_login($courseid);

global $DB, $USER;

$context = context_course::instance($courseid);

// Permission check: User can view their own report, OR must have teacher capability.
if ($userid != $USER->id) {
    require_capability('mod/quiz:viewreports', $context);
}

$course  = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$student = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

$rates = [];
$stats = [];

if ($focustype === 'grades') {
    // 1. Fetch General Grades data.
    $sql = "SELECT q.id, q.name, qa.sumgrades as grade, q.sumgrades as maxgrade
            FROM {quiz_attempts} qa
            JOIN {quiz} q ON q.id = qa.quiz
            WHERE q.course = :courseid AND qa.userid = :userid AND qa.state = 'finished'";
    $rows = $DB->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $userid]);

    foreach ($rows as $r) {
        $percent = $r->maxgrade ? round(($r->grade / $r->maxgrade) * 100) : 0;
        $rates[] = [
            'name'  => $r->name,
            'score' => number_format($r->grade, 1) . ' / ' . number_format($r->maxgrade, 1),
            'rate'  => $percent,
        ];
        $stats[$r->name] = $percent;
    }
} else {
    // 2. Fetch Competency data.
    $sql = "
        SELECT c.id, c.shortname, c.description,
               CAST(SUM(qa.maxfraction) AS DECIMAL(12, 1)) AS attempts,
               CAST(SUM(qas.fraction) AS DECIMAL(12, 1)) AS correct
        FROM {quiz_attempts} quiza
        JOIN {user} u ON quiza.userid = u.id
        JOIN {question_usages} qu ON qu.id = quiza.uniqueid
        JOIN {question_attempts} qa ON qa.questionusageid = qu.id
        JOIN {quiz} quiz ON quiz.id = quiza.quiz
        JOIN {qbank_competency_qmap} m ON m.questionid = qa.questionid
        JOIN {competency} c ON c.id = m.competencyid
        JOIN (
            SELECT MAX(fraction) AS fraction, questionattemptid
            FROM {question_attempt_steps}
            GROUP BY questionattemptid
        ) qas ON qas.questionattemptid = qa.id
        WHERE quiz.course = :courseid AND u.id = :userid AND quiza.state = 'finished'
        GROUP BY c.id, c.shortname, c.description
    ";
    $rows = $DB->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $userid]);

    foreach ($rows as $r) {
        $percent = $r->attempts ? round(($r->correct / $r->attempts) * 100) : 0;
        $rates[] = [
            'shortname'   => $r->shortname,
            'description' => strip_tags(html_entity_decode($r->description, ENT_QUOTES, 'UTF-8')),
            'rate'        => $percent,
        ];
        $stats[$r->shortname] = $percent;
    }
}

// Generate AI Comment with the correct focus and custom prompt, or use POSTed content.
if (!empty($pdfcontent)) {
    $comment = $pdfcontent;
} else {
    $comment = local_competency_report_generate_comment($stats, 'student', $customprompt, $focustype);
}
// Strip any non-BMP unicode characters (emojis) to prevent TCPDF font warnings.
$comment = preg_replace('/[^\x{0000}-\x{FFFF}]/u', '', $comment);

/* PDF Initialization */
$pdf = new TCPDF();
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Moodle Competency Report');
$pdf->SetTitle(get_string('studentpdfreport', 'local_competency_report'));
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->AddPage();
$pdf->SetFont('freeserif', '', 12);

// Header Info.
$pdf->SetFont('freeserif', 'B', 14);
$pdf->Cell(0, 10, fullname($student), 0, 1, 'L');
$pdf->SetFont('freeserif', '', 11);
$pdf->Cell(0, 7, $course->fullname, 0, 1, 'L');

$titletext = ($focustype === 'grades') ? "General Grades and Academic Performance Card" : get_string('studentpdfreport', 'local_competency_report');
$pdf->Cell(0, 7, $titletext, 0, 1, 'L');
$pdf->Ln(5);

/* Table Header */
$pdf->SetFillColor(224, 224, 224);
$pdf->SetFont('freeserif', 'B', 10);

if ($focustype === 'grades') {
    $pdf->Cell(100, 10, "Quiz / Exam Name", 1, 0, 'C', true);
    $pdf->Cell(40, 10, "Score achieved", 1, 0, 'C', true);
    $pdf->Cell(40, 10, "Success rate", 1, 1, 'C', true);
} else {
    $pdf->Cell(40, 10, get_string('competencycode', 'local_competency_report'), 1, 0, 'C', true);
    $pdf->Cell(100, 10, get_string('competency', 'local_competency_report'), 1, 0, 'C', true);
    $pdf->Cell(40, 10, get_string('success', 'local_competency_report'), 1, 1, 'C', true);
}

/* Table Body */
$pdf->SetFont('freeserif', '', 10);
foreach ($rates as $row) {
    $rate = $row['rate'];

    if ($rate >= 80) {
        $pdf->SetFillColor(204, 255, 204); // Green.
    } else if ($rate >= 60) {
        $pdf->SetFillColor(204, 229, 255); // Blue.
    } else if ($rate >= 40) {
        $pdf->SetFillColor(255, 243, 205); // Orange.
    } else {
        $pdf->SetFillColor(248, 215, 218); // Red.
    }

    $x = $pdf->GetX();
    $y = $pdf->GetY();

    if ($focustype === 'grades') {
        $nameheight = $pdf->getStringHeight(100, $row['name']);
        $lineheight = max(10, $nameheight);

        $pdf->MultiCell(100, $lineheight, $row['name'], 1, 'L', true, 0, $x, $y, true);
        $pdf->MultiCell(40, $lineheight, $row['score'], 1, 'C', true, 0, $x + 100, $y, true);
        $pdf->MultiCell(40, $lineheight, '%' . $rate, 1, 'C', true, 1, $x + 140, $y, true);
    } else {
        $desc = $row['description'];
        $descheight = $pdf->getStringHeight(100, $desc);
        $lineheight = max(10, $descheight);

        $pdf->MultiCell(40, $lineheight, $row['shortname'], 1, 'C', true, 0, $x, $y, true);
        $pdf->MultiCell(100, $lineheight, $desc, 1, 'L', true, 0, $x + 40, $y, true);
        $pdf->MultiCell(40, $lineheight, '%' . $rate, 1, 'C', true, 1, $x + 140, $y, true);
    }
}

// AI Comment Section.
$pdf->Ln(10);
$pdf->SetFont('freeserif', 'B', 11);
$pdf->Cell(0, 10, "✨ Pedagogical AI Analysis Commentary", 0, 1);
$pdf->SetFont('freeserif', '', 10);
$pdf->writeHTML($comment, true, false, true, false, '');

// Legend.
$pdf->Ln(10);
$pdf->SetFont('freeserif', 'B', 9);
$pdf->Cell(0, 7, get_string('colorlegend', 'local_competency_report'), 0, 1);
$pdf->SetFont('freeserif', '', 8);
$legend = get_string('redlegend', 'local_competency_report') . " | " .
          get_string('orangelegend', 'local_competency_report') . " | " .
          get_string('bluelegend', 'local_competency_report') . " | " .
          get_string('greenlegend', 'local_competency_report');
$pdf->Cell(0, 5, $legend, 0, 1);

// Clear output buffer to prevent PHP warnings/headers-already-sent errors from corrupting the PDF.
if (ob_get_length()) {
    ob_end_clean();
}
$pdf->Output("rapor_" . $student->idnumber . ".pdf", "I");
exit;
