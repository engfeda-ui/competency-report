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
 * Term Comprehensive Report Page.
 *
 * Implements the 100-point term assessment model:
 *   Practical (40%) + Final Theory (30%) + Participation (20%) + Assignments (10%) = 100
 *
 * Enforces retake policy caps:
 *   - Theory Retakes: capped at 18.0 / 30 (60% of 30)
 *   - Practical Retakes: capped at 24.0 / 40 (60% of 40)
 *   - Pass criteria: Total >= 60, Best Theory >= 18, Practical >= 24
 *
 * Supports both Single Course mode and All Courses / Specializations mode.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/csvlib.class.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
$groupid  = optional_param('groupid', 0, PARAM_INT);
$quizid   = optional_param('quizid', 0, PARAM_INT);
$clearcsv = optional_param('clear_csv', 0, PARAM_INT);

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

$PAGE->set_url('/local/comp_report_ext/term_comprehensive_report.php', [
    'courseid' => $courseid,
    'groupid'  => $groupid,
    'quizid'   => $quizid,
]);
$PAGE->set_title(get_string('term_comprehensive_report', 'local_comp_report_ext'));
$PAGE->set_heading($course ? format_string($course->fullname) . ' — ' . get_string('term_comprehensive_report', 'local_comp_report_ext') : get_string('term_comprehensive_report', 'local_comp_report_ext'));
$PAGE->set_pagelayout($courseid > 0 ? 'course' : 'admin');
$PAGE->set_context($context);

// 1. Fetch available courses for the Course Selector dropdown.
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

$courseoptions = [[
    'id'       => 0,
    'name'     => get_string('all_courses_specializations', 'local_comp_report_ext'),
    'selected' => ($courseid == 0),
]];
foreach ($allcourses as $c) {
    $courseoptions[] = [
        'id'       => (int)$c->id,
        'name'     => format_string($c->fullname),
        'selected' => ($courseid == (int)$c->id),
    ];
}

// 2. Fetch available groups.
$groupoptions = [[
    'id'       => 0,
    'name'     => get_string('allgroups', 'local_comp_report_ext'),
    'selected' => ($groupid == 0),
]];
if ($courseid > 0) {
    $groups = groups_get_all_groups($courseid);
    foreach ($groups as $g) {
        $groupoptions[] = [
            'id'       => $g->id,
            'name'     => format_string($g->name),
            'selected' => ($groupid == $g->id),
        ];
    }
}

// Handle clear CSV data from session if requested.
if ($clearcsv && confirm_sesskey()) {
    if (isset($SESSION->comp_report_part_data[$courseid])) {
        unset($SESSION->comp_report_part_data[$courseid]);
    }
    redirect(new moodle_url('/local/comp_report_ext/term_comprehensive_report.php', [
        'courseid' => $courseid,
        'groupid'  => $groupid,
        'quizid'   => $quizid,
    ]));
}

// Handle CSV / TSV text paste or file upload for Participation & Assignments.
$uploadedcustom = $SESSION->comp_report_part_data[$courseid] ?? [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    $csvtext = optional_param('csv_text', '', PARAM_RAW);
    if (!empty($_FILES['part_file']['tmp_name']) && is_uploaded_file($_FILES['part_file']['tmp_name'])) {
        $csvtext = file_get_contents($_FILES['part_file']['tmp_name']);
    }

    if (!empty($csvtext)) {
        $lines = preg_split("/\r\n|\n|\r/", trim($csvtext));
        if (!empty($lines)) {
            $parseddata = [];
            $header = null;
            $hmap = ['email' => -1, 'id' => -1, 'participation' => -1, 'assignments' => -1, 'retake1_t' => -1, 'retake2_t' => -1, 'retake1_p' => -1, 'retake2_p' => -1];

            foreach ($lines as $lineidx => $line) {
                if (trim($line) === '') {
                    continue;
                }
                $delimiter = (strpos($line, "\t") !== false) ? "\t" : ((strpos($line, ';') !== false) ? ';' : ',');
                $cols = str_getcsv($line, $delimiter);

                if ($header === null) {
                    $lowercols = array_map('strtolower', array_map('trim', $cols));
                    foreach ($lowercols as $cidx => $cname) {
                        if (preg_match('/(email|mail|بريد)/i', $cname)) { $hmap['email'] = $cidx; }
                        else if (preg_match('/(id|academic|idnumber|رقم|جامعي|أكاديمي)/i', $cname)) { $hmap['id'] = $cidx; }
                        else if (preg_match('/(part|مشاركة|حضور|attendance)/i', $cname)) { $hmap['participation'] = $cidx; }
                        else if (preg_match('/(assign|واجب|تكليف|homework)/i', $cname)) { $hmap['assignments'] = $cidx; }
                        else if (preg_match('/(retake1_t|retake1.*theory|إعادة.*نظري.*1)/i', $cname)) { $hmap['retake1_t'] = $cidx; }
                        else if (preg_match('/(retake2_t|retake2.*theory|إعادة.*نظري.*2)/i', $cname)) { $hmap['retake2_t'] = $cidx; }
                        else if (preg_match('/(retake1_p|retake1.*prac|إعادة.*عملي.*1)/i', $cname)) { $hmap['retake1_p'] = $cidx; }
                        else if (preg_match('/(retake2_p|retake2.*prac|إعادة.*عملي.*2)/i', $cname)) { $hmap['retake2_p'] = $cidx; }
                    }
                    if ($hmap['email'] !== -1 || $hmap['id'] !== -1 || $hmap['participation'] !== -1) {
                        $header = $lowercols;
                        continue;
                    }
                    $header = [];
                }

                $key = '';
                if ($hmap['email'] !== -1 && !empty($cols[$hmap['email']])) {
                    $key = strtolower(trim($cols[$hmap['email']]));
                } else if ($hmap['id'] !== -1 && !empty($cols[$hmap['id']])) {
                    $key = trim($cols[$hmap['id']]);
                } else if (!empty($cols[0])) {
                    $key = trim($cols[0]);
                }

                if (!empty($key)) {
                    $entry = [];
                    if ($hmap['participation'] !== -1 && isset($cols[$hmap['participation']])) {
                        $entry['participation'] = (float)str_replace(',', '.', trim($cols[$hmap['participation']]));
                    }
                    if ($hmap['assignments'] !== -1 && isset($cols[$hmap['assignments']])) {
                        $entry['assignments'] = (float)str_replace(',', '.', trim($cols[$hmap['assignments']]));
                    }
                    if ($hmap['retake1_t'] !== -1 && isset($cols[$hmap['retake1_t']]) && trim($cols[$hmap['retake1_t']]) !== '') {
                        $entry['retake1_t'] = (float)str_replace(',', '.', trim($cols[$hmap['retake1_t']]));
                    }
                    if ($hmap['retake2_t'] !== -1 && isset($cols[$hmap['retake2_t']]) && trim($cols[$hmap['retake2_t']]) !== '') {
                        $entry['retake2_t'] = (float)str_replace(',', '.', trim($cols[$hmap['retake2_t']]));
                    }
                    if ($hmap['retake1_p'] !== -1 && isset($cols[$hmap['retake1_p']]) && trim($cols[$hmap['retake1_p']]) !== '') {
                        $entry['retake1_p'] = (float)str_replace(',', '.', trim($cols[$hmap['retake1_p']]));
                    }
                    if ($hmap['retake2_p'] !== -1 && isset($cols[$hmap['retake2_p']]) && trim($cols[$hmap['retake2_p']]) !== '') {
                        $entry['retake2_p'] = (float)str_replace(',', '.', trim($cols[$hmap['retake2_p']]));
                    }
                    $parseddata[$key] = $entry;
                }
            }

            if (!empty($parseddata)) {
                if (!isset($SESSION->comp_report_part_data)) {
                    $SESSION->comp_report_part_data = [];
                }
                $SESSION->comp_report_part_data[$courseid] = $parseddata;
                $uploadedcustom = $parseddata;
            }
        }
    }
}

$isallcourses = ($courseid == 0);
$selected_cids = $isallcourses ? array_keys($allcourses) : [$courseid];
$student_rows = [];
$quizoptions = [];
$primary_quiz_name = '—';

if (!empty($selected_cids)) {
    [$incourses, $courseparams] = $DB->get_in_or_equal($selected_cids, SQL_PARAMS_NAMED, 'cid');

    // A. Preload Practical evaluation scores.
    $prac_sql = "SELECT p.courseid, p.studentid, AVG(p.competency_percent) AS avg_percent
                   FROM {local_comp_report_ext_prac} p
                  WHERE p.courseid $incourses
               GROUP BY p.courseid, p.studentid";
    $prac_records = $DB->get_records_sql($prac_sql, $courseparams);
    $prac_lookup = [];
    foreach ($prac_records as $pr) {
        $prac_lookup[$pr->courseid][$pr->studentid] = (float)$pr->avg_percent;
    }

    // B. Preload Quizzes and Attempts.
    $quizzes = $DB->get_records_sql("
        SELECT id, course, name, sumgrades, grade
          FROM {quiz}
         WHERE course $incourses
      ORDER BY course ASC, id ASC",
        $courseparams
    );

    $course_primary_quizzes = [];
    foreach ($quizzes as $q) {
        $isretake = preg_match('/(retake|إعادة)/iu', $q->name);
        if (!isset($course_primary_quizzes[$q->course]) && !$isretake) {
            $course_primary_quizzes[$q->course] = $q;
        }
    }
    foreach ($quizzes as $q) {
        if (!isset($course_primary_quizzes[$q->course])) {
            $course_primary_quizzes[$q->course] = $q;
        }
    }

    if (!$isallcourses && $quizid > 0 && isset($quizzes[$quizid])) {
        $course_primary_quizzes[$courseid] = $quizzes[$quizid];
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

    // C. Preload Gradebook assignments & participation.
    $allgradeitems = $DB->get_records_sql("
        SELECT id, courseid, itemname, itemmodule, itemtype, grademax
          FROM {grade_items}
         WHERE courseid $incourses",
        $courseparams
    );

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
            } else if ($gi->grademax > 0 && ($gi->itemtype === 'manual' || in_array($gi->itemmodule, ['attendance', 'forum']))) {
                $part_grades_lookup[$cid][$g->userid] = min(20.0, ((float)$g->finalgrade / (float)$gi->grademax) * 20.0);
            }
        }
    }

    // D. Fetch students enrolled per course.
    foreach ($selected_cids as $cid) {
        $cinfo = $allcourses[$cid] ?? (object)['fullname' => 'Course ' . $cid, 'shortname' => 'C' . $cid];
        $ccontext = context_course::instance($cid);

        if (!$isallcourses && $groupid > 0) {
            $students = get_enrolled_users($ccontext, 'local/comp_report_ext:viewownreport', $groupid, 'u.id, u.firstname, u.lastname, u.email, u.idnumber');
            if (empty($students)) {
                $students = get_enrolled_users($ccontext, '', $groupid, 'u.id, u.firstname, u.lastname, u.email, u.idnumber');
            }
        } else {
            $students = get_enrolled_users($ccontext, 'local/comp_report_ext:viewownreport', 0, 'u.id, u.firstname, u.lastname, u.email, u.idnumber');
            if (empty($students)) {
                $students = get_enrolled_users($ccontext, '', 0, 'u.id, u.firstname, u.lastname, u.email, u.idnumber');
            }
        }

        $primaryquiz = $course_primary_quizzes[$cid] ?? null;
        $sumgradesmax = ($primaryquiz && $primaryquiz->sumgrades > 0) ? (float)$primaryquiz->sumgrades : 100.0;

        foreach ($students as $st) {
            // Student Groups.
            $stgroups = groups_get_all_groups($cid, $st->id);
            $groupnames = !empty($stgroups) ? implode(', ', array_map(function($g) { return format_string($g->name); }, $stgroups)) : get_string('region_unassigned', 'local_comp_report_ext');

            // 1. Theory score /30.
            $att1 = $primaryquiz ? ($quiz_attempts_lookup[$primaryquiz->id][$st->id][1] ?? null) : null;
            $att2 = $primaryquiz ? ($quiz_attempts_lookup[$primaryquiz->id][$st->id][2] ?? null) : null;
            $att3 = $primaryquiz ? ($quiz_attempts_lookup[$primaryquiz->id][$st->id][3] ?? null) : null;

            $theory_orig = ($att1 !== null) ? round(($att1 / $sumgradesmax) * 30.0, 2) : 0.0;
            $retake1_t   = ($att2 !== null) ? min(18.0, round(($att2 / $sumgradesmax) * 30.0, 2)) : null;
            $retake2_t   = ($att3 !== null) ? min(18.0, round(($att3 / $sumgradesmax) * 30.0, 2)) : null;

            // Custom retakes from upload if available.
            $emailkey = strtolower(trim($st->email));
            $idkey    = trim($st->idnumber);
            $customst = $uploadedcustom[$emailkey] ?? ($uploadedcustom[$idkey] ?? null);

            if ($customst) {
                if (isset($customst['retake1_t'])) { $retake1_t = min(18.0, (float)$customst['retake1_t']); }
                if (isset($customst['retake2_t'])) { $retake2_t = min(18.0, (float)$customst['retake2_t']); }
            }

            $best_theory = max($theory_orig, $retake1_t ?? 0.0, $retake2_t ?? 0.0);
            $theory_pass = ($best_theory >= 18.0);

            // 2. Practical score /40.
            $prac_pct = $prac_lookup[$cid][$st->id] ?? null;
            $prac_orig = ($prac_pct !== null) ? round(($prac_pct / 100.0) * 40.0, 2) : null;
            $retake1_p = ($customst && isset($customst['retake1_p'])) ? min(24.0, (float)$customst['retake1_p']) : null;
            $retake2_p = ($customst && isset($customst['retake2_p'])) ? min(24.0, (float)$customst['retake2_p']) : null;

            $best_prac = null;
            if ($prac_orig !== null || $retake1_p !== null || $retake2_p !== null) {
                $best_prac = max($prac_orig ?? 0.0, $retake1_p ?? 0.0, $retake2_p ?? 0.0);
            }
            $prac_pass = ($best_prac !== null) ? ($best_prac >= 24.0) : null;

            // 3. Participation /20 & Assignments /10.
            $participation = $part_grades_lookup[$cid][$st->id] ?? 0.0;
            $assignments   = 0.0;
            if (!empty($assign_grades_lookup[$cid][$st->id])) {
                $assign_pcts = $assign_grades_lookup[$cid][$st->id];
                $assignments = min(10.0, round((array_sum($assign_pcts) / count($assign_pcts) / 100.0) * 10.0, 2));
            }
            if ($customst) {
                if (isset($customst['participation'])) { $participation = min(20.0, (float)$customst['participation']); }
                if (isset($customst['assignments'])) { $assignments = min(10.0, (float)$customst['assignments']); }
            }

            // 4. Expected Participation benchmark (derived from theory mastery).
            $expected_part = round(($best_theory / 30.0) * 20.0, 1);

            // 5. Term Total /100 & GPA Scale.
            $term_total = round($best_theory + ($best_prac ?? 0.0) + $participation + $assignments, 2);
            $overall_pass = ($term_total >= 60.0);
            $eval = \local_comp_report_ext\competency_calculator::eval_scale($term_total);

            $student_rows[] = [
                'index'                 => count($student_rows) + 1,
                'id'                    => $st->id,
                'fullname'              => fullname($st),
                'email'                 => $st->email,
                'idnumber'              => $st->idnumber ?: '—',
                'courseid'              => $cid,
                'coursename'            => format_string($cinfo->fullname),
                'courseshortname'       => format_string($cinfo->shortname),
                'groupname'             => $groupnames,
                'theory_final_30'       => number_format($theory_orig, 1),
                'theory_orig'           => number_format($theory_orig, 1),
                'retake1_t'             => ($retake1_t !== null) ? number_format($retake1_t, 1) : '—',
                'retake2_t'             => ($retake2_t !== null) ? number_format($retake2_t, 1) : '—',
                'has_retake1'           => ($retake1_t !== null || $retake1_p !== null),
                'has_retake2'           => ($retake2_t !== null || $retake2_p !== null),
                'best_theory'           => number_format($best_theory, 1),
                'theory_pass'           => $theory_pass,
                'prac_orig_40'          => ($prac_orig !== null) ? number_format($prac_orig, 1) : '—',
                'prac_orig'             => ($prac_orig !== null) ? number_format($prac_orig, 1) : '—',
                'retake1_p'             => ($retake1_p !== null) ? number_format($retake1_p, 1) : '—',
                'retake2_p'             => ($retake2_p !== null) ? number_format($retake2_p, 1) : '—',
                'best_practical'        => ($best_prac !== null) ? number_format($best_prac, 1) : '—',
                'best_prac'             => ($best_prac !== null) ? number_format($best_prac, 1) : '—',
                'practical_pass'        => $prac_pass,
                'prac_pass'             => $prac_pass,
                'has_practical'         => ($best_prac !== null),
                'has_prac'              => ($best_prac !== null),
                'participation'         => number_format($participation, 1),
                'assignments'           => number_format($assignments, 1),
                'expected_participation'=> number_format($expected_part, 1),
                'expected_part'         => number_format($expected_part, 1),
                'term_total'            => number_format($term_total, 1),
                'term_total_raw'        => $term_total,
                'overall_pass'          => $overall_pass,
                'gpa_letter'            => $eval['letter'],
                'gpa_value'             => number_format($eval['gpa'], 2),
                'gpa_label'             => $eval['label'],
                'gpa_badge'             => $eval['badge'],
                'detail_url'            => (new moodle_url('/local/comp_report_ext/student_competency_detail.php', [
                    'courseid' => $cid,
                    'userid'   => $st->id,
                ]))->out(false),
            ];
        }
    }
}

// 6. Aggregate cohort summary indicators.
$enrolled_count = count($student_rows);
$passed_count   = 0;
$failed_count   = 0;
$theory_passed  = 0;
$prac_passed    = 0;
$retake1_count  = 0;
$retake2_count  = 0;
$total_term_sum = 0.0;

$gpa_counts = [
    'A+' => 0, 'A' => 0, 'B+' => 0, 'B' => 0,
    'C+' => 0, 'C' => 0, 'D+' => 0, 'D' => 0, 'F' => 0,
];

foreach ($student_rows as $st) {
    if ($st['overall_pass']) {
        $passed_count++;
    } else {
        $failed_count++;
    }
    if ($st['theory_pass']) { $theory_passed++; }
    if ($st['prac_pass'] === true) { $prac_passed++; }
    if ($st['retake1_t'] !== '—' || $st['retake1_p'] !== '—') { $retake1_count++; }
    if ($st['retake2_t'] !== '—' || $st['retake2_p'] !== '—') { $retake2_count++; }

    $total_term_sum += $st['term_total_raw'];
    $gpa_counts[$st['gpa_letter']] = ($gpa_counts[$st['gpa_letter']] ?? 0) + 1;
}

$pass_rate        = ($enrolled_count > 0) ? round(($passed_count / $enrolled_count) * 100.0, 1) : 0.0;
$term_avg         = ($enrolled_count > 0) ? round($total_term_sum / $enrolled_count, 1) : 0.0;
$theory_pass_rate = ($enrolled_count > 0) ? round(($theory_passed / $enrolled_count) * 100.0, 1) : 0.0;
$prac_pass_rate   = ($enrolled_count > 0) ? round(($prac_passed / $enrolled_count) * 100.0, 1) : 0.0;

// GPA distribution table rows.
$gpa_rows = [];
$all_scales = \local_comp_report_ext\competency_calculator::get_grading_scale();
foreach ($all_scales as $sc) {
    $count = $gpa_counts[$sc['letter']] ?? 0;
    $pct   = ($enrolled_count > 0) ? round(($count / $enrolled_count) * 100.0, 1) : 0.0;
    $gpa_rows[] = [
        'letter'     => $sc['letter'],
        'gpa'        => number_format($sc['gpa'], 2),
        'range'      => $sc['min'] . '% - ' . $sc['max'] . '%',
        'label'      => $sc['label'],
        'count'      => $count,
        'percentage' => number_format($pct, 1),
        'pct'        => number_format($pct, 1),
        'badge'      => $sc['badge'],
    ];
}

// 7. Prepare render data object.
$renderdata = new stdClass();
$renderdata->courseid            = $courseid;
$renderdata->groupid             = $groupid;
$renderdata->groups              = $groupoptions;
$renderdata->course_options      = $courseoptions;
$renderdata->is_all_courses      = $isallcourses;
$renderdata->quizid              = $quizid;
$renderdata->quizoptions         = $quizoptions;
$renderdata->quiz_name           = $isallcourses ? get_string('all_courses_specializations', 'local_comp_report_ext') : ($primaryquiz ? format_string($primaryquiz->name) : 'Default Assessment');
$renderdata->has_data            = !empty($student_rows);
$renderdata->has_custom_upload   = !empty($uploadedcustom);
$renderdata->custom_upload_count = count($uploadedcustom);
$renderdata->sesskey             = sesskey();

$renderdata->enrolled_count      = $enrolled_count;
$renderdata->passed_count        = $passed_count;
$renderdata->failed_count        = $failed_count;
$renderdata->pass_rate           = number_format($pass_rate, 1);
$renderdata->term_avg            = number_format($term_avg, 1);
$renderdata->theory_pass_rate    = number_format($theory_pass_rate, 1);
$renderdata->prac_pass_rate      = number_format($prac_pass_rate, 1);
$renderdata->retake1_count       = $retake1_count;
$renderdata->retake2_count       = $retake2_count;

$renderdata->student_list        = $student_rows;
$renderdata->gpa_rows            = $gpa_rows;

$renderdata->gpa_labels_json     = json_encode(array_column($all_scales, 'letter'));
$renderdata->gpa_counts_json     = json_encode(array_values($gpa_counts));
$renderdata->student_list_json   = json_encode($student_rows);

echo $OUTPUT->header();

$page = new \local_comp_report_ext\output\term_comprehensive_report_page($renderdata);
echo $OUTPUT->render($page);

echo $OUTPUT->footer();
