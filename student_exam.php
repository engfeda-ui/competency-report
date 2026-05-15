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
 * Report for student competency analysis based on a specific quiz selection.
 *
 * @package    local_yetkinlik
 * @copyright  2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$quizid   = optional_param('quizid', 0, PARAM_INT);

require_login($courseid);
$context = context_course::instance($courseid);

// Page definitions and navigation setup.
$PAGE->set_url('/local/yetkinlik/student_exam.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('studentexam', 'local_yetkinlik'));
$PAGE->set_heading(get_string('studentexam', 'local_yetkinlik'));
$PAGE->set_pagelayout('course');

// 1. Prepare the list of quizzes completed by the student.
$quizzesraw = $DB->get_records_sql("
    SELECT DISTINCT q.id, q.name
      FROM {quiz} q
      JOIN {quiz_attempts} qa ON qa.quiz = q.id
     WHERE qa.userid = ? AND q.course = ? AND qa.state = 'finished'
  ORDER BY q.name
", [$USER->id, $courseid]);

// Build the quiz selection dropdown data.
$quizzes = [['id' => 0, 'name' => get_string('selectquiz', 'local_yetkinlik'), 'selected' => ($quizid == 0)]];
foreach ($quizzesraw as $q) {
    $quizzes[] = [
        'id' => $q->id,
        'name' => $q->name,
        'selected' => ($quizid == $q->id),
    ];
}

$renderdata = new stdClass();
$renderdata->courseid = $courseid;
$renderdata->quizid = $quizid;
$renderdata->quizzes = $quizzes;
$renderdata->rows = [];

if ($quizid) {
    // 2. Fetch competency analysis data for the selected quiz.
    $sql = "SELECT c.shortname, c.description, SUM(qa.maxfraction) AS attempts, SUM(qas.fraction) AS correct
            FROM {quiz_attempts} quiza
            JOIN {question_usages} qu ON qu.id = quiza.uniqueid
            JOIN {question_attempts} qa ON qa.questionusageid = qu.id
            JOIN {quiz} quiz ON quiz.id = quiza.quiz
            JOIN {qbank_yetkinlik_qmap} m ON m.questionid = qa.questionid
            JOIN {competency} c ON c.id = m.competencyid
            JOIN (
                SELECT MAX(fraction) AS fraction, questionattemptid
                FROM {question_attempt_steps}
                GROUP BY questionattemptid
            ) qas ON qas.questionattemptid = qa.id
            WHERE quiz.id = ? AND quiza.userid = ? AND quiza.state = 'finished'
            GROUP BY c.shortname, c.description
            ORDER BY c.shortname";

    $renderdata->rows = $DB->get_records_sql($sql, [$quizid, $USER->id]);
}

// 3. Output Generation.
echo $OUTPUT->header();

$page = new \local_yetkinlik\output\student_exam_page($renderdata);
echo $OUTPUT->render($page);

echo $OUTPUT->footer();
