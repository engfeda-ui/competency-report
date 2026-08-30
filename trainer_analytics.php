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
 * Trainer Performance Analytics Page.
 *
 * Provides comparative pedagogical analysis across trainers, showing
 * student mastery achievements, practical assessment scores, pass rates,
 * specializations taught, and competency strengths/weaknesses.
 * Supports Single Course mode, Multi-Course custom selection, and All Specializations mode.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$courseid     = optional_param('courseid', 0, PARAM_INT);
$rawcourseids = optional_param_array('courseids', [], PARAM_INT);
$groupid      = optional_param('groupid', 0, PARAM_INT);

// Authentication & Context Setup.
if ($courseid > 0) {
    require_login($courseid);
    $context = context_course::instance($courseid);
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
} else {
    require_login();
    $context = context_system::instance();
    $course = null;
}

$canviewext = has_capability('local/comp_report_ext:viewreports', $context);
$canviewold = has_capability('local/competency_report:viewreports', $context);
if (!$canviewext && !$canviewold && !has_capability('moodle/site:config', $context)) {
    require_capability('local/comp_report_ext:viewreports', $context);
}

$PAGE->set_url('/local/comp_report_ext/trainer_analytics.php', [
    'courseid' => $courseid,
    'groupid'  => $groupid,
]);
$PAGE->set_title(get_string('trainer_analytics', 'local_comp_report_ext'));
$PAGE->set_heading($course ? format_string($course->fullname) . ' — ' . get_string('trainer_analytics', 'local_comp_report_ext') : get_string('trainer_analytics', 'local_comp_report_ext'));
$PAGE->set_pagelayout($courseid > 0 ? 'course' : 'admin');
$PAGE->set_context($context);

// 1. Fetch available courses for selection.
$isadmin = is_siteadmin();
if ($isadmin) {
    $allcourses = $DB->get_records_select(
        'course',
        'id > 1 AND visible = 1',
        null,
        'fullname ASC',
        'id, fullname, shortname'
    );
} else {
    $mycourses = enrol_get_my_courses('id, fullname, shortname', 'fullname ASC');
    $allcourses = [];
    foreach ($mycourses as $c) {
        if ($c->id > 1) {
            $allcourses[$c->id] = $c;
        }
    }
}

// 2. Resolve Selected Courses.
$selectedcourseids = [];
if (!empty($rawcourseids)) {
    foreach ($rawcourseids as $cid) {
        if (isset($allcourses[$cid])) {
            $selectedcourseids[] = (int)$cid;
        }
    }
}
if (empty($selectedcourseids)) {
    if ($courseid > 0 && isset($allcourses[$courseid])) {
        $selectedcourseids = [$courseid];
    } else if (!empty($allcourses)) {
        $selectedcourseids = array_keys($allcourses);
    }
}

$selected_count    = count($selectedcourseids);
$total_count       = count($allcourses);
$is_all_selected   = ($selected_count === $total_count);
$is_single_course  = ($selected_count === 1);
$active_course_id  = $is_single_course ? reset($selectedcourseids) : 0;
$isallcourses      = !$is_single_course;

// Build Course Checkbox Options for Dropdown.
$courseoptions = [];
foreach ($allcourses as $c) {
    $courseoptions[] = [
        'id'       => (int)$c->id,
        'name'     => format_string($c->fullname),
        'shortname'=> format_string($c->shortname),
        'selected' => in_array((int)$c->id, $selectedcourseids),
    ];
}

if ($is_all_selected) {
    $course_btn_label = get_string('all_courses_specializations', 'local_comp_report_ext');
} else if ($is_single_course) {
    $single_cid = reset($selectedcourseids);
    $course_btn_label = format_string($allcourses[$single_cid]->shortname ?? $allcourses[$single_cid]->fullname);
} else {
    $course_btn_label = get_string('selected_courses_count', 'local_comp_report_ext', $selected_count);
}

// 3. Fetch available groups for single course mode.
$groupoptions = [[
    'id'       => 0,
    'name'     => get_string('allgroups', 'local_comp_report_ext'),
    'selected' => ($groupid == 0),
]];
if ($is_single_course && $active_course_id > 0) {
    $groups = groups_get_all_groups($active_course_id);
    foreach ($groups as $g) {
        $groupoptions[] = [
            'id'       => $g->id,
            'name'     => format_string($g->name),
            'selected' => ($groupid == $g->id),
        ];
    }
}

// 4. Fetch trainer practical evaluation records.
$trainers_prac_records = [];
if (!empty($selectedcourseids)) {
    [$incourses, $courseparams] = $DB->get_in_or_equal($selectedcourseids, SQL_PARAMS_NAMED, 'cid');
    $trainer_prac_sql = "
        SELECT p.trainerid, p.courseid, c.fullname as coursename, c.shortname as courseshortname,
               u.firstname, u.lastname, u.email,
               COUNT(DISTINCT p.studentid) AS student_count,
               COUNT(p.id) AS total_evaluations,
               AVG(p.competency_percent) AS avg_practical_pct
          FROM {local_comp_report_ext_prac} p
          JOIN {user} u ON u.id = p.trainerid
          JOIN {course} c ON c.id = p.courseid
         WHERE p.courseid $incourses AND u.deleted = 0
      GROUP BY p.trainerid, p.courseid, c.fullname, c.shortname, u.firstname, u.lastname, u.email";
    $trainers_prac_records = $DB->get_records_sql($trainer_prac_sql, $courseparams);
}

// 5. Aggregate trainer profiles across courses.
$trainers_map = [];
foreach ($trainers_prac_records as $tp) {
    $tid = (int)$tp->trainerid;
    if (!isset($trainers_map[$tid])) {
        $trainers_map[$tid] = (object)[
            'id'                => $tid,
            'fullname'          => fullname($tp),
            'email'             => $tp->email,
            'courses'           => [],
            'evaluations_count' => 0,
            'total_students'    => 0,
            'practical_pcts'    => [],
        ];
    }
    $trainers_map[$tid]->courses[$tp->courseid] = format_string($tp->courseshortname ?: $tp->coursename);
    $trainers_map[$tid]->evaluations_count += (int)$tp->total_evaluations;
    $trainers_map[$tid]->total_students += (int)$tp->student_count;
    $trainers_map[$tid]->practical_pcts[] = (float)$tp->avg_practical_pct;
}

// In single course mode, also include enrolled teachers who haven't submitted practicals yet.
if ($is_single_course && $active_course_id > 0) {
    $ccontext = context_course::instance($active_course_id);
    $enrolled_teachers = get_enrolled_users($ccontext, 'moodle/course:update', $groupid, 'u.id, u.firstname, u.lastname, u.email');
    foreach ($enrolled_teachers as $et) {
        if (!isset($trainers_map[$et->id])) {
            $trainers_map[$et->id] = (object)[
                'id'                => (int)$et->id,
                'fullname'          => fullname($et),
                'email'             => $et->email,
                'courses'           => [$active_course_id => format_string($allcourses[$active_course_id]->shortname ?? 'Course')],
                'evaluations_count' => 0,
                'total_students'    => 0,
                'practical_pcts'    => [0.0],
            ];
        }
    }
}

// 6. Preload student competency scores for each trainer.
$trainer_rows         = [];
$trainer_names        = [];
$trainer_mastery_data = [];
$trainer_pass_data    = [];

foreach ($trainers_map as $trainerid => $trainer) {
    if (!empty($selectedcourseids)) {
        [$incourses, $courseparams] = $DB->get_in_or_equal($selectedcourseids, SQL_PARAMS_NAMED, 'cid');
        $courseparams['trainerid'] = $trainerid;
        $st_records = $DB->get_records_sql(
            "SELECT DISTINCT p.courseid, p.studentid, p.competencyid, p.competency_percent
               FROM {local_comp_report_ext_prac} p
              WHERE p.courseid $incourses AND p.trainerid = :trainerid",
            $courseparams
        );
    } else {
        $st_records = [];
    }

    $comp_ids = [];
    $student_comp_map = [];
    $comp_aggregates  = [];
    foreach ($st_records as $rec) {
        $comp_ids[$rec->competencyid] = $rec->competencyid;
        $student_comp_map[$rec->studentid][] = (float)$rec->competency_percent;
        $comp_aggregates[$rec->competencyid][] = (float)$rec->competency_percent;
    }

    // Resolve competency names.
    $comp_names = [];
    if (!empty($comp_ids)) {
        [$incomps, $compparams] = $DB->get_in_or_equal(array_keys($comp_ids), SQL_PARAMS_NAMED, 'cmp');
        $comp_objs = $DB->get_records_sql("SELECT id, shortname FROM {competency} WHERE id $incomps", $compparams);
        foreach ($comp_objs as $co) {
            $comp_names[$co->id] = format_string($co->shortname);
        }
    }

    $avg_mastery = 0.0;
    $pass_rate   = 0.0;
    $passed_students = 0;
    $total_students = count($student_comp_map);

    if ($total_students > 0) {
        $all_student_avgs = [];
        foreach ($student_comp_map as $sid => $scores) {
            $savg = array_sum($scores) / count($scores);
            $all_student_avgs[] = $savg;
            if ($savg >= 60.0) {
                $passed_students++;
            }
        }
        $avg_mastery = round(array_sum($all_student_avgs) / count($all_student_avgs), 1);
        $pass_rate   = round(($passed_students / $total_students) * 100.0, 1);
    } else if (!empty($trainer->practical_pcts)) {
        $avg_mastery = round(array_sum($trainer->practical_pcts) / count($trainer->practical_pcts), 1);
        $pass_rate   = ($avg_mastery >= 60.0) ? 100.0 : 0.0;
        $total_students = $trainer->total_students;
    }

    // Top and weak competencies for this trainer.
    $top_comp_name  = '—';
    $weak_comp_name = '—';
    if (!empty($comp_aggregates)) {
        $comp_averages = [];
        foreach ($comp_aggregates as $cid => $cscores) {
            $cname = $comp_names[$cid] ?? ('Comp ' . $cid);
            $comp_averages[$cname] = array_sum($cscores) / count($cscores);
        }
        arsort($comp_averages);
        $top_comp_name  = key($comp_averages) . ' (' . round(reset($comp_averages), 1) . '%)';
        end($comp_averages);
        $weak_comp_name = key($comp_averages) . ' (' . round(end($comp_averages), 1) . '%)';
    }

    $color = ($avg_mastery >= 80) ? '#28a745' : (($avg_mastery >= 60) ? '#007bff' : (($avg_mastery >= 40) ? '#ffc107' : '#dc3545'));
    $badge = ($avg_mastery >= 80) ? 'badge-success' : (($avg_mastery >= 60) ? 'badge-primary' : (($avg_mastery >= 40) ? 'badge-warning' : 'badge-danger'));

    $specs_list = array_values($trainer->courses);
    $specs_display = implode(', ', $specs_list);

    $trainer_rows[] = [
        'index'             => count($trainer_rows) + 1,
        'id'                => $trainer->id,
        'fullname'          => $trainer->fullname,
        'email'             => $trainer->email,
        'specs_count'       => count($specs_list),
        'specs_display'     => $specs_display,
        'student_count'     => $total_students,
        'evaluations_count' => $trainer->evaluations_count,
        'avg_mastery'       => number_format($avg_mastery, 1),
        'avg_mastery_raw'   => $avg_mastery,
        'pass_rate'         => number_format($pass_rate, 1),
        'top_comp'          => $top_comp_name,
        'weak_comp'         => $weak_comp_name,
        'color'             => $color,
        'badge'             => $badge,
    ];

    $trainer_names[]        = $trainer->fullname;
    $trainer_mastery_data[] = $avg_mastery;
    $trainer_pass_data[]    = $pass_rate;
}

// Sort trainers by avg mastery descending.
usort($trainer_rows, function($a, $b) {
    return $b['avg_mastery_raw'] <=> $a['avg_mastery_raw'];
});
foreach ($trainer_rows as $idx => &$tr) {
    $tr['index'] = $idx + 1;
}
unset($tr);

$hasdata = !empty($trainer_rows);

// Prepare render data.
$renderdata = new stdClass();
$renderdata->courseid               = $courseid;
$renderdata->groupid                = $groupid;
$renderdata->groups                 = $groupoptions;
$renderdata->course_options         = $courseoptions;
$renderdata->course_btn_label       = $course_btn_label;
$renderdata->selected_courses_count = $selected_count;
$renderdata->is_all_courses         = $isallcourses;
$renderdata->is_single_course       = $is_single_course;
$renderdata->has_data               = $hasdata;
$renderdata->trainer_count          = count($trainer_rows);

$renderdata->trainer_list           = $trainer_rows;
$renderdata->trainer_names_json     = json_encode($trainer_names);
$renderdata->trainer_mastery_json   = json_encode($trainer_mastery_data);
$renderdata->trainer_pass_json      = json_encode($trainer_pass_data);
$renderdata->trainer_list_json      = json_encode($trainer_rows);

echo $OUTPUT->header();

$page = new \local_comp_report_ext\output\trainer_analytics_page($renderdata);
echo $OUTPUT->render($page);

echo $OUTPUT->footer();
