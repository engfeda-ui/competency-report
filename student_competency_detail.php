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
 * Detailed competency report for a specific student.
 *
 * Uses the weighted competency calculator so that practical exam results
 * and configured assessment weights are reflected here.
 *
 * @package    local_competency_report
 * @copyright  2026 Mahmoud Salem
 * @copyright  based on work by 2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/ai.php'); // Include for AI commentary generation.

use local_competency_report\competency_calculator;

$courseid = required_param('courseid', PARAM_INT);
$userid   = optional_param('userid', $USER->id, PARAM_INT);

// Basic login check for the course.
require_login($courseid);
$context = context_course::instance($courseid);

// Permission check: if the user is looking at someone else's report, they must have the report viewing capability.
if ($userid != $USER->id) {
    require_capability('mod/quiz:viewreports', $context);
}

$course  = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$student = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

// Page definitions and setup.
$PAGE->set_url('/local/competency_report/student_competency_detail.php', ['courseid' => $courseid, 'userid' => $userid]);
$PAGE->set_context($context);
$PAGE->set_pagelayout('course');
$PAGE->set_title(get_string('studentreport', 'local_competency_report'));
$PAGE->set_heading(fullname($student) . ' - ' . $course->fullname);

// 1. Data Preparation — use weighted calculator.
$calculator = new competency_calculator($courseid);
$weightedscores = $calculator->get_student_scores($userid);

// 2. Prepare success rates for AI processing and build rows for template.
$rates = [];
$rows  = [];

foreach ($weightedscores as $compid => $data) {
    $comp = $data['competency'];
    $pct  = $data['percent'];

    $rates[$comp->shortname] = $pct;

    // Build a row object compatible with the existing template.
    $row                      = new stdClass();
    $row->id                  = $comp->id;
    $row->shortname           = $comp->shortname;
    $row->description         = isset($comp->description) ? $comp->description : '';
    $row->descriptionformat   = isset($comp->descriptionformat) ? $comp->descriptionformat : FORMAT_HTML;
    $row->weighted_percent    = $pct;
    $row->passed              = $data['passed'];
    $row->breakdown           = $data['breakdown'];
    $row->hasbreakdown        = !empty($data['breakdown']);
    $rows[] = $row;
}

$renderdata = new stdClass();
$renderdata->rows      = $rows;
$renderdata->courseid  = $courseid;
$renderdata->userid    = $userid;
$renderdata->weighted  = $calculator->has_assessments(); // true = weighted mode, false = legacy.
$pdfurl = new moodle_url('/local/competency_report/parent_pdf.php', ['courseid' => $courseid, 'userid' => $userid]);
$renderdata->pdf_url  = $pdfurl->out(false);

// AI feedback is now loaded on-demand via AJAX to avoid slow page loads.
$renderdata->ai_comment = null;

// 3. Output Generation.
echo $OUTPUT->header();

$page = new \local_competency_report\output\student_competency_detail_page($renderdata);
echo $OUTPUT->render($page);

echo $OUTPUT->footer();
