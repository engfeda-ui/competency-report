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
 * Teacher view for specific student analysis based on a selected quiz.
 *
 * @package    local_yetkinlik
 * @copyright  2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/yetkinlik/forms/selector_form.php');

$courseid = required_param('courseid', PARAM_INT);
require_login($courseid);
$context = context_course::instance($courseid);

// Ensure the user has teacher-level report viewing capabilities.
require_capability('mod/quiz:viewreports', $context);

// Page configuration and navigation setup.
$PAGE->set_url('/local/yetkinlik/teacher_student_exam.php', ['courseid' => $courseid]);
$PAGE->set_title(get_string('studentanalysis', 'local_yetkinlik'));
$PAGE->set_heading(get_string('studentanalysis', 'local_yetkinlik'));
$PAGE->set_pagelayout('course');

// Initialize the form: Hide competency, show quiz.
$mform = new \local_yetkinlik_selector_form(null, [
    'courseid' => $courseid,
    'showcompetency' => false, // This hides competencies.
    'showquiz' => true, // Added comma after last item to fix NormalizedArrays error.
]);

$data = new stdClass();
$data->rows = [];
$data->userid = 0;
$data->quizid = 0;

if ($fromform = $mform->get_data()) {
    $data->userid = $fromform->userid;
    $data->quizid = $fromform->quizid; // Getting the quiz selection.

    if ($data->userid && $data->quizid) {
        // Query to calculate competency performance for the specific student and quiz.
        $sql = "SELECT c.id, c.shortname,
                       SUM(qa.maxfraction) AS attempts,
                       SUM(qas.fraction) AS correct
                FROM {quiz_attempts} quiza
                JOIN {question_usages} qu ON qu.id = quiza.uniqueid
                JOIN {question_attempts} qa ON qa.questionusageid = qu.id
                JOIN {qbank_yetkinlik_qmap} m ON m.questionid = qa.questionid
                JOIN {competency} c ON c.id = m.competencyid
                JOIN (
                    SELECT MAX(fraction) AS fraction, questionattemptid
                    FROM {question_attempt_steps}
                    GROUP BY questionattemptid
                ) qas ON qas.questionattemptid = qa.id
                WHERE quiza.quiz = :quizid
                  AND quiza.userid = :userid
                  AND quiza.state = 'finished'
                GROUP BY c.id, c.shortname
                ORDER BY c.shortname ASC";

        $records = $DB->get_records_sql($sql, ['quizid' => $data->quizid, 'userid' => $data->userid]);

        foreach ($records as $r) {
            $rawrate = $r->attempts ? ($r->correct / $r->attempts) * 100 : 0;
            $rowclass = ($rawrate >= 70) ? 'table-success' : (($rawrate >= 50) ? 'table-warning' : 'table-danger');

            $data->rows[] = (object) [
                'shortname' => s($r->shortname),
                'attempts'  => number_format($r->attempts, 0),
                'correct'   => number_format($r->correct, 1),
                'rate'      => number_format($rawrate, 1),
                'rowclass'  => $rowclass,
                'raw_rate'  => round($rawrate, 1), // Added comma after last item to fix NormalizedArrays error.
            ];
        }
    }
}

// Render the output.
echo $OUTPUT->header();

$page = new \local_yetkinlik\output\teacher_student_exam_page($data, $mform);
echo $OUTPUT->render($page);

echo $OUTPUT->footer();
