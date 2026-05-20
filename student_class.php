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
 * Student Performance Analysis Page compared with Class and Course averages.
 *
 * @package    local_competency_report
 * @copyright  2026 Mahmoud Salem
 * @copyright  based on work by 2026 Hakan Ã‡iÄŸci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$course   = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context  = context_course::instance($courseid);

require_login($course);

// Students can view their own class comparison; teachers can view it too.
if (!has_capability('local/competency_report:viewownreport', $context) && !has_capability('local/competency_report:viewreports', $context)) {
    require_capability('local/competency_report:viewownreport', $context);
}

// Page settings and navigation.
$PAGE->set_url('/local/competency_report/student_class.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('studentanalysis', 'local_competency_report'));
$PAGE->set_heading(get_string('analysisfor', 'local_competency_report', $course->fullname));
$PAGE->set_pagelayout('course');

// 1. General Course Average Query.
$coursesql = "SELECT c.id, c.shortname,
                     CAST(SUM(qa.maxfraction) AS DECIMAL(12, 1)) AS attempts,
                     CAST(SUM(qas.fraction) AS DECIMAL(12, 1)) AS correct
              FROM {quiz_attempts} quiza
              JOIN {quiz} quiz ON quiz.id = quiza.quiz
              JOIN {question_usages} qu ON qu.id = quiza.uniqueid
              JOIN {question_attempts} qa ON qa.questionusageid = qu.id
              JOIN {qbank_competency_qmap} m ON m.questionid = qa.questionid
              JOIN {competency} c ON c.id = m.competencyid
              JOIN (
                  SELECT MAX(fraction) AS fraction, questionattemptid
                  FROM {question_attempt_steps}
                  GROUP BY questionattemptid
              ) qas ON qas.questionattemptid = qa.id
              WHERE quiz.course = :courseid AND quiza.state = 'finished'
              GROUP BY c.id, c.shortname";

$coursedata = $DB->get_records_sql($coursesql, ['courseid' => $courseid]);

$renderdata = new stdClass();
$renderdata->coursedata = $coursedata;
$renderdata->classdata = [];
$renderdata->studentdata = [];

if (!empty($coursedata)) {
    // 2. Class (Department) Average.
    if (!empty($USER->department)) {
        // Build class SQL directly — avoids fragile str_replace on the base query.
        $classsql = "SELECT c.id, c.shortname,
                            CAST(SUM(qa.maxfraction) AS DECIMAL(12, 1)) AS attempts,
                            CAST(SUM(qas.fraction) AS DECIMAL(12, 1)) AS correct
                     FROM {quiz_attempts} quiza
                     JOIN {user} u ON quiza.userid = u.id
                     JOIN {quiz} quiz ON quiz.id = quiza.quiz
                     JOIN {question_usages} qu ON qu.id = quiza.uniqueid
                     JOIN {question_attempts} qa ON qa.questionusageid = qu.id
                     JOIN {qbank_competency_qmap} m ON m.questionid = qa.questionid
                     JOIN {competency} c ON c.id = m.competencyid
                     JOIN (
                         SELECT MAX(fraction) AS fraction, questionattemptid
                         FROM {question_attempt_steps}
                         GROUP BY questionattemptid
                     ) qas ON qas.questionattemptid = qa.id
                     WHERE quiz.course = :courseid
                       AND quiza.state = 'finished'
                       AND u.department = :dept
                     GROUP BY c.id, c.shortname";
        $renderdata->classdata = $DB->get_records_sql($classsql, [
            'courseid' => $courseid,
            'dept'     => $USER->department,
        ]);
    }

    // 3. Student's Individual Data.
    $studentsql = "SELECT c.id, c.shortname,
                          CAST(SUM(qa.maxfraction) AS DECIMAL(12, 1)) AS attempts,
                          CAST(SUM(qas.fraction) AS DECIMAL(12, 1)) AS correct
                   FROM {quiz_attempts} quiza
                   JOIN {quiz} quiz ON quiz.id = quiza.quiz
                   JOIN {question_usages} qu ON qu.id = quiza.uniqueid
                   JOIN {question_attempts} qa ON qa.questionusageid = qu.id
                   JOIN {qbank_competency_qmap} m ON m.questionid = qa.questionid
                   JOIN {competency} c ON c.id = m.competencyid
                   JOIN (
                       SELECT MAX(fraction) AS fraction, questionattemptid
                       FROM {question_attempt_steps}
                       GROUP BY questionattemptid
                   ) qas ON qas.questionattemptid = qa.id
                   WHERE quiz.course = :courseid
                     AND quiza.state = 'finished'
                     AND quiza.userid = :userid
                   GROUP BY c.id, c.shortname";
    $renderdata->studentdata = $DB->get_records_sql($studentsql, [
        'courseid' => $courseid,
        'userid'   => $USER->id,
    ]);
}

// Render the output.
echo $OUTPUT->header();

$page = new \local_competency_report\output\student_class_page($renderdata);
echo $OUTPUT->render($page);

echo $OUTPUT->footer();
