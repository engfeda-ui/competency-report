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
 * Teacher view: Student-specific competency performance report.
 * Provides filters and detailed question links with modal support.
 *
 * @package    local_yetkinlik
 * @copyright  2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

$courseid = required_param('courseid', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$competencyid = optional_param('competencyid', 0, PARAM_INT);

require_login($courseid);
$context = context_course::instance($courseid);
require_capability('mod/quiz:viewreports', $context);

$PAGE->set_url('/local/yetkinlik/teacher_student_competency.php', ['courseid' => $courseid]);
$PAGE->set_title(get_string('teacherstudentcompetency', 'local_yetkinlik'));
$PAGE->set_heading(get_string('teacherstudentcompetency', 'local_yetkinlik'));
$PAGE->set_pagelayout('course');

// 1. Data Preparation.
$students = get_enrolled_users($context);
$studentoptions = [0 => get_string('selectstudent', 'local_yetkinlik')];
foreach ($students as $s) {
    $studentoptions[$s->id] = fullname($s);
}

$competencies = $DB->get_records_sql("
    SELECT DISTINCT c.id, c.shortname
    FROM {qbank_yetkinlik_qmap} m
    JOIN {competency} c ON c.id = m.competencyid
    ORDER BY c.shortname");

$compoptions = [0 => get_string('selectcompetency', 'local_yetkinlik')];
foreach ($competencies as $c) {
    $compoptions[$c->id] = $c->shortname;
}

/**
 * Filter form for student and competency selection.
 */
class local_yetkinlik_teacher_form extends moodleform {
    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement(
            'autocomplete',
            'userid',
            get_string('selectstudent', 'local_yetkinlik'),
            $this->_customdata['studentoptions']
        );

        $mform->addElement(
            'autocomplete',
            'competencyid',
            get_string('selectcompetency', 'local_yetkinlik'),
            $this->_customdata['compoptions']
        );

        $this->add_action_buttons(false, get_string('show', 'local_yetkinlik'));
    }
}

$mform = new local_yetkinlik_teacher_form(null, ['studentoptions' => $studentoptions, 'compoptions' => $compoptions]);
$mform->set_data(['courseid' => $courseid, 'userid' => $userid, 'competencyid' => $competencyid]);

if ($frmdata = $mform->get_data()) {
    $userid = $frmdata->userid;
    $competencyid = $frmdata->competencyid;
}

// 3. Report Data Fetching.
$renderdata = new stdClass();
$renderdata->userid = $userid;
$renderdata->competencyid = $competencyid;
$renderdata->competencies = $competencies;
$renderdata->rows = [];
$renderdata->questiondetails = [];

if ($userid && $competencyid) {
    // 2. Fetch competency details if a specific one is selected.
    if ($comp = $DB->get_record('competency', ['id' => $competencyid])) {
        $renderdata->description = format_text($comp->description, $comp->descriptionformat);
    }

    // 3a. Summary Table Data.
    $sqlsummary = "SELECT quiz.id AS quizid, quiz.name AS quizname, MAX(quiza.id) as lastattemptid,
                          SUM(qa.maxfraction) AS questions, SUM(qas.fraction) AS correct
                   FROM {quiz_attempts} quiza
                   JOIN {quiz} quiz ON quiz.id = quiza.quiz
                   JOIN {question_usages} qu ON qu.id = quiza.uniqueid
                   JOIN {question_attempts} qa ON qa.questionusageid = qu.id
                   JOIN {qbank_yetkinlik_qmap} map ON map.questionid = qa.questionid
                   JOIN (
                       SELECT MAX(fraction) AS fraction, questionattemptid
                       FROM {question_attempt_steps}
                       GROUP BY questionattemptid
                   ) qas ON qas.questionattemptid = qa.id
                   WHERE map.competencyid = :competencyid
                     AND quiza.userid = :userid
                     AND quiz.course = :courseid
                     AND quiza.state = 'finished'
                   GROUP BY quiz.id, quiz.name";

    $summaryrows = $DB->get_records_sql(
        $sqlsummary,
        ['competencyid' => $competencyid, 'userid' => $userid, 'courseid' => $courseid]
    );

    $tq = 0;
    $tc = 0;
    foreach ($summaryrows as $r) {
        $rate = $r->questions ? number_format(($r->correct / $r->questions) * 100, 1) : 0;
        $renderdata->rows[] = [
            'quizname' => $r->quizname,
            'quizurl' => (new moodle_url('/mod/quiz/review.php', ['attempt' => $r->lastattemptid]))->out(false),
            'questions' => (float) $r->questions,
            'correct' => number_format($r->correct, 1),
            'rate' => $rate,
            'color' => ($rate >= 80) ? 'green' : (($rate >= 40) ? 'orange' : 'red'),
        ];
        $tq += $r->questions;
        $tc += $r->correct;
    }

    if ($tq > 0) {
        $trate = number_format(($tc / $tq) * 100, 1);
        $renderdata->total = [
            'questions' => $tq,
            'correct' => number_format($tc, 1),
            'rate' => $trate,
            'color' => ($trate >= 80) ? 'green' : 'red',
        ];
    }

    // 3b. Detail Table Data.
    $sqldetails = "SELECT qa.id, q.name AS qname, quiz.name AS quizname, quiza.id AS attemptid, qa.slot
                   FROM {quiz_attempts} quiza
                   JOIN {quiz} quiz ON quiz.id = quiza.quiz
                   JOIN {question_usages} qu ON qu.id = quiza.uniqueid
                   JOIN {question_attempts} qa ON qa.questionusageid = qu.id
                   JOIN {question} q ON q.id = qa.questionid
                   INNER JOIN {qbank_yetkinlik_qmap} map ON map.questionid = qa.questionid
                   WHERE map.competencyid = :competencyid AND quiza.userid = :userid AND quiza.state = 'finished'
                   ORDER BY quiz.name ASC, qa.slot ASC";

    $questions = $DB->get_records_sql($sqldetails, ['competencyid' => $competencyid, 'userid' => $userid]);

    foreach ($questions as $q) {
        // Page and showall parameters removed.
        // URL now points to reviewquestion.php and includes slot parameter.
        $renderdata->questiondetails[] = [
            'quizname'     => $q->quizname,
            'questionname' => $q->qname,
            'attemptid'    => $q->attemptid,
            'slot'         => $q->slot,
            'url'          => (new moodle_url('/mod/quiz/reviewquestion.php', [
                'attempt' => $q->attemptid,
                'slot'    => $q->slot,
            ]))->out(false),
        ];
    }
}

echo $OUTPUT->header();
$page = new \local_yetkinlik\output\teacher_student_competency_page($renderdata, $mform);
echo $OUTPUT->render($page);
echo $OUTPUT->footer();
