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
 * AJAX Endpoint for on-demand AI pedagogical commentary.
 *
 * @package    local_competency_report
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/ai.php');

// 1. Parameter Validation.
$courseid     = optional_param('courseid', 0, PARAM_INT);
$userid       = optional_param('userid', 0, PARAM_INT);
$customprompt = optional_param('custom_prompt', '', PARAM_RAW);
$contexttype  = optional_param('context_type', 'student', PARAM_ALPHA); // 'student' or 'school'

// 2. Authentication & Access Controls.
require_login();

if ($contexttype === 'school') {
    if ($courseid) {
        $context = context_course::instance($courseid);
        require_capability('moodle/course:view', $context);
    } else {
        $context = context_system::instance();
        require_capability('moodle/site:config', $context);
    }
} else {
    // Student context.
    if (empty($courseid)) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'error' => 'Missing course ID']);
        exit;
    }
    $context = context_course::instance($courseid);
    require_login($courseid);

    // If requesting another student's report, require teacher capability.
    if ($userid && $userid != $USER->id) {
        require_capability('mod/quiz:viewreports', $context);
    } else {
        // Accessing own report.
        if (empty($userid)) {
            $userid = $USER->id;
        }
        require_capability('local/competency_report:viewownreport', $context);
    }
}

// 3. Performance Data Queries.
$rates = [];

if ($contexttype === 'school') {
    if ($courseid) {
        $wheresql = "WHERE quiz.course = :courseid AND quiza.state = 'finished'";
        $params = ['courseid' => $courseid];
    } else {
        $wheresql = "WHERE quiza.state = 'finished'";
        $params = [];
    }

    $sql = "SELECT c.id, c.shortname,
                   SUM(qa.maxfraction) AS attempts,
                   SUM(qas.fraction) AS correct
            FROM {quiz_attempts} quiza
            JOIN {quiz} quiz ON quiz.id = quiza.quiz
            JOIN {question_usages} qu ON qu.id = quiza.uniqueid
            JOIN {question_attempts} qa ON qa.questionusageid = qu.id
            JOIN {qbank_competency_qmap} m ON m.questionid = qa.questionid
            JOIN {competency} c ON c.id = m.competencyid
            JOIN (
                SELECT MAX(fraction) AS fraction, questionattemptid
                FROM {question_attempt_steps}
                GROUP BY questionattemptid
            ) qas ON qas.questionattemptid = qa.id
            $wheresql
            GROUP BY c.id, c.shortname
            ORDER BY c.shortname ASC";

    $rows = $DB->get_records_sql($sql, $params);
    foreach ($rows as $r) {
        $rates[$r->shortname] = $r->attempts ? ($r->correct / $r->attempts) * 100 : 0;
    }
} else {
    // Student stats query.
    $sql = "SELECT c.id, c.shortname,
                   CAST(SUM(qa.maxfraction) AS DECIMAL(12, 1)) AS attempts,
                   CAST(SUM(qas.fraction) AS DECIMAL(12, 1)) AS correct
            FROM {quiz_attempts} quiza
            JOIN {question_usages} qu ON qu.id = quiza.uniqueid
            JOIN {question_attempts} qa ON qa.questionusageid = qu.id
            JOIN {quiz} quiz ON quiz.id = quiza.quiz
            JOIN {qbank_competency_qmap} m ON m.questionid = qa.questionid
            JOIN {competency} c ON c.id = m.competencyid
            JOIN (
                SELECT MAX(fraction) AS fraction, questionattemptid
                FROM {question_attempt_steps}
                GROUP BY questionattemptid
            ) qas ON qas.questionattemptid = qa.id
            WHERE quiz.course = :courseid AND quiza.userid = :userid AND quiza.state = 'finished'
            GROUP BY c.id, c.shortname";

    $rows = $DB->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $userid]);
    foreach ($rows as $r) {
        $rates[$r->shortname] = $r->attempts ? ($r->correct / $r->attempts) * 100 : 0;
    }
}

// 4. Generate AI Commentary.
if (empty($rates)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => get_string('nodatafound', 'local_competency_report')]);
    exit;
}

$comment = local_competency_report_generate_comment($rates, $contexttype, $customprompt);

// 5. JSON Response Output.
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'html' => format_text($comment, FORMAT_HTML)
]);
exit;
