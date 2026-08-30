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
 * and competency strengths/weaknesses for each trainer's cohort.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$groupid  = optional_param('groupid', 0, PARAM_INT);

require_login($courseid);
$context = context_course::instance($courseid);

$canviewext = has_capability('local/comp_report_ext:viewreports', $context);
$canviewold = has_capability('local/competency_report:viewreports', $context);
if (!$canviewext && !$canviewold) {
    require_capability('local/comp_report_ext:viewreports', $context);
}

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

$PAGE->set_url('/local/comp_report_ext/trainer_analytics.php', [
    'courseid' => $courseid,
    'groupid'  => $groupid,
]);
$PAGE->set_title(get_string('trainer_analytics', 'local_comp_report_ext'));
$PAGE->set_heading(format_string($course->fullname) . ' — ' . get_string('trainer_analytics', 'local_comp_report_ext'));
$PAGE->set_pagelayout('course');
$PAGE->set_context($context);

// 1. Fetch available groups.
$groups = groups_get_all_groups($courseid);
$groupoptions = [[
    'id'       => 0,
    'name'     => get_string('allgroups', 'local_comp_report_ext'),
    'selected' => ($groupid == 0),
]];
foreach ($groups as $g) {
    $groupoptions[] = [
        'id'       => $g->id,
        'name'     => format_string($g->name),
        'selected' => ($groupid == $g->id),
    ];
}

// 2. Fetch all trainers with practical evaluations in this course.
$trainer_prac_sql = "
    SELECT p.trainerid, u.firstname, u.lastname, u.email,
           COUNT(DISTINCT p.studentid) AS student_count,
           COUNT(p.id) AS total_evaluations,
           AVG(p.competency_percent) AS avg_practical_pct
      FROM {local_comp_report_ext_prac} p
      JOIN {user} u ON u.id = p.trainerid
     WHERE p.courseid = :courseid AND u.deleted = 0
  GROUP BY p.trainerid, u.firstname, u.lastname, u.email";
$trainers_prac = $DB->get_records_sql($trainer_prac_sql, ['courseid' => $courseid]);

// Also fetch enrolled teachers in this course who may not have entered practicals yet.
$enrolled_teachers = get_enrolled_users($context, 'moodle/course:update', $groupid, 'u.id, u.firstname, u.lastname, u.email');

$all_trainers_map = [];
foreach ($trainers_prac as $tp) {
    $all_trainers_map[$tp->trainerid] = (object)[
        'id'               => (int)$tp->trainerid,
        'fullname'         => fullname($tp),
        'email'            => $tp->email,
        'student_count'    => (int)$tp->student_count,
        'evaluations_count'=> (int)$tp->total_evaluations,
        'avg_practical'    => round((float)$tp->avg_practical_pct, 1),
    ];
}
foreach ($enrolled_teachers as $et) {
    if (!isset($all_trainers_map[$et->id])) {
        $all_trainers_map[$et->id] = (object)[
            'id'               => (int)$et->id,
            'fullname'         => fullname($et),
            'email'            => $et->email,
            'student_count'    => 0,
            'evaluations_count'=> 0,
            'avg_practical'    => 0.0,
        ];
    }
}

// 3. Preload competencies in this course.
$compsql = "SELECT DISTINCT c.id, c.shortname
              FROM {qbank_comp_ext_qmap} m
              JOIN {competency} c ON c.id = m.competencyid
             WHERE m.courseid = :courseid";
$competencies = $DB->get_records_sql($compsql, ['courseid' => $courseid]);

// 4. Compute detailed cohort analytics per trainer.
$calculator = new \local_comp_report_ext\competency_calculator($courseid);
$trainer_rows = [];
$trainer_names = [];
$trainer_mastery_data = [];
$trainer_pass_data = [];

foreach ($all_trainers_map as $trainerid => $trainer) {
    // Get students evaluated by this trainer (or in groups assigned to this teacher).
    $trainer_students_sql = "
        SELECT DISTINCT p.studentid
          FROM {local_comp_report_ext_prac} p
         WHERE p.courseid = :courseid AND p.trainerid = :trainerid";
    $st_records = $DB->get_records_sql($trainer_students_sql, ['courseid' => $courseid, 'trainerid' => $trainerid]);
    $st_ids = array_keys($st_records);

    $cohort_size = count($st_ids);
    $avg_mastery = 0.0;
    $pass_rate   = 0.0;
    $top_comp_name = '—';
    $weak_comp_name = '—';

    if (!empty($st_ids)) {
        // Preload and calculate scores.
        $calculator->preload_user_data(array_map('intval', $st_ids));
        $all_scores = [];
        $passed_students = 0;
        $comp_aggregates = [];

        foreach ($st_ids as $sid) {
            $scores = $calculator->get_student_scores((int)$sid);
            $student_comp_scores = [];
            foreach ($scores as $cid => $data) {
                $student_comp_scores[] = (float)$data['percent'];
                $cname = is_object($data['competency']) ? $data['competency']->shortname : ($data['competency']['shortname'] ?? 'Comp ' . $cid);
                $comp_aggregates[$cname][] = (float)$data['percent'];
            }
            if (!empty($student_comp_scores)) {
                $student_avg = array_sum($student_comp_scores) / count($student_comp_scores);
                $all_scores[] = $student_avg;
                if ($student_avg >= 60.0) {
                    $passed_students++;
                }
            }
        }

        if (!empty($all_scores)) {
            $avg_mastery = round(array_sum($all_scores) / count($all_scores), 1);
            $pass_rate   = round(($passed_students / count($all_scores)) * 100.0, 1);
        }

        // Top and weak competencies.
        if (!empty($comp_aggregates)) {
            $comp_averages = [];
            foreach ($comp_aggregates as $cname => $cscores) {
                $comp_averages[$cname] = array_sum($cscores) / count($cscores);
            }
            arsort($comp_averages);
            $top_comp_name  = key($comp_averages) . ' (' . round(reset($comp_averages), 1) . '%)';
            end($comp_averages);
            $weak_comp_name = key($comp_averages) . ' (' . round(end($comp_averages), 1) . '%)';
        }
    } else {
        $avg_mastery = $trainer->avg_practical;
        $pass_rate   = ($avg_mastery >= 60.0) ? 100.0 : 0.0;
        $cohort_size = $trainer->student_count;
    }

    $color = ($avg_mastery >= 80) ? '#28a745' : (($avg_mastery >= 60) ? '#007bff' : (($avg_mastery >= 40) ? '#ffc107' : '#dc3545'));
    $badge = ($avg_mastery >= 80) ? 'badge-success' : (($avg_mastery >= 60) ? 'badge-primary' : (($avg_mastery >= 40) ? 'badge-warning' : 'badge-danger'));

    $trainer_rows[] = [
        'index'            => count($trainer_rows) + 1,
        'id'               => $trainer->id,
        'fullname'         => $trainer->fullname,
        'email'            => $trainer->email,
        'student_count'    => $cohort_size,
        'evaluations_count'=> $trainer->evaluations_count,
        'avg_mastery'      => number_format($avg_mastery, 1),
        'avg_mastery_raw'  => $avg_mastery,
        'pass_rate'        => number_format($pass_rate, 1),
        'avg_practical'    => number_format($trainer->avg_practical, 1),
        'top_comp'         => $top_comp_name,
        'weak_comp'        => $weak_comp_name,
        'color'            => $color,
        'badge'            => $badge,
    ];

    $trainer_names[]        = $trainer->fullname;
    $trainer_mastery_data[] = $avg_mastery;
    $trainer_pass_data[]    = $pass_rate;
}

// Sort trainers by avg mastery descending.
usort($trainer_rows, function($a, $b) {
    return $b['avg_mastery_raw'] <=> $a['avg_mastery_raw'];
});
// Re-index.
foreach ($trainer_rows as $idx => &$tr) {
    $tr['index'] = $idx + 1;
}
unset($tr);

$hasdata = !empty($trainer_rows);

// Prepare render data.
$renderdata = new stdClass();
$renderdata->courseid          = $courseid;
$renderdata->groupid           = $groupid;
$renderdata->groups            = $groupoptions;
$renderdata->has_data          = $hasdata;
$renderdata->trainer_count     = count($trainer_rows);

$renderdata->trainer_list      = $trainer_rows;
$renderdata->trainer_names_json= json_encode($trainer_names);
$renderdata->trainer_mastery_json = json_encode($trainer_mastery_data);
$renderdata->trainer_pass_json    = json_encode($trainer_pass_data);
$renderdata->trainer_list_json    = json_encode($trainer_rows);

echo $OUTPUT->header();

$page = new \local_comp_report_ext\output\trainer_analytics_page($renderdata);
echo $OUTPUT->render($page);

echo $OUTPUT->footer();
