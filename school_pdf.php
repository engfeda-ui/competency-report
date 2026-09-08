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
 * Premium Executive PDF export for the Institutional Competency Dashboard.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/tcpdf/tcpdf.php');
require_once(__DIR__ . '/lib.php');

global $DB, $USER;

// 1. Authentication & Capability Checks.
require_login();
$context = context_system::instance();
if (!has_capability('moodle/site:config', $context) && !has_capability('local/comp_report_ext:viewreports', $context)) {
    require_capability('moodle/site:config', $context);
}

// 2. Parameters.
$categoryid = optional_param('categoryid', 0, PARAM_INT);
$courseid   = optional_param('courseid', 0, PARAM_INT);

// 3. Category Filter.
$catwhereq = '';
$catwherep = '';
$paramsq = [];
$paramsp = [];

$categoryname = get_string('all_categories', 'local_comp_report_ext');
if ($categoryid > 0) {
    $catwhereq = ' AND c.category = :catid ';
    $catwherep = ' AND c.category = :catid ';
    $paramsq['catid'] = $categoryid;
    $paramsp['catid'] = $categoryid;
    $catrec = $DB->get_record('course_categories', ['id' => $categoryid]);
    if ($catrec) {
        $categoryname = format_string($catrec->name);
    }
}

if ($courseid > 0) {
    $catwhereq .= ' AND q.course = :cid ';
    $catwherep .= ' AND pr.courseid = :cid ';
    $paramsq['cid'] = $courseid;
    $paramsp['cid'] = $courseid;
}

// 4. Data Aggregations.
$sqltheory = "
    SELECT q.course AS courseid,
           COUNT(DISTINCT quiza.userid) AS student_count,
           COUNT(DISTINCT m.competencyid) AS comp_count,
           CAST(SUM(qa.maxfraction) AS DECIMAL(12, 1)) AS attempts,
           CAST(SUM(qas.fraction) AS DECIMAL(12, 1)) AS correct
    FROM {quiz_attempts} quiza
    JOIN {quiz} q ON q.id = quiza.quiz
    JOIN {course} c ON c.id = q.course
    JOIN {question_usages} qu ON qu.id = quiza.uniqueid
    JOIN {question_attempts} qa ON qa.questionusageid = qu.id
    JOIN {qbank_comp_ext_qmap} m ON m.questionid = qa.questionid
    JOIN (
        SELECT MAX(fraction) AS fraction, questionattemptid
        FROM {question_attempt_steps}
        GROUP BY questionattemptid
    ) qas ON qas.questionattemptid = qa.id
    WHERE quiza.state = 'finished' AND q.course != " . SITEID . " $catwhereq
    GROUP BY q.course
";
$theorybycourse = $DB->get_records_sql($sqltheory, $paramsq);

$sqlpractical = "
    SELECT pr.courseid,
           COUNT(DISTINCT pr.studentid) AS student_count,
           COUNT(DISTINCT pr.competencyid) AS comp_count,
           AVG(pr.competency_percent) AS avg_percent,
           COUNT(pr.id) AS total_entries
    FROM {local_comp_report_ext_prac} pr
    JOIN {course} c ON c.id = pr.courseid
    WHERE pr.courseid != " . SITEID . " $catwherep
    GROUP BY pr.courseid
";
$practicalbycourse = $DB->get_records_sql($sqlpractical, $paramsp);

$sqlstudents = "
    SELECT COUNT(DISTINCT all_students.userid) AS total_students
    FROM (
        SELECT quiza.userid
        FROM {quiz_attempts} quiza
        JOIN {quiz} q ON q.id = quiza.quiz
        JOIN {course} c ON c.id = q.course
        JOIN {question_usages} qu ON qu.id = quiza.uniqueid
        JOIN {question_attempts} qa ON qa.questionusageid = qu.id
        JOIN {qbank_comp_ext_qmap} m ON m.questionid = qa.questionid
        WHERE quiza.state = 'finished' AND q.course != " . SITEID . " $catwhereq
        UNION
        SELECT pr.studentid AS userid
        FROM {local_comp_report_ext_prac} pr
        JOIN {course} c ON c.id = pr.courseid
        WHERE pr.courseid != " . SITEID . " $catwherep
    ) all_students
";
$totalevaluatedstudents = (int)$DB->get_field_sql($sqlstudents, array_merge($paramsq, $paramsp));

$courseids = array_unique(array_merge(
    array_keys($theorybycourse),
    array_keys($practicalbycourse)
));

$coursesdata = [];
$totalmasterysum = 0;

if (!empty($courseids)) {
    list($cinsql, $cinparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
    $coursesinfo = $DB->get_records_sql("
        SELECT c.id, c.fullname, c.shortname, c.category, cc.name AS category_name
        FROM {course} c
        LEFT JOIN {course_categories} cc ON cc.id = c.category
        WHERE c.id $cinsql
        ORDER BY cc.name ASC, c.fullname ASC
    ", $cinparams);

    foreach ($courseids as $cid) {
        if (!isset($coursesinfo[$cid])) {
            continue;
        }
        $cinfo = $coursesinfo[$cid];

        $hastheory = isset($theorybycourse[$cid]) && $theorybycourse[$cid]->attempts > 0;
        $theoryrate = $hastheory ? round(($theorybycourse[$cid]->correct / $theorybycourse[$cid]->attempts) * 100, 1) : null;
        $theorystudents = $hastheory ? (int)$theorybycourse[$cid]->student_count : 0;

        $hasprac = isset($practicalbycourse[$cid]) && $practicalbycourse[$cid]->total_entries > 0;
        $pracrate = $hasprac ? round((float)$practicalbycourse[$cid]->avg_percent, 1) : null;
        $pracstudents = $hasprac ? (int)$practicalbycourse[$cid]->student_count : 0;

        $overallrate = 0.0;
        if ($hastheory && $hasprac) {
            $overallrate = round(($theoryrate + $pracrate) / 2, 1);
        } else if ($hastheory) {
            $overallrate = $theoryrate;
        } else if ($hasprac) {
            $overallrate = $pracrate;
        }

        $totalstudents = max($theorystudents, $pracstudents);
        $totalmasterysum += $overallrate;

        $coursesdata[] = [
            'id'             => $cid,
            'fullname'       => format_string($cinfo->fullname),
            'shortname'      => format_string($cinfo->shortname),
            'category_name'  => format_string($cinfo->category_name ?? $categoryname),
            'students_count' => $totalstudents,
            'theory_rate'    => $hastheory ? number_format($theoryrate, 1) . '%' : '—',
            'prac_rate'      => $hasprac ? number_format($pracrate, 1) . '%' : '—',
            'overall_rate'   => number_format($overallrate, 1) . '%',
            'raw_overall'    => $overallrate,
        ];
    }
}

$evaluatedcoursescount = count($coursesdata);
$overallinstitutionmastery = $evaluatedcoursescount > 0 ? round($totalmasterysum / $evaluatedcoursescount, 1) : 0.0;

// 5. PDF Setup (TCPDF).
$reporttitle = get_string('institutional_dashboard_title', 'local_comp_report_ext');

$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator('Moodle - Competency Report Plugin');
$pdf->SetTitle($reporttitle);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);
$pdf->SetMargins(12, 12, 12);
$pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);
$pdf->AddPage();

local_comp_report_ext_render_pdf_header_logos($pdf);

// Font configuration.
$pdf->SetFont('freeserif', '', 11);

// Header section.
$pdf->SetFont('freeserif', 'B', 15);
$pdf->Cell(0, 8, $reporttitle, 0, 1, 'L');

$pdf->SetFont('freeserif', '', 9);
$pdf->Cell(0, 5, get_string('category', 'core') . ": " . $categoryname, 0, 1, 'L');
$dateconfig = get_string('strftimedatetimeshort', 'langconfig');
$pdf->Cell(0, 5, get_string('creation_date', 'local_comp_report_ext') . ": " . userdate(time(), $dateconfig), 0, 1, 'L');
$pdf->Ln(4);

// KPI Summary Table.
$kpihtml = '
<table border="1" cellpadding="6" style="background-color: #f8fafc; font-size: 9pt;">
    <tr bgcolor="#1e293b" style="color: #ffffff; font-weight: bold; text-align: center;">
        <th width="25%">' . get_string('active_courses_count', 'local_comp_report_ext') . '</th>
        <th width="25%">' . get_string('total_evaluated_students', 'local_comp_report_ext') . '</th>
        <th width="25%">' . get_string('overall_institution_mastery', 'local_comp_report_ext') . '</th>
        <th width="25%">' . get_string('status', 'local_comp_report_ext') . '</th>
    </tr>
    <tr align="center" style="font-size: 11pt; font-weight: bold;">
        <td width="25%">' . $evaluatedcoursescount . '</td>
        <td width="25%">' . number_format($totalevaluatedstudents) . '</td>
        <td width="25%" style="color: #059669;">%' . number_format($overallinstitutionmastery, 1) . '</td>
        <td width="25%">' . (
            $overallinstitutionmastery >= 70 ?
            get_string('status_excellent', 'local_comp_report_ext') :
            get_string('status_competent', 'local_comp_report_ext')
        ) . '</td>
    </tr>
</table>';
$pdf->writeHTML($kpihtml, true, false, true, false, '');
$pdf->Ln(4);

// Courses Breakdown Table.
$coursesoverviewtitle = get_string('courses_overview_title', 'local_comp_report_ext');
$tablehtml = '
<h4 style="font-size: 11pt; font-weight: bold; margin-bottom: 4px;">' . $coursesoverviewtitle . '</h4>
<table border="1" cellpadding="5" style="font-size: 8.5pt;">
    <thead>
        <tr bgcolor="#f1f5f9" style="font-weight: bold; text-align: center;">
            <th width="8%">#</th>
            <th width="32%">' . get_string('course') . '</th>
            <th width="20%">' . get_string('category') . '</th>
            <th width="10%">' . get_string('total_evaluated_students', 'local_comp_report_ext') . '</th>
            <th width="10%">' . get_string('theory_mastery', 'local_comp_report_ext') . '</th>
            <th width="10%">' . get_string('practical_mastery', 'local_comp_report_ext') . '</th>
            <th width="10%">' . get_string('overall_mastery', 'local_comp_report_ext') . '</th>
        </tr>
    </thead>
    <tbody>';

foreach ($coursesdata as $c) {
    $bgcolor = $c['raw_overall'] >= 80 ? '#ecfdf5' : ($c['raw_overall'] >= 60 ? '#eff6ff' : '#fef2f2');
    $cfull = s($c['fullname']);
    $cshort = s($c['shortname']);
    $tablehtml .= '
        <tr bgcolor="' . $bgcolor . '">
            <td width="8%" align="center">' . $c['id'] . '</td>
            <td width="32%"><b>' . $cfull . '</b><br><small style="color: #64748b;">(' . $cshort . ')</small></td>
            <td width="20%">' . s($c['category_name']) . '</td>
            <td width="10%" align="center"><b>' . $c['students_count'] . '</b></td>
            <td width="10%" align="center">' . $c['theory_rate'] . '</td>
            <td width="10%" align="center">' . $c['prac_rate'] . '</td>
            <td width="10%" align="center" style="font-weight: bold; color: #1e3a8a;">' . $c['overall_rate'] . '</td>
        </tr>';
}

$tablehtml .= '</tbody></table>';
$pdf->writeHTML($tablehtml, true, false, true, false, '');

// Clean buffer and output.
$filename = "Institutional_Report_" . date('Ymd_His') . ".pdf";
if (ob_get_length()) {
    ob_end_clean();
}
$pdf->Output($filename, "I");
exit;
