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
$catwhere_q = '';
$catwhere_p = '';
$params_q = [];
$params_p = [];

if ($categoryid > 0) {
    $catwhere_q = ' AND c.category = :catid ';
    $catwhere_p = ' AND c.category = :catid ';
    $params_q['catid'] = $categoryid;
    $params_p['catid'] = $categoryid;
}

// 4. Efficient Data Aggregations (Single-pass SQL queries).

// A. Theory Competency Aggregations per Course.
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

// B. Practical Competency Aggregations per Course.
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

// C. Site-wide Competency Performance (for Top/Lowest Rankings).
$sql_comps_theory = "
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
    WHERE quiza.state = 'finished' AND q.course != " . SITEID . " $catwhere_q
    GROUP BY comp.id, comp.shortname, comp.description
";
$comps_theory = $DB->get_records_sql($sql_comps_theory, $params_q);

$sql_comps_prac = "
    SELECT comp.id, comp.shortname, comp.description,
           AVG(pr.competency_percent) AS avg_percent,
           COUNT(pr.id) AS entries
    FROM {competency} comp
    JOIN {local_comp_report_ext_prac} pr ON pr.competencyid = comp.id
    JOIN {course} c ON c.id = pr.courseid
    WHERE pr.courseid != " . SITEID . " $catwhere_p
    GROUP BY comp.id, comp.shortname, comp.description
";
$comps_prac = $DB->get_records_sql($sql_comps_prac, $params_p);

// D. Total Distinct Evaluated Students.
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

// 5. Build Course Master List & KPI Metrics.
$course_ids = array_unique(array_merge(
    array_keys($theory_by_course),
    array_keys($practical_by_course)
));

$courses_data = [];
$total_mastery_sum = 0;
$evaluated_courses_count = 0;

$tier_high_count = 0;
$tier_mod_count  = 0;
$tier_low_count  = 0;

$chart_course_labels = [];
$chart_course_data   = [];

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

        // Theory metrics.
        $has_theory = isset($theory_by_course[$cid]) && $theory_by_course[$cid]->attempts > 0;
        $theory_rate = $has_theory ? round(($theory_by_course[$cid]->correct / $theory_by_course[$cid]->attempts) * 100, 1) : null;
        $theory_students = $has_theory ? (int)$theory_by_course[$cid]->student_count : 0;

        // Practical metrics.
        $has_prac = isset($practical_by_course[$cid]) && $practical_by_course[$cid]->total_entries > 0;
        $prac_rate = $has_prac ? round((float)$practical_by_course[$cid]->avg_percent, 1) : null;
        $prac_students = $has_prac ? (int)$practical_by_course[$cid]->student_count : 0;

        // Overall calculation for course.
        $overall_rate = 0.0;
        if ($has_theory && $has_prac) {
            $overall_rate = round(($theory_rate + $prac_rate) / 2, 1);
        } else if ($has_theory) {
            $overall_rate = $theory_rate;
        } else if ($has_prac) {
            $overall_rate = $prac_rate;
        }

        $total_students = max($theory_students, $prac_students);

        // Tier classification.
        if ($overall_rate >= 80) {
            $tier_high_count++;
            $status_key = 'status_excellent';
            $badge_class = 'badge-success text-white';
            $row_class = 'table-success';
        } else if ($overall_rate >= 60) {
            $tier_mod_count++;
            $status_key = 'status_competent';
            $badge_class = 'badge-info text-white';
            $row_class = 'table-info';
        } else {
            $tier_low_count++;
            $status_key = 'status_at_risk';
            $badge_class = 'badge-danger text-white';
            $row_class = 'table-danger';
        }

        $courses_data[] = [
            'id'             => $cid,
            'fullname'       => format_string($cinfo->fullname),
            'shortname'      => format_string($cinfo->shortname),
            'category_name'  => format_string($cinfo->category_name ?? get_string('all_categories', 'local_comp_report_ext')),
            'students_count' => $total_students,
            'theory_rate'    => $has_theory ? number_format($theory_rate, 1) . '%' : '—',
            'prac_rate'      => $has_prac ? number_format($prac_rate, 1) . '%' : '—',
            'overall_rate'   => number_format($overall_rate, 1) . '%',
            'raw_overall'    => $overall_rate,
            'status_label'   => get_string($status_key, 'local_comp_report_ext'),
            'badge_class'    => $badge_class,
            'row_class'      => $row_class,
            'report_url'     => (new moodle_url('/local/comp_report_ext/course_master_report.php', ['courseid' => $cid]))->out(false),
        ];

        $total_mastery_sum += $overall_rate;
        $evaluated_courses_count++;

        // Add to chart arrays.
        $chart_course_labels[] = format_string($cinfo->shortname);
        $chart_course_data[]   = $overall_rate;
    }
}

// 6. Overall Institutional KPI Calculations.
$overall_institution_mastery = $evaluated_courses_count > 0 ? round($total_mastery_sum / $evaluated_courses_count, 1) : 0.0;

// 7. Site-wide Competencies Ranking (Top 5 & Lowest 5).
$all_comps = [];
foreach ($comps_theory as $compid => $ct) {
    $crate = $ct->attempts > 0 ? ($ct->correct / $ct->attempts) * 100 : 0;
    $all_comps[$compid] = [
        'id'          => $compid,
        'shortname'   => format_string($ct->shortname),
        'description' => html_entity_decode(strip_tags($ct->description), ENT_QUOTES, 'UTF-8'),
        'rates'       => [$crate],
    ];
}
foreach ($comps_prac as $compid => $cp) {
    if (!isset($all_comps[$compid])) {
        $all_comps[$compid] = [
            'id'          => $compid,
            'shortname'   => format_string($cp->shortname),
            'description' => html_entity_decode(strip_tags($cp->description), ENT_QUOTES, 'UTF-8'),
            'rates'       => [],
        ];
    }
    $all_comps[$compid]['rates'][] = (float)$cp->avg_percent;
}

$ranked_comps = [];
foreach ($all_comps as $compid => $cinfo) {
    $comp_avg = !empty($cinfo['rates']) ? round(array_sum($cinfo['rates']) / count($cinfo['rates']), 1) : 0.0;
    $ranked_comps[] = [
        'id'          => $compid,
        'shortname'   => $cinfo['shortname'],
        'description' => $cinfo['description'],
        'rate'        => $comp_avg,
        'rate_str'    => number_format($comp_avg, 1) . '%',
        'is_high'     => $comp_avg >= 70,
        'is_low'      => $comp_avg < 60,
    ];
}

usort($ranked_comps, function ($a, $b) {
    return $b['rate'] <=> $a['rate'];
});

$top_5_competencies    = array_slice($ranked_comps, 0, 5);
$lowest_5_competencies = array_reverse(array_slice(array_reverse($ranked_comps), 0, 5));
$total_assessed_comps  = count($ranked_comps);

// 8. Package Data for Output.
$renderdata = new stdClass();
$renderdata->has_data                    = !empty($courses_data);
$renderdata->categoryid                  = $categoryid;
$renderdata->categories                  = $catoptions;
$renderdata->courses                     = $courses_data;
$renderdata->total_courses               = $evaluated_courses_count;
$renderdata->total_students              = number_format($total_evaluated_students);
$renderdata->total_competencies          = number_format($total_assessed_comps);
$renderdata->overall_mastery             = number_format($overall_institution_mastery, 1);
$renderdata->top_competencies            = $top_5_competencies;
$renderdata->lowest_competencies         = $lowest_5_competencies;

// Chart JSON Payloads.
$renderdata->chart_courses_labels_json   = json_encode($chart_course_labels);
$renderdata->chart_courses_data_json     = json_encode($chart_course_data);
$renderdata->chart_dist_data_json        = json_encode([$tier_high_count, $tier_mod_count, $tier_low_count]);

$renderdata->pdf_url = (new moodle_url('/local/comp_report_ext/school_pdf.php', ['categoryid' => $categoryid]))->out(false);

// 9. Render Page.
echo $OUTPUT->header();

$page = new \local_comp_report_ext\output\school_report_page($renderdata);
echo $OUTPUT->render($page);

echo $OUTPUT->footer();
