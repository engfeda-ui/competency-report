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
 * Group Analytics Dashboard.
 *
 * Visual dashboard displaying aggregate competency performance, learning curves,
 * distribution charts, and gap analysis for a group or whole course cohort.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$groupid  = optional_param('groupid', 0, PARAM_INT);

// Access control.
require_login($courseid);
$context = context_course::instance($courseid);
$canviewext = has_capability('local/comp_report_ext:viewreports', $context);
$canviewold = has_capability('local/competency_report:viewreports', $context);
if (!$canviewext && !$canviewold) {
    require_capability('local/comp_report_ext:viewreports', $context);
}

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

$PAGE->set_url('/local/comp_report_ext/group_analytics_dashboard.php', [
    'courseid' => $courseid,
    'groupid'  => $groupid,
]);
$PAGE->set_title(get_string('group_analytics_dashboard', 'local_comp_report_ext'));
$PAGE->set_heading($course->fullname . ' — ' . get_string('group_analytics_dashboard', 'local_comp_report_ext'));
$PAGE->set_pagelayout('course');
$PAGE->set_context($context);

// Group selection options.
$groups = groups_get_all_groups($courseid);
$groupoptions = [
    [
        'id' => 0,
        'name' => get_string('allgroups', 'local_comp_report_ext'),
        'selected' => ($groupid == 0),
    ],
];
foreach ($groups as $g) {
    $groupoptions[] = [
        'id' => $g->id,
        'name' => format_string($g->name),
        'selected' => ($g->id == $groupid),
    ];
}

// Query students (Filtering by selected group and student role).
if ($groupid > 0) {
    $students = $DB->get_records_sql("
        SELECT DISTINCT u.id, u.firstname, u.lastname
          FROM {groups_members} gm
          JOIN {user} u ON u.id = gm.userid
          JOIN {role_assignments} ra ON ra.userid = u.id
          JOIN {context} ctx ON ctx.id = ra.contextid
         WHERE gm.groupid = :groupid
           AND ctx.instanceid = :courseid
           AND ra.roleid = (SELECT id FROM {role} WHERE shortname = 'student')
           AND u.deleted = 0",
        ['groupid' => $groupid, 'courseid' => $courseid]
    );
} else {
    $students = $DB->get_records_sql("
        SELECT DISTINCT u.id, u.firstname, u.lastname
          FROM {role_assignments} ra
          JOIN {role} r ON r.id = ra.roleid
          JOIN {context} ctx ON ctx.id = ra.contextid
          JOIN {user} u ON u.id = ra.userid
         WHERE ctx.instanceid = :courseid
           AND ctx.contextlevel = 50
           AND r.shortname = 'student'
           AND u.deleted = 0",
        ['courseid' => $courseid]
    );
}

$calculator = new \local_comp_report_ext\competency_calculator($courseid);
$comp_scores = [];
$student_overall_averages = [];

$threshold = (int)(get_config('local_comp_report_ext', 'success_threshold') ?: 60);

$student_list = [];

// Calculate student scores based on configured Assessment Weights.
foreach ($students as $student) {
    $scores = $calculator->get_student_scores((int)$student->id);
    if (empty($scores)) {
        continue;
    }

    $student_sum = 0.0;
    $student_count = 0;

    foreach ($scores as $compid => $data) {
        $shortname = html_entity_decode(format_string($data['competency']->shortname), ENT_QUOTES, 'UTF-8');
        $comp_scores[$compid]['shortname'] = $shortname;
        $comp_scores[$compid]['scores'][]  = (float)$data['percent'];

        $student_sum += (float)$data['percent'];
        $student_count++;
    }

    if ($student_count > 0) {
        $avg_pct = round($student_sum / $student_count, 1);
        $student_overall_averages[] = $avg_pct;

        if ($avg_pct < 40) {
            $tier = 'critical';
            $tier_name = get_string('critical_tier', 'local_comp_report_ext') ?: 'Critical (< 40%)';
            $badge_class = 'badge-danger';
        } else if ($avg_pct < 60) {
            $tier = 'developing';
            $tier_name = get_string('developing_tier', 'local_comp_report_ext') ?: 'Developing (40-59%)';
            $badge_class = 'badge-warning';
        } else if ($avg_pct < 80) {
            $tier = 'proficient';
            $tier_name = get_string('proficient_tier', 'local_comp_report_ext') ?: 'Proficient (60-79%)';
            $badge_class = 'badge-primary';
        } else {
            $tier = 'exemplary';
            $tier_name = get_string('exemplary_tier', 'local_comp_report_ext') ?: 'Exemplary (80-100%)';
            $badge_class = 'badge-success';
        }

        $needs_remediation = ($avg_pct < $threshold);

        $decile_bin = min(9, (int)floor($avg_pct / 10));

        $student_list[] = [
            'index'             => count($student_list) + 1,
            'id'                => (int)$student->id,
            'fullname'          => fullname($student),
            'average'           => number_format($avg_pct, 1),
            'average_raw'       => $avg_pct,
            'tier'              => $tier,
            'tier_name'         => $tier_name,
            'badge_class'       => $badge_class,
            'needs_remediation' => $needs_remediation,
            'decile_bin'        => $decile_bin,
            'detail_url'        => (new moodle_url('/local/comp_report_ext/student_competency_detail.php', [
                'courseid' => $courseid,
                'userid'   => $student->id,
            ]))->out(false),
        ];
    }
}

// Calculate Dashboard KPIs.
$has_data = !empty($student_overall_averages);
$avg_mastery = 0.0;
$remediation_percent = 0.0;
$top_strength = '—';
$critical_gap = '—';

// Mastery distribution tiers.
$distribution = ['critical' => 0, 'developing' => 0, 'proficient' => 0, 'exemplary' => 0];

// Radar chart labels & data.
$radar_labels = [];
$radar_data = [];

// Score Distribution Histogram labels & data (10 detailed decile bins).
$histogram_labels = ['0-10%', '11-20%', '21-30%', '31-40%', '41-50%', '51-60%', '61-70%', '71-80%', '81-90%', '91-100%'];
$score_histogram  = array_fill(0, 10, 0);

// Theory vs Practice labels & data.
$theory_data = [];
$practice_data = [];

if ($has_data) {
    // 1. Average Mastery
    $avg_mastery = round(array_sum($student_overall_averages) / count($student_overall_averages), 1);

    // 2. Remediation rate
    $remediation_count = 0;
    foreach ($student_overall_averages as $avg) {
        if ($avg < $threshold) {
            $remediation_count++;
        }
    }
    $remediation_percent = round(($remediation_count / count($student_overall_averages)) * 100, 1);

    // 3. Strengths and gaps
    $comp_averages = [];
    foreach ($comp_scores as $compid => $cdata) {
        $avg_score = round(array_sum($cdata['scores']) / count($cdata['scores']), 1);
        $comp_averages[$compid] = [
            'shortname' => $cdata['shortname'],
            'average'   => $avg_score,
        ];
        // Populate radar chart
        $radar_labels[] = $cdata['shortname'];
        $radar_data[]   = $avg_score;
    }
    uasort($comp_averages, function ($a, $b) {
        return $a['average'] <=> $b['average'];
    });

    if (!empty($comp_averages)) {
        $keys = array_keys($comp_averages);
        $first_comp = $comp_averages[$keys[0]];
        $last_comp = $comp_averages[$keys[count($keys) - 1]];

        $critical_gap = html_entity_decode($first_comp['shortname'], ENT_QUOTES, 'UTF-8') . ' (' . number_format($first_comp['average'], 1) . '%)';
        $top_strength = html_entity_decode($last_comp['shortname'], ENT_QUOTES, 'UTF-8') . ' (' . number_format($last_comp['average'], 1) . '%)';
    }

    // 4. Mastery Distribution
    foreach ($student_overall_averages as $avg) {
        if ($avg < 40) {
            $distribution['critical']++;
        } else if ($avg < 60) {
            $distribution['developing']++;
        } else if ($avg < 80) {
            $distribution['proficient']++;
        } else {
            $distribution['exemplary']++;
        }
    }

    // 5. Score Distribution Histogram (10 decile bins)
    foreach ($student_overall_averages as $avg) {
        if ($avg <= 10) {
            $score_histogram[0]++;
        } else if ($avg <= 20) {
            $score_histogram[1]++;
        } else if ($avg <= 30) {
            $score_histogram[2]++;
        } else if ($avg <= 40) {
            $score_histogram[3]++;
        } else if ($avg <= 50) {
            $score_histogram[4]++;
        } else if ($avg <= 60) {
            $score_histogram[5]++;
        } else if ($avg <= 70) {
            $score_histogram[6]++;
        } else if ($avg <= 80) {
            $score_histogram[7]++;
        } else if ($avg <= 90) {
            $score_histogram[8]++;
        } else {
            $score_histogram[9]++;
        }
    }

    // 6. Theory vs Practice Gap Analysis
    foreach ($comp_scores as $compid => $cdata) {
        $t_scores = [];
        $p_scores = [];

        foreach ($students as $student) {
            $student_scores = $calculator->get_student_scores((int)$student->id, $compid);
            if (!empty($student_scores[$compid]['breakdown'])) {
                foreach ($student_scores[$compid]['breakdown'] as $b) {
                    if ($b['type'] === 'quiz') {
                        $t_scores[] = (float)$b['score_pct'];
                    } else if ($b['type'] === 'practical') {
                        $p_scores[] = (float)$b['score_pct'];
                    }
                }
            }
        }

        $theory_data[]   = !empty($t_scores) ? round(array_sum($t_scores) / count($t_scores), 1) : 0.0;
        $practice_data[] = !empty($p_scores) ? round(array_sum($p_scores) / count($p_scores), 1) : 0.0;
    }
}

// Package data for views.
$renderdata = new stdClass();
$renderdata->courseid          = $courseid;
$renderdata->groupid           = $groupid;
$renderdata->groups            = $groupoptions;
$renderdata->has_data          = $has_data;
$renderdata->avg_mastery       = number_format($avg_mastery, 1);
$renderdata->remediation_rate  = number_format($remediation_percent, 1);
$renderdata->top_strength      = $top_strength;
$renderdata->critical_gap      = $critical_gap;

// JSON strings for Chart.js rendering scripts
$renderdata->radar_labels_json = json_encode($radar_labels);
$renderdata->radar_data_json   = json_encode($radar_data);

$renderdata->dist_data_json    = json_encode([
    $distribution['critical'],
    $distribution['developing'],
    $distribution['proficient'],
    $distribution['exemplary']
]);

$renderdata->histogram_labels_json = json_encode($histogram_labels);
$renderdata->histogram_data_json   = json_encode($score_histogram);

$renderdata->gap_labels_json    = json_encode($radar_labels);
$renderdata->gap_theory_json    = json_encode($theory_data);
$renderdata->gap_practice_json  = json_encode($practice_data);

$renderdata->student_list      = $student_list;
$renderdata->student_list_json = json_encode($student_list);

// Output rendering.
echo $OUTPUT->header();

$page = new \local_comp_report_ext\output\group_analytics_dashboard_page($renderdata);
echo $OUTPUT->render($page);

echo $OUTPUT->footer();
