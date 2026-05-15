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
 * PDF report generator for student competencies.
 *
 * @package    local_yetkinlik
 * @copyright  2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/tcpdf/tcpdf.php');

$courseid = required_param('courseid', PARAM_INT);
// Get userid from URL, default to current user if not provided.
$userid   = optional_param('userid', $USER->id, PARAM_INT);

require_login($courseid);

global $DB, $USER;

$context = context_course::instance($courseid);

// Permission check: User can view their own report, OR must have teacher capability.
if ($userid != $USER->id) {
    require_capability('mod/quiz:viewreports', $context);
}

$course  = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$student = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

// Fetch competency data.
$sql = "
    SELECT c.id, c.shortname, c.description,
           CAST(SUM(qa.maxfraction) AS DECIMAL(12, 1)) AS attempts,
           CAST(SUM(qas.fraction) AS DECIMAL(12, 1)) AS correct
    FROM {quiz_attempts} quiza
    JOIN {user} u ON quiza.userid = u.id
    JOIN {question_usages} qu ON qu.id = quiza.uniqueid
    JOIN {question_attempts} qa ON qa.questionusageid = qu.id
    JOIN {quiz} quiz ON quiz.id = quiza.quiz
    JOIN {qbank_yetkinlik_qmap} m ON m.questionid = qa.questionid
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

$rates = [];
$stats = [];
foreach ($rows as $r) {
    $percent = $r->attempts ? round(($r->correct / $r->attempts) * 100) : 0;
    $rates[] = [
        'shortname'   => $r->shortname,
        'description' => strip_tags(html_entity_decode($r->description, ENT_QUOTES, 'UTF-8')),
        'rate'        => $percent,
    ];
    $stats[$r->shortname] = $percent;
}

// Generate AI Comment.
require_once(__DIR__ . '/ai.php');
$comment = local_yetkinlik_generate_comment($stats, 'student');

/* PDF Initialization */
$pdf = new TCPDF();
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Moodle Yetkinlik');
$pdf->SetTitle(get_string('studentpdfreport', 'local_yetkinlik'));
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->AddPage();
$pdf->SetFont('freeserif', '', 12);

// Header Info.
$pdf->SetFont('freeserif', 'B', 14);
$pdf->Cell(0, 10, fullname($student), 0, 1, 'L');
$pdf->SetFont('freeserif', '', 11);
$pdf->Cell(0, 7, $course->fullname, 0, 1, 'L');
$pdf->Cell(0, 7, get_string('studentpdfreport', 'local_yetkinlik'), 0, 1, 'L');
$pdf->Ln(5);

/* Table Header */
$pdf->SetFillColor(224, 224, 224);
$pdf->SetFont('freeserif', 'B', 10);
$pdf->Cell(40, 10, get_string('competencycode', 'local_yetkinlik'), 1, 0, 'C', true);
$pdf->Cell(100, 10, get_string('competency', 'local_yetkinlik'), 1, 0, 'C', true);
$pdf->Cell(40, 10, get_string('success', 'local_yetkinlik'), 1, 1, 'C', true);

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

    $desc = $row['description'];
    $descheight = $pdf->getStringHeight(100, $desc);
    $lineheight = max(10, $descheight);

    // MultiCell handles long text.
    $x = $pdf->GetX();
    $y = $pdf->GetY();

    $pdf->MultiCell(40, $lineheight, $row['shortname'], 1, 'C', true, 0, $x, $y, true);
    $pdf->MultiCell(100, $lineheight, $desc, 1, 'L', true, 0, $x + 40, $y, true);
    $pdf->MultiCell(40, $lineheight, '%' . $rate, 1, 'C', true, 1, $x + 140, $y, true);
}

// AI Comment Section.
$pdf->Ln(10);
$pdf->SetFont('freeserif', 'B', 11);
$pdf->Cell(0, 10, get_string('generalcomment', 'local_yetkinlik'), 0, 1);
$pdf->SetFont('freeserif', '', 10);
$pdf->writeHTML($comment, true, false, true, false, '');

// Legend.
$pdf->Ln(10);
$pdf->SetFont('freeserif', 'B', 9);
$pdf->Cell(0, 7, get_string('colorlegend', 'local_yetkinlik'), 0, 1);
$pdf->SetFont('freeserif', '', 8);
$legend = get_string('redlegend', 'local_yetkinlik') . " | " .
          get_string('orangelegend', 'local_yetkinlik') . " | " .
          get_string('bluelegend', 'local_yetkinlik') . " | " .
          get_string('greenlegend', 'local_yetkinlik');
$pdf->Cell(0, 5, $legend, 0, 1);

$pdf->Output("rapor_" . $student->idnumber . ".pdf", "I");
exit;
