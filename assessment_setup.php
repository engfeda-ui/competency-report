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

if ($action === 'save' && confirm_sesskey()) {
    // Save all assessments submitted from the form.
    $ids     = optional_param_array('assessment_id',     [], PARAM_INT);
    $names   = optional_param_array('assessment_name',   [], PARAM_TEXT);
    $types   = optional_param_array('assessment_type',   [], PARAM_ALPHA);
    $quizids = optional_param_array('assessment_quizid', [], PARAM_INT);
    $weights = optional_param_array('assessment_weight', [], PARAM_FLOAT);

    $now = time();

    // Delete all existing assessments for this course and rebuild.
    $DB->delete_records('local_competency_report_asmt', ['courseid' => $courseid]);

    foreach ($ids as $idx => $existingid) {
        $name   = trim($names[$idx]  ?? '');
        $type   = $types[$idx]   ?? 'quiz';
        $quizid = $quizids[$idx] ?? null;
        $weight = (float)($weights[$idx] ?? 0);

        if ($name === '' || $weight <= 0) {
            continue; // Skip empty rows.
        }

        $record = new stdClass();
        $record->courseid     = $courseid;
        $record->quizid       = ($type === 'quiz' && $quizid > 0) ? $quizid : null;
        $record->name         = $name;
        $record->type         = ($type === 'practical') ? 'practical' : 'quiz';
        $record->weight       = $weight;
        $record->timecreated  = $now;
        $record->timemodified = $now;

        $DB->insert_record('local_competency_report_asmt', $record);
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
$renderdata->sesskey      = sesskey();

$page = new \local_competency_report\output\assessment_setup_page($renderdata);
echo $OUTPUT->render($page);

echo $OUTPUT->footer();
