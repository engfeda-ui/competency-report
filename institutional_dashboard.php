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
 * Institutional & Multi-Specialization Dashboard.
 *
 * Provides a high-level cross-specialization and regional performance dashboard
 * aggregating multiple courses, dynamic group-to-region mapping, and master student cohorts.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$courseid      = optional_param('courseid', 0, PARAM_INT);
$rawcourseids  = optional_param_array('courseids', [], PARAM_INT);
$action        = optional_param('action', '', PARAM_ALPHAEXT);
$savemsg       = optional_param('savemsg', 0, PARAM_INT);

// Authentication & Context Setup.
if ($courseid > 0) {
    require_login($courseid);
    $context = context_course::instance($courseid);
} else {
    require_login();
    $context = context_system::instance();
}

$canviewext = has_capability('local/comp_report_ext:viewreports', $context);
$canviewold = has_capability('local/competency_report:viewreports', $context);
if (!$canviewext && !$canviewold && !has_capability('moodle/site:config', $context)) {
    require_capability('local/comp_report_ext:viewreports', $context);
}

// Setup Page.
$PAGE->set_url('/local/comp_report_ext/institutional_dashboard.php', [
    'courseid' => $courseid,
]);
$PAGE->set_title(get_string('institutional_dashboard', 'local_comp_report_ext'));
$PAGE->set_heading(get_string('institutional_dashboard', 'local_comp_report_ext'));
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
        'id, fullname, shortname, category'
    );
} else {
    $mycourses = enrol_get_my_courses('id, fullname, shortname, category', 'fullname ASC');
    $allcourses = [];
    foreach ($mycourses as $c) {
        if ($c->id > 1) {
            $allcourses[$c->id] = $c;
        }
    }
}

// Determine selected courses.
$selectedcourseids = [];
if (!empty($rawcourseids)) {
    foreach ($rawcourseids as $cid) {
        if (isset($allcourses[$cid])) {
            $selectedcourseids[] = (int)$cid;
        }
    }
}
// Default to current course or first available if none selected.
if (empty($selectedcourseids)) {
    if ($courseid > 0 && isset($allcourses[$courseid])) {
        $selectedcourseids = [$courseid];
    } else if (!empty($allcourses)) {
        $selectedcourseids = array_slice(array_keys($allcourses), 0, min(5, count($allcourses)));
    }
}

// 2. Load saved Group-to-Region mappings.
$savedmappingraw = get_config('local_comp_report_ext', 'group_region_mappings');
$savedmappings = !empty($savedmappingraw) ? json_decode($savedmappingraw, true) : [];
if (!is_array($savedmappings)) {
    $savedmappings = [];
}

// 3. Handle Saving Group-to-Region Mappings via POST.
if ($action === 'save_mapping' && $_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    $postedmap = optional_param_array('group_region', [], PARAM_TEXT);
    foreach ($postedmap as $gid => $rname) {
        $cleanrname = trim($rname);
        if (!empty($cleanrname)) {
            $savedmappings[(int)$gid] = $cleanrname;
        } else if (isset($savedmappings[(int)$gid])) {
            unset($savedmappings[(int)$gid]);
        }
    }
    set_config('group_region_mappings', json_encode($savedmappings), 'local_comp_report_ext');

    $redirecturl = new moodle_url('/local/comp_report_ext/institutional_dashboard.php', [
        'courseid' => $courseid,
        'savemsg'  => 1,
    ]);
    foreach ($selectedcourseids as $cid) {
        $redirecturl->param('courseids[]', $cid);
    }
    redirect($redirecturl);
}

// 4. Auto-suggest Region Helper function.
$suggest_region = function($groupname) use (&$savedmappings) {
    $gname = (string)$groupname;
    if (preg_match('/(الرياض|الوسطى|ryd|central)/iu', $gname)) {
        return get_string('region_central', 'local_comp_report_ext');
    }
    if (preg_match('/(الدمام|الشرقية|الخبر|الأحساء|dmm|east|khobar)/iu', $gname)) {
        return get_string('region_eastern', 'local_comp_report_ext');
    }
    if (preg_match('/(جدة|الغربية|مكة|المدينة|ينبع|jed|west|makkah|madinah)/iu', $gname)) {
        return get_string('region_western', 'local_comp_report_ext');
    }
    if (preg_match('/(الجنوبية|أبها|جيزان|نجران|south|abha|jizan)/iu', $gname)) {
        return get_string('region_southern', 'local_comp_report_ext');
    }
    return get_string('region_central', 'local_comp_report_ext');
};

// 5. Discover groups and students across all selected courses.
$all_groups_discovered = [];
$group_region_lookup   = [];
$courses_data          = [];
$all_students_master   = [];

if (!empty($selectedcourseids)) {
    [$incourses, $courseparams] = $DB->get_in_or_equal($selectedcourseids, SQL_PARAMS_NAMED, 'cid');

    // A. Fetch groups in selected courses.
    $grouprecords = $DB->get_records_sql("
        SELECT g.id, g.courseid, g.name, c.fullname as coursename, c.shortname as courseshortname
          FROM {groups} g
          JOIN {course} c ON c.id = g.courseid
         WHERE g.courseid $incourses
      ORDER BY c.fullname ASC, g.name ASC",
        $courseparams
    );

    foreach ($grouprecords as $g) {
        $savedregion = $savedmappings[(int)$g->id] ?? null;
        $isautosuggested = false;
        if (empty($savedregion)) {
            $savedregion = $suggest_region($g->name);
            $isautosuggested = true;
        }
        $group_region_lookup[(int)$g->id] = $savedregion;

        $all_groups_discovered[] = [
            'id'              => (int)$g->id,
            'courseid'        => (int)$g->courseid,
            'coursename'      => format_string($g->coursename),
            'courseshortname' => format_string($g->courseshortname),
            'name'            => format_string($g->name),
            'region'          => $savedregion,
            'is_suggested'    => $isautosuggested,
        ];
    }

    // B. Preload practical scores across selected courses.
    $pracsql = "SELECT p.courseid, p.studentid, AVG(p.competency_percent) AS avg_percent
                  FROM {local_comp_report_ext_prac} p
                 WHERE p.courseid $incourses
              GROUP BY p.courseid, p.studentid";
    $pracrecords = $DB->get_records_sql($pracsql, $courseparams);
    $prac_lookup = [];
    foreach ($pracrecords as $pr) {
        $prac_lookup[$pr->courseid][$pr->studentid] = (float)$pr->avg_percent;
    }

    // C. Preload primary quizzes and attempts across selected courses.
    $allquizzes = $DB->get_records_sql("
        SELECT id, course, name, sumgrades, grade
          FROM {quiz}
         WHERE course $incourses
      ORDER BY course ASC, id ASC",
        $courseparams
    );
    $course_primary_quizzes = [];
    foreach ($allquizzes as $q) {
        $isretake = preg_match('/(retake|إعادة)/iu', $q->name);
        if (!isset($course_primary_quizzes[$q->course]) && !$isretake) {
            $course_primary_quizzes[$q->course] = $q;
        }
    }
    // Fallback for courses where all quizzes have retake in name.
    foreach ($allquizzes as $q) {
        if (!isset($course_primary_quizzes[$q->course])) {
            $course_primary_quizzes[$q->course] = $q;
        }
    }

    $quiz_attempts_lookup = [];
    if (!empty($course_primary_quizzes)) {
        $quizids = array_map(function($q) { return $q->id; }, $course_primary_quizzes);
        [$inquizzes, $quizparams] = $DB->get_in_or_equal($quizids, SQL_PARAMS_NAMED, 'qid');
        $allattempts = $DB->get_records_sql("
            SELECT id, quiz, userid, attempt, sumgrades
              FROM {quiz_attempts}
             WHERE quiz $inquizzes AND state = 'finished'
          ORDER BY userid ASC, attempt ASC",
            $quizparams
        );
        foreach ($allattempts as $att) {
            $quiz_attempts_lookup[$att->quiz][$att->userid][$att->attempt] = (float)$att->sumgrades;
        }
    }

    // D. Preload gradebook assignments & participation across selected courses.
    $allgradeitems = $DB->get_records_sql("
        SELECT id, courseid, itemname, itemmodule, itemtype, grademax
          FROM {grade_items}
         WHERE courseid $incourses",
        $courseparams
    );
    $assign_items = [];
    $part_items   = [];
    foreach ($allgradeitems as $gi) {
        if ($gi->itemmodule === 'assign') {
            $assign_items[$gi->courseid][$gi->id] = (float)$gi->grademax;
        } else if ($gi->itemtype === 'manual' || in_array($gi->itemmodule, ['attendance', 'forum'])) {
            $part_items[$gi->courseid][$gi->id] = (float)$gi->grademax;
        }
    }

    $assign_grades_lookup = [];
    $part_grades_lookup   = [];
    if (!empty($allgradeitems)) {
        $itemids = array_keys($allgradeitems);
        [$initemids, $itemparams] = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED, 'giid');
        $allgrades = $DB->get_records_sql("
            SELECT id, itemid, userid, finalgrade
              FROM {grade_grades}
             WHERE itemid $initemids AND finalgrade IS NOT NULL",
            $itemparams
        );
        foreach ($allgrades as $g) {
            $gi = $allgradeitems[$g->itemid];
            $cid = $gi->courseid;
            if ($gi->itemmodule === 'assign' && $gi->grademax > 0) {
                $pct = ((float)$g->finalgrade / (float)$gi->grademax) * 100.0;
                $assign_grades_lookup[$cid][$g->userid][] = $pct;
            } else if ($gi->grademax > 0) {
                $part_grades_lookup[$cid][$g->userid] = min(20.0, ((float)$g->finalgrade / (float)$gi->grademax) * 20.0);
            }
        }
    }

    // E. Fetch students enrolled per course with their groups.
    foreach ($selectedcourseids as $cid) {
        $cinfo = $allcourses[$cid];
        $students = $DB->get_records_sql("
            SELECT DISTINCT u.id, u.firstname, u.lastname, u.email, u.idnumber,
                   gm.groupid, g.name as groupname
              FROM {role_assignments} ra
              JOIN {role} r ON r.id = ra.roleid
              JOIN {context} ctx ON ctx.id = ra.contextid
              JOIN {user} u ON u.id = ra.userid
         LEFT JOIN {groups_members} gm ON gm.userid = u.id AND gm.groupid IN (SELECT id FROM {groups} WHERE courseid = :cgid)
         LEFT JOIN {groups} g ON g.id = gm.groupid
             WHERE ctx.instanceid = :courseid
               AND ctx.contextlevel = 50
               AND r.shortname = 'student'
               AND u.deleted = 0
          ORDER BY u.firstname ASC, u.lastname ASC",
            ['courseid' => $cid, 'cgid' => $cid]
        );

        $primaryquiz = $course_primary_quizzes[$cid] ?? null;
        $sumgradesmax = ($primaryquiz && $primaryquiz->sumgrades > 0) ? (float)$primaryquiz->sumgrades : 100.0;

        foreach ($students as $st) {
            $groupid = (int)$st->groupid;
            $groupname = $st->groupname ? format_string($st->groupname) : get_string('region_unassigned', 'local_comp_report_ext');
            $regionname = $group_region_lookup[$groupid] ?? $suggest_region($groupname);

            // 1. Theory score /30 (with 18 cap on retakes).
            $att1 = $primaryquiz ? ($quiz_attempts_lookup[$primaryquiz->id][$st->id][1] ?? null) : null;
            $att2 = $primaryquiz ? ($quiz_attempts_lookup[$primaryquiz->id][$st->id][2] ?? null) : null;
            $att3 = $primaryquiz ? ($quiz_attempts_lookup[$primaryquiz->id][$st->id][3] ?? null) : null;

            $theory_orig = ($att1 !== null) ? round(($att1 / $sumgradesmax) * 30.0, 2) : 0.0;
            $retake1_t   = ($att2 !== null) ? min(18.0, round(($att2 / $sumgradesmax) * 30.0, 2)) : null;
            $retake2_t   = ($att3 !== null) ? min(18.0, round(($att3 / $sumgradesmax) * 30.0, 2)) : null;
            $best_theory = max($theory_orig, $retake1_t ?? 0.0, $retake2_t ?? 0.0);
            $theory_pass = ($best_theory >= 18.0);

            // 2. Practical score /40.
            $prac_pct = $prac_lookup[$cid][$st->id] ?? null;
            $best_practical = ($prac_pct !== null) ? round(($prac_pct / 100.0) * 40.0, 2) : null;
            $prac_pass = ($best_practical !== null) ? ($best_practical >= 24.0) : null;

            // 3. Participation /20 & Assignments /10.
            $participation = $part_grades_lookup[$cid][$st->id] ?? 0.0;
            $assign_pcts   = $assign_grades_lookup[$cid][$st->id] ?? [];
            $assignments   = !empty($assign_pcts) ? min(10.0, round((array_sum($assign_pcts) / count($assign_pcts) / 100.0) * 10.0, 2)) : 0.0;

            // 4. Term Total /100 & Evaluation Scale.
            $term_total = round($best_theory + ($best_practical ?? 0.0) + $participation + $assignments, 2);
            $overall_pass = ($term_total >= 60.0);
            $eval = \local_comp_report_ext\competency_calculator::eval_scale($term_total);

            $all_students_master[] = [
                'id'              => (int)$st->id,
                'fullname'        => fullname($st),
                'idnumber'        => $st->idnumber ?: '—',
                'email'           => $st->email,
                'courseid'        => (int)$cid,
                'coursename'      => format_string($cinfo->fullname),
                'courseshortname' => format_string($cinfo->shortname),
                'groupid'         => $groupid,
                'groupname'       => $groupname,
                'region'          => $regionname,
                'best_theory'     => number_format($best_theory, 1),
                'theory_pass'     => $theory_pass,
                'best_practical'  => ($best_practical !== null) ? number_format($best_practical, 1) : '—',
                'prac_pass'       => $prac_pass,
                'has_practical'   => ($best_practical !== null),
                'participation'   => number_format($participation, 1),
                'assignments'     => number_format($assignments, 1),
                'term_total'      => number_format($term_total, 1),
                'term_total_raw'  => $term_total,
                'overall_pass'    => $overall_pass,
                'gpa_letter'      => $eval['letter'],
                'gpa_value'       => number_format($eval['gpa'], 2),
                'gpa_label'       => $eval['label'],
                'gpa_badge'       => $eval['badge'],
                'detail_url'      => (new moodle_url('/local/comp_report_ext/student_competency_detail.php', [
                    'courseid' => $cid,
                    'userid'   => $st->id,
                ]))->out(false),
            ];
        }
    }
}

// 6. Aggregate analytics by Specialization, by Region, and by Group.
$spec_agg   = [];
$region_agg = [];
$group_agg  = [];
$cohort_kpis = [
    'total_students' => count($all_students_master),
    'passed_count'   => 0,
    'failed_count'   => 0,
    'total_score'    => 0.0,
];

foreach ($all_students_master as $st) {
    $cid = $st['courseid'];
    $rname = $st['region'];
    $gid = $st['groupid'];
    $gname = $st['groupname'];
    $pass = $st['overall_pass'];
    $score = $st['term_total_raw'];

    if ($pass) {
        $cohort_kpis['passed_count']++;
    } else {
        $cohort_kpis['failed_count']++;
    }
    $cohort_kpis['total_score'] += $score;

    // Specialization aggregate.
    if (!isset($spec_agg[$cid])) {
        $spec_agg[$cid] = [
            'id'           => $cid,
            'name'         => $st['coursename'],
            'shortname'    => $st['courseshortname'],
            'total'        => 0,
            'passed'       => 0,
            'score_sum'    => 0.0,
            'gpa_counts'   => [],
        ];
    }
    $spec_agg[$cid]['total']++;
    if ($pass) { $spec_agg[$cid]['passed']++; }
    $spec_agg[$cid]['score_sum'] += $score;
    $spec_agg[$cid]['gpa_counts'][$st['gpa_letter']] = ($spec_agg[$cid]['gpa_counts'][$st['gpa_letter']] ?? 0) + 1;

    // Region aggregate.
    if (!isset($region_agg[$rname])) {
        $region_agg[$rname] = [
            'name'         => $rname,
            'total'        => 0,
            'passed'       => 0,
            'score_sum'    => 0.0,
            'courses'      => [],
            'groups'       => [],
        ];
    }
    $region_agg[$rname]['total']++;
    if ($pass) { $region_agg[$rname]['passed']++; }
    $region_agg[$rname]['score_sum'] += $score;
    $region_agg[$rname]['courses'][$cid] = $st['coursename'];
    $region_agg[$rname]['groups'][$gid] = $gname;

    // Group aggregate.
    $gkey = $cid . '_' . $gid;
    if (!isset($group_agg[$gkey])) {
        $group_agg[$gkey] = [
            'id'           => $gid,
            'name'         => $gname,
            'coursename'   => $st['coursename'],
            'region'       => $rname,
            'total'        => 0,
            'passed'       => 0,
            'score_sum'    => 0.0,
        ];
    }
    $group_agg[$gkey]['total']++;
    if ($pass) { $group_agg[$gkey]['passed']++; }
    $group_agg[$gkey]['score_sum'] += $score;
}

// Build output view models.
$spec_rows = [];
foreach ($spec_agg as $cid => $data) {
    $avg = $data['total'] > 0 ? round($data['score_sum'] / $data['total'], 1) : 0.0;
    $passrate = $data['total'] > 0 ? round(($data['passed'] / $data['total']) * 100.0, 1) : 0.0;
    $spec_rows[] = [
        'id'        => $cid,
        'name'      => $data['name'],
        'shortname' => $data['shortname'],
        'total'     => $data['total'],
        'passed'    => $data['passed'],
        'failed'    => $data['total'] - $data['passed'],
        'avg_score' => number_format($avg, 1),
        'pass_rate' => number_format($passrate, 1),
        'pass_color'=> $passrate >= 80 ? '#28a745' : ($passrate >= 60 ? '#007bff' : '#dc3545'),
        'badge'     => $passrate >= 80 ? 'badge-success' : ($passrate >= 60 ? 'badge-primary' : 'badge-danger'),
    ];
}
usort($spec_rows, function($a, $b) { return (float)$b['pass_rate'] <=> (float)$a['pass_rate']; });

$region_rows = [];
foreach ($region_agg as $rname => $data) {
    $avg = $data['total'] > 0 ? round($data['score_sum'] / $data['total'], 1) : 0.0;
    $passrate = $data['total'] > 0 ? round(($data['passed'] / $data['total']) * 100.0, 1) : 0.0;
    $region_rows[] = [
        'name'        => $rname,
        'total'       => $data['total'],
        'passed'      => $data['passed'],
        'failed'      => $data['total'] - $data['passed'],
        'avg_score'   => number_format($avg, 1),
        'pass_rate'   => number_format($passrate, 1),
        'spec_count'  => count($data['courses']),
        'group_count' => count($data['groups']),
        'pass_color'  => $passrate >= 80 ? '#28a745' : ($passrate >= 60 ? '#007bff' : '#dc3545'),
        'badge'       => $passrate >= 80 ? 'badge-success' : ($passrate >= 60 ? 'badge-primary' : 'badge-danger'),
    ];
}
usort($region_rows, function($a, $b) { return (float)$b['pass_rate'] <=> (float)$a['pass_rate']; });

$group_rows = [];
foreach ($group_agg as $gkey => $data) {
    $avg = $data['total'] > 0 ? round($data['score_sum'] / $data['total'], 1) : 0.0;
    $passrate = $data['total'] > 0 ? round(($data['passed'] / $data['total']) * 100.0, 1) : 0.0;
    $group_rows[] = [
        'name'       => $data['name'],
        'coursename' => $data['coursename'],
        'region'     => $data['region'],
        'total'      => $data['total'],
        'passed'     => $data['passed'],
        'avg_score'  => number_format($avg, 1),
        'pass_rate'  => number_format($passrate, 1),
        'badge'      => $passrate >= 80 ? 'badge-success' : ($passrate >= 60 ? 'badge-primary' : 'badge-danger'),
    ];
}
usort($group_rows, function($a, $b) { return (float)$b['pass_rate'] <=> (float)$a['pass_rate']; });

// Global summary indicators.
$total_students = $cohort_kpis['total_students'];
$overall_pass_rate = $total_students > 0 ? round(($cohort_kpis['passed_count'] / $total_students) * 100.0, 1) : 0.0;
$overall_avg_score = $total_students > 0 ? round($cohort_kpis['total_score'] / $total_students, 1) : 0.0;

// Prepare courses dropdown options.
$course_options = [];
foreach ($allcourses as $c) {
    $selected = in_array((int)$c->id, $selectedcourseids);
    $course_options[] = [
        'id'       => (int)$c->id,
        'name'     => format_string($c->fullname),
        'selected' => $selected,
    ];
}

// 7. Prepare render data object.
$renderdata = new stdClass();
$renderdata->courseid              = $courseid;
$renderdata->has_data              = !empty($all_students_master);
$renderdata->savemsg               = $savemsg;
$renderdata->sesskey               = sesskey();

// Course Selector data.
$renderdata->course_options        = $course_options;
$renderdata->selected_courses_count= count($selectedcourseids);
$renderdata->all_groups            = $all_groups_discovered;
$renderdata->groups_count          = count($all_groups_discovered);

// KPI Summary Scorecards.
$renderdata->total_students        = $total_students;
$renderdata->total_specializations = count($spec_rows);
$renderdata->total_regions         = count($region_rows);
$renderdata->total_groups          = count($group_rows);
$renderdata->overall_pass_rate     = number_format($overall_pass_rate, 1);
$renderdata->overall_avg_score     = number_format($overall_avg_score, 1);

// Tables & Lists.
$renderdata->spec_list             = $spec_rows;
$renderdata->region_list           = $region_rows;
$renderdata->group_list            = $group_rows;
$renderdata->student_list          = $all_students_master;

// Chart JSON payloads.
$renderdata->spec_labels_json      = json_encode(array_column($spec_rows, 'shortname'));
$renderdata->spec_pass_json        = json_encode(array_map('floatval', array_column($spec_rows, 'pass_rate')));
$renderdata->spec_avg_json         = json_encode(array_map('floatval', array_column($spec_rows, 'avg_score')));

$renderdata->region_labels_json    = json_encode(array_column($region_rows, 'name'));
$renderdata->region_pass_json      = json_encode(array_map('floatval', array_column($region_rows, 'pass_rate')));
$renderdata->region_avg_json       = json_encode(array_map('floatval', array_column($region_rows, 'avg_score')));

$renderdata->student_list_json     = json_encode($all_students_master);

echo $OUTPUT->header();

$page = new \local_comp_report_ext\output\institutional_dashboard_page($renderdata);
echo $OUTPUT->render($page);

echo $OUTPUT->footer();
