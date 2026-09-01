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
$catwhere_q = '';
$catwhere_p = '';
$params_q = [];
$params_p = [];

$category_name = get_string('all_categories', 'local_comp_report_ext');
if ($categoryid > 0) {
    $catwhere_q = ' AND c.category = :catid ';
    $catwhere_p = ' AND c.category = :catid ';
    $params_q['catid'] = $categoryid;
    $params_p['catid'] = $categoryid;
    $catrec = $DB->get_record('course_categories', ['id' => $categoryid]);
    if ($catrec) {
        $category_name = format_string($catrec->name);
    }
}

if ($courseid > 0) {
    $catwhere_q .= ' AND q.course = :cid ';
    $catwhere_p .= ' AND pr.courseid = :cid ';
    $params_q['cid'] = $courseid;
    $params_p['cid'] = $courseid;
}

// 4. Data Aggregations.
$sql_theory = "
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
    WHERE quiza.state = 'finished' AND q.course != " . SITEID . " $catwhere_q
    GROUP BY q.course
";
$theory_by_course = $DB->get_records_sql($sql_theory, $params_q);

$sql_practical = "
    SELECT pr.courseid,
           COUNT(DISTINCT pr.studentid) AS student_count,
           COUNT(DISTINCT pr.competencyid) AS comp_count,
           AVG(pr.competency_percent) AS avg_percent,
           COUNT(pr.id) AS total_entries
    FROM {local_comp_report_ext_prac} pr
    JOIN {course} c ON c.id = pr.courseid
    WHERE pr.courseid != " . SITEID . " $catwhere_p
    GROUP BY pr.courseid
";
$practical_by_course = $DB->get_records_sql($sql_practical, $params_p);

$sql_students = "
    SELECT COUNT(DISTINCT all_students.userid) AS total_students
    FROM (
        SELECT quiza.userid
        FROM {quiz_attempts} quiza
        JOIN {quiz} q ON q.id = quiza.quiz
        JOIN {course} c ON c.id = q.course
        JOIN {question_usages} qu ON qu.id = quiza.uniqueid
        JOIN {question_attempts} qa ON qa.questionusageid = qu.id
        JOIN {qbank_comp_ext_qmap} m ON m.questionid = qa.questionid
        WHERE quiza.state = 'finished' AND q.course != " . SITEID . " $catwhere_q
        UNION
        SELECT pr.studentid AS userid
        FROM {local_comp_report_ext_prac} pr
        JOIN {course} c ON c.id = pr.courseid
        WHERE pr.courseid != " . SITEID . " $catwhere_p
    ) all_students
";
$total_evaluated_students = (int)$DB->get_field_sql($sql_students, array_merge($params_q, $params_p));

$course_ids = array_unique(array_merge(
    array_keys($theory_by_course),
    array_keys($practical_by_course)
));

$courses_data = [];
$total_mastery_sum = 0;

if (!empty($course_ids)) {
    list($cinsql, $cinparams) = $DB->get_in_or_equal($course_ids, SQL_PARAMS_NAMED);
    $courses_info = $DB->get_records_sql("
        SELECT c.id, c.fullname, c.shortname, c.category, cc.name AS category_name
        FROM {course} c
        LEFT JOIN {course_categories} cc ON cc.id = c.category
        WHERE c.id $cinsql
        ORDER BY cc.name ASC, c.fullname ASC
    ", $cinparams);

    foreach ($course_ids as $cid) {
        if (!isset($courses_info[$cid])) {
            continue;
        }
        $cinfo = $courses_info[$cid];

        $has_theory = isset($theory_by_course[$cid]) && $theory_by_course[$cid]->attempts > 0;
        $theory_rate = $has_theory ? round(($theory_by_course[$cid]->correct / $theory_by_course[$cid]->attempts) * 100, 1) : null;
        $theory_students = $has_theory ? (int)$theory_by_course[$cid]->student_count : 0;

        $has_prac = isset($practical_by_course[$cid]) && $practical_by_course[$cid]->total_entries > 0;
        $prac_rate = $has_prac ? round((float)$practical_by_course[$cid]->avg_percent, 1) : null;
        $prac_students = $has_prac ? (int)$practical_by_course[$cid]->student_count : 0;

        $overall_rate = 0.0;
        if ($has_theory && $has_prac) {
            $overall_rate = round(($theory_rate + $prac_rate) / 2, 1);
        } else if ($has_theory) {
            $overall_rate = $theory_rate;
        } else if ($has_prac) {
            $overall_rate = $prac_rate;
        }

        $total_students = max($theory_students, $prac_students);
        $total_mastery_sum += $overall_rate;

        $courses_data[] = [
            'id'             => $cid,
            'fullname'       => format_string($cinfo->fullname),
            'shortname'      => format_string($cinfo->shortname),
            'category_name'  => format_string($cinfo->category_name ?? $category_name),
            'students_count' => $total_students,
            'theory_rate'    => $has_theory ? number_format($theory_rate, 1) . '%' : '—',
            'prac_rate'      => $has_prac ? number_format($prac_rate, 1) . '%' : '—',
            'overall_rate'   => number_format($overall_rate, 1) . '%',
            'raw_overall'    => $overall_rate,
        ];
    }
}

$evaluated_courses_count = count($courses_data);
$overall_institution_mastery = $evaluated_courses_count > 0 ? round($total_mastery_sum / $evaluated_courses_count, 1) : 0.0;

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
$pdf->Cell(0, 5, get_string('category', 'core') . ": " . $category_name, 0, 1, 'L');
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
        <td width="25%">' . $evaluated_courses_count . '</td>
        <td width="25%">' . number_format($total_evaluated_students) . '</td>
        <td width="25%" style="color: #059669;">%' . number_format($overall_institution_mastery, 1) . '</td>
        <td width="25%">' . ($overall_institution_mastery >= 70 ? get_string('status_excellent', 'local_comp_report_ext') : get_string('status_competent', 'local_comp_report_ext')) . '</td>
    </tr>
</table>';
$pdf->writeHTML($kpihtml, true, false, true, false, '');
$pdf->Ln(4);

// Courses Breakdown Table.
$tablehtml = '
<h4 style="font-size: 11pt; font-weight: bold; margin-bottom: 4px;">' . get_string('courses_overview_title', 'local_comp_report_ext') . '</h4>
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

foreach ($courses_data as $c) {
    $bgcolor = $c['raw_overall'] >= 80 ? '#ecfdf5' : ($c['raw_overall'] >= 60 ? '#eff6ff' : '#fef2f2');
    $tablehtml .= '
        <tr bgcolor="' . $bgcolor . '">
            <td width="8%" align="center">' . $c['id'] . '</td>
            <td width="32%"><b>' . s($c['fullname']) . '</b><br><small style="color: #64748b;">(' . s($c['shortname']) . ')</small></td>
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
