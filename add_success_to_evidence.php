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
 * AI and Rule-based commentary logic for competencies.
 *
 * @package    local_competency_report
 * @copyright  2026 Mahmoud Salem
 * @copyright  based on work by 2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

// Parameters.
$courseid = optional_param('courseid', 0, PARAM_INT);
$run = optional_param('run', 0, PARAM_BOOL);

// Security and Context check.
if ($courseid > 0) {
    $context = context_course::instance($courseid);
    require_login($courseid);
} else {
    $context = context_system::instance();
    require_login();
}
require_capability('moodle/site:config', context_system::instance());

// Page Settings.
if ($courseid > 0) {
    $PAGE->set_url('/local/competency_report/add_success_to_evidence.php', ['courseid' => $courseid]);
    $PAGE->set_context($context);
    $PAGE->set_title(get_string('process_success_title', 'local_competency_report'));
    $PAGE->set_heading(get_string('process_success_heading', 'local_competency_report'));
} else {
    $PAGE->set_url('/local/competency_report/add_success_to_evidence.php');
    $PAGE->set_context($context);
    $PAGE->set_title(get_string('process_success_title', 'local_competency_report'));
    $PAGE->set_heading(get_string('process_success_heading', 'local_competency_report'));
}

// 1. If no course ID is selected, display course selection screen.
if ($courseid == 0) {
    echo $OUTPUT->header();

    // Fetch courses that have competencies mapped to them.
    $sql = "SELECT DISTINCT c.id, c.fullname
              FROM {course} c
              JOIN {qbank_competency_qmap} m ON m.courseid = c.id
             WHERE c.visible = 1
          ORDER BY c.fullname";
    $courses = $DB->get_records_sql($sql);

    if (empty($courses)) {
        // Fallback: fetch all active courses.
        $courses = $DB->get_records('course', ['visible' => 1], 'fullname ASC');
    }

    echo $OUTPUT->box_start('generalbox boxaligncenter', 'course-selector-box', [
        'style' => 'max-width: 600px; margin: 40px auto; padding: 25px; '
            . 'border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); '
            . 'border: 1px solid #e9ecef; background: #fff;',
    ]);

    echo html_writer::tag('h3', get_string('select_course_process', 'local_competency_report'), [
        'class' => 'text-center mb-4 font-weight-bold text-primary',
    ]);

    $options = [0 => get_string('select_course_option', 'local_competency_report')];
    foreach ($courses as $c) {
        if ($c->id == SITEID) {
            continue;
        }
        $options[$c->id] = format_string($c->fullname);
    }

    // Output a simple form.
    echo html_writer::start_tag('form', [
        'action' => new moodle_url('/local/competency_report/add_success_to_evidence.php'),
        'method' => 'GET',
        'class' => 'form-inline justify-content-center',
    ]);

    echo html_writer::select($options, 'courseid', 0, false, [
        'class' => 'form-control mr-2 shadow-sm',
        'id' => 'select-course-id',
        'style' => 'min-width: 350px; height: 38px;',
    ]);

    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'value' => get_string('btn_select', 'local_competency_report'),
        'class' => 'btn btn-primary shadow-sm font-weight-bold',
    ]);

    echo html_writer::end_tag('form');

    echo $OUTPUT->box_end();
    echo $OUTPUT->footer();
    exit;
}

// 2. If course ID is selected, display confirm/run process screen.
echo $OUTPUT->header();

if ($run) {
    require_sesskey();
    // Create an adhoc task.
    $task = new \local_competency_report\task\process_competency_rates_task();
    $task->set_custom_data([
        'courseid' => $courseid,
        'adminid' => $USER->id,
    ]);

    \core\task\manager::queue_adhoc_task($task);

    echo $OUTPUT->notification(get_string('process_queued', 'local_competency_report'), 'success');
    echo $OUTPUT->continue_button(new moodle_url('/course/view.php', ['id' => $courseid]));
} else {
    // Information box and action button.
    echo $OUTPUT->box(get_string('process_success_desc', 'local_competency_report'), 'generalbox boxaligncenter');

    $url = new moodle_url($PAGE->url, ['run' => 1, 'courseid' => $courseid, 'sesskey' => sesskey()]);
    echo $OUTPUT->single_button($url, get_string('btn_process_now', 'local_competency_report'));
}

echo $OUTPUT->footer();
