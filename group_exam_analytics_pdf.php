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
 * PDF report generator for Exam & Grade Analytics report.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/tcpdf/tcpdf.php');
require_once(__DIR__ . '/lib.php');

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

    // Retake detection.
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
        if (!empty($attempts)) {
            foreach ($attempts as $att) {
                if ($att->sumgrades !== null) {
                    $attnum = (int)$att->attempt;
                    $attscores[$attnum] = round(((float)$att->sumgrades / $sumgradesmax) * 100.0, 1);
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

            $studentlist[] = [
                'fullname'   => fullname($student),
                'group'      => !empty($usergroups[$student->id]) ? implode(', ', $usergroups[$student->id]) : '—',
                'att1'       => ($att1score !== null) ? '%' . number_format($att1score, 1) : '—',
                'att2'       => ($att2score !== null) ? '%' . number_format($att2score, 1) : '—',
                'att3'       => ($att3score !== null) ? '%' . number_format($att3score, 1) : '—',
                'finalscore' => '%' . number_format($scorepct, 1),
                'finalgrade' => number_format(($scorepct / 100.0) * $quizmaxgrade, 1) . ' / ' . number_format($quizmaxgrade, 1),
                'status'     => $statuslabel,
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
    }
    $passrate = round(($passedcount / $n) * 100, 1);
    $variance = ($n > 1) ? ($sumsq / ($n - 1)) : 0.0;
    $statsigma = round(sqrt($variance), 1);
}

// 5. Build PDF Document.
$pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator('Moodle');
$pdf->SetTitle(format_string($course->fullname) . ' — ' . $quizname);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);
$pdf->AddPage();

local_comp_report_ext_render_pdf_header_logos($pdf, true);
$pdf->SetFont('freeserif', '', 10);

// Title & Meta.
$html = '<h2 style="color:#1e293b; margin-bottom:4px;">' . s(format_string($course->fullname)) . ' — ' . s($quizname) . '</h2>';
$html .= '<p style="color:#64748b; font-size:9pt; margin-top:0;"><b>' . get_string('group', 'local_comp_report_ext') . ':</b> ' . s($groupname)
    . '  |  <b>' . get_string('creation_date', 'local_comp_report_ext') . ':</b> ' . userdate(time()) . '</p>';

// KPI Cards Grid.
$html .= '<table cellpadding="6" style="margin-bottom:12px; font-size:9pt; border-collapse:collapse;"><tr>';
$html .= '<td style="background-color:#eff6ff; border:1px solid #bfdbfe; width:16%; text-align:center;"><b>' . get_string('exam_avg_score', 'local_comp_report_ext') . '</b><br><span style="font-size:12pt; color:#1d4ed8;">%' . number_format($examavg, 1) . '</span></td>';
$html .= '<td style="background-color:#f0fdf4; border:1px solid #bbf7d0; width:16%; text-align:center;"><b>' . get_string('exam_pass_rate_label', 'local_comp_report_ext') . '</b><br><span style="font-size:12pt; color:#15803d;">%' . number_format($passrate, 1) . '</span></td>';
$html .= '<td style="background-color:#fefce8; border:1px solid #fef08a; width:16%; text-align:center;"><b>' . get_string('exam_highest_score', 'local_comp_report_ext') . '</b><br><span style="font-size:12pt; color:#a16207;">%' . number_format($highestscore, 1) . '</span></td>';
$html .= '<td style="background-color:#fff1f2; border:1px solid #fecdd3; width:16%; text-align:center;"><b>' . get_string('exam_lowest_score', 'local_comp_report_ext') . '</b><br><span style="font-size:12pt; color:#be123c;">%' . number_format($lowestscore, 1) . '</span></td>';
$html .= '<td style="background-color:#f8fafc; border:1px solid #e2e8f0; width:18%; text-align:center;"><b>' . get_string('stats_mean', 'local_comp_report_ext') . '</b><br><span style="font-size:12pt; color:#334155;">%' . number_format($examavg, 1) . '</span></td>';
$html .= '<td style="background-color:#f8fafc; border:1px solid #e2e8f0; width:18%; text-align:center;"><b>' . get_string('stats_sigma', 'local_comp_report_ext') . '</b><br><span style="font-size:12pt; color:#334155;">' . number_format($statsigma, 1) . '</span></td>';
$html .= '</tr></table><br>';

// Student Scores Roster.
$html .= '<table border="1" cellpadding="5" style="border-collapse:collapse; font-size:8.5pt;">';
$html .= '<thead><tr bgcolor="#1e293b" style="color:#ffffff; font-weight:bold;">';
$html .= '<th width="5%" align="center">#</th>';
$html .= '<th width="24%" align="left">' . get_string('student', 'local_comp_report_ext') . '</th>';
$html .= '<th width="16%" align="left">' . get_string('group', 'local_comp_report_ext') . '</th>';
$html .= '<th width="10%" align="center">Attempt 1</th>';
$html .= '<th width="10%" align="center">Retake 1</th>';
$html .= '<th width="10%" align="center">Retake 2</th>';
$html .= '<th width="12%" align="center">Final Grade</th>';
$html .= '<th width="13%" align="center">Status</th>';
$html .= '</tr></thead><tbody>';

$i = 1;
foreach ($studentlist as $st) {
    $bg = ($i % 2 === 0) ? '#f8fafc' : '#ffffff';
    $html .= '<tr bgcolor="' . $bg . '">';
    $html .= '<td width="5%" align="center">' . $i++ . '</td>';
    $html .= '<td width="24%"><b>' . s($st['fullname']) . '</b></td>';
    $html .= '<td width="16%">' . s($st['group']) . '</td>';
    $html .= '<td width="10%" align="center">' . $st['att1'] . '</td>';
    $html .= '<td width="10%" align="center">' . $st['att2'] . '</td>';
    $html .= '<td width="10%" align="center">' . $st['att3'] . '</td>';
    $html .= '<td width="12%" align="center" style="font-weight:bold; color:#1d4ed8;">' . $st['finalscore'] . '<br><small style="font-size:7pt; color:#64748b;">(' . $st['finalgrade'] . ')</small></td>';
    $html .= '<td width="13%" align="center">' . s($st['status']) . '</td>';
    $html .= '</tr>';
}
$html .= '</tbody></table>';

$html = preg_replace('/[^\x{0000}-\x{FFFF}]/u', '', $html);
$pdf->writeHTML($html, true, false, true, false, '');

$pdf->Output('Exam_Analytics_' . clean_filename($quizname) . '_' . date('Ymd_His') . '.pdf', 'I');
