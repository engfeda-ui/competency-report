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
 * Main student competency report page.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @copyright  based on work by 2026 Hakan Ã‡iÄŸci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/ai.php');

$courseid = required_param('courseid', PARAM_INT);
require_login($courseid);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);
$userid = $USER->id;

// Students can view their own report; teachers can view any student's report.
if (
    !has_capability('local/competency_report:viewownreport', $context)
    && !has_capability('local/competency_report:viewreports', $context)
) {
    require_capability('local/competency_report:viewownreport', $context);
}

// Page Setup.
$PAGE->set_url('/local/competency_report/student_report.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_pagelayout('course');
$PAGE->set_title(get_string('studentreport', 'local_comp_report_ext'));
$PAGE->set_heading($course->fullname);

// 1. Data Query.
// Fetch student's achievement data mapped to competencies within the current course.
$sql = "SELECT c.id, c.shortname, c.description, c.descriptionformat,
               CAST(SUM(qa.maxfraction) AS DECIMAL(12, 1)) AS questions,
               CAST(SUM(qas.fraction) AS DECIMAL(12, 1)) AS correct
        FROM {quiz_attempts} quiza
        JOIN {question_usages} qu ON qu.id = quiza.uniqueid
        JOIN {question_attempts} qa ON qa.questionusageid = qu.id
        JOIN {quiz} quiz ON quiz.id = quiza.quiz
        JOIN {qbank_comp_ext_qmap} m ON m.questionid = qa.questionid
        JOIN {competency} c ON c.id = m.competencyid
        JOIN (
            SELECT MAX(fraction) AS fraction, questionattemptid
            FROM {question_attempt_steps}
            GROUP BY questionattemptid
        ) qas ON qas.questionattemptid = qa.id
        WHERE quiz.course = :courseid AND quiza.userid = :userid AND quiza.state = 'finished'
        GROUP BY c.id, c.shortname, c.description, c.descriptionformat";

$rows = $DB->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $userid]);

// 2. Prepare Success Rates for AI Feedback.
// These rates will be sent to the AI function to generate a personalized commentary.
$rates = [];
foreach ($rows as $r) {
    $rates[$r->shortname] = $r->questions ? ($r->correct / $r->questions) * 100 : 0;
}

// 3. Compute CLASS AVERAGE rates for the Radar Chart comparison.
$classavgrows = $DB->get_records_sql("
    SELECT c.id, c.shortname,
           CAST(SUM(qa.maxfraction) AS DECIMAL(12,1)) AS questions,
           CAST(SUM(qas.fraction) AS DECIMAL(12,1)) AS correct
    FROM {quiz_attempts} quiza
    JOIN {question_usages} qu ON qu.id = quiza.uniqueid
    JOIN {question_attempts} qa ON qa.questionusageid = qu.id
    JOIN {quiz} quiz ON quiz.id = quiza.quiz
    JOIN {qbank_comp_ext_qmap} m ON m.questionid = qa.questionid
    JOIN {competency} c ON c.id = m.competencyid
    JOIN (
        SELECT MAX(fraction) AS fraction, questionattemptid
        FROM {question_attempt_steps}
        GROUP BY questionattemptid
    ) qas ON qas.questionattemptid = qa.id
    WHERE quiz.course = :courseid AND quiza.state = 'finished'
    GROUP BY c.id, c.shortname
", ['courseid' => $courseid]);

$classrates = [];
foreach ($classavgrows as $cr) {
    $classrates[$cr->shortname] = $cr->questions ? round(($cr->correct / $cr->questions) * 100, 1) : 0;
}

// Build radar chart data (labels, student values, class avg values).
$chartlabels = [];
$chartstudent = [];
$chartclass = [];
foreach ($rates as $shortname => $rate) {
    $chartlabels[] = $shortname;
    $chartstudent[] = round($rate, 1);
    $chartclass[] = $classrates[$shortname] ?? 0;
}
$chartdata = json_encode([
    'labels'   => $chartlabels,
    'student'  => $chartstudent,
    'class'    => $chartclass,
]);

// 4. Prepare Render Data Object.
$renderdata = new stdClass();
$renderdata->rows = $rows;
$renderdata->courseid = $courseid;
$renderdata->userid = $userid;
$renderdata->context = $context;
$renderdata->pdf_url = (new moodle_url('/local/competency_report/parent_pdf.php', ['courseid' => $courseid]))->out(false);
$renderdata->chart_data = $chartdata;
$renderdata->has_radar = count($chartlabels) >= 2; // Only show chart if ≥2 competencies.

// AI feedback is now loaded on-demand via AJAX to avoid slow page loads.
$renderdata->ai_comment = null;

// 5. Output Generation.
echo $OUTPUT->header();

$page = new \local_comp_report_ext\output\student_report_page($renderdata);
echo $OUTPUT->render($page);

echo $OUTPUT->footer();
