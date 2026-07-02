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
 * Assessment setup page — configure which quizzes/practicals contribute to
 * competency scoring and with what weight.
 *
 * @package    local_competency_report
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);

require_login($courseid);
$context = context_course::instance($courseid);
require_capability('local/competency_report:manageassessments', $context);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

$PAGE->set_url('/local/competency_report/assessment_setup.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_pagelayout('course');
$PAGE->set_title(get_string('assessmentsetup', 'local_competency_report'));
$PAGE->set_heading($course->fullname . ' — ' . get_string('assessmentsetup', 'local_competency_report'));

// -----------------------------------------------------------------------
// Handle POST actions.
// -----------------------------------------------------------------------
$action = optional_param('action', '', PARAM_ALPHA);

if ($action === 'add' && confirm_sesskey()) {
    $type     = optional_param('assessment_type', 'quiz', PARAM_ALPHA);
    $name     = trim(optional_param('assessment_name', '', PARAM_TEXT));
    $quizid   = optional_param('assessment_quizid', null, PARAM_INT);
    $assignid = optional_param('assessment_assignid', null, PARAM_INT);
    $weight   = optional_param('assessment_weight', 0.0, PARAM_FLOAT);

    if ($name === '' || $weight < 0) {
        redirect(
            new moodle_url('/local/competency_report/assessment_setup.php', ['courseid' => $courseid]),
            get_string('invaliddata', 'local_competency_report'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    $record = new stdClass();
    $record->courseid     = $courseid;
    $record->quizid       = ($type === 'quiz' && $quizid > 0) ? $quizid : null;
    $record->assignid     = ($type === 'practical' && $assignid > 0) ? $assignid : null;
    $record->name         = $name;
    $record->type         = ($type === 'practical') ? 'practical' : 'quiz';
    $record->weight       = $weight;
    $record->timecreated  = time();
    $record->timemodified = time();

    $DB->insert_record('local_competency_report_asmt', $record);

    redirect(
        new moodle_url('/local/competency_report/assessment_setup.php', ['courseid' => $courseid]),
        get_string('assessmentsaved', 'local_competency_report'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

if ($action === 'update_existing' && confirm_sesskey()) {
    $names   = optional_param_array('name', [], PARAM_TEXT);
    $weights = optional_param_array('weight', [], PARAM_FLOAT);

    foreach ($names as $id => $name) {
        $name   = trim($name);
        $weight = isset($weights[$id]) ? (float)$weights[$id] : 0.0;

        if ($name !== '' && $weight >= 0) {
            $DB->update_record('local_competency_report_asmt', (object)[
                'id'           => $id,
                'name'         => $name,
                'weight'       => $weight,
                'timemodified' => time()
            ]);
        }
    }

    redirect(
        new moodle_url('/local/competency_report/assessment_setup.php', ['courseid' => $courseid]),
        get_string('assessmentsaved', 'local_competency_report'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

if ($action === 'delete' && confirm_sesskey()) {
    $deleteid = required_param('deleteid', PARAM_INT);
    $DB->delete_records('local_competency_report_asmt', ['id' => $deleteid, 'courseid' => $courseid]);
    redirect(
        new moodle_url('/local/competency_report/assessment_setup.php', ['courseid' => $courseid]),
        get_string('assessmentdeleted', 'local_competency_report'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// -----------------------------------------------------------------------
// Data for the view.
// -----------------------------------------------------------------------
// Existing configured assessments.
$assessments = array_values($DB->get_records(
    'local_competency_report_asmt',
    ['courseid' => $courseid],
    'id ASC'
));

// Calculate total weight of configured assessments.
$totalweight = array_sum(array_column($assessments, 'weight'));

// All quizzes in this course (for the quiz selector dropdown).
$quizzes = $DB->get_records('quiz', ['course' => $courseid], 'name ASC', 'id, name');

// All assignments in this course (for the assign selector dropdown).
$assignments = $DB->get_records('assign', ['course' => $courseid], 'name ASC', 'id, name');

// -----------------------------------------------------------------------
// Output.
// -----------------------------------------------------------------------
echo $OUTPUT->header();

// Warning if total weight ≠ 100.
if (!empty($assessments) && abs($totalweight - 100) > 0.01) {
    echo $OUTPUT->notification(
        get_string('weightwarning', 'local_competency_report', round($totalweight, 1)),
        \core\output\notification::NOTIFY_WARNING
    );
}

$renderdata = new stdClass();
$renderdata->courseid     = $courseid;
$renderdata->assessments  = $assessments;
$renderdata->totalweight  = round($totalweight, 1);
$renderdata->quizzes      = array_values($quizzes);
$renderdata->assignments  = array_values($assignments);
$renderdata->sesskey      = sesskey();

$page = new \local_competency_report\output\assessment_setup_page($renderdata);
echo $OUTPUT->render($page);

echo $OUTPUT->footer();
