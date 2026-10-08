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
 * Excel export for Class / Student Competency Report.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/excellib.class.php');

$courseid   = required_param('courseid', PARAM_INT);
$userid     = optional_param('userid', 0, PARAM_INT);
$competency = optional_param('competencyid', 0, PARAM_INT);

require_login($courseid);
$context = context_course::instance($courseid);
$canviewext = has_capability('local/comp_report_ext:viewreports', $context);
$canviewold = has_capability('local/competency_report:viewreports', $context);
if (!$canviewext && !$canviewold) {
    require_capability('local/comp_report_ext:viewreports', $context);
}

global $DB;

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$studentname = '';
if ($userid > 0) {
    $st = $DB->get_record('user', ['id' => $userid]);
    if ($st) {
        $studentname = fullname($st);
    }
}

// Course General SQL.
$coursesql = "SELECT c.id, c.shortname,
                     SUM(qa.maxfraction) AS attempts,
                     SUM(qas.fraction) AS correct
              FROM {quiz_attempts} quiza
              JOIN {question_usages} qu ON qu.id = quiza.uniqueid
              JOIN {question_attempts} qa ON qa.questionusageid = qu.id
              JOIN {quiz} quiz ON quiz.id = quiza.quiz
              JOIN {qbank_comp_ext_qmap} m ON m.questionid = qa.questionid
              JOIN {competency} c ON c.id = m.competencyid
              JOIN (SELECT s.questionattemptid, MAX(s.fraction) AS fraction
                      FROM {question_attempt_steps} s
                      JOIN {question_attempts} qa2 ON qa2.id = s.questionattemptid
                      JOIN {question_usages} qu2   ON qu2.id = qa2.questionusageid
                      JOIN {quiz_attempts} qa3     ON qa3.uniqueid = qu2.id
                      JOIN {quiz} q2               ON q2.id = qa3.quiz
                     WHERE q2.course = :subcourseid
                       AND qa3.state = 'finished'
                     GROUP BY s.questionattemptid) qas ON qas.questionattemptid = qa.id
              WHERE quiz.course = :courseid AND quiza.state = 'finished'";

if ($competency) {
    $coursesql .= " AND c.id = :competencyid";
}
$coursesql .= " GROUP BY c.id, c.shortname ORDER BY c.shortname ASC";

$params = ['courseid' => $courseid, 'subcourseid' => $courseid, 'competencyid' => $competency];
$coursedata = $DB->get_records_sql($coursesql, $params);

$classdata = [];
$studentdata = [];

if (!empty($coursedata) && $userid) {
    // 1. Check if user belongs to group(s) in this course.
    $usergroups = $DB->get_fieldset_sql("
        SELECT gm.groupid
        FROM {groups_members} gm
        JOIN {groups} g ON g.id = gm.groupid
        WHERE g.courseid = :courseid AND gm.userid = :userid
    ", ['courseid' => $courseid, 'userid' => $userid]);

    if (!empty($usergroups)) {
        [$groupinsql, $groupparams] = $DB->get_in_or_equal($usergroups, SQL_PARAMS_NAMED, 'grp');
        $classsql = str_replace(
            "FROM {quiz_attempts} quiza",
            "FROM {quiz_attempts} quiza JOIN {groups_members} gm ON gm.userid = quiza.userid",
            $coursesql
        );
        $classsql = str_replace(
            "WHERE quiz.course = :courseid",
            "WHERE quiz.course = :courseid AND gm.groupid " . $groupinsql,
            $classsql
        );
        $classparams = array_merge(['courseid' => $courseid, 'subcourseid' => $courseid, 'competencyid' => $competency], $groupparams);
        $classdata = $DB->get_records_sql($classsql, $classparams);
    } else {
        $userdept = $DB->get_field('user', 'department', ['id' => $userid]);
        if (!empty($userdept)) {
            $classsql = str_replace(
                "FROM {quiz_attempts} quiza",
                "FROM {quiz_attempts} quiza JOIN {user} u ON quiza.userid = u.id",
                $coursesql
            );
            $classsql = str_replace(
                "WHERE quiz.course",
                "WHERE u.department = :dept AND quiz.course",
                $classsql
            );
            $classdata = $DB->get_records_sql($classsql, [
                'courseid' => $courseid,
                'subcourseid' => $courseid,
                'dept' => $userdept,
                'competencyid' => $competency,
            ]);
        }
    }

    if (empty($classdata)) {
        $classdata = $coursedata;
    }

    $studentsql = str_replace(
        "WHERE quiz.course",
        "WHERE quiza.userid = :userid AND quiz.course",
        $coursesql
    );
    $studentdata = $DB->get_records_sql($studentsql, [
        'courseid' => $courseid,
        'subcourseid' => $courseid,
        'userid' => $userid,
        'competencyid' => $competency,
    ]);
}

function safe_excel_str($str): string {
    return clean_param(strip_tags((string)$str), PARAM_TEXT);
}

$filename = clean_filename('Class_Report_' . $course->shortname . '_' . date('Ymd_His') . '.xlsx');
$workbook = new MoodleExcelWorkbook($filename);

$format_title = $workbook->add_format(['bold' => 1, 'size' => 14, 'align' => 'left']);
$format_meta  = $workbook->add_format(['italic' => 1, 'size' => 10, 'color' => 'gray']);
$format_header = $workbook->add_format([
    'bold' => 1,
    'bg_color' => 'navy',
    'color' => 'white',
    'border' => 1,
    'align' => 'center',
    'valign' => 'vcenter',
]);
$format_header_left = $workbook->add_format([
    'bold' => 1,
    'bg_color' => 'navy',
    'color' => 'white',
    'border' => 1,
    'align' => 'left',
    'valign' => 'vcenter',
]);
$format_cell = $workbook->add_format(['border' => 1, 'align' => 'center', 'valign' => 'vcenter']);
$format_cell_bold = $workbook->add_format(['bold' => 1, 'border' => 1, 'align' => 'left', 'valign' => 'vcenter']);
$format_total = $workbook->add_format(['bold' => 1, 'bg_color' => 'silver', 'border' => 1, 'align' => 'center', 'valign' => 'vcenter']);
$format_total_left = $workbook->add_format(['bold' => 1, 'bg_color' => 'silver', 'border' => 1, 'align' => 'left', 'valign' => 'vcenter']);

$worksheet = $workbook->add_worksheet('Class Competencies');

$worksheet->write_string(0, 0, safe_excel_str($course->fullname) . ' — ' . get_string('studentclassreport', 'local_comp_report_ext'), $format_title);
$metatext = get_string('course', 'moodle') . ': ' . safe_excel_str($course->shortname);
if (!empty($studentname)) {
    $metatext .= ' | ' . get_string('student', 'local_comp_report_ext') . ': ' . safe_excel_str($studentname);
}
$metatext .= ' | ' . userdate(time());
$worksheet->write_string(1, 0, $metatext, $format_meta);

$row = 3;
$col = 0;
$worksheet->write_string($row, $col++, '#', $format_header);
$worksheet->write_string($row, $col++, get_string('competencyname', 'local_comp_report_ext'), $format_header_left);
$worksheet->write_string($row, $col++, get_string('courseavg', 'local_comp_report_ext'), $format_header);
$worksheet->write_string($row, $col++, get_string('classavg', 'local_comp_report_ext'), $format_header);
if ($userid > 0) {
    $worksheet->write_string($row, $col++, get_string('studentavg', 'local_comp_report_ext'), $format_header);
}
$row++;

$worksheet->set_column(0, 0, 5);
$worksheet->set_column(1, 1, 30);
$worksheet->set_column(2, $col, 18);

$idx = 1;
$sumcourse = 0;
$sumclass  = 0;
$sumstud   = 0;
$count     = 0;

foreach ($coursedata as $cid => $c) {
    $courserate = $c->attempts ? round(($c->correct / $c->attempts) * 100, 1) : 0;
    $classrate  = (isset($classdata[$cid]) && $classdata[$cid]->attempts) ?
        round(($classdata[$cid]->correct / $classdata[$cid]->attempts) * 100, 1) : 0;
    $studrate   = (isset($studentdata[$cid]) && $studentdata[$cid]->attempts) ?
        round(($studentdata[$cid]->correct / $studentdata[$cid]->attempts) * 100, 1) : 0;

    $cpos = 0;
    $worksheet->write_number($row, $cpos++, $idx++, $format_cell);
    $worksheet->write_string($row, $cpos++, safe_excel_str($c->shortname), $format_cell_bold);
    $worksheet->write_string($row, $cpos++, $courserate . '%', $format_cell);
    $worksheet->write_string($row, $cpos++, $classrate . '%', $format_cell);
    if ($userid > 0) {
        $worksheet->write_string($row, $cpos++, $studrate . '%', $format_cell);
        $sumstud += $studrate;
    }

    $sumcourse += $courserate;
    $sumclass  += $classrate;
    $count++;
    $row++;
}

if ($count > 0) {
    $cpos = 0;
    $worksheet->write_string($row, $cpos++, '', $format_total);
    $worksheet->write_string($row, $cpos++, get_string('total', 'moodle') . ' / ' . get_string('averagegrade', 'local_comp_report_ext'), $format_total_left);
    $worksheet->write_string($row, $cpos++, round($sumcourse / $count, 1) . '%', $format_total);
    $worksheet->write_string($row, $cpos++, round($sumclass / $count, 1) . '%', $format_total);
    if ($userid > 0) {
        $worksheet->write_string($row, $cpos++, round($sumstud / $count, 1) . '%', $format_total);
    }
}

$workbook->close();
exit;
