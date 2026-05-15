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
 * @package    local_yetkinlik
 * @copyright  2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

// Parameters.
$courseid = required_param('courseid', PARAM_INT);
$run = optional_param('run', 0, PARAM_BOOL);

// Security and Context.
$context = context_course::instance($courseid);
require_login($courseid);
require_capability('moodle/site:config', context_system::instance());

// Page Settings.
$PAGE->set_url('/local/yetkinlik/add_success_to_evidence.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('process_success_title', 'local_yetkinlik'));
$PAGE->set_heading(get_string('process_success_heading', 'local_yetkinlik'));

echo $OUTPUT->header();

if ($run) {
    // Create an adhoc task.
    $task = new \local_yetkinlik\task\process_competency_rates_task();
    $task->set_custom_data([
        'courseid' => $courseid,
        'adminid' => $USER->id,
    ]);

    \core\task\manager::queue_adhoc_task($task);

    echo $OUTPUT->notification(get_string('process_queued', 'local_yetkinlik'), 'success');
    echo $OUTPUT->continue_button(new moodle_url('/course/view.php', ['id' => $courseid]));
} else {
    // Information box and action button.
    echo $OUTPUT->box(get_string('process_success_desc', 'local_yetkinlik'), 'generalbox boxaligncenter');

    $url = new moodle_url($PAGE->url, ['run' => 1, 'courseid' => $courseid]);
    echo $OUTPUT->single_button($url, get_string('btn_process_now', 'local_yetkinlik'));
}

echo $OUTPUT->footer();
