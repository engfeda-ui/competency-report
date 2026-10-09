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
 * Excel report generator for Exam & Grade Analytics report.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/excellib.class.php');

$courseid = required_param('courseid', PARAM_INT);
$groupid  = optional_param('groupid', 0, PARAM_INT);
$quizid   = optional_param('quizid', 0, PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);

$context = context_course::instance($courseid);
$canviewext = has_capability('local/comp_report_ext:viewreports', $context);
$canviewold = has_capability('local/competency_report:viewreports', $context);
if (!$canviewext && !$canviewold) {
    require_capability('local/comp_report_ext:viewreports', $context);
}

global $DB;

if ($groupid > 0) {
    $grouprow  = $DB->get_record('groups', ['id' => $groupid, 'courseid' => $courseid]);
    $groupname = $grouprow ? format_string($grouprow->name) : get_string('allgroups', 'local_comp_report_ext');
} else {
    $groupname = get_string('allgroups', 'local_comp_report_ext');
}

// 1. Determine Target Quiz.
$allquizzes = $DB->get_records('quiz', ['course' => $courseid], 'name ASC', 'id, name, grade, sumgrades');
if ($quizid <= 0 || !isset($allquizzes[$quizid])) {
    $asmts = $DB->get_records_sql(
        "SELECT id, quizid FROM {local_comp_report_ext_asmt}
          WHERE courseid = :courseid AND type = 'quiz' AND quizid IS NOT NULL AND quizid > 0
       ORDER BY weight DESC, id ASC",
        ['courseid' => $courseid]
    );
    if (!empty($asmts)) {
        $asmt = reset($asmts);
        $quizid = (int)$asmt->quizid;
    } else if (!empty($allquizzes)) {
        $q = reset($allquizzes);
        $quizid = (int)$q->id;
    }
}

$quiz = $allquizzes[$quizid] ?? null;
$quizname = $quiz ? format_string($quiz->name) : '—';

// 2. Fetch Enrolled Students.
if ($groupid > 0) {
    $students = $DB->get_records_sql(
        "SELECT DISTINCT u.id, u.firstname, u.lastname
           FROM {groups_members} gm
           JOIN {user} u ON u.id = gm.userid
           JOIN {role_assignments} ra ON ra.userid = u.id
           JOIN {context} ctx ON ctx.id = ra.contextid
          WHERE gm.groupid = :groupid
            AND ctx.instanceid = :courseid
            AND ctx.contextlevel = 50
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

// 3. Bulk-load Groups & Collect Scores.
$usergroups = [];
$studentlist = [];
$rawscores = [];

$studentids = array_keys($students);
if (!empty($studentids)) {
    [$insql, $inparams] = $DB->get_in_or_equal($studentids, SQL_PARAMS_NAMED, 'uid');
    $gmrecords = $DB->get_records_sql("
        SELECT gm.id, gm.userid, g.id AS groupid, g.name AS groupname
          FROM {groups_members} gm
          JOIN {groups} g ON g.id = gm.groupid
         WHERE g.courseid = :courseid AND gm.userid $insql
      ORDER BY g.name ASC
    ", array_merge(['courseid' => $courseid], $inparams));
    foreach ($gmrecords as $gm) {
        $usergroups[$gm->userid][] = format_string($gm->groupname);
    }
}

/**
 * Sanitize cell values against CSV/Excel Formula Injection.
 *
 * @param mixed $val
 * @return string
 */
function safe_excel_str($val): string {
    $str = (string)$val;
    if ($str !== '' && in_array($str[0], ['=', '+', '-', '@', "\t", "\r"])) {
        return "'" . $str;
    }
    return $str;
}

$threshold = (float)(get_config('local_comp_report_ext', 'success_threshold') ?: 60.0);

if ($quiz && !empty($students)) {
    $sumgradesmax = (float)($quiz->sumgrades > 0 ? $quiz->sumgrades : 100.0);
    $quizmaxgrade = (float)($quiz->grade > 0 ? $quiz->grade : $sumgradesmax);

    // Retake quizzes detection.
    $retake1quizzes = [];
    $retake2quizzes = [];
    $r1pattern = '/(retake[\s\-]*1|1[\s]*st[\s]*retake|first[\s\-]*retake|' .
        'إعادة[\s]*1|الإعادة[\s]*الأولى|الدور[\s]*الثاني|محاولة[\s]*2)/iu';
    $r2pattern = '/(retake[\s\-]*2|2[\s]*nd[\s]*retake|second[\s\-]*retake|' .
        'إعادة[\s]*2|الإعادة[\s]*الثانية|الدور[\s]*الثالث|محاولة[\s]*3)/iu';
    foreach ($allquizzes as $cq) {
        if ((int)$cq->id === (int)$quiz->id) {
            continue;
        }
        $cname = $cq->name;
        if (preg_match($r1pattern, $cname)) {
            $retake1quizzes[$cq->id] = $cq;
        } else if (preg_match($r2pattern, $cname)) {
            $retake2quizzes[$cq->id] = $cq;
        }
    }

    // Bulk-load student attempts for primary quiz and retakes (eliminates N+1 queries).
    $userattempts = [];
    $userr1attempts = [];
    $userr2attempts = [];

    if (!empty($studentids)) {
        [$uinsql, $uinparams] = $DB->get_in_or_equal($studentids, SQL_PARAMS_NAMED, 'attuid');
        $uinparams['aquizid'] = $quiz->id;
        $allattempts = $DB->get_records_sql(
            "SELECT id, userid, attempt, sumgrades FROM {quiz_attempts}
              WHERE quiz = :aquizid AND userid $uinsql AND state = 'finished'
           ORDER BY userid ASC, attempt ASC",
            $uinparams
        );
        foreach ($allattempts as $att) {
            $userattempts[$att->userid][] = $att;
        }

        if (!empty($retake1quizzes)) {
            $r1quizids = array_keys($retake1quizzes);
            [$r1qinsql, $r1qparams] = $DB->get_in_or_equal($r1quizids, SQL_PARAMS_NAMED, 'r1qid');
            [$r1uinsql, $r1uparams] = $DB->get_in_or_equal($studentids, SQL_PARAMS_NAMED, 'r1uid');
            $allr1 = $DB->get_records_sql(
                "SELECT id, userid, quiz, sumgrades FROM {quiz_attempts}
                  WHERE quiz $r1qinsql AND userid $r1uinsql AND state = 'finished'
               ORDER BY userid ASC, sumgrades DESC, timefinish DESC",
                array_merge($r1qparams, $r1uparams)
            );
            foreach ($allr1 as $att) {
                if (!isset($userr1attempts[$att->userid])) {
                    $userr1attempts[$att->userid] = $att;
                }
            }
        }

        if (!empty($retake2quizzes)) {
            $r2quizids = array_keys($retake2quizzes);
            [$r2qinsql, $r2qparams] = $DB->get_in_or_equal($r2quizids, SQL_PARAMS_NAMED, 'r2qid');
            [$r2uinsql, $r2uparams] = $DB->get_in_or_equal($studentids, SQL_PARAMS_NAMED, 'r2uid');
            $allr2 = $DB->get_records_sql(
                "SELECT id, userid, quiz, sumgrades FROM {quiz_attempts}
                  WHERE quiz $r2qinsql AND userid $r2uinsql AND state = 'finished'
               ORDER BY userid ASC, sumgrades DESC, timefinish DESC",
                array_merge($r2qparams, $r2uparams)
            );
            foreach ($allr2 as $att) {
                if (!isset($userr2attempts[$att->userid])) {
                    $userr2attempts[$att->userid] = $att;
                }
            }
        }
    }

    foreach ($students as $student) {
        $attempts = $userattempts[$student->id] ?? [];

        $attscores = [];
        $attraws   = [];
        if (!empty($attempts)) {
            foreach ($attempts as $att) {
                if ($att->sumgrades !== null) {
                    $attnum = (int)$att->attempt;
                    $attscores[$attnum] = round(((float)$att->sumgrades / $sumgradesmax) * 100.0, 1);
                    $attraws[$attnum]   = (float)$att->sumgrades;
                }
            }
        }

        $att1score = $attscores[1] ?? null;
        $att2score = $attscores[2] ?? null;
        $att3score = $attscores[3] ?? null;

        // Fallback to separate retake quizzes.
        if ($att2score === null && isset($userr1attempts[$student->id])) {
            $r1att = $userr1attempts[$student->id];
            $r1max = (float)($retake1quizzes[$r1att->quiz]->sumgrades > 0 ? $retake1quizzes[$r1att->quiz]->sumgrades : 100.0);
            if ($r1att->sumgrades !== null) {
                $att2score = round(((float)$r1att->sumgrades / $r1max) * 100.0, 1);
            }
        }

        if ($att3score === null && isset($userr2attempts[$student->id])) {
            $r2att = $userr2attempts[$student->id];
            $r2max = (float)($retake2quizzes[$r2att->quiz]->sumgrades > 0 ? $retake2quizzes[$r2att->quiz]->sumgrades : 100.0);
            if ($r2att->sumgrades !== null) {
                $att3score = round(((float)$r2att->sumgrades / $r2max) * 100.0, 1);
            }
        }

        if ($att1score !== null || $att2score !== null || $att3score !== null) {
            $validscores = array_filter([$att1score, $att2score, $att3score], fn($s) => $s !== null);
            $retakecount = ($att2score !== null ? 1 : 0) + ($att3score !== null ? 1 : 0);

            $scorepct = 0.0;
            $statuslabel = '—';

            // 60% retake policy cap.
            if ($att1score !== null && $att1score >= 60.0) {
                $scorepct = $att1score;
                $statuslabel = get_string('passed_first_attempt', 'local_comp_report_ext');
            } else if ($att2score !== null && $att2score >= 60.0) {
                $scorepct = 60.0;
                $statuslabel = get_string('passed_retake_1', 'local_comp_report_ext') . ' (Cap 60%)';
            } else if ($att3score !== null && $att3score >= 60.0) {
                $scorepct = 60.0;
                $statuslabel = get_string('passed_retake_2', 'local_comp_report_ext') . ' (Cap 60%)';
            } else {
                $scorepct = !empty($validscores) ? max($validscores) : 0.0;
                $statuslabel = get_string('failed_status', 'local_comp_report_ext');
            }

            $rawscores[] = $scorepct;

            if ($scorepct < 60) {
                $tiername = 'At-Risk (<60%)';
            } else if ($scorepct < 75) {
                $tiername = 'Satisfactory (60–74%)';
            } else if ($scorepct < 90) {
                $tiername = 'Very Good (75–89%)';
            } else {
                $tiername = 'Outstanding (90–100%)';
            }

            $studentlist[] = [
                'fullname'   => fullname($student),
                'group'      => !empty($usergroups[$student->id]) ? implode(', ', $usergroups[$student->id]) : '—',
                'att1'       => ($att1score !== null) ? '%' . number_format($att1score, 1) : '—',
                'att2'       => ($att2score !== null) ? '%' . number_format($att2score, 1) : '—',
                'att3'       => ($att3score !== null) ? '%' . number_format($att3score, 1) : '—',
                'finalscore' => '%' . number_format($scorepct, 1),
                'finalgrade' => number_format(($scorepct / 100.0) * $quizmaxgrade, 1) . ' / ' . number_format($quizmaxgrade, 1),
                'retakes'    => $retakecount,
                'status'     => $statuslabel,
                'tier'       => $tiername,
            ];
        }
    }
}

// 4. Calculate Stats & KPIs.
$hasdata = !empty($rawscores);
$examavg = 0.0;
$passrate = 0.0;
$highestscore = 0.0;
$lowestscore = 0.0;
$statsigma = 0.0;

$tiercounts = [
    'failed'      => 0,
    'passing'     => 0,
    'verygood'    => 0,
    'outstanding' => 0,
];

if ($hasdata) {
    $n = count($rawscores);
    $examavg = round(array_sum($rawscores) / $n, 1);
    $highestscore = max($rawscores);
    $lowestscore = min($rawscores);

    $passedcount = 0;
    $sumsq = 0.0;
    foreach ($rawscores as $score) {
        if ($score >= $threshold) {
            $passedcount++;
        }
        $sumsq += pow($score - $examavg, 2);
        if ($score < 60) {
            $tiercounts['failed']++;
        } else if ($score < 75) {
            $tiercounts['passing']++;
        } else if ($score < 90) {
            $tiercounts['verygood']++;
        } else {
            $tiercounts['outstanding']++;
        }
    }
    $passrate = round(($passedcount / $n) * 100, 1);
    $variance = ($n > 1) ? ($sumsq / ($n - 1)) : 0.0;
    $statsigma = round(sqrt($variance), 1);
}

// 5. Create Excel Workbook.
$cleanfilename = 'Exam_Analytics_' . clean_filename($quizname) . '_' . date('Ymd_His') . '.xlsx';
$workbook = new MoodleExcelWorkbook($cleanfilename);

// Styles.
$formattitle = $workbook->add_format(['bold' => 1, 'size' => 14, 'color' => 'navy']);
$formatmeta  = $workbook->add_format(['size' => 10, 'color' => 'gray']);
$formatheader = $workbook->add_format([
    'bold' => 1,
    'color' => 'white',
    'bg_color' => 'navy',
    'border' => 1,
    'align' => 'center',
    'valign' => 'vcenter',
]);
$formatheaderleft = $workbook->add_format([
    'bold' => 1,
    'color' => 'white',
    'bg_color' => 'navy',
    'border' => 1,
    'align' => 'left',
    'valign' => 'vcenter',
]);
$formatcell = $workbook->add_format(['border' => 1, 'align' => 'center', 'valign' => 'vcenter']);
$formatcellleft = $workbook->add_format(['border' => 1, 'align' => 'left', 'valign' => 'vcenter']);
$formatcellbold = $workbook->add_format(['bold' => 1, 'border' => 1, 'align' => 'left', 'valign' => 'vcenter']);
$formatkpilabel = $workbook->add_format(['bold' => 1, 'bg_color' => '#f1f5f9', 'border' => 1, 'align' => 'left']);
$formatkpival   = $workbook->add_format(['bold' => 1, 'border' => 1, 'align' => 'center']);

// Sheet 1: Summary & Psychometrics.
$wssummary = $workbook->add_worksheet('Overview & KPIs');
$wssummary->set_column(0, 0, 32);
$wssummary->set_column(1, 1, 24);

$r = 0;
$wssummary->write_string($r++, 0, format_string($course->fullname) . ' — ' . $quizname, $formattitle);
$metatext = get_string('group', 'local_comp_report_ext') . ': ' . $groupname . '  |  ' . userdate(time());
$wssummary->write_string($r++, 0, $metatext, $formatmeta);
$r++;

$wssummary->write_string($r, 0, 'Metric / KPI', $formatheaderleft);
$wssummary->write_string($r++, 1, 'Value', $formatheader);

$wssummary->write_string($r, 0, 'Total Enrolled Students', $formatkpilabel);
$wssummary->write_number($r++, 1, count($students), $formatkpival);

$wssummary->write_string($r, 0, 'Students Attempted Exam', $formatkpilabel);
$wssummary->write_number($r++, 1, count($studentlist), $formatkpival);

$wssummary->write_string($r, 0, get_string('exam_avg_score', 'local_comp_report_ext'), $formatkpilabel);
$wssummary->write_string($r++, 1, '%' . number_format($examavg, 1), $formatkpival);

$wssummary->write_string($r, 0, get_string('exam_pass_rate_label', 'local_comp_report_ext'), $formatkpilabel);
$wssummary->write_string($r++, 1, '%' . number_format($passrate, 1), $formatkpival);

$wssummary->write_string($r, 0, get_string('exam_highest_score', 'local_comp_report_ext'), $formatkpilabel);
$wssummary->write_string($r++, 1, '%' . number_format($highestscore, 1), $formatkpival);

$wssummary->write_string($r, 0, get_string('exam_lowest_score', 'local_comp_report_ext'), $formatkpilabel);
$wssummary->write_string($r++, 1, '%' . number_format($lowestscore, 1), $formatkpival);

$wssummary->write_string($r, 0, get_string('stats_mean', 'local_comp_report_ext'), $formatkpilabel);
$wssummary->write_string($r++, 1, '%' . number_format($examavg, 1), $formatkpival);

$wssummary->write_string($r, 0, get_string('stats_sigma', 'local_comp_report_ext'), $formatkpilabel);
$wssummary->write_string($r++, 1, number_format($statsigma, 1), $formatkpival);

$r += 2;
$wssummary->write_string($r, 0, 'Academic Performance Tier', $formatheaderleft);
$wssummary->write_string($r++, 1, 'Student Count', $formatheader);

$wssummary->write_string($r, 0, 'Outstanding (90–100%)', $formatkpilabel);
$wssummary->write_number($r++, 1, $tiercounts['outstanding'], $formatkpival);

$wssummary->write_string($r, 0, 'Very Good (75–89%)', $formatkpilabel);
$wssummary->write_number($r++, 1, $tiercounts['verygood'], $formatkpival);

$wssummary->write_string($r, 0, 'Satisfactory (60–74%)', $formatkpilabel);
$wssummary->write_number($r++, 1, $tiercounts['passing'], $formatkpival);

$wssummary->write_string($r, 0, 'At-Risk / Failed (<60%)', $formatkpilabel);
$wssummary->write_number($r++, 1, $tiercounts['failed'], $formatkpival);

// Sheet 2: Student Score Roster.
$wsroster = $workbook->add_worksheet('Student Roster');
$wsroster->set_column(0, 0, 5);
$wsroster->set_column(1, 1, 28);
$wsroster->set_column(2, 2, 20);
$wsroster->set_column(3, 3, 14);
$wsroster->set_column(4, 4, 14);
$wsroster->set_column(5, 5, 14);
$wsroster->set_column(6, 6, 16);
$wsroster->set_column(7, 7, 16);
$wsroster->set_column(8, 8, 12);
$wsroster->set_column(9, 9, 24);
$wsroster->set_column(10, 10, 22);

$r = 0;
$c = 0;
$wsroster->write_string($r, $c++, '#', $formatheader);
$wsroster->write_string($r, $c++, get_string('student', 'local_comp_report_ext'), $formatheaderleft);
$wsroster->write_string($r, $c++, get_string('group', 'local_comp_report_ext'), $formatheaderleft);
$att1lbl = get_string('attempt_number', 'local_comp_report_ext', 1) ?: 'Attempt 1';
$wsroster->write_string($r, $c++, $att1lbl, $formatheader);
$wsroster->write_string($r, $c++, 'Retake 1', $formatheader);
$wsroster->write_string($r, $c++, 'Retake 2', $formatheader);
$wsroster->write_string($r, $c++, 'Final Recorded %', $formatheader);
$wsroster->write_string($r, $c++, 'Final Grade', $formatheader);
$wsroster->write_string($r, $c++, 'Retakes', $formatheader);
$wsroster->write_string($r, $c++, 'Retake Status', $formatheader);
$wsroster->write_string($r++, $c++, 'Performance Tier', $formatheader);

$idx = 1;
foreach ($studentlist as $s) {
    $c = 0;
    $wsroster->write_number($r, $c++, $idx++, $formatcell);
    $wsroster->write_string($r, $c++, safe_excel_str($s['fullname']), $formatcellbold);
    $wsroster->write_string($r, $c++, safe_excel_str($s['group']), $formatcellleft);
    $wsroster->write_string($r, $c++, safe_excel_str($s['att1']), $formatcell);
    $wsroster->write_string($r, $c++, safe_excel_str($s['att2']), $formatcell);
    $wsroster->write_string($r, $c++, safe_excel_str($s['att3']), $formatcell);
    $wsroster->write_string($r, $c++, safe_excel_str($s['finalscore']), $formatcellbold);
    $wsroster->write_string($r, $c++, safe_excel_str($s['finalgrade']), $formatcell);
    $wsroster->write_number($r, $c++, $s['retakes'], $formatcell);
    $wsroster->write_string($r, $c++, safe_excel_str($s['status']), $formatcell);
    $wsroster->write_string($r++, $c++, safe_excel_str($s['tier']), $formatcell);
}

$workbook->close();
