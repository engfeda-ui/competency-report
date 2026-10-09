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
 * Excel export for Course Master Report.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/excellib.class.php');

$courseid = required_param('courseid', PARAM_INT);

require_login($courseid);
$context = context_course::instance($courseid);
$canviewext = has_capability('local/comp_report_ext:viewreports', $context);
$canviewold = has_capability('local/competency_report:viewreports', $context);
if (!$canviewext && !$canviewold) {
    require_capability('local/comp_report_ext:viewreports', $context);
}

global $DB;

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$reporttitle = get_string('coursemasterreport', 'local_comp_report_ext');

// 1. Overall Statistics.
$studentscount = $DB->count_records_sql("
    SELECT COUNT(DISTINCT u.id)
    FROM {user} u
    JOIN {role_assignments} ra ON ra.userid = u.id
    JOIN {context} ctx ON ctx.id = ra.contextid
    WHERE ctx.instanceid = :courseid
      AND ra.roleid = (SELECT id FROM {role} WHERE shortname = 'student')
", ['courseid' => $courseid]);

$groupscount = $DB->count_records('groups', ['courseid' => $courseid]);

$compscount = $DB->count_records_sql("
    SELECT COUNT(DISTINCT competencyid)
    FROM {qbank_comp_ext_qmap}
    WHERE courseid = :courseid
", ['courseid' => $courseid]);

$quizzescount = $DB->count_records('quiz', ['course' => $courseid]);

// 2. Exams & General Grades.
$rawquizzes = $DB->get_records_sql("
    SELECT q.id, q.name, AVG(qa.sumgrades) as avggrade, q.sumgrades as maxgrade, COUNT(DISTINCT qa.userid) as attempts,
           (SELECT COUNT(slot.id) FROM {quiz_slots} slot WHERE slot.quizid = q.id) as numquestions
    FROM {quiz} q
    LEFT JOIN {quiz_attempts} qa ON qa.quiz = q.id AND qa.state = 'finished'
    WHERE q.course = :courseid
    GROUP BY q.id, q.name, q.sumgrades
    ORDER BY q.name ASC
", ['courseid' => $courseid]);

// 3. Course-wide Competency Rates.
$rawcomps = $DB->get_records_sql("
    SELECT c.id, c.shortname,
           SUM(qa.maxfraction) AS attempts,
           SUM(qas.fraction) AS correct
    FROM {quiz_attempts} quiza
    JOIN {quiz} quiz ON quiz.id = quiza.quiz AND quiz.course = :courseid1
    JOIN {question_usages} qu ON qu.id = quiza.uniqueid
    JOIN {question_attempts} qa ON qa.questionusageid = qu.id
    JOIN {qbank_comp_ext_qmap} m ON m.questionid = qa.questionid AND m.courseid = :courseid2
    JOIN {competency} c ON c.id = m.competencyid
    JOIN (
        SELECT s.questionattemptid, MAX(s.fraction) AS fraction
        FROM {question_attempt_steps} s
        JOIN {question_attempts} qa2 ON qa2.id = s.questionattemptid
        JOIN {question_usages} qu2 ON qu2.id = qa2.questionusageid
        JOIN {quiz_attempts} qa3 ON qa3.uniqueid = qu2.id
        JOIN {quiz} q2 ON q2.id = qa3.quiz
        WHERE q2.course = :courseid3 AND qa3.state = 'finished'
        GROUP BY s.questionattemptid
    ) qas ON qas.questionattemptid = qa.id
    WHERE quiza.state = 'finished'
    GROUP BY c.id, c.shortname
    ORDER BY c.shortname ASC
", ['courseid1' => $courseid, 'courseid2' => $courseid, 'courseid3' => $courseid]);

// 4. Group Comparison Matrix.
$compslist = $DB->get_records_sql("
    SELECT DISTINCT c.id, c.shortname
    FROM {qbank_comp_ext_qmap} m
    JOIN {competency} c ON c.id = m.competencyid
    WHERE m.courseid = :courseid
    ORDER BY c.shortname ASC
", ['courseid' => $courseid]);

$groups = $DB->get_records('groups', ['courseid' => $courseid], 'name ASC');

$groupcompraw = $DB->get_records_sql("
    SELECT
        CONCAT(gm.groupid, '_', m.competencyid) as unique_key,
        gm.groupid,
        m.competencyid,
        SUM(qa.maxfraction) AS total_max,
        SUM(qas.fraction) AS total_fraction
    FROM {quiz_attempts} quiza
    JOIN {quiz} q ON q.id = quiza.quiz AND q.course = :courseid1
    JOIN {question_usages} qu ON qu.id = quiza.uniqueid
    JOIN {question_attempts} qa ON qa.questionusageid = qu.id
    JOIN {qbank_comp_ext_qmap} m ON m.questionid = qa.questionid AND m.courseid = :courseid2
    JOIN {groups_members} gm ON gm.userid = quiza.userid
    JOIN {groups} g ON g.id = gm.groupid AND g.courseid = :courseid3
    JOIN (
        SELECT s.questionattemptid, MAX(s.fraction) AS fraction
        FROM {question_attempt_steps} s
        JOIN {question_attempts} qa2 ON qa2.id = s.questionattemptid
        JOIN {question_usages} qu2 ON qu2.id = qa2.questionusageid
        JOIN {quiz_attempts} qa3 ON qa3.uniqueid = qu2.id
        JOIN {quiz} q2 ON q2.id = qa3.quiz
        WHERE q2.course = :courseid4 AND qa3.state = 'finished'
        GROUP BY s.questionattemptid
    ) qas ON qas.questionattemptid = qa.id
    WHERE quiza.state = 'finished'
    GROUP BY gm.groupid, m.competencyid
", [
    'courseid1' => $courseid,
    'courseid2' => $courseid,
    'courseid3' => $courseid,
    'courseid4' => $courseid,
]);

$groupmap = [];
foreach ($groupcompraw as $gr) {
    $groupmap[$gr->groupid][$gr->competencyid] = [
        'att' => (float)$gr->total_max,
        'cor' => (float)$gr->total_fraction,
    ];
}

/**
 * Sanitize a string for Excel export to prevent formula injection.
 *
 * @param mixed $str
 * @return string
 */
function safe_str($str): string {
    $clean = clean_param(strip_tags((string)$str), PARAM_TEXT);
    if ($clean !== '' && in_array($clean[0], ['=', '+', '-', '@'], true)) {
        return "'" . $clean;
    }
    return $clean;
}

$filename = clean_filename('Course_Master_' . $course->shortname . '_' . date('Ymd_His') . '.xlsx');
$workbook = new MoodleExcelWorkbook($filename);

$formattitle = $workbook->add_format(['bold' => 1, 'size' => 14, 'align' => 'left']);
$formatmeta  = $workbook->add_format(['italic' => 1, 'size' => 10, 'color' => 'gray']);
$formatheader = $workbook->add_format([
    'bold' => 1,
    'bg_color' => 'navy',
    'color' => 'white',
    'border' => 1,
    'align' => 'center',
    'valign' => 'vcenter',
]);
$formatheaderleft = $workbook->add_format([
    'bold' => 1,
    'bg_color' => 'navy',
    'color' => 'white',
    'border' => 1,
    'align' => 'left',
    'valign' => 'vcenter',
]);
$formatcell = $workbook->add_format(['border' => 1, 'align' => 'center', 'valign' => 'vcenter']);
$formatcellleft = $workbook->add_format(['border' => 1, 'align' => 'left', 'valign' => 'vcenter']);
$formatcellbold = $workbook->add_format(['bold' => 1, 'border' => 1, 'align' => 'left', 'valign' => 'vcenter']);
$formatstatlbl = $workbook->add_format(['bold' => 1, 'bg_color' => 'cyan', 'border' => 1, 'align' => 'center']);
$formatstatval = $workbook->add_format(['bold' => 1, 'size' => 12, 'border' => 1, 'align' => 'center']);

// -------------------------------------------------------------
// Sheet 1: Quizzes & Overview.
$ws1 = $workbook->add_worksheet('Overview & Quizzes');
$titlestr = safe_str($course->fullname) . ' — ' . $reporttitle;
$ws1->write_string(0, 0, $titlestr, $formattitle);

$metastr = get_string('course', 'moodle') . ': '
    . safe_str($course->shortname) . ' | ' . userdate(time());
$ws1->write_string(1, 0, $metastr, $formatmeta);

// KPI Stats.
$ws1->write_string(3, 0, get_string('allusers', 'local_comp_report_ext'), $formatstatlbl);
$ws1->write_string(3, 1, get_string('selectgroup', 'local_comp_report_ext'), $formatstatlbl);
$ws1->write_string(3, 2, get_string('allcompetencies', 'local_comp_report_ext'), $formatstatlbl);
$ws1->write_string(3, 3, get_string('searchquiz', 'local_comp_report_ext'), $formatstatlbl);

$ws1->write_number(4, 0, $studentscount, $formatstatval);
$ws1->write_number(4, 1, $groupscount, $formatstatval);
$ws1->write_number(4, 2, $compscount, $formatstatval);
$ws1->write_number(4, 3, $quizzescount, $formatstatval);

// Quizzes Table.
$row = 6;
$ws1->write_string($row, 0, '#', $formatheader);
$ws1->write_string($row, 1, get_string('quizname', 'local_comp_report_ext'), $formatheaderleft);
$ws1->write_string($row, 2, get_string('questioncount', 'local_comp_report_ext'), $formatheader);
$ws1->write_string($row, 3, get_string('attempts', 'local_comp_report_ext'), $formatheader);
$ws1->write_string($row, 4, get_string('averagegrade', 'local_comp_report_ext'), $formatheader);
$ws1->write_string($row, 5, get_string('maxgrade', 'local_comp_report_ext'), $formatheader);
$ws1->write_string($row, 6, get_string('successrate', 'local_comp_report_ext'), $formatheader);
$row++;

$ws1->set_column(0, 0, 5);
$ws1->set_column(1, 1, 35);
$ws1->set_column(2, 6, 16);

$idx = 1;
foreach ($rawquizzes as $qz) {
    $avg = ($qz->avggrade !== null) ? round((float)$qz->avggrade, 2) : 0;
    $max = (float)$qz->maxgrade;
    $rate = ($max > 0) ? round(($avg / $max) * 100, 1) : 0;

    $ws1->write_number($row, 0, $idx++, $formatcell);
    $ws1->write_string($row, 1, safe_str($qz->name), $formatcellbold);
    $ws1->write_number($row, 2, (int)$qz->numquestions, $formatcell);
    $ws1->write_number($row, 3, (int)$qz->attempts, $formatcell);
    $ws1->write_number($row, 4, $avg, $formatcell);
    $ws1->write_number($row, 5, $max, $formatcell);
    $ws1->write_string($row, 6, $rate . '%', $formatcell);
    $row++;
}

// -------------------------------------------------------------
// Sheet 2: Competency Rates.
$ws2 = $workbook->add_worksheet('Competencies');
$compheading = safe_str($course->fullname) . ' — '
    . get_string('competency_overview_title', 'local_comp_report_ext');
$ws2->write_string(0, 0, $compheading, $formattitle);
$ws2->write_string(1, 0, userdate(time()), $formatmeta);

$row = 3;
$ws2->write_string($row, 0, '#', $formatheader);
$ws2->write_string($row, 1, get_string('competencycode', 'local_comp_report_ext'), $formatheaderleft);
$ws2->write_string($row, 2, get_string('questioncount', 'local_comp_report_ext'), $formatheader);
$ws2->write_string($row, 3, get_string('correctcount', 'local_comp_report_ext'), $formatheader);
$ws2->write_string($row, 4, get_string('successrate', 'local_comp_report_ext'), $formatheader);
$row++;

$ws2->set_column(0, 0, 5);
$ws2->set_column(1, 1, 30);
$ws2->set_column(2, 4, 18);

$idx = 1;
foreach ($rawcomps as $rc) {
    $att = (float)$rc->attempts;
    $cor = (float)$rc->correct;
    $crate = ($att > 0) ? round(($cor / $att) * 100, 1) : 0;

    $ws2->write_number($row, 0, $idx++, $formatcell);
    $ws2->write_string($row, 1, safe_str($rc->shortname), $formatcellbold);
    $ws2->write_number($row, 2, round($att, 1), $formatcell);
    $ws2->write_number($row, 3, round($cor, 1), $formatcell);
    $ws2->write_string($row, 4, $crate . '%', $formatcell);
    $row++;
}

// -------------------------------------------------------------
// Sheet 3: Group Matrix.
$ws3 = $workbook->add_worksheet('Group Matrix');
$groupheading = safe_str($course->fullname) . ' — '
    . get_string('groupcomparison', 'local_comp_report_ext');
$ws3->write_string(0, 0, $groupheading, $formattitle);
$ws3->write_string(1, 0, userdate(time()), $formatmeta);

$row = 3;
$col = 0;
$ws3->write_string($row, $col++, '#', $formatheader);
$ws3->write_string($row, $col++, get_string('group', 'local_comp_report_ext'), $formatheaderleft);
foreach ($compslist as $c) {
    $ws3->write_string($row, $col++, safe_str($c->shortname), $formatheader);
}
$ws3->write_string($row, $col++, get_string('overall_performance', 'local_comp_report_ext'), $formatheader);
$row++;

$ws3->set_column(0, 0, 5);
$ws3->set_column(1, 1, 25);
for ($cidx = 2; $cidx <= $col; $cidx++) {
    $ws3->set_column($cidx, $cidx, 15);
}

$idx = 1;
foreach ($groups as $g) {
    $cpos = 0;
    $ws3->write_number($row, $cpos++, $idx++, $formatcell);
    $ws3->write_string($row, $cpos++, safe_str($g->name), $formatcellbold);

    $sumrates = 0;
    $ratedcomps = 0;
    foreach ($compslist as $c) {
        if (isset($groupmap[$g->id][$c->id]) && $groupmap[$g->id][$c->id]['att'] > 0) {
            $grate = round(($groupmap[$g->id][$c->id]['cor'] / $groupmap[$g->id][$c->id]['att']) * 100, 1);
            $ws3->write_string($row, $cpos++, $grate . '%', $formatcell);
            $sumrates += $grate;
            $ratedcomps++;
        } else {
            $ws3->write_string($row, $cpos++, '—', $formatcell);
        }
    }

    $overall = ($ratedcomps > 0) ? round($sumrates / $ratedcomps, 1) : 0;
    $ws3->write_string($row, $cpos++, ($ratedcomps > 0 ? $overall . '%' : '—'), $formatcell);
    $row++;
}

$workbook->close();
exit;
