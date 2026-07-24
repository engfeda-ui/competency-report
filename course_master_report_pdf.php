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
 * Landscape PDF report generator for the Unified Course Master Report.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/tcpdf/tcpdf.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/ai.php');

$courseid   = required_param('courseid', PARAM_INT);
$pdfcontent = optional_param('pdf_content', '', PARAM_RAW);
$customprompt = optional_param('custom_prompt', '', PARAM_RAW);

require_login($courseid);
$context = context_course::instance($courseid);
require_capability('moodle/course:update', $context);

global $DB;

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$reporttitle = get_string('coursemasterreport', 'local_comp_report_ext');

// 1. Overall Statistics.
$studentscount = $DB->count_records_sql("
    SELECT COUNT(DISTINCT u.id)
    FROM {user} u
    JOIN {role_assignments} ra ON ra.userid = u.id
    JOIN {context} ctx ON ctx.id = ra.contextid
    WHERE ctx.instanceid = :courseid
      AND ra.roleid = (SELECT id FROM {role} WHERE shortname = 'student')
", ['courseid' => $courseid]);

$groupscount = $DB->count_records('groups', ['courseid' => $courseid]);

$compscount = $DB->count_records_sql("
    SELECT COUNT(DISTINCT competencyid)
    FROM {qbank_comp_ext_qmap}
    WHERE courseid = :courseid
", ['courseid' => $courseid]);

$quizzescount = $DB->count_records('quiz', ['course' => $courseid]);

// 2. Exams & General Grades.
$rawquizzes = $DB->get_records_sql("
    SELECT q.id, q.name, AVG(qa.sumgrades) as avggrade, q.sumgrades as maxgrade, COUNT(DISTINCT qa.userid) as attempts,
           (SELECT COUNT(slot.id) FROM {quiz_slots} slot WHERE slot.quizid = q.id) as numquestions
    FROM {quiz} q
    LEFT JOIN {quiz_attempts} qa ON qa.quiz = q.id AND qa.state = 'finished'
    WHERE q.course = :courseid
    GROUP BY q.id, q.name, q.sumgrades
    ORDER BY q.name ASC
", ['courseid' => $courseid]);

// 3. Course-wide Competency Rates.
$rawcomps = $DB->get_records_sql("
    SELECT c.id, c.shortname,
           SUM(qa.maxfraction) AS attempts,
           SUM(qas.fraction) AS correct
    FROM {quiz_attempts} quiza
    JOIN {quiz} quiz ON quiz.id = quiza.quiz
    JOIN {question_usages} qu ON qu.id = quiza.uniqueid
    JOIN {question_attempts} qa ON qa.questionusageid = qu.id
    JOIN {qbank_comp_ext_qmap} m ON m.questionid = qa.questionid
    JOIN {competency} c ON c.id = m.competencyid
    JOIN (
        SELECT MAX(fraction) AS fraction, questionattemptid
        FROM {question_attempt_steps}
        GROUP BY questionattemptid
    ) qas ON qas.questionattemptid = qa.id
    WHERE quiz.course = :courseid AND quiza.state = 'finished'
    GROUP BY c.id, c.shortname
    ORDER BY c.shortname ASC
", ['courseid' => $courseid]);

// 4. Group Comparison Matrix.
$compslist = $DB->get_records_sql("
    SELECT DISTINCT c.id, c.shortname
    FROM {qbank_comp_ext_qmap} m
    JOIN {competency} c ON c.id = m.competencyid
    WHERE m.courseid = :courseid
    ORDER BY c.shortname ASC
", ['courseid' => $courseid]);

$groups = $DB->get_records('groups', ['courseid' => $courseid], 'name ASC');

$groupcompraw = $DB->get_records_sql("
    SELECT
        CONCAT(gm.groupid, '_', m.competencyid) as unique_key,
        gm.groupid,
        m.competencyid,
        SUM(qa.maxfraction) AS total_max,
        SUM(qas.fraction) AS total_fraction
    FROM {quiz_attempts} quiza
    JOIN {question_usages} qu ON qu.id = quiza.uniqueid
    JOIN {question_attempts} qa ON qa.questionusageid = qu.id
    JOIN {qbank_comp_ext_qmap} m ON m.questionid = qa.questionid
    JOIN {groups_members} gm ON gm.userid = quiza.userid
    JOIN (
        SELECT MAX(fraction) AS fraction, questionattemptid
        FROM {question_attempt_steps}
        GROUP BY questionattemptid
    ) qas ON qas.questionattemptid = qa.id
    WHERE quiza.state = 'finished'
      AND quiza.userid IN (
          SELECT userid FROM {groups_members} WHERE groupid IN (SELECT id FROM {groups} WHERE courseid = :courseid)
      )
    GROUP BY gm.groupid, m.competencyid
", ['courseid' => $courseid]);

$groupmap = [];
foreach ($groupcompraw as $gr) {
    $groupmap[$gr->groupid][$gr->competencyid] = [
        'att' => (float)$gr->total_max,
        'cor' => (float)$gr->total_fraction,
    ];
}

// -------------------------------------------------------------
// Build HTML Output sections for PDF.

// Stats Grid Table.
$statshtml = '
<table border="1" cellpadding="6" style="border-collapse: collapse; background-color: #f8f9fa;">
    <tr bgcolor="#17a2b8" style="color: #ffffff; font-weight: bold; font-size: 11pt;">
        <th align="center" width="25%">' . get_string('allusers', 'local_comp_report_ext') . '</th>
        <th align="center" width="25%">' . get_string('selectgroup', 'local_comp_report_ext') . '</th>
        <th align="center" width="25%">' . get_string('allcompetencies', 'local_comp_report_ext') . '</th>
        <th align="center" width="25%">' . get_string('searchquiz', 'local_comp_report_ext') . '</th>
    </tr>
    <tr style="font-weight: bold; font-size: 14pt;">
        <td align="center" width="25%">' . $studentscount . '</td>
        <td align="center" width="25%">' . $groupscount . '</td>
        <td align="center" width="25%">' . $compscount . '</td>
        <td align="center" width="25%">' . $quizzescount . '</td>
    </tr>
</table>';

// Quizzes Grades Table.
$quizzeshtml = '
<table border="1" cellpadding="5" style="border-collapse: collapse; font-size: 8.5pt;">
    <thead>
        <tr bgcolor="#f2f2f2" style="font-weight: bold;">
            <th width="32%">' . get_string('searchquiz', 'local_comp_report_ext') . '</th>
            <th width="15%" align="center">' . get_string('questioncount', 'local_comp_report_ext') . '</th>
            <th width="18%" align="center">' . get_string('participantcount', 'local_comp_report_ext') . '</th>
            <th width="18%" align="center">' . get_string('averagegrade', 'local_comp_report_ext') . '</th>
            <th width="17%" align="center">' . get_string('successrate', 'local_comp_report_ext') . '</th>
        </tr>
    </thead>
    <tbody>';
foreach ($rawquizzes as $q) {
    $bgcolor = '#ffffff';
    $celltext = '-';
    $numq = ($q->numquestions > 0) ? (int)$q->numquestions : '-';
    $part = $q->attempts . ($studentscount > 0 ? ' / ' . $studentscount : '');
    if ($q->attempts > 0 && $q->maxgrade > 0) {
        $rate = ($q->avggrade / $q->maxgrade) * 100;
        $celltext = '%' . number_format($rate, 1);
        $bgcolor = $rate >= 80 ? '#e6ffec' : ($rate >= 60 ? '#e6f2ff' : ($rate >= 40 ? '#fff9e6' : '#ffe6e6'));
    }
    $quizzeshtml .= '
        <tr bgcolor="' . $bgcolor . '">
            <td width="32%"><b>' . s($q->name) . '</b></td>
            <td width="15%" align="center">' . $numq . '</td>
            <td width="18%" align="center">' . $part . '</td>
            <td width="18%" align="center">'
                . ($q->attempts > 0 ? number_format($q->avggrade, 1) . ' / ' . number_format($q->maxgrade, 1) : '-')
                . '</td>
            <td width="17%" align="center" style="font-weight: bold;">' . $celltext . '</td>
        </tr>';
}
$quizzeshtml .= '</tbody></table>';

// Competencies Summary Table.
$compshtml = '
<table border="1" cellpadding="5" style="border-collapse: collapse; font-size: 8.5pt;">
    <thead>
        <tr bgcolor="#f2f2f2" style="font-weight: bold;">
            <th width="45%">' . get_string('competencyname', 'local_comp_report_ext') . '</th>
            <th width="15%" align="center">' . get_string('questioncount', 'local_comp_report_ext') . '</th>
            <th width="20%" align="center">' . get_string('correctcount', 'local_comp_report_ext') . '</th>
            <th width="20%" align="center">' . get_string('successrate', 'local_comp_report_ext') . '</th>
        </tr>
    </thead>
    <tbody>';
foreach ($rawcomps as $rc) {
    $bgcolor = '#ffffff';
    $celltext = '-';
    if ($rc->attempts > 0) {
        $rate = ($rc->correct / $rc->attempts) * 100;
        $celltext = '%' . number_format($rate, 1);
        $bgcolor = $rate >= 80 ? '#e6ffec' : ($rate >= 60 ? '#e6f2ff' : ($rate >= 40 ? '#fff9e6' : '#ffe6e6'));
    }
    $compshtml .= '
        <tr bgcolor="' . $bgcolor . '">
            <td width="45%"><b>' . s($rc->shortname) . '</b></td>
            <td width="15%" align="center">' . number_format($rc->attempts, 0) . '</td>
            <td width="20%" align="center">' . number_format($rc->correct, 1) . '</td>
            <td width="20%" align="center" style="font-weight: bold;">' . $celltext . '</td>
        </tr>';
}
$compshtml .= '</tbody></table>';

// Matrix Column Widths dynamically.
$colcount = count($compslist);
$groupcolwidth = 24; // Width in percentage.
$compcolwidth = 76 / max(1, $colcount); // Width in percentage.

// Group Comparison Grid Table.
$matrixhtml = '
<table border="1" cellpadding="5" style="border-collapse: collapse; font-size: 8.5pt;">
    <thead>
        <tr bgcolor="#f2f2f2" style="font-weight: bold;">
            <th width="' . $groupcolwidth . '%">' . get_string('selectgroup', 'local_comp_report_ext') . '</th>';
foreach ($compslist as $c) {
    $matrixhtml .= '<th width="' . $compcolwidth . '%" align="center">' . s($c->shortname) . '</th>';
}
$matrixhtml .= '</tr></thead><tbody>';

foreach ($groups as $g) {
    $matrixhtml .= '<tr>';
    $matrixhtml .= '<td width="' . $groupcolwidth . '%"><b>' . s($g->name) . '</b></td>';
    foreach ($compslist as $c) {
        $celltext = '-';
        $bgcolor = '#ffffff';
        if (isset($groupmap[$g->id][$c->id])) {
            $att = $groupmap[$g->id][$c->id]['att'];
            $cor = $groupmap[$g->id][$c->id]['cor'];
            if ($att > 0) {
                $rate = ($cor / $att) * 100;
                $celltext = '%' . number_format($rate, 1);
                $bgcolor = $rate >= 80 ? '#e6ffec' : ($rate >= 60 ? '#e6f2ff' : ($rate >= 40 ? '#fff9e6' : '#ffe6e6'));
            }
        }
        $matrixhtml .= '<td width="' . $compcolwidth . '%" align="center" bgcolor="' . $bgcolor . '" style="font-weight: bold;">'
            . $celltext . '</td>';
    }
    $matrixhtml .= '</tr>';
}
$matrixhtml .= '</tbody></table>';

// AI Commentary processing.
$comment = '';
if (!empty($pdfcontent)) {
    $comment = $pdfcontent;
} else {
    // Compile rates array for LLM fallback.
    $rates = [];
    foreach ($rawcomps as $rc) {
        $rates[$rc->shortname] = $rc->attempts ? ($rc->correct / $rc->attempts) * 100 : 0;
    }
    if (!empty($rates)) {
        $comment = local_comp_report_ext_generate_comment($rates, 'course_master', $customprompt, 'competency');
    }
}

// Strip non-BMP emojis.
$statshtml  = preg_replace('/[^\x{0000}-\x{FFFF}]/u', '', $statshtml);
$quizzeshtml = preg_replace('/[^\x{0000}-\x{FFFF}]/u', '', $quizzeshtml);
$compshtml   = preg_replace('/[^\x{0000}-\x{FFFF}]/u', '', $compshtml);
$matrixhtml  = preg_replace('/[^\x{0000}-\x{FFFF}]/u', '', $matrixhtml);
$comment     = preg_replace('/[^\x{0000}-\x{FFFF}]/u', '', $comment);

// -------------------------------------------------------------
// PDF Generation (Landscape).
$pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator('Moodle');
$pdf->SetTitle($reporttitle);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);
$pdf->AddPage();
local_comp_report_ext_render_pdf_header_logos($pdf, true);

// Set font for robust UTF-8 / Arabic support.
$pdf->SetFont('freeserif', '', 10);

// Header Banner.
$pdf->SetFont('freeserif', 'B', 15);
$pdf->Cell(0, 10, $reporttitle, 0, 1, 'L');
$pdf->SetFont('freeserif', '', 10);
$pdf->Cell(0, 6, "Subject / Course: " . $course->fullname, 0, 1, 'L');

$dateconfig = get_string('strftimedatetimeshort', 'langconfig');
$dateinfo = get_string('creation_date', 'local_comp_report_ext') . ": " . userdate(time(), $dateconfig);
$pdf->Cell(0, 6, $dateinfo, 0, 1, 'L');
$pdf->Ln(4);

// Section 1: Stats Grid.
$pdf->SetFont('freeserif', 'B', 12);
$pdf->Cell(0, 8, "1. " . get_string('course_stats', 'local_comp_report_ext'), 0, 1, 'L');
$pdf->Ln(2);
$pdf->SetFont('freeserif', '', 9);
$pdf->writeHTML($statshtml, true, false, true, false, '');
$pdf->Ln(6);

// Section 2: Exams Summary.
$pdf->SetFont('freeserif', 'B', 12);
$pdf->Cell(0, 8, "2. " . get_string('exam_grades_summary', 'local_comp_report_ext'), 0, 1, 'L');
$pdf->Ln(2);
$pdf->SetFont('freeserif', '', 9);
$pdf->writeHTML($quizzeshtml, true, false, true, false, '');

// Force new page for the heavy grid sections.
$pdf->AddPage();

// Section 3: Competencies Achievements.
$pdf->SetFont('freeserif', 'B', 12);
$pdf->Cell(0, 8, "3. " . get_string('summaryreport', 'local_comp_report_ext'), 0, 1, 'L');
$pdf->Ln(2);
$pdf->SetFont('freeserif', '', 9);
$pdf->writeHTML($compshtml, true, false, true, false, '');
$pdf->Ln(6);

// Section 4: Group Comparison Grid.
$pdf->SetFont('freeserif', 'B', 12);
$pdf->Cell(0, 8, "4. " . get_string('group_comparison_grid', 'local_comp_report_ext'), 0, 1, 'L');
$pdf->Ln(2);
$pdf->SetFont('freeserif', '', 9);
$pdf->writeHTML($matrixhtml, true, false, true, false, '');

// Section 5: AI Commentary.
if (!empty($comment)) {
    $pdf->AddPage();
    $pdf->SetFont('freeserif', 'B', 13);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(0, 10, " ✨ Pedagogical AI Analysis Commentary & Strategy", 0, 1, 'L', true);
    $pdf->Ln(2);

    $pdf->SetFont('freeserif', '', 10);
    $pdf->writeHTML($comment, true, false, true, false, '');
}

// Final output.
$reportfilename = "Course_Master_Report_" . clean_filename($course->shortname);
$filename = $reportfilename . ".pdf";

// Clear output buffer.
if (ob_get_length()) {
    ob_end_clean();
}
$pdf->Output($filename, "I");
exit;
