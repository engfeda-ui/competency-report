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
 * Report showing student performance per quiz for a specific selected competency.
 *
 * @package    local_yetkinlik
 * @copyright  2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$competencyid = optional_param('competencyid', 0, PARAM_INT);

require_login($courseid);
$context = context_course::instance($courseid);

// Page definitions and navigation setup.
$PAGE->set_url('/local/yetkinlik/student_competency_exams.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('studentcompetencyexams', 'local_yetkinlik'));
$PAGE->set_heading(get_string('studentcompetencyexams', 'local_yetkinlik'));
$PAGE->set_pagelayout('course');

// 1. Fetch available competencies for the selection filter.
$compsraw = $DB->get_records_sql("
    SELECT DISTINCT c.id, c.shortname
    FROM {qbank_yetkinlik_qmap} m
    JOIN {competency} c ON c.id = m.competencyid
    ORDER BY c.shortname");

$competencies = [];
foreach ($compsraw as $c) {
    $competencies[] = [
        'id' => $c->id,
        'shortname' => format_string($c->shortname),
        'selected' => ($c->id == $competencyid),
    ];
}

$renderdata = new stdClass();
$renderdata->courseid = $courseid;
$renderdata->competencyid = $competencyid;
$renderdata->competencies = $competencies;
$renderdata->rows = [];
$renderdata->questiondetails = [];

if ($competencyid) {
    // 2. Fetch competency details if a specific one is selected.
    if ($comp = $DB->get_record('competency', ['id' => $competencyid])) {
        $renderdata->description = format_text($comp->description, $comp->descriptionformat);
    }

    // 3. Fetch summary performance data for the current user in the selected competency across all quizzes.
    // Added MAX(quiza.id) as lastattemptid to enable links.
    $sqlsummary = "SELECT quiz.id AS quizid, quiz.name AS quizname, MAX(quiza.id) as lastattemptid,
                          SUM(qa.maxfraction) AS questions, SUM(qas.fraction) AS correct
                   FROM {quiz_attempts} quiza
                   JOIN {question_usages} qu ON qu.id = quiza.uniqueid
                   JOIN {question_attempts} qa ON qa.questionusageid = qu.id
                   JOIN {quiz} quiz ON quiz.id = quiza.quiz
                   JOIN {qbank_yetkinlik_qmap} m ON m.questionid = qa.questionid
                   JOIN (
                       SELECT MAX(fraction) AS fraction, questionattemptid
                       FROM {question_attempt_steps}
                       GROUP BY questionattemptid
                   ) qas ON qas.questionattemptid = qa.id
                   WHERE quiz.course = :courseid
                     AND m.competencyid = :competencyid
                     AND quiza.userid = :userid
                     AND quiza.state = 'finished'
                   GROUP BY quiz.id, quiz.name
                   ORDER BY quiz.id";

    $summaryrows = $DB->get_records_sql($sqlsummary, [
        'courseid' => $courseid,
        'competencyid' => $competencyid,
        'userid' => $USER->id,
    ]);

    foreach ($summaryrows as $r) {
        // Link added for quiz review.
        $r->quizurl = (new moodle_url('/mod/quiz/review.php', ['attempt' => $r->lastattemptid]))->out(false);
        $renderdata->rows[] = $r;
    }

    // 4. Fetch detailed question attempts related to the competency with page-specific links.
    // Removed JOIN {quiz_slots} and target qa.slot directly.
    $sqldetails = "SELECT qa.id, q.name AS qname, quiz.name AS quizname, quiza.id AS attemptid, qa.slot
                   FROM {quiz_attempts} quiza
                   JOIN {quiz} quiz ON quiz.id = quiza.quiz
                   JOIN {question_usages} qu ON qu.id = quiza.uniqueid
                   JOIN {question_attempts} qa ON qa.questionusageid = qu.id
                   JOIN {question} q ON q.id = qa.questionid
                   INNER JOIN {qbank_yetkinlik_qmap} m ON m.questionid = qa.questionid
                   WHERE m.competencyid = :competencyid
                     AND quiza.userid = :userid
                     AND quiza.state = 'finished'
                     AND quiz.course = :courseid
                   ORDER BY quiz.name ASC, qa.slot ASC";

    $questions = $DB->get_records_sql($sqldetails, [
        'competencyid' => $competencyid,
        'userid' => $USER->id,
        'courseid' => $courseid,
    ]);

    foreach ($questions as $q) {
        // Switch to reviewquestion.php with slot parameter.
        $renderdata->questiondetails[] = [
            'quizname'     => $q->quizname,
            'questionname' => $q->qname,
            'url'          => (new moodle_url('/mod/quiz/reviewquestion.php', [
                'attempt' => $q->attemptid,
                'slot'    => $q->slot,
            ]))->out(false),
        ];
    }
}

// 5. Output Generation.
echo $OUTPUT->header();

$page = new \local_yetkinlik\output\student_competency_exams_page($renderdata);
echo $OUTPUT->render($page);

echo $OUTPUT->footer();
