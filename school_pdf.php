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
 * Premium PDF export for school-wide or group competency & grade analysis.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/tcpdf/tcpdf.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/ai.php');

global $DB, $USER;

// 1. Parameter Validation.
$courseid     = required_param('courseid', PARAM_INT);
$groupid      = optional_param('groupid', 0, PARAM_INT);
$focustype    = optional_param('focus_type', 'competency', PARAM_ALPHA); // Focus type: competency or grades.
$customprompt = optional_param('custom_prompt', '', PARAM_RAW);
$pdfcontent   = optional_param('pdf_content', '', PARAM_RAW);

// 2. Authentication & Capability Checks.
if ($courseid > 0) {
    require_login($courseid);
    $context = context_course::instance($courseid);
    if (!has_capability('local/comp_report_ext:viewreports', $context) && !has_capability('local/competency_report:viewreports', $context)) {
    require_capability('local/comp_report_ext:viewreports', $context);
}
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
} else {
    // If no course is specified, treat as a site-wide report (admin access).
    require_login();
    $context = context_system::instance();
    require_capability('moodle/site:config', $context);
    $course = $DB->get_record('course', ['id' => SITEID], '*', MUST_EXIST);
    $course->fullname = get_string('schoolreport', 'local_comp_report_ext');
}

$group = null;
if ($groupid && $courseid > 0) {
    $group = $DB->get_record('groups', ['id' => $groupid, 'courseid' => $courseid], '*', MUST_EXIST);
}

// Determine Context Type string for AI analysis.
$contexttype = ($groupid > 0) ? 'group' : 'school';

// 3. Define report title based on focus and scope.
if ($focustype === 'grades') {
    if ($group) {
        $reporttitle = "General Grades Report - Group: " . $group->name;
    } else {
        $reporttitle = "General Grades Report - Course: " . $course->fullname;
    }
} else {
    if ($group) {
        $reporttitle = "Detailed Competency Report - Group: " . $group->name;
    } else {
        $reporttitle = "Detailed Competency Report - Course: " . $course->fullname;
    }
}

// 4. Performance Data Queries.
$rates = [];
$tablehtml = '';

if ($focustype === 'grades') {
    // GENERAL GRADES MODE.
    if ($groupid && $courseid > 0) {
        $sql = "SELECT q.id, q.name, AVG(qa.sumgrades) as avggrade, q.sumgrades as maxgrade, COUNT(qa.id) as attempts
                FROM {quiz_attempts} qa
                JOIN {quiz} q ON q.id = qa.quiz
                JOIN {groups_members} gm ON gm.userid = qa.userid
                WHERE q.course = :courseid AND gm.groupid = :groupid AND qa.state = 'finished'
                GROUP BY q.id, q.name, q.sumgrades
                ORDER BY q.name ASC";
        $rows = $DB->get_records_sql($sql, ['courseid' => $courseid, 'groupid' => $groupid]);
    } else if ($courseid > 0) {
        $sql = "SELECT q.id, q.name, AVG(qa.sumgrades) as avggrade, q.sumgrades as maxgrade, COUNT(qa.id) as attempts
                FROM {quiz_attempts} qa
                JOIN {quiz} q ON q.id = qa.quiz
                WHERE q.course = :courseid AND qa.state = 'finished'
                GROUP BY q.id, q.name, q.sumgrades
                ORDER BY q.name ASC";
        $rows = $DB->get_records_sql($sql, ['courseid' => $courseid]);
    } else {
        $sql = "SELECT q.id, q.name, AVG(qa.sumgrades) as avggrade, q.sumgrades as maxgrade, COUNT(qa.id) as attempts
                FROM {quiz_attempts} qa
                JOIN {quiz} q ON q.id = qa.quiz
                WHERE qa.state = 'finished'
                GROUP BY q.id, q.name, q.sumgrades
                ORDER BY q.name ASC";
        $rows = $DB->get_records_sql($sql, []);
    }

    foreach ($rows as $r) {
        $rate = $r->maxgrade ? round(($r->avggrade / $r->maxgrade) * 100) : 0;
        $rates[$r->name] = $rate;
    }

    // Build Table HTML for General Grades.
    $tablehtml = '
    <table border="1" cellpadding="6">
        <thead>
            <tr bgcolor="#f2f2f2" style="font-weight: bold;">
                <th width="45%" align="center">Quiz / Exam Name</th>
                <th width="15%" align="center">Attempts</th>
                <th width="24%" align="center">Average Score</th>
                <th width="16%" align="center">Success Rate</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($rows as $r) {
        $rate = $r->maxgrade ? round(($r->avggrade / $r->maxgrade) * 100) : 0;
        $bgcolor = $rate >= 80 ? '#e6ffec' : ($rate >= 60 ? '#e6f2ff' : ($rate >= 40 ? '#fff9e6' : '#ffe6e6'));
        $avgscore = number_format($r->avggrade, 1) . ' / ' . number_format($r->maxgrade, 1);

        $tablehtml .= '
            <tr bgcolor="' . $bgcolor . '">
                <td width="45%"><b>' . s($r->name) . '</b></td>
                <td width="15%" align="center">' . $r->attempts . '</td>
                <td width="24%" align="center">' . $avgscore . '</td>
                <td width="16%" align="center"><b>%' . $rate . '</b></td>
            </tr>';
    }
    $tablehtml .= '</tbody></table>';
} else {
    // COMPETENCY ACHIEVEMENTS MODE.
    if ($groupid && $courseid > 0) {
        $wheresql = "WHERE quiz.course = :courseid AND quiza.state = 'finished' "
            . "AND quiza.userid IN (SELECT userid FROM {groups_members} WHERE groupid = :groupid)";
        $params = ['courseid' => $courseid, 'groupid' => $groupid];
    } else if ($courseid > 0) {
        $wheresql = "WHERE quiz.course = :courseid AND quiza.state = 'finished'";
        $params = ['courseid' => $courseid];
    } else {
        $wheresql = "WHERE quiza.state = 'finished'";
        $params = [];
    }

    $sql = "
        SELECT c.id, c.shortname, c.description,
               CAST(SUM(qa.maxfraction) AS DECIMAL(12, 1)) AS attempts,
               CAST(SUM(qas.fraction) AS DECIMAL(12, 1)) AS correct
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
        $wheresql
        GROUP BY c.id, c.shortname, c.description
        ORDER BY c.shortname ASC
    ";

    $rows = $DB->get_records_sql($sql, $params);

    foreach ($rows as $r) {
        $rate = $r->attempts ? round(($r->correct / $r->attempts) * 100) : 0;
        $rates[$r->shortname] = $rate;
    }

    // Build Table HTML for Competency achievements.
    $tablehtml = '
    <table border="1" cellpadding="6">
        <thead>
            <tr bgcolor="#f2f2f2" style="font-weight: bold;">
                <th width="15%" align="center">' . get_string('competencycode', 'local_comp_report_ext') . '</th>
                <th width="41%" align="center">' . get_string('competencyname', 'local_comp_report_ext') . '</th>
                <th width="14%" align="center">' . get_string('questioncount', 'local_comp_report_ext') . '</th>
                <th width="14%" align="center">' . get_string('correctcount', 'local_comp_report_ext') . '</th>
                <th width="16%" align="center">' . get_string('successrate', 'local_comp_report_ext') . '</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($rows as $r) {
        $rate = $r->attempts ? round(($r->correct / $r->attempts) * 100) : 0;
        $cleandesc = html_entity_decode(strip_tags($r->description), ENT_QUOTES, 'UTF-8');
        $bgcolor = $rate >= 80 ? '#e6ffec' : ($rate >= 60 ? '#e6f2ff' : ($rate >= 40 ? '#fff9e6' : '#ffe6e6'));

        $tablehtml .= '
            <tr bgcolor="' . $bgcolor . '">
                <td width="15%" align="center"><b>' . s($r->shortname) . '</b></td>
                <td width="41%">' . s($cleandesc) . '</td>
                <td width="14%" align="center">' . $r->attempts . '</td>
                <td width="14%" align="center">' . $r->correct . '</td>
                <td width="16%" align="center"><b>%' . $rate . '</b></td>
            </tr>';
    }
    $tablehtml .= '</tbody></table>';
}

// 5. Generate AI comment using exact parameters.
// Generate AI Comment with the correct focus and custom prompt, or use POSTed content.
if (!empty($pdfcontent)) {
    $comment = $pdfcontent;
} else {
    $contextdetails = local_comp_report_ext_build_context_details($courseid);
    $comment = local_comp_report_ext_generate_comment($rates, $contexttype, $customprompt, $focustype, $contextdetails);
}
// Strip any non-BMP unicode characters (emojis) to prevent TCPDF font warnings.
$comment = preg_replace('/[^\x{0000}-\x{FFFF}]/u', '', $comment);

// 6. PDF Generation (TCPDF).
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator('Moodle');
$pdf->SetTitle($reporttitle);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);
$pdf->AddPage();
local_comp_report_ext_render_pdf_header_logos($pdf);

// Set font for robust UTF-8 / Arabic support.
$pdf->SetFont('freeserif', '', 11);

// Header Banner.
$pdf->SetFont('freeserif', 'B', 16);
$pdf->Cell(0, 10, $reporttitle, 0, 1, 'L');
$pdf->SetFont('freeserif', '', 10);
$pdf->Cell(0, 6, "Subject / Course: " . $course->fullname, 0, 1, 'L');
if ($group) {
    $pdf->Cell(0, 6, "Group / Class: " . $group->name, 0, 1, 'L');
}

$dateconfig = get_string('strftimedatetimeshort', 'langconfig');
$dateinfo = get_string('creation_date', 'local_comp_report_ext') . ": " . userdate(time(), $dateconfig);
$pdf->Cell(0, 6, $dateinfo, 0, 1, 'L');
$pdf->Ln(5);

// Render HTML Table.
$pdf->SetFont('freeserif', '', 10);
$pdf->writeHTML($tablehtml, true, false, true, false, '');

// Render AI Commentary Section.
if (!empty($comment)) {
    $pdf->Ln(8);
    $pdf->SetFont('freeserif', 'B', 12);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(0, 10, " ✨ Pedagogical AI Analysis Commentary", 0, 1, 'L', true);
    $pdf->Ln(2);

    $pdf->SetFont('freeserif', '', 10);
    $pdf->writeHTML($comment, true, false, true, false, '');
}

// Legend.
$pdf->Ln(8);
$pdf->SetFont('freeserif', 'B', 9);
$pdf->Cell(0, 7, get_string('colorlegend', 'local_comp_report_ext'), 0, 1);
$pdf->SetFont('freeserif', '', 8);
$legend = get_string('redlegend', 'local_comp_report_ext') . " | " .
          get_string('orangelegend', 'local_comp_report_ext') . " | " .
          get_string('bluelegend', 'local_comp_report_ext') . " | " .
          get_string('greenlegend', 'local_comp_report_ext');
$pdf->Cell(0, 5, $legend, 0, 1);

// Final PDF output.
$filename = "report_" . clean_filename($reporttitle) . ".pdf";
// Clear output buffer to prevent PHP warnings/headers-already-sent errors from corrupting the PDF.
if (ob_get_length()) {
    ob_end_clean();
}
$pdf->Output($filename, "I");
exit;
