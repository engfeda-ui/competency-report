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
 * Report for competency analysis based on school-wide or course-specific data.
 *
 * @package    local_yetkinlik
 * @copyright  2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/ai.php');

require_login();

$courseid = optional_param('courseid', 0, PARAM_INT);
global $DB, $OUTPUT, $PAGE;

// Determine context and visibility based on courseid parameter.
if ($courseid) {
    $context = context_course::instance($courseid);
    require_capability('moodle/course:view', $context);
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    $reporttitle = get_string('report_title', 'local_yetkinlik', $course->fullname);
    $wheresql = "WHERE quiz.course = :courseid AND quiza.state = 'finished'";
    $params = ['courseid' => $courseid];
} else {
    // If no course is specified, treat as a site-wide report (admin access).
    $context = context_system::instance();
    require_capability('moodle/site:config', $context);
    $reporttitle = get_string('report_title', 'local_yetkinlik');
    $wheresql = "WHERE quiza.state = 'finished'";
    $params = [];
}

// Page definitions.
$PAGE->set_url(new moodle_url('/local/yetkinlik/school_report.php', ['courseid' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_title($reporttitle);
$PAGE->set_heading($reporttitle);
$PAGE->set_pagelayout('report');

// Main SQL Query to fetch competency success rates.
$sql = "SELECT c.id, c.shortname, c.description,
               SUM(qa.maxfraction) AS attempts,
               SUM(qas.fraction) AS correct
        FROM {quiz_attempts} quiza
        JOIN {quiz} quiz ON quiz.id = quiza.quiz
        JOIN {question_usages} qu ON qu.id = quiza.uniqueid
        JOIN {question_attempts} qa ON qa.questionusageid = qu.id
        JOIN {qbank_yetkinlik_qmap} m ON m.questionid = qa.questionid
        JOIN {competency} c ON c.id = m.competencyid
        JOIN (
            SELECT MAX(fraction) AS fraction, questionattemptid
            FROM {question_attempt_steps}
            GROUP BY questionattemptid
        ) qas ON qas.questionattemptid = qa.id
        $wheresql
        GROUP BY c.id, c.shortname, c.description
        ORDER BY c.shortname ASC";

$rows = $DB->get_records_sql($sql, $params);

// Data preparation for the output.
$renderdata = new stdClass();
$renderdata->courseid = $courseid;
$renderdata->rows = $rows;
$renderdata->comment = '';

if (!empty($rows)) {
    $rates = [];
    foreach ($rows as $r) {
        $rates[$r->shortname] = $r->attempts ? ($r->correct / $r->attempts) * 100 : 0;
    }
    // Generate AI commentary based on success rates (Function defined in ai.php).
    $renderdata->comment = local_yetkinlik_generate_comment($rates);
}

// Output generation.
echo $OUTPUT->header();

$page = new \local_yetkinlik\output\school_report_page($renderdata);
echo $OUTPUT->render($page);

echo $OUTPUT->footer();
