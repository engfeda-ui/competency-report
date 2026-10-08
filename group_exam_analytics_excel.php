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

$threshold = (int)(get_config('local_comp_report_ext', 'success_threshold') ?: 60);

if ($quiz && !empty($students)) {
    $sumgradesmax = (float)($quiz->sumgrades > 0 ? $quiz->sumgrades : 100.0);
    $quizmaxgrade = (float)($quiz->grade > 0 ? $quiz->grade : $sumgradesmax);

    // Retake quizzes detection.
    $retake1quizzes = [];
    $retake2quizzes = [];
    foreach ($allquizzes as $cq) {
        if ((int)$cq->id === (int)$quiz->id) {
            continue;
        }
        $cname = $cq->name;
        if (preg_match('/(retake[\s\-]*1|1[\s]*st[\s]*retake|first[\s\-]*retake|إعادة[\s]*1|الإعادة[\s]*الأولى|الدور[\s]*الثاني|محاولة[\s]*2)/iu', $cname)) {
            $retake1quizzes[$cq->id] = $cq;
        } else if (preg_match('/(retake[\s\-]*2|2[\s]*nd[\s]*retake|second[\s\-]*retake|إعادة[\s]*2|الإعادة[\s]*الثانية|الدور[\s]*الثالث|محاولة[\s]*3)/iu', $cname)) {
            $retake2quizzes[$cq->id] = $cq;
        }
    }

    foreach ($students as $student) {
        $attempts = $DB->get_records_sql(
            "SELECT id, attempt, sumgrades FROM {quiz_attempts}
              WHERE quiz = :quizid AND userid = :userid AND state = 'finished'
           ORDER BY attempt ASC",
            ['quizid' => $quiz->id, 'userid' => $student->id]
        );

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
        if ($att2score === null && !empty($retake1quizzes)) {
            $retake1quizids = array_keys($retake1quizzes);
            [$in1sql, $in1params] = $DB->get_in_or_equal($retake1quizids, SQL_PARAMS_NAMED, 'rq1');
            $in1params['userid'] = $student->id;
            $r1attempts = $DB->get_records_sql(
                "SELECT id, quiz, sumgrades FROM {quiz_attempts}
                  WHERE quiz $in1sql AND userid = :userid AND state = 'finished'
               ORDER BY sumgrades DESC",
                $in1params, 0, 1
            );
            if (!empty($r1attempts)) {
                $r1att = reset($r1attempts);
                $r1max = (float)($retake1quizzes[$r1att->quiz]->sumgrades > 0 ? $retake1quizzes[$r1att->quiz]->sumgrades : 100.0);
                if ($r1att->sumgrades !== null) {
                    $att2score = round(((float)$r1att->sumgrades / $r1max) * 100.0, 1);
                }
            }
        }

        if ($att3score === null && !empty($retake2quizzes)) {
            $retake2quizids = array_keys($retake2quizzes);
            [$in2sql, $in2params] = $DB->get_in_or_equal($retake2quizids, SQL_PARAMS_NAMED, 'rq2');
            $in2params['userid'] = $student->id;
            $r2attempts = $DB->get_records_sql(
                "SELECT id, quiz, sumgrades FROM {quiz_attempts}
                  WHERE quiz $in2sql AND userid = :userid AND state = 'finished'
               ORDER BY sumgrades DESC",
                $in2params, 0, 1
            );
            if (!empty($r2attempts)) {
                $r2att = reset($r2attempts);
                $r2max = (float)($retake2quizzes[$r2att->quiz]->sumgrades > 0 ? $retake2quizzes[$r2att->quiz]->sumgrades : 100.0);
                if ($r2att->sumgrades !== null) {
                    $att3score = round(((float)$r2att->sumgrades / $r2max) * 100.0, 1);
                }
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
$format_title = $workbook->add_format(['bold' => 1, 'size' => 14, 'color' => 'navy']);
$format_meta  = $workbook->add_format(['size' => 10, 'color' => 'gray']);
$format_header = $workbook->add_format(['bold' => 1, 'color' => 'white', 'bg_color' => 'navy', 'border' => 1, 'align' => 'center', 'valign' => 'vcenter']);
$format_header_left = $workbook->add_format(['bold' => 1, 'color' => 'white', 'bg_color' => 'navy', 'border' => 1, 'align' => 'left', 'valign' => 'vcenter']);
$format_cell = $workbook->add_format(['border' => 1, 'align' => 'center', 'valign' => 'vcenter']);
$format_cell_left = $workbook->add_format(['border' => 1, 'align' => 'left', 'valign' => 'vcenter']);
$format_cell_bold = $workbook->add_format(['bold' => 1, 'border' => 1, 'align' => 'left', 'valign' => 'vcenter']);
$format_kpi_label = $workbook->add_format(['bold' => 1, 'bg_color' => '#f1f5f9', 'border' => 1, 'align' => 'left']);
$format_kpi_val   = $workbook->add_format(['bold' => 1, 'border' => 1, 'align' => 'center']);

// --- Sheet 1: Summary & Psychometrics ---
$ws_summary = $workbook->add_worksheet('Overview & KPIs');
$ws_summary->set_column(0, 0, 32);
$ws_summary->set_column(1, 1, 24);

$r = 0;
$ws_summary->write_string($r++, 0, format_string($course->fullname) . ' — ' . $quizname, $format_title);
$ws_summary->write_string($r++, 0, get_string('group', 'local_comp_report_ext') . ': ' . $groupname . '  |  ' . userdate(time()), $format_meta);
$r++;

$ws_summary->write_string($r, 0, 'Metric / KPI', $format_header_left);
$ws_summary->write_string($r++, 1, 'Value', $format_header);

$ws_summary->write_string($r, 0, 'Total Enrolled Students', $format_kpi_label);
$ws_summary->write_number($r++, 1, count($students), $format_kpi_val);

$ws_summary->write_string($r, 0, 'Students Attempted Exam', $format_kpi_label);
$ws_summary->write_number($r++, 1, count($studentlist), $format_kpi_val);

$ws_summary->write_string($r, 0, get_string('exam_avg_score', 'local_comp_report_ext'), $format_kpi_label);
$ws_summary->write_string($r++, 1, '%' . number_format($examavg, 1), $format_kpi_val);

$ws_summary->write_string($r, 0, get_string('exam_pass_rate_label', 'local_comp_report_ext'), $format_kpi_label);
$ws_summary->write_string($r++, 1, '%' . number_format($passrate, 1), $format_kpi_val);

$ws_summary->write_string($r, 0, get_string('exam_highest_score', 'local_comp_report_ext'), $format_kpi_label);
$ws_summary->write_string($r++, 1, '%' . number_format($highestscore, 1), $format_kpi_val);

$ws_summary->write_string($r, 0, get_string('exam_lowest_score', 'local_comp_report_ext'), $format_kpi_label);
$ws_summary->write_string($r++, 1, '%' . number_format($lowestscore, 1), $format_kpi_val);

$ws_summary->write_string($r, 0, get_string('stats_mean', 'local_comp_report_ext'), $format_kpi_label);
$ws_summary->write_string($r++, 1, '%' . number_format($examavg, 1), $format_kpi_val);

$ws_summary->write_string($r, 0, get_string('stats_sigma', 'local_comp_report_ext'), $format_kpi_label);
$ws_summary->write_string($r++, 1, number_format($statsigma, 1), $format_kpi_val);

$r += 2;
$ws_summary->write_string($r, 0, 'Academic Performance Tier', $format_header_left);
$ws_summary->write_string($r++, 1, 'Student Count', $format_header);

$ws_summary->write_string($r, 0, 'Outstanding (90–100%)', $format_kpi_label);
$ws_summary->write_number($r++, 1, $tiercounts['outstanding'], $format_kpi_val);

$ws_summary->write_string($r, 0, 'Very Good (75–89%)', $format_kpi_label);
$ws_summary->write_number($r++, 1, $tiercounts['verygood'], $format_kpi_val);

$ws_summary->write_string($r, 0, 'Satisfactory (60–74%)', $format_kpi_label);
$ws_summary->write_number($r++, 1, $tiercounts['passing'], $format_kpi_val);

$ws_summary->write_string($r, 0, 'At-Risk / Failed (<60%)', $format_kpi_label);
$ws_summary->write_number($r++, 1, $tiercounts['failed'], $format_kpi_val);

// --- Sheet 2: Student Score Roster ---
$ws_roster = $workbook->add_worksheet('Student Roster');
$ws_roster->set_column(0, 0, 5);
$ws_roster->set_column(1, 1, 28);
$ws_roster->set_column(2, 2, 20);
$ws_roster->set_column(3, 3, 14);
$ws_roster->set_column(4, 4, 14);
$ws_roster->set_column(5, 5, 14);
$ws_roster->set_column(6, 6, 16);
$ws_roster->set_column(7, 7, 16);
$ws_roster->set_column(8, 8, 12);
$ws_roster->set_column(9, 9, 24);
$ws_roster->set_column(10, 10, 22);

$r = 0;
$c = 0;
$ws_roster->write_string($r, $c++, '#', $format_header);
$ws_roster->write_string($r, $c++, get_string('student', 'local_comp_report_ext'), $format_header_left);
$ws_roster->write_string($r, $c++, get_string('group', 'local_comp_report_ext'), $format_header_left);
$ws_roster->write_string($r, $c++, get_string('attempt_number', 'local_comp_report_ext', 1) ?: 'Attempt 1', $format_header);
$ws_roster->write_string($r, $c++, 'Retake 1', $format_header);
$ws_roster->write_string($r, $c++, 'Retake 2', $format_header);
$ws_roster->write_string($r, $c++, 'Final Recorded %', $format_header);
$ws_roster->write_string($r, $c++, 'Final Grade', $format_header);
$ws_roster->write_string($r, $c++, 'Retakes', $format_header);
$ws_roster->write_string($r, $c++, 'Retake Status', $format_header);
$ws_roster->write_string($r++, $c++, 'Performance Tier', $format_header);

$idx = 1;
foreach ($studentlist as $s) {
    $c = 0;
    $ws_roster->write_number($r, $c++, $idx++, $format_cell);
    $ws_roster->write_string($r, $c++, $s['fullname'], $format_cell_bold);
    $ws_roster->write_string($r, $c++, $s['group'], $format_cell_left);
    $ws_roster->write_string($r, $c++, $s['att1'], $format_cell);
    $ws_roster->write_string($r, $c++, $s['att2'], $format_cell);
    $ws_roster->write_string($r, $c++, $s['att3'], $format_cell);
    $ws_roster->write_string($r, $c++, $s['finalscore'], $format_cell_bold);
    $ws_roster->write_string($r, $c++, $s['finalgrade'], $format_cell);
    $ws_roster->write_number($r, $c++, $s['retakes'], $format_cell);
    $ws_roster->write_string($r, $c++, $s['status'], $format_cell);
    $ws_roster->write_string($r++, $c++, $s['tier'], $format_cell);
}

$workbook->close();
