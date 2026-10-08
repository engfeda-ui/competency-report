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
 * Excel export for Student Competency Report Card.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/excellib.class.php');

$courseid = required_param('courseid', PARAM_INT);
$userid   = optional_param('userid', 0, PARAM_INT);

require_login($courseid);
$context = context_course::instance($courseid);

if ($userid <= 0) {
    $userid = $USER->id;
}

if ($userid != $USER->id) {
    $canview = has_capability('local/comp_report_ext:viewreports', $context)
            || has_capability('mod/quiz:viewreports', $context)
            || has_capability('moodle/competency:usercompetencyview', $context);
    if (!$canview) {
        require_capability('local/comp_report_ext:viewreports', $context);
    }
} else {
    if (!has_capability('local/comp_report_ext:viewownreport', $context)
        && !has_capability('local/comp_report_ext:viewreports', $context)
        && !has_capability('local/competency_report:viewownreport', $context)
        && !has_capability('local/competency_report:viewreports', $context)
    ) {
        require_capability('local/comp_report_ext:viewownreport', $context);
    }
}

global $DB;

$course  = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$student = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

// 1. Data Query.
$sql = "SELECT c.id, c.shortname, c.description, c.descriptionformat,
               CAST(SUM(qa.maxfraction) AS DECIMAL(12, 1)) AS questions,
               CAST(SUM(qas.fraction) AS DECIMAL(12, 1)) AS correct
        FROM {quiz_attempts} quiza
        JOIN {question_usages} qu ON qu.id = quiza.uniqueid
        JOIN {question_attempts} qa ON qa.questionusageid = qu.id
        JOIN {quiz} quiz ON quiz.id = quiza.quiz
        JOIN {qbank_comp_ext_qmap} m ON m.questionid = qa.questionid
        JOIN {competency} c ON c.id = m.competencyid
        JOIN (
            SELECT s.questionattemptid, MAX(s.fraction) AS fraction
              FROM {question_attempt_steps} s
              JOIN {question_attempts} qa2 ON qa2.id = s.questionattemptid
              JOIN {question_usages} qu2   ON qu2.id = qa2.questionusageid
              JOIN {quiz_attempts} qa3     ON qa3.uniqueid = qu2.id
              JOIN {quiz} q2               ON q2.id = qa3.quiz
             WHERE q2.course = :subcourseid1
               AND qa3.state = 'finished'
             GROUP BY s.questionattemptid
        ) qas ON qas.questionattemptid = qa.id
        WHERE quiz.course = :courseid AND quiza.userid = :userid AND quiza.state = 'finished'
        GROUP BY c.id, c.shortname, c.description, c.descriptionformat
        ORDER BY c.shortname ASC";

$rows = $DB->get_records_sql($sql, ['courseid' => $courseid, 'subcourseid1' => $courseid, 'userid' => $userid]);

// 2. Class Averages.
$classavgrows = $DB->get_records_sql("
    SELECT c.id, c.shortname,
           CAST(SUM(qa.maxfraction) AS DECIMAL(12,1)) AS questions,
           CAST(SUM(qas.fraction) AS DECIMAL(12,1)) AS correct
    FROM {quiz_attempts} quiza
    JOIN {question_usages} qu ON qu.id = quiza.uniqueid
    JOIN {question_attempts} qa ON qa.questionusageid = qu.id
    JOIN {quiz} quiz ON quiz.id = quiza.quiz
    JOIN {qbank_comp_ext_qmap} m ON m.questionid = qa.questionid
    JOIN {competency} c ON c.id = m.competencyid
    JOIN (
        SELECT s.questionattemptid, MAX(s.fraction) AS fraction
          FROM {question_attempt_steps} s
          JOIN {question_attempts} qa2 ON qa2.id = s.questionattemptid
          JOIN {question_usages} qu2   ON qu2.id = qa2.questionusageid
          JOIN {quiz_attempts} qa3     ON qa3.uniqueid = qu2.id
          JOIN {quiz} q2               ON q2.id = qa3.quiz
         WHERE q2.course = :subcourseid2
           AND qa3.state = 'finished'
         GROUP BY s.questionattemptid
    ) qas ON qas.questionattemptid = qa.id
    WHERE quiz.course = :courseid AND quiza.state = 'finished'
    GROUP BY c.id, c.shortname",
    ['courseid' => $courseid, 'subcourseid2' => $courseid]
);

$classrates = [];
foreach ($classavgrows as $cr) {
    $classrates[$cr->shortname] = $cr->questions ? round(($cr->correct / $cr->questions) * 100, 1) : 0;
}

function safe_excel_str($str): string {
    return clean_param(strip_tags((string)$str), PARAM_TEXT);
}

$filename = clean_filename('ReportCard_' . $student->username . '_' . date('Ymd_His') . '.xlsx');
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
$format_cell_left = $workbook->add_format(['border' => 1, 'align' => 'left', 'valign' => 'vcenter']);
$format_cell_bold = $workbook->add_format(['bold' => 1, 'border' => 1, 'align' => 'left', 'valign' => 'vcenter']);
$format_total = $workbook->add_format(['bold' => 1, 'bg_color' => 'silver', 'border' => 1, 'align' => 'center', 'valign' => 'vcenter']);
$format_total_left = $workbook->add_format(['bold' => 1, 'bg_color' => 'silver', 'border' => 1, 'align' => 'left', 'valign' => 'vcenter']);

$worksheet = $workbook->add_worksheet('Report Card');

$worksheet->write_string(0, 0, safe_excel_str($course->fullname) . ' — ' . get_string('myreportcard', 'local_comp_report_ext'), $format_title);
$worksheet->write_string(1, 0, get_string('student', 'local_comp_report_ext') . ': ' . safe_excel_str(fullname($student)) . ' (' . $student->username . ') | ' . userdate(time()), $format_meta);

$row = 3;
$worksheet->write_string($row, 0, '#', $format_header);
$worksheet->write_string($row, 1, get_string('competencycode', 'local_comp_report_ext'), $format_header_left);
$worksheet->write_string($row, 2, get_string('competency', 'local_comp_report_ext'), $format_header_left);
$worksheet->write_string($row, 3, get_string('questioncount', 'local_comp_report_ext'), $format_header);
$worksheet->write_string($row, 4, get_string('correctcount', 'local_comp_report_ext'), $format_header);
$worksheet->write_string($row, 5, get_string('successrate', 'local_comp_report_ext'), $format_header);
$worksheet->write_string($row, 6, get_string('classavg', 'local_comp_report_ext'), $format_header);
$row++;

$worksheet->set_column(0, 0, 5);
$worksheet->set_column(1, 1, 15);
$worksheet->set_column(2, 2, 40);
$worksheet->set_column(3, 6, 18);

$idx = 1;
$totq = 0;
$totc = 0;
$sumrate = 0;
$count = 0;

foreach ($rows as $r) {
    $q = (float)$r->questions;
    $c = (float)$r->correct;
    $rate = ($q > 0) ? round(($c / $q) * 100, 1) : 0;
    $classrate = $classrates[$r->shortname] ?? 0;

    $worksheet->write_number($row, 0, $idx++, $format_cell);
    $worksheet->write_string($row, 1, safe_excel_str($r->shortname), $format_cell_bold);
    $worksheet->write_string($row, 2, safe_excel_str($r->description), $format_cell_left);
    $worksheet->write_number($row, 3, round($q, 1), $format_cell);
    $worksheet->write_number($row, 4, round($c, 1), $format_cell);
    $worksheet->write_string($row, 5, $rate . '%', $format_cell);
    $worksheet->write_string($row, 6, $classrate . '%', $format_cell);

    $totq += $q;
    $totc += $c;
    $sumrate += $rate;
    $count++;
    $row++;
}

if ($count > 0) {
    $avgrate = round($sumrate / $count, 1);
    $worksheet->write_string($row, 0, '', $format_total);
    $worksheet->write_string($row, 1, get_string('total', 'moodle'), $format_total_left);
    $worksheet->write_string($row, 2, '', $format_total);
    $worksheet->write_number($row, 3, round($totq, 1), $format_total);
    $worksheet->write_number($row, 4, round($totc, 1), $format_total);
    $worksheet->write_string($row, 5, $avgrate . '%', $format_total);
    $worksheet->write_string($row, 6, '—', $format_total);
}

$workbook->close();
exit;
