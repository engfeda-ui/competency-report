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
 * AJAX Endpoint to generate a personalized AI remedial study plan.
 *
 * @package    local_competency_report
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/ai.php');

// 1. Parameters.
$courseid = required_param('courseid', PARAM_INT);
$userid   = optional_param('userid', 0, PARAM_INT);
$language = optional_param('language', 'English', PARAM_ALPHA);

// 2. Auth.
require_login($courseid);
$context = context_course::instance($courseid);
$PAGE->set_context($context);

if (empty($userid)) {
    $userid = $USER->id;
}
if ($userid != $USER->id) {
    require_capability('mod/quiz:viewreports', $context);
} else {
    require_capability('local/competency_report:viewownreport', $context);
}

// 3. Fetch competency rates for this student.
$sql = "SELECT c.id, c.shortname, c.description,
               CAST(SUM(qa.maxfraction) AS DECIMAL(12,1)) AS questions,
               CAST(SUM(qas.fraction) AS DECIMAL(12,1)) AS correct
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
        GROUP BY c.id, c.shortname, c.description";

$rows = $DB->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $userid]);

if (empty($rows)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => get_string('nodatafound', 'local_competency_report')]);
    exit;
}

// Separate into weak (<60%) and strong (≥60%) competencies.
$weak = [];
$strong = [];
foreach ($rows as $r) {
    $rate = $r->questions ? round(($r->correct / $r->questions) * 100, 1) : 0;
    $clean = html_entity_decode(strip_tags($r->description), ENT_QUOTES, 'UTF-8');
    if ($rate < 60) {
        $weak[$r->shortname] = ['desc' => $clean, 'rate' => $rate];
    } else {
        $strong[$r->shortname] = ['desc' => $clean, 'rate' => $rate];
    }
}

$student = $DB->get_record('user', ['id' => $userid], 'firstname, lastname');
$studentname = fullname($student);
$course = $DB->get_record('course', ['id' => $courseid], 'fullname');

// 4. Build an enriched study-plan prompt.
$prompt = "You are an expert educational psychologist and pedagogical coach.
Create a highly structured, actionable, personalized 2-week remedial study plan for the student \"{$studentname}\" enrolled in the course \"{$course->fullname}\".

STUDENT PERFORMANCE DATA:
";

if (!empty($weak)) {
    $prompt .= "\nCOMPETENCIES NEEDING INTENSIVE WORK (below 60%):\n";
    foreach ($weak as $code => $info) {
        $prompt .= "- {$code}: {$info['desc']} — Current mastery: {$info['rate']}%\n";
    }
}
if (!empty($strong)) {
    $prompt .= "\nCOMPETENCIES ALREADY STRONG (above 60%):\n";
    foreach ($strong as $code => $info) {
        $prompt .= "- {$code}: {$info['desc']} — Current mastery: {$info['rate']}%\n";
    }
}

$prompt .= "
STRICT REQUIREMENTS FOR THE STUDY PLAN:
1. Write DIRECTLY in {$language}. Do NOT include any preamble.
2. Format: Clean HTML with structured headings, bullet lists, and a weekly schedule table.
3. Structure MUST include:
   - <h4><strong>📊 Performance Summary</strong></h4>: Brief 2-sentence overview.
   - <h4><strong>🎯 Priority Focus Areas</strong></h4>: Ranked weak competencies with why they matter.
   - <h4><strong>📅 2-Week Study Schedule</strong></h4>: An HTML <table> with Day | Focus Area | Activity Type | Duration.
   - <h4><strong>📝 Study Techniques per Competency</strong></h4>: Specific technique for each weak area.
   - <h4><strong>✅ Success Milestones</strong></h4>: Clear measurable targets for end of week 1 and week 2.
4. Be extremely specific and actionable — no vague advice.
5. Maximum 350 words. Be concise and direct.
";

// 5. Call AI with study plan system prompt.
$plan = local_competency_report_generate_study_plan($prompt);

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'html'    => format_text($plan, FORMAT_HTML, ['context' => $context]),
]);
exit;
