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
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/csvlib.class.php');

$courseid = required_param('courseid', PARAM_INT);
$groupid  = optional_param('groupid', 0, PARAM_INT);
$quizid   = optional_param('quizid', 0, PARAM_INT);
$clearcsv = optional_param('clear_csv', 0, PARAM_INT);

require_login($courseid);
$context = context_course::instance($courseid);

$canviewext = has_capability('local/comp_report_ext:viewreports', $context);
$canviewold = has_capability('local/competency_report:viewreports', $context);
if (!$canviewext && !$canviewold) {
    require_capability('local/comp_report_ext:viewreports', $context);
}

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

$PAGE->set_url('/local/comp_report_ext/term_comprehensive_report.php', [
    'courseid' => $courseid,
    'groupid'  => $groupid,
    'quizid'   => $quizid,
]);
$PAGE->set_title(get_string('term_comprehensive_report', 'local_comp_report_ext'));
$PAGE->set_heading(format_string($course->fullname) . ' — ' . get_string('term_comprehensive_report', 'local_comp_report_ext'));
$PAGE->set_pagelayout('course');
$PAGE->set_context($context);

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
                // Support comma, semicolon, or tab separation.
                $delimiter = (strpos($line, "\t") !== false) ? "\t" : ((strpos($line, ';') !== false) ? ';' : ',');
                $cols = str_getcsv($line, $delimiter);

                if ($header === null) {
                    // Detect header row.
                    $lowercols = array_map('strtolower', array_map('trim', $cols));
                    foreach ($lowercols as $cidx => $colname) {
                        if (preg_match('/email|بريد/u', $colname)) {
                            $hmap['email'] = $cidx;
                        } else if (preg_match('/^id$|academic|student.*id|رقم.*اكاديمي|رقم.*جامعي/u', $colname)) {
                            $hmap['id'] = $cidx;
                        } else if (preg_match('/particip|مشارك/u', $colname)) {
                            $hmap['participation'] = $cidx;
                        } else if (preg_match('/assign|homework|task|تكليف|واجب/u', $colname)) {
                            $hmap['assignments'] = $cidx;
                        } else if (preg_match('/retake.*1.*t|t1|نظري.*1/u', $colname)) {
                            $hmap['retake1_t'] = $cidx;
                        } else if (preg_match('/retake.*2.*t|t2|نظري.*2/u', $colname)) {
                            $hmap['retake2_t'] = $cidx;
                        } else if (preg_match('/retake.*1.*p|p1|عملي.*1/u', $colname)) {
                            $hmap['retake1_p'] = $cidx;
                        } else if (preg_match('/retake.*2.*p|p2|عملي.*2/u', $colname)) {
                            $hmap['retake2_p'] = $cidx;
                        }
                    }
                    if ($hmap['email'] !== -1 || $hmap['id'] !== -1) {
                        $header = $lowercols;
                        continue;
                    } else {
                        // Default column mapping if no header matches.
                        $hmap = ['id' => 0, 'email' => 1, 'participation' => 2, 'assignments' => 3, 'retake1_t' => 4, 'retake2_t' => 5, 'retake1_p' => 6, 'retake2_p' => 7];
                    }
                }

                $key = '';
                if ($hmap['email'] !== -1 && !empty($cols[$hmap['email']])) {
                    $key = strtolower(trim($cols[$hmap['email']]));
                } else if ($hmap['id'] !== -1 && !empty($cols[$hmap['id']])) {
                    $key = 'id_' . strtolower(trim($cols[$hmap['id']]));
                }

                if (!empty($key)) {
                    $part = ($hmap['participation'] !== -1 && isset($cols[$hmap['participation']]) && is_numeric(trim($cols[$hmap['participation']]))) ? (float)trim($cols[$hmap['participation']]) : 0.0;
                    $asgn = ($hmap['assignments'] !== -1 && isset($cols[$hmap['assignments']]) && is_numeric(trim($cols[$hmap['assignments']]))) ? (float)trim($cols[$hmap['assignments']]) : 0.0;
                    $r1t  = ($hmap['retake1_t'] !== -1 && isset($cols[$hmap['retake1_t']]) && is_numeric(trim($cols[$hmap['retake1_t']]))) ? (float)trim($cols[$hmap['retake1_t']]) : null;
                    $r2t  = ($hmap['retake2_t'] !== -1 && isset($cols[$hmap['retake2_t']]) && is_numeric(trim($cols[$hmap['retake2_t']]))) ? (float)trim($cols[$hmap['retake2_t']]) : null;
                    $r1p  = ($hmap['retake1_p'] !== -1 && isset($cols[$hmap['retake1_p']]) && is_numeric(trim($cols[$hmap['retake1_p']]))) ? (float)trim($cols[$hmap['retake1_p']]) : null;
                    $r2p  = ($hmap['retake2_p'] !== -1 && isset($cols[$hmap['retake2_p']]) && is_numeric(trim($cols[$hmap['retake2_p']]))) ? (float)trim($cols[$hmap['retake2_p']]) : null;

                    $parseddata[$key] = [
                        'participation' => min(20.0, max(0.0, $part)),
                        'assignments'   => min(10.0, max(0.0, $asgn)),
                        'retake1_t'     => ($r1t !== null) ? min(18.0, max(0.0, $r1t)) : null,
                        'retake2_t'     => ($r2t !== null) ? min(18.0, max(0.0, $r2t)) : null,
                        'retake1_p'     => ($r1p !== null) ? min(24.0, max(0.0, $r1p)) : null,
                        'retake2_p'     => ($r2p !== null) ? min(24.0, max(0.0, $r2p)) : null,
                    ];
                }
            }
            if (!empty($parseddata)) {
                $SESSION->comp_report_part_data[$courseid] = $parseddata;
                $uploadedcustom = $parseddata;
            }
        }
    }
}

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

// 2. Fetch available quizzes in course.
$allcoursequizzes = $DB->get_records('quiz', ['course' => $courseid], 'name ASC', 'id, name, sumgrades, grade');
$quizoptions = [];
$primaryquiz = null;

foreach ($allcoursequizzes as $cq) {
    // Determine if this is a retake quiz to prioritize regular final quizzes.
    $isretake = preg_match('/(retake|إعادة|الدور[\s]*(الثاني|الثالث)|محاولة[\s]*(2|3))/iu', $cq->name);
    $selected = ($quizid > 0 && (int)$cq->id === $quizid);

    $quizoptions[] = [
        'id'       => (int)$cq->id,
        'name'     => format_string($cq->name),
        'selected' => $selected,
        'isretake' => (bool)$isretake,
    ];

    if ($selected) {
        $primaryquiz = $cq;
    }
}

// If no quiz selected, pick the first non-retake quiz or first quiz.
if (!$primaryquiz && !empty($allcoursequizzes)) {
    foreach ($allcoursequizzes as $cq) {
        if (!preg_match('/(retake|إعادة)/iu', $cq->name)) {
            $primaryquiz = $cq;
            break;
        }
    }
    if (!$primaryquiz) {
        $primaryquiz = reset($allcoursequizzes);
    }
    $quizid = (int)$primaryquiz->id;
    foreach ($quizoptions as &$opt) {
        if ($opt['id'] === $quizid) {
            $opt['selected'] = true;
        }
    }
    unset($opt);
}

// 3. Separate Retake Quizzes detection in course.
$retake1quizzes = [];
$retake2quizzes = [];
if ($primaryquiz) {
    foreach ($allcoursequizzes as $cq) {
        if ((int)$cq->id === (int)$primaryquiz->id) {
            continue;
        }
        $cname = $cq->name;
        $isretake1 = preg_match('/(retake[\s\-]*1|1[\s]*st[\s]*retake|first[\s\-]*retake|إعادة[\s]*1|الإعادة[\s]*الأولى|الدور[\s]*الثاني|محاولة[\s]*2)/iu', $cname);
        $isretake2 = preg_match('/(retake[\s\-]*2|2[\s]*nd[\s]*retake|second[\s\-]*retake|إعادة[\s]*2|الإعادة[\s]*الثانية|الدور[\s]*الثالث|محاولة[\s]*3)/iu', $cname);

        if ($isretake1) {
            $retake1quizzes[$cq->id] = $cq;
        } else if ($isretake2) {
            $retake2quizzes[$cq->id] = $cq;
        }
    }
}

// 4. Fetch students roster.
if ($groupid > 0) {
    $students = $DB->get_records_sql("
        SELECT DISTINCT u.id, u.firstname, u.lastname, u.email, u.idnumber
          FROM {groups_members} gm
          JOIN {user} u ON u.id = gm.userid
          JOIN {role_assignments} ra ON ra.userid = u.id
          JOIN {context} ctx ON ctx.id = ra.contextid
         WHERE gm.groupid = :groupid
           AND ctx.instanceid = :courseid
           AND ctx.contextlevel = 50
           AND ra.roleid = (SELECT id FROM {role} WHERE shortname = 'student')
           AND u.deleted = 0
      ORDER BY u.firstname ASC, u.lastname ASC",
        ['groupid' => $groupid, 'courseid' => $courseid]
    );
} else {
    $students = $DB->get_records_sql("
        SELECT DISTINCT u.id, u.firstname, u.lastname, u.email, u.idnumber
          FROM {role_assignments} ra
          JOIN {role} r ON r.id = ra.roleid
          JOIN {context} ctx ON ctx.id = ra.contextid
          JOIN {user} u ON u.id = ra.userid
         WHERE ctx.instanceid = :courseid
           AND ctx.contextlevel = 50
           AND r.shortname = 'student'
           AND u.deleted = 0
      ORDER BY u.firstname ASC, u.lastname ASC",
        ['courseid' => $courseid]
    );
}

// 5. Preload practical scores for all students in this course.
$pracsql = "SELECT p.studentid, AVG(p.competency_percent) AS avg_percent, COUNT(p.id) as count_records
              FROM {local_comp_report_ext_prac} p
             WHERE p.courseid = :courseid
          GROUP BY p.studentid";
$pracrecords = $DB->get_records_sql($pracsql, ['courseid' => $courseid]);

// 6. Preload quiz attempts for primary quiz and separate retake quizzes.
$userids = !empty($students) ? array_keys($students) : [];
$primaryattempts = [];
if (!empty($userids) && $primaryquiz) {
    [$inusersql, $inuserparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uqa');
    $inuserparams['quizid'] = $primaryquiz->id;

    $allattempts = $DB->get_records_sql(
        "SELECT id, userid, attempt, sumgrades, timefinish
           FROM {quiz_attempts}
          WHERE quiz = :quizid AND userid $inusersql AND state = 'finished'
       ORDER BY userid ASC, attempt ASC",
        $inuserparams
    );
    foreach ($allattempts as $att) {
        $primaryattempts[$att->userid][$att->attempt] = (float)$att->sumgrades;
    }
}

// 7. Auto-detect Assignments & Participation from Moodle Gradebook.
$user_assignment_scores = [];
$user_participation_scores = [];

if (!empty($userids)) {
    // A. Assignments (/10) from Moodle Gradebook assign module.
    $assignitems = $DB->get_records_sql(
        "SELECT id, itemname, grademax
           FROM {grade_items}
          WHERE courseid = :courseid AND itemmodule = 'assign'",
        ['courseid' => $courseid]
    );
    if (!empty($assignitems)) {
        [$initems, $itemparams] = $DB->get_in_or_equal(array_keys($assignitems), SQL_PARAMS_NAMED, 'gitem');
        [$inuids, $uidparams]   = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'guser');
        $allassigngrades = $DB->get_records_sql(
            "SELECT id, itemid, userid, finalgrade, rawgrade
               FROM {grade_grades}
              WHERE itemid $initems AND userid $inuids",
            array_merge($itemparams, $uidparams)
        );
        $user_assign_pcts = [];
        foreach ($allassigngrades as $g) {
            $maxg = (float)$assignitems[$g->itemid]->grademax;
            if ($maxg > 0 && $g->finalgrade !== null) {
                $user_assign_pcts[$g->userid][] = ((float)$g->finalgrade / $maxg) * 100.0;
            }
        }
        foreach ($user_assign_pcts as $uid => $pcts) {
            if (!empty($pcts)) {
                $avgpct = array_sum($pcts) / count($pcts);
                $user_assignment_scores[$uid] = min(10.0, round(($avgpct / 100.0) * 10.0, 2));
            }
        }
    }

    // B. Participation (/20) from Moodle Gradebook manual/attendance items.
    $partitems = $DB->get_records_sql(
        "SELECT id, itemname, grademax
           FROM {grade_items}
          WHERE courseid = :courseid
            AND (itemtype = 'manual' OR itemmodule IN ('attendance', 'forum'))
            AND (itemname LIKE '%participat%' OR itemname LIKE '%مشارك%' OR itemname LIKE '%حضور%' OR itemname LIKE '%attend%')",
        ['courseid' => $courseid]
    );
    if (!empty($partitems)) {
        [$inpartitems, $partitemparams] = $DB->get_in_or_equal(array_keys($partitems), SQL_PARAMS_NAMED, 'pitem');
        [$inuids2, $uidparams2]         = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'puser');
        $allpartgrades = $DB->get_records_sql(
            "SELECT id, itemid, userid, finalgrade
               FROM {grade_grades}
              WHERE itemid $inpartitems AND userid $inuids2",
            array_merge($partitemparams, $uidparams2)
        );
        foreach ($allpartgrades as $g) {
            $maxg = (float)$partitems[$g->itemid]->grademax;
            if ($maxg > 0 && $g->finalgrade !== null) {
                $user_participation_scores[$g->userid] = min(20.0, round(((float)$g->finalgrade / $maxg) * 20.0, 2));
            }
        }
    }
}

// 8. Calculate comprehensive 100-point term records for each student.
$sumgradesmax = ($primaryquiz && $primaryquiz->sumgrades > 0) ? (float)$primaryquiz->sumgrades : 100.0;
$studentrows = [];
$totals_scores = [];
$counts = [
    'enrolled'      => count($students),
    'overall_pass'  => 0,
    'overall_fail'  => 0,
    'theory_pass'   => 0,
    'theory_fail'   => 0,
    'prac_pass'     => 0,
    'prac_fail'     => 0,
    'prac_none'     => 0,
    'retake1_count' => 0,
    'retake2_count' => 0,
];
$gpadist = [
    'exceptional' => 0,
    'excellent'   => 0,
    'superior'    => 0,
    'verygood'    => 0,
    'aboveavg'    => 0,
    'good'        => 0,
    'highpass'    => 0,
    'pass'        => 0,
    'fail'        => 0,
];


foreach ($students as $student) {
    $emailkey = strtolower(trim($student->email));
    $idkey    = 'id_' . strtolower(trim($student->idnumber ?: $student->id));
    $custom   = $uploadedcustom[$emailkey] ?? ($uploadedcustom[$idkey] ?? null);

    // 1. Final Theory (/30) and Theory Retakes (capped at 18).
    $att1 = $primaryattempts[$student->id][1] ?? null;
    $att2 = $primaryattempts[$student->id][2] ?? null;
    $att3 = $primaryattempts[$student->id][3] ?? null;

    $theory_final_30 = ($att1 !== null) ? round(($att1 / $sumgradesmax) * 30.0, 2) : 0.0;
    
    // Retake 1 theory: from attempt 2, or custom CSV upload (capped at 18.0 = 60% of 30).
    $retake1_t = null;
    if ($custom && $custom['retake1_t'] !== null) {
        $retake1_t = min(18.0, (float)$custom['retake1_t']);
    } else if ($att2 !== null) {
        $raw30 = ($att2 / $sumgradesmax) * 30.0;
        $retake1_t = min(18.0, round($raw30, 2));
    }

    // Retake 2 theory: from attempt 3, or custom CSV upload (capped at 18.0).
    $retake2_t = null;
    if ($custom && $custom['retake2_t'] !== null) {
        $retake2_t = min(18.0, (float)$custom['retake2_t']);
    } else if ($att3 !== null) {
        $raw30_3 = ($att3 / $sumgradesmax) * 30.0;
        $retake2_t = min(18.0, round($raw30_3, 2));
    }

    // Best Theory score (/30).
    $best_theory = max($theory_final_30, $retake1_t ?? 0.0, $retake2_t ?? 0.0);
    $theory_pass = ($best_theory >= 18.0);

    // 2. Practical (/40) and Practical Retakes (capped at 24.0 = 60% of 40).
    $prac_rec = $pracrecords[$student->id] ?? null;
    $prac_orig_40 = ($prac_rec && $prac_rec->avg_percent !== null)
        ? round(((float)$prac_rec->avg_percent / 100.0) * 40.0, 2)
        : null;

    $retake1_p = ($custom && $custom['retake1_p'] !== null) ? min(24.0, (float)$custom['retake1_p']) : null;
    $retake2_p = ($custom && $custom['retake2_p'] !== null) ? min(24.0, (float)$custom['retake2_p']) : null;

    $best_practical = ($prac_orig_40 !== null || $retake1_p !== null || $retake2_p !== null)
        ? max($prac_orig_40 ?? 0.0, $retake1_p ?? 0.0, $retake2_p ?? 0.0)
        : null;

    $practical_pass = ($best_practical !== null) ? ($best_practical >= 24.0) : null;

    // 3. Participation (/20) & Assignments (/10).
    $participation = 0.0;
    if ($custom && isset($custom['participation'])) {
        $participation = (float)$custom['participation'];
    } else if (isset($user_participation_scores[$student->id])) {
        $participation = (float)$user_participation_scores[$student->id];
    }

    $assignments = 0.0;
    if ($custom && isset($custom['assignments'])) {
        $assignments = (float)$custom['assignments'];
    } else if (isset($user_assignment_scores[$student->id])) {
        $assignments = (float)$user_assignment_scores[$student->id];
    }

    // Expected participation benchmark formula from standalone: (bestTheory + bestPractical) * 20 / 70
    $expected_part = round(($best_theory + ($best_practical ?? 0.0)) * (20.0 / 70.0), 1);

    // 4. Term Total (/100) & Overall Pass.
    $term_total   = round($best_theory + ($best_practical ?? 0.0) + $participation + $assignments, 2);
    $overall_pass = ($term_total >= 60.0);

    // 5. GPA Evaluation Scale.
    $eval = \local_comp_report_ext\competency_calculator::eval_scale($term_total);

    // Retake flags.
    $has_retake1 = ($retake1_t !== null || $retake1_p !== null);
    $has_retake2 = ($retake2_t !== null || $retake2_p !== null);

    // Aggregate cohort counters.
    if ($overall_pass) {
        $counts['overall_pass']++;
    } else {
        $counts['overall_fail']++;
    }
    if ($theory_pass) {
        $counts['theory_pass']++;
    } else {
        $counts['theory_fail']++;
    }
    if ($practical_pass === true) {
        $counts['prac_pass']++;
    } else if ($practical_pass === false) {
        $counts['prac_fail']++;
    } else {
        $counts['prac_none']++;
    }
    if ($has_retake1) {
        $counts['retake1_count']++;
    }
    if ($has_retake2) {
        $counts['retake2_count']++;
    }
    $gpadist[$eval['key']]++;
    $totals_scores[] = $term_total;

    $studentrows[] = [
        'index'                 => count($studentrows) + 1,
        'id'                    => (int)$student->id,
        'fullname'              => fullname($student),
        'idnumber'              => $student->idnumber ?: '—',
        'email'                 => $student->email,
        'theory_final_30'       => ($att1 !== null) ? number_format($theory_final_30, 1) : '—',
        'retake1_t'             => ($retake1_t !== null) ? number_format($retake1_t, 1) : '—',
        'retake2_t'             => ($retake2_t !== null) ? number_format($retake2_t, 1) : '—',
        'best_theory'           => number_format($best_theory, 1),
        'theory_pass'           => $theory_pass,
        'prac_orig_40'          => ($prac_orig_40 !== null) ? number_format($prac_orig_40, 1) : '—',
        'retake1_p'             => ($retake1_p !== null) ? number_format($retake1_p, 1) : '—',
        'retake2_p'             => ($retake2_p !== null) ? number_format($retake2_p, 1) : '—',
        'best_practical'        => ($best_practical !== null) ? number_format($best_practical, 1) : '—',
        'practical_pass'        => $practical_pass,
        'has_practical'         => ($best_practical !== null),
        'participation'         => number_format($participation, 1),
        'expected_participation'=> number_format($expected_part, 1),
        'assignments'           => number_format($assignments, 1),
        'term_total'            => number_format($term_total, 1),
        'overall_pass'          => $overall_pass,
        'gpa_letter'            => $eval['letter'],
        'gpa_value'             => number_format($eval['gpa'], 2),
        'gpa_label'             => $eval['label'],
        'gpa_color'             => $eval['color'],
        'gpa_badge'             => $eval['badge'],
        'has_retake1'           => $has_retake1,
        'has_retake2'           => $has_retake2,
        'detail_url'            => (new moodle_url('/local/comp_report_ext/student_competency_detail.php', [
            'courseid' => $courseid,
            'userid'   => $student->id,
        ]))->out(false),
    ];
}

// 8. Overall KPI stats and averages.
$hasdata = !empty($studentrows);
$termaverage = $hasdata ? round(array_sum($totals_scores) / count($totals_scores), 1) : 0.0;
$passrate    = ($counts['enrolled'] > 0) ? round(($counts['overall_pass'] / $counts['enrolled']) * 100.0, 1) : 0.0;
$theorypassrate = ($counts['enrolled'] > 0) ? round(($counts['theory_pass'] / $counts['enrolled']) * 100.0, 1) : 0.0;
$practested = $counts['prac_pass'] + $counts['prac_fail'];
$pracpassrate = ($practested > 0) ? round(($counts['prac_pass'] / $practested) * 100.0, 1) : 0.0;

// GPA distribution table rows.
$gpaorder = [
    ['key' => 'exceptional', 'letter' => 'A+', 'gpa' => '5.00', 'label' => get_string('eval_exceptional', 'local_comp_report_ext'), 'badge' => 'badge-success'],
    ['key' => 'excellent',   'letter' => 'A',  'gpa' => '4.75', 'label' => get_string('eval_excellent', 'local_comp_report_ext'),   'badge' => 'badge-success'],
    ['key' => 'superior',    'letter' => 'B+', 'gpa' => '4.50', 'label' => get_string('eval_superior', 'local_comp_report_ext'),    'badge' => 'badge-info'],
    ['key' => 'verygood',    'letter' => 'B',  'gpa' => '4.00', 'label' => get_string('eval_verygood', 'local_comp_report_ext'),    'badge' => 'badge-info'],
    ['key' => 'aboveavg',    'letter' => 'C+', 'gpa' => '3.50', 'label' => get_string('eval_aboveavg', 'local_comp_report_ext'),    'badge' => 'badge-warning'],
    ['key' => 'good',        'letter' => 'C',  'gpa' => '3.00', 'label' => get_string('eval_good', 'local_comp_report_ext'),        'badge' => 'badge-warning'],
    ['key' => 'highpass',    'letter' => 'D+', 'gpa' => '2.50', 'label' => get_string('eval_highpass', 'local_comp_report_ext'),    'badge' => 'badge-primary'],
    ['key' => 'pass',        'letter' => 'D',  'gpa' => '2.00', 'label' => get_string('eval_pass', 'local_comp_report_ext'),        'badge' => 'badge-secondary'],
    ['key' => 'fail',        'letter' => 'F',  'gpa' => '1.00', 'label' => get_string('eval_fail', 'local_comp_report_ext'),        'badge' => 'badge-danger'],
];
$gparows = [];
foreach ($gpaorder as $item) {
    $count = $gpadist[$item['key']];
    $pct = ($counts['enrolled'] > 0) ? round(($count / $counts['enrolled']) * 100.0, 1) : 0.0;
    $gparows[] = [
        'letter' => $item['letter'],
        'gpa'    => $item['gpa'],
        'label'  => $item['label'],
        'badge'  => $item['badge'],
        'count'  => $count,
        'pct'    => $pct,
    ];
}

// 9. Prepare render data.
$renderdata = new stdClass();
$renderdata->courseid          = $courseid;
$renderdata->groupid           = $groupid;
$renderdata->groups            = $groupoptions;
$renderdata->quizid            = $quizid;
$renderdata->quizoptions       = $quizoptions;
$renderdata->quiz_name         = $primaryquiz ? format_string($primaryquiz->name) : '—';
$renderdata->has_data          = $hasdata;
$renderdata->has_custom_upload = !empty($uploadedcustom);
$renderdata->custom_upload_count = count($uploadedcustom);
$renderdata->sesskey           = sesskey();

$renderdata->enrolled_count    = $counts['enrolled'];
$renderdata->passed_count      = $counts['overall_pass'];
$renderdata->failed_count      = $counts['overall_fail'];
$renderdata->pass_rate         = number_format($passrate, 1);
$renderdata->term_avg          = number_format($termaverage, 1);
$renderdata->theory_pass_rate  = number_format($theorypassrate, 1);
$renderdata->prac_pass_rate    = number_format($pracpassrate, 1);
$renderdata->retake1_count     = $counts['retake1_count'];
$renderdata->retake2_count     = $counts['retake2_count'];

$renderdata->student_list      = $studentrows;
$renderdata->gpa_rows          = $gparows;

// Chart data JSON.
$renderdata->gpa_labels_json   = json_encode(array_column($gpaorder, 'letter'));
$renderdata->gpa_counts_json   = json_encode(array_values($gpadist));
$renderdata->student_list_json = json_encode($studentrows);

echo $OUTPUT->header();

$page = new \local_comp_report_ext\output\term_comprehensive_report_page($renderdata);
echo $OUTPUT->render($page);

echo $OUTPUT->footer();
