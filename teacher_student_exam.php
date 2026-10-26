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
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @copyright  based on work by 2026 Hakan Ã‡iÄŸci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->dirroot . '/local/comp_report_ext/forms/selector_form.php');

$courseid = required_param('courseid', PARAM_INT);
require_login($courseid);
$context = context_course::instance($courseid);

// Ensure the user has teacher-level report viewing capabilities.
$canviewext  = has_capability('local/comp_report_ext:viewreports', $context);
$canviewold  = has_capability('local/competency_report:viewreports', $context);
$canviewquiz = has_capability('mod/quiz:viewreports', $context);
if (!$canviewext && !$canviewold && !$canviewquiz) {
    require_capability('local/comp_report_ext:viewreports', $context);
}

// Page configuration and navigation setup.
$PAGE->set_url('/local/comp_report_ext/teacher_student_exam.php', ['courseid' => $courseid]);
$PAGE->set_title(get_string('studentanalysis', 'local_comp_report_ext'));
$PAGE->set_heading(get_string('studentanalysis', 'local_comp_report_ext'));
$PAGE->set_pagelayout('course');

// Initialize the form: Hide competency, show quiz.
$mform = new \local_comp_report_ext_selector_form(null, [
    'courseid' => $courseid,
    'showcompetency' => false, // This hides competencies.
    'showquiz' => true, // Added comma after last item to fix NormalizedArrays error.
]);

$data = new stdClass();
$data->rows = [];
$data->userid = 0;
$data->quizid = 0;
$data->courseid = $courseid;

if ($fromform = $mform->get_data()) {
    $data->userid = $fromform->userid;
    $data->quizid = $fromform->quizid; // Getting the quiz selection.

    if ($data->userid && $data->quizid) {
        [$allcoursecomps, $aliasmap] = local_comp_report_ext_get_canonical_competencies($courseid);

        // Fetch best attempt ID to prevent attempt duplicate multiplication.
        $bestattempt = $DB->get_record_sql(
            "SELECT id, sumgrades FROM {quiz_attempts}
              WHERE quiz = :quizid AND userid = :userid AND state = 'finished'
           ORDER BY sumgrades DESC, id DESC",
            ['quizid' => $data->quizid, 'userid' => $data->userid],
            IGNORE_MULTIPLE
        );

        if ($bestattempt) {
            // Query to calculate competency performance for the specific student and quiz best attempt.
            $sql = "SELECT c.id, c.shortname,
                           SUM(qa.maxfraction) AS attempts,
                           SUM(qas.fraction) AS correct
                    FROM {quiz_attempts} quiza
                    JOIN {question_usages} qu ON qu.id = quiza.uniqueid
                    JOIN {question_attempts} qa ON qa.questionusageid = qu.id
                    JOIN {qbank_comp_ext_qmap} m ON m.questionid = qa.questionid
                    JOIN {competency} c ON c.id = m.competencyid
                    JOIN (
                        SELECT s.questionattemptid, MAX(s.fraction) AS fraction
                          FROM {question_attempt_steps} s
                          JOIN {question_attempts} qa2 ON qa2.id = s.questionattemptid
                          JOIN {question_usages} qu2   ON qu2.id = qa2.questionusageid
                          JOIN {quiz_attempts} qa3     ON qa3.uniqueid = qu2.id
                         WHERE qa3.id = :bestattid2
                           AND qa3.state = 'finished'
                         GROUP BY s.questionattemptid
                    ) qas ON qas.questionattemptid = qa.id
                    WHERE quiza.id = :bestattid
                      AND quiza.state = 'finished'
                    GROUP BY c.id, c.shortname
                    ORDER BY c.shortname ASC";

            $records = $DB->get_records_sql($sql, [
                'bestattid'  => $bestattempt->id,
                'bestattid2' => $bestattempt->id,
            ]);

            // Group by canonical competency ID.
            $canonicalrecords = [];
            foreach ($records as $r) {
                $canid = $aliasmap[$r->id] ?? (int)$r->id;
                if (!isset($canonicalrecords[$canid])) {
                    $canonicalrecords[$canid] = (object)[
                        'shortname' => $allcoursecomps[$canid]->shortname ?? $r->shortname,
                        'attempts'  => 0.0,
                        'correct'   => 0.0,
                    ];
                }
                $canonicalrecords[$canid]->attempts += (float)$r->attempts;
                $canonicalrecords[$canid]->correct  += (float)$r->correct;
            }

            foreach ($canonicalrecords as $r) {
                $rawrate = $r->attempts > 0 ? ($r->correct / $r->attempts) * 100 : 0;
                $rowclass = ($rawrate >= 70) ? 'table-success' : (($rawrate >= 50) ? 'table-warning' : 'table-danger');

                $data->rows[] = (object) [
                    'shortname' => s($r->shortname),
                    'attempts'  => number_format($r->attempts, 0),
                    'correct'   => number_format($r->correct, 1),
                    'rate'      => number_format($rawrate, 1),
                    'rowclass'  => $rowclass,
                    'raw_rate'  => round($rawrate, 1),
                ];
            }
        }
    }
}

// Render the output.
echo $OUTPUT->header();

$page = new \local_comp_report_ext\output\teacher_student_exam_page($data, $mform);
echo $OUTPUT->render($page);

echo $OUTPUT->footer();
