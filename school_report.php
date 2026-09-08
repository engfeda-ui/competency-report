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
 * Modern Institutional & Site-Wide Competency Dashboard.
 *
 * Provides a high-level executive dashboard aggregating competency achievement,
 * course comparisons, KPI metrics, and focus skill rankings across the institution.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

global $DB, $OUTPUT, $PAGE;

// 1. Authentication & Capability Enforcement.
require_login();
$context = context_system::instance();

if (!has_capability('moodle/site:config', $context) && !has_capability('local/comp_report_ext:viewreports', $context)) {
    require_capability('moodle/site:config', $context);
}

// 2. Parameters & Page Setup.
$categoryid = optional_param('categoryid', 0, PARAM_INT);

$pageurl = new moodle_url('/local/comp_report_ext/school_report.php', ['categoryid' => $categoryid]);
$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('institutional_dashboard_title', 'local_comp_report_ext'));
$PAGE->set_heading(get_string('institutional_dashboard_title', 'local_comp_report_ext'));

// 3. Category Filter Setup.
$categories = $DB->get_records('course_categories', null, 'name ASC', 'id, name');
$catoptions = [];
$catoptions[] = [
    'id' => 0,
    'name' => get_string('all_categories', 'local_comp_report_ext'),
    'selected' => ($categoryid == 0),
];
foreach ($categories as $cat) {
    $catoptions[] = [
        'id' => $cat->id,
        'name' => format_string($cat->name),
        'selected' => ($categoryid == $cat->id),
    ];
}

// SQL filtering clause for categories.
$catwhereq = '';
$catwherep = '';
$paramsq = [];
$paramsp = [];

if ($categoryid > 0) {
    $catwhereq = ' AND c.category = :catid ';
    $catwherep = ' AND c.category = :catid ';
    $paramsq['catid'] = $categoryid;
    $paramsp['catid'] = $categoryid;
}

// 4. Efficient Data Aggregations (Single-pass SQL queries).

// A. Theory Competency Aggregations per Course.
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

// B. Practical Competency Aggregations per Course.
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

// C. Site-wide Competency Performance (for Top/Lowest Rankings).
$sqlcompstheory = "
    SELECT comp.id, comp.shortname, comp.description,
           CAST(SUM(qa.maxfraction) AS DECIMAL(12, 1)) AS attempts,
           CAST(SUM(qas.fraction) AS DECIMAL(12, 1)) AS correct
    FROM {competency} comp
    JOIN {qbank_comp_ext_qmap} m ON m.competencyid = comp.id
    JOIN {question_attempts} qa ON qa.questionid = m.questionid
    JOIN {question_usages} qu ON qu.id = qa.questionusageid
    JOIN {quiz_attempts} quiza ON quiza.uniqueid = qu.id
    JOIN {quiz} q ON q.id = quiza.quiz
    JOIN {course} c ON c.id = q.course
    JOIN (
        SELECT MAX(fraction) AS fraction, questionattemptid
        FROM {question_attempt_steps}
        GROUP BY questionattemptid
    ) qas ON qas.questionattemptid = qa.id
    WHERE quiza.state = 'finished' AND q.course != " . SITEID . " $catwhereq
    GROUP BY comp.id, comp.shortname, comp.description
";
$compstheory = $DB->get_records_sql($sqlcompstheory, $paramsq);

$sqlcompsprac = "
    SELECT comp.id, comp.shortname, comp.description,
           AVG(pr.competency_percent) AS avg_percent,
           COUNT(pr.id) AS entries
    FROM {competency} comp
    JOIN {local_comp_report_ext_prac} pr ON pr.competencyid = comp.id
    JOIN {course} c ON c.id = pr.courseid
    WHERE pr.courseid != " . SITEID . " $catwherep
    GROUP BY comp.id, comp.shortname, comp.description
";
$compsprac = $DB->get_records_sql($sqlcompsprac, $paramsp);

// D. Total Distinct Evaluated Students.
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

// 5. Build Course Master List & KPI Metrics.
$courseids = array_unique(array_merge(
    array_keys($theorybycourse),
    array_keys($practicalbycourse)
));

$coursesdata = [];
$totalmasterysum = 0;
$evaluatedcoursescount = 0;

$tierhighcount = 0;
$tiermodcount  = 0;
$tierlowcount  = 0;

$chartcourselabels = [];
$chartcoursedata   = [];

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

        // Theory metrics.
        $hastheory = isset($theorybycourse[$cid]) && $theorybycourse[$cid]->attempts > 0;
        $theoryrate = $hastheory ? round(($theorybycourse[$cid]->correct / $theorybycourse[$cid]->attempts) * 100, 1) : null;
        $theorystudents = $hastheory ? (int)$theorybycourse[$cid]->student_count : 0;

        // Practical metrics.
        $hasprac = isset($practicalbycourse[$cid]) && $practicalbycourse[$cid]->total_entries > 0;
        $pracrate = $hasprac ? round((float)$practicalbycourse[$cid]->avg_percent, 1) : null;
        $pracstudents = $hasprac ? (int)$practicalbycourse[$cid]->student_count : 0;

        // Overall calculation for course.
        $overallrate = 0.0;
        if ($hastheory && $hasprac) {
            $overallrate = round(($theoryrate + $pracrate) / 2, 1);
        } else if ($hastheory) {
            $overallrate = $theoryrate;
        } else if ($hasprac) {
            $overallrate = $pracrate;
        }

        $totalstudents = max($theorystudents, $pracstudents);

        // Tier classification.
        if ($overallrate >= 80) {
            $tierhighcount++;
            $statuskey = 'status_excellent';
            $badgeclass = 'badge-success text-white';
            $rowclass = 'table-success';
        } else if ($overallrate >= 60) {
            $tiermodcount++;
            $statuskey = 'status_competent';
            $badgeclass = 'badge-info text-white';
            $rowclass = 'table-info';
        } else {
            $tierlowcount++;
            $statuskey = 'status_at_risk';
            $badgeclass = 'badge-danger text-white';
            $rowclass = 'table-danger';
        }

        $coursesdata[] = [
            'id'             => $cid,
            'fullname'       => format_string($cinfo->fullname),
            'shortname'      => format_string($cinfo->shortname),
            'category_name'  => format_string($cinfo->category_name ?? get_string('all_categories', 'local_comp_report_ext')),
            'students_count' => $totalstudents,
            'theory_rate'    => $hastheory ? number_format($theoryrate, 1) . '%' : '—',
            'prac_rate'      => $hasprac ? number_format($pracrate, 1) . '%' : '—',
            'overall_rate'   => number_format($overallrate, 1) . '%',
            'raw_overall'    => $overallrate,
            'status_label'   => get_string($statuskey, 'local_comp_report_ext'),
            'badge_class'    => $badgeclass,
            'row_class'      => $rowclass,
            'report_url'     => (new moodle_url(
                '/local/comp_report_ext/course_master_report.php',
                ['courseid' => $cid]
            ))->out(false),
        ];

        $totalmasterysum += $overallrate;
        $evaluatedcoursescount++;

        // Add to chart arrays.
        $chartcourselabels[] = format_string($cinfo->shortname);
        $chartcoursedata[]   = $overallrate;
    }
}

// 6. Overall Institutional KPI Calculations.
$overallinstitutionmastery = $evaluatedcoursescount > 0 ? round($totalmasterysum / $evaluatedcoursescount, 1) : 0.0;

// 7. Site-wide Competencies Ranking (Top 5 & Lowest 5).
$allcomps = [];
foreach ($compstheory as $compid => $ct) {
    $crate = $ct->attempts > 0 ? ($ct->correct / $ct->attempts) * 100 : 0;
    $allcomps[$compid] = [
        'id'          => $compid,
        'shortname'   => format_string($ct->shortname),
        'description' => html_entity_decode(strip_tags($ct->description), ENT_QUOTES, 'UTF-8'),
        'rates'       => [$crate],
    ];
}
foreach ($compsprac as $compid => $cp) {
    if (!isset($allcomps[$compid])) {
        $allcomps[$compid] = [
            'id'          => $compid,
            'shortname'   => format_string($cp->shortname),
            'description' => html_entity_decode(strip_tags($cp->description), ENT_QUOTES, 'UTF-8'),
            'rates'       => [],
        ];
    }
    $allcomps[$compid]['rates'][] = (float)$cp->avg_percent;
}

$rankedcomps = [];
foreach ($allcomps as $compid => $cinfo) {
    $compavg = !empty($cinfo['rates']) ? round(array_sum($cinfo['rates']) / count($cinfo['rates']), 1) : 0.0;
    $rankedcomps[] = [
        'id'          => $compid,
        'shortname'   => $cinfo['shortname'],
        'description' => $cinfo['description'],
        'rate'        => $compavg,
        'rate_str'    => number_format($compavg, 1) . '%',
        'is_high'     => $compavg >= 70,
        'is_low'      => $compavg < 60,
    ];
}

usort($rankedcomps, function ($a, $b) {
    return $b['rate'] <=> $a['rate'];
});

$top5competencies    = array_slice($rankedcomps, 0, 5);
$lowest5competencies = array_reverse(array_slice(array_reverse($rankedcomps), 0, 5));
$totalassessedcomps  = count($rankedcomps);

// 8. Package Data for Output.
$renderdata = new stdClass();
$renderdata->has_data                    = !empty($coursesdata);
$renderdata->categoryid                  = $categoryid;
$renderdata->categories                  = $catoptions;
$renderdata->courses                     = $coursesdata;
$renderdata->total_courses               = $evaluatedcoursescount;
$renderdata->total_students              = number_format($totalevaluatedstudents);
$renderdata->total_competencies          = number_format($totalassessedcomps);
$renderdata->overall_mastery             = number_format($overallinstitutionmastery, 1);
$renderdata->top_competencies            = $top5competencies;
$renderdata->lowest_competencies         = $lowest5competencies;

// Chart JSON Payloads.
$renderdata->chart_courses_labels_json   = json_encode($chartcourselabels);
$renderdata->chart_courses_data_json     = json_encode($chartcoursedata);
$renderdata->chart_dist_data_json        = json_encode([$tierhighcount, $tiermodcount, $tierlowcount]);

$renderdata->pdf_url = (new moodle_url('/local/comp_report_ext/school_pdf.php', ['categoryid' => $categoryid]))->out(false);

// 9. Render Page.
echo $OUTPUT->header();

$page = new \local_comp_report_ext\output\school_report_page($renderdata);
echo $OUTPUT->render($page);

echo $OUTPUT->footer();
