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
$PAGE->set_title(get_string('groupperformance', 'local_comp_report_ext'));
$PAGE->set_heading(format_string($course->fullname) . ' — ' . get_string('groupperformance', 'local_comp_report_ext'));
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
    $students = $DB->get_records_sql(
        "SELECT DISTINCT u.id, u.firstname, u.lastname
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
    $students = $DB->get_records_sql(
        "SELECT DISTINCT u.id, u.firstname, u.lastname
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
$compscores = [];
$studentaverages = [];

$threshold = (int)(get_config('local_comp_report_ext', 'success_threshold') ?: 60);

$studentlist = [];

// Calculate student scores based on configured Assessment Weights.
foreach ($students as $student) {
    $scores = $calculator->get_student_scores((int)$student->id);
    if (empty($scores)) {
        continue;
    }

    $studentsum = 0.0;
    $studentcount = 0;

    foreach ($scores as $compid => $data) {
        $shortname = html_entity_decode(format_string($data['competency']->shortname), ENT_QUOTES, 'UTF-8');
        $compscores[$compid]['shortname'] = $shortname;
        $compscores[$compid]['scores'][]  = (float)$data['percent'];

        $studentsum += (float)$data['percent'];
        $studentcount++;
    }

    if ($studentcount > 0) {
        $avgpct = round($studentsum / $studentcount, 1);
        $studentaverages[] = $avgpct;

        if ($avgpct < 40) {
            $tier = 'critical';
            $tiername = get_string('critical_tier', 'local_comp_report_ext') ?: 'Critical (< 40%)';
            $badgeclass = 'badge-danger';
        } else if ($avgpct < 60) {
            $tier = 'developing';
            $tiername = get_string('developing_tier', 'local_comp_report_ext') ?: 'Developing (40-59%)';
            $badgeclass = 'badge-warning';
        } else if ($avgpct < 80) {
            $tier = 'proficient';
            $tiername = get_string('proficient_tier', 'local_comp_report_ext') ?: 'Proficient (60-79%)';
            $badgeclass = 'badge-primary';
        } else {
            $tier = 'exemplary';
            $tiername = get_string('exemplary_tier', 'local_comp_report_ext') ?: 'Exemplary (80-100%)';
            $badgeclass = 'badge-success';
        }

        $needsremediation = ($avgpct < $threshold);

        $decilebin = min(9, (int)floor($avgpct / 10));

        $studentlist[] = [
            'index'             => count($studentlist) + 1,
            'id'                => (int)$student->id,
            'fullname'          => fullname($student),
            'average'           => number_format($avgpct, 1),
            'average_raw'       => $avgpct,
            'tier'              => $tier,
            'tier_name'         => $tiername,
            'badge_class'       => $badgeclass,
            'needs_remediation' => $needsremediation,
            'decile_bin'        => $decilebin,
            'detail_url'        => (new moodle_url('/local/comp_report_ext/student_competency_detail.php', [
                'courseid' => $courseid,
                'userid'   => $student->id,
            ]))->out(false),
        ];
    }
}

// Calculate Dashboard KPIs.
$hasdata = !empty($studentaverages);
$avgmastery = 0.0;
$remediationpercent = 0.0;
$topstrength = '—';
$criticalgap = '—';

// Mastery distribution tiers.
$distribution = ['critical' => 0, 'developing' => 0, 'proficient' => 0, 'exemplary' => 0];

// Radar chart labels & data.
$radarlabels = [];
$radardata = [];

// Score Distribution Histogram labels & data (10 detailed decile bins).
$histogramlabels = ['0-10%', '11-20%', '21-30%', '31-40%', '41-50%', '51-60%', '61-70%', '71-80%', '81-90%', '91-100%'];
$scorehistogram  = array_fill(0, 10, 0);

// Theory vs Practice labels & data.
$theorydata = [];
$practicedata = [];

if ($hasdata) {
    // 1. Average Mastery.
    $avgmastery = round(array_sum($studentaverages) / count($studentaverages), 1);

    // 2. Remediation rate.
    $remediationcount = 0;
    foreach ($studentaverages as $avg) {
        if ($avg < $threshold) {
            $remediationcount++;
        }
    }
    $remediationpercent = round(($remediationcount / count($studentaverages)) * 100, 1);

    // 3. Strengths and gaps.
    $compaverages = [];
    foreach ($compscores as $compid => $cdata) {
        $avgscore = round(array_sum($cdata['scores']) / count($cdata['scores']), 1);
        $compaverages[$compid] = [
            'shortname' => $cdata['shortname'],
            'average'   => $avgscore,
        ];
        // Populate radar chart.
        $radarlabels[] = $cdata['shortname'];
        $radardata[]   = $avgscore;
    }
    uasort($compaverages, function ($a, $b) {
        return $a['average'] <=> $b['average'];
    });

    if (!empty($compaverages)) {
        $keys = array_keys($compaverages);
        $firstcomp = $compaverages[$keys[0]];
        $lastcomp = $compaverages[$keys[count($keys) - 1]];

        $gapname = html_entity_decode($firstcomp['shortname'], ENT_QUOTES, 'UTF-8');
        $criticalgap = $gapname . ' (' . number_format($firstcomp['average'], 1) . '%)';

        $strname = html_entity_decode($lastcomp['shortname'], ENT_QUOTES, 'UTF-8');
        $topstrength = $strname . ' (' . number_format($lastcomp['average'], 1) . '%)';
    }

    // 4. Mastery Distribution.
    foreach ($studentaverages as $avg) {
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

    // 5. Score Distribution Histogram (10 decile bins).
    foreach ($studentaverages as $avg) {
        if ($avg <= 10) {
            $scorehistogram[0]++;
        } else if ($avg <= 20) {
            $scorehistogram[1]++;
        } else if ($avg <= 30) {
            $scorehistogram[2]++;
        } else if ($avg <= 40) {
            $scorehistogram[3]++;
        } else if ($avg <= 50) {
            $scorehistogram[4]++;
        } else if ($avg <= 60) {
            $scorehistogram[5]++;
        } else if ($avg <= 70) {
            $scorehistogram[6]++;
        } else if ($avg <= 80) {
            $scorehistogram[7]++;
        } else if ($avg <= 90) {
            $scorehistogram[8]++;
        } else {
            $scorehistogram[9]++;
        }
    }

    // 6. Theory vs Practice Gap Analysis.
    foreach ($compscores as $compid => $cdata) {
        $tscores = [];
        $pscores = [];

        foreach ($students as $student) {
            $studentscores = $calculator->get_student_scores((int)$student->id, $compid);
            if (!empty($studentscores[$compid]['breakdown'])) {
                foreach ($studentscores[$compid]['breakdown'] as $b) {
                    if ($b['type'] === 'quiz') {
                        $tscores[] = (float)$b['score_pct'];
                    } else if ($b['type'] === 'practical') {
                        $pscores[] = (float)$b['score_pct'];
                    }
                }
            }
        }

        $theorydata[]   = !empty($tscores) ? round(array_sum($tscores) / count($tscores), 1) : 0.0;
        $practicedata[] = !empty($pscores) ? round(array_sum($pscores) / count($pscores), 1) : 0.0;
    }
}

// Package data for views.
$renderdata = new stdClass();
$renderdata->courseid          = $courseid;
$renderdata->groupid           = $groupid;
$renderdata->groups            = $groupoptions;
$renderdata->has_data          = $hasdata;
$renderdata->avg_mastery       = number_format($avgmastery, 1);
$renderdata->remediation_rate  = number_format($remediationpercent, 1);
$renderdata->top_strength      = $topstrength;
$renderdata->critical_gap      = $criticalgap;

// JSON strings for Chart.js rendering scripts.
$renderdata->radar_labels_json = json_encode($radarlabels);
$renderdata->radar_data_json   = json_encode($radardata);

$renderdata->dist_data_json    = json_encode([
    $distribution['critical'],
    $distribution['developing'],
    $distribution['proficient'],
    $distribution['exemplary'],
]);

$renderdata->histogram_labels_json = json_encode($histogramlabels);
$renderdata->histogram_data_json   = json_encode($scorehistogram);

$renderdata->gap_labels_json    = json_encode($radarlabels);
$renderdata->gap_theory_json    = json_encode($theorydata);
$renderdata->gap_practice_json  = json_encode($practicedata);

$renderdata->student_list      = $studentlist;
$renderdata->student_list_json = json_encode($studentlist);

// Output rendering.
echo $OUTPUT->header();

$page = new \local_comp_report_ext\output\group_analytics_dashboard_page($renderdata);
echo $OUTPUT->render($page);

echo $OUTPUT->footer();
