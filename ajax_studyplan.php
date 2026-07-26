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
 * AJAX Endpoint to generate a personalized AI remedial study plan (session-based).
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/ai.php');

// 1. Parameters.
$courseid = required_param('courseid', PARAM_INT);
$userid   = optional_param('userid', 0, PARAM_INT);
$language = optional_param('language', 'English', PARAM_ALPHA);
$sessions = optional_param('sessions', 10, PARAM_INT); // Number of 1-hour sessions.

// Clamp sessions to a safe range.
$sessions = max(1, min(60, $sessions));

// Scale max words proportionally: ~60 words per session, min 200, max 1200.
$maxwords = max(200, min(1200, $sessions * 60));

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
    if (
        !has_capability('local/comp_report_ext:viewownreport', $context)
        && !has_capability('local/comp_report_ext:viewreports', $context)
        && !has_capability('local/competency_report:viewownreport', $context)
        && !has_capability('local/competency_report:viewreports', $context)
    ) {
        require_capability('local/comp_report_ext:viewownreport', $context);
    }
}

// 2b. Verify AI is enabled.
if (!get_config('local_comp_report_ext', 'enable_ai')) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => get_string('ai_not_configured', 'local_comp_report_ext')]);
    exit;
}

// 3. Fetch competency rates for this student.
$sql = "SELECT c.id, c.shortname, c.description,
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
        WHERE quiz.course = :courseid AND quiza.userid = :userid AND quiza.state = 'finished'
        GROUP BY c.id, c.shortname, c.description";

$rows = $DB->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $userid]);

if (empty($rows)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => get_string('nodatafound', 'local_comp_report_ext')]);
    exit;
}

// Separate into weak (<60%) and strong (≥60%) competencies.
$weak   = [];
$strong = [];
foreach ($rows as $r) {
    $rate  = $r->questions ? round(($r->correct / $r->questions) * 100, 1) : 0;
    $clean = html_entity_decode(strip_tags($r->description), ENT_QUOTES, 'UTF-8');
    if ($rate < 60) {
        $weak[$r->shortname] = ['desc' => $clean, 'rate' => $rate];
    } else {
        $strong[$r->shortname] = ['desc' => $clean, 'rate' => $rate];
    }
}

$student     = \core_user::get_user($userid);
$studentname = fullname($student);
$course      = $DB->get_record('course', ['id' => $courseid], 'fullname');

// Decide how to label sessions in the prompt.
$weakcount       = count($weak);
$sessionspercomp = ($weakcount > 0) ? (int)ceil($sessions / $weakcount) : $sessions;
// Midpoint session number for milestone checkpoints.
$midpoint = (int)round($sessions / 2);

// 4. Build the session-based study-plan prompt.
$prompt = "You are an expert educational psychologist and pedagogical coach.\n"
    . "Create a highly structured, actionable, personalized remedial study plan for the student "
    . "\"{$studentname}\" enrolled in the course \"{$course->fullname}\".\n\n"
    . "PLAN PARAMETERS:\n"
    . "- Total sessions available: {$sessions} sessions\n"
    . "- Duration per session: 1 hour (60 minutes)\n"
    . "- Each session is an independent 1-hour block to be scheduled by the teacher/student\n\n"
    . "STUDENT PERFORMANCE DATA:\n";

if (!empty($weak)) {
    $prompt .= "\nCOMPETENCIES NEEDING INTENSIVE REMEDIATION (below 60% mastery):\n";
    foreach ($weak as $code => $info) {
        $prompt .= "  - [{$code}] {$info['desc']} — Current mastery: {$info['rate']}%\n";
    }
}
if (!empty($strong)) {
    $prompt .= "\nCOMPETENCIES ALREADY STRONG (above 60% mastery — for review only):\n";
    foreach ($strong as $code => $info) {
        $prompt .= "  - [{$code}] {$info['desc']} — Current mastery: {$info['rate']}%\n";
    }
}

$prompt .= "
STRICT REQUIREMENTS:
1. Write ENTIRELY in {$language}. No preamble, no meta-commentary.
2. Output clean HTML only (headings, lists, and one schedule table).
3. MANDATORY SECTIONS IN THIS ORDER:

   <h4><strong>📊 Performance Summary</strong></h4>
   2 sentences: overall performance diagnosis and main priority.

   <h4><strong>🎯 Priority Focus Areas</strong></h4>
   Ranked bullet list of weak competencies — one sentence per item explaining WHY it is critical.

   <h4><strong>📅 Session-by-Session Study Schedule ({$sessions} Sessions × 1 Hour Each)</strong></h4>
   An HTML <table> with these columns:
   | Session # | Competency Code | Session Goal | Suggested Activities | Time Allocation |
   - Distribute the {$sessions} sessions across ALL weak competencies by priority (weakest gets more sessions).
   - Weaker competencies get more sessions proportionally.
   - Every session must be exactly 1 hour and self-contained (schedulable by the teacher).

   <h4><strong>📝 Learning Strategies per Competency</strong></h4>
   For EACH weak competency: 2-3 specific, named techniques (e.g., spaced repetition, worked examples, retrieval practice).

   <h4><strong>✅ Milestone Checkpoints</strong></h4>
   Define 2-3 measurable checkpoints at Sessions {$midpoint}, and {$sessions} to assess progress.

4. Be SPECIFIC and ACTIONABLE — no generic advice.
5. Maximum {$maxwords} words total.
";

// 5. Call AI with study plan system prompt.
$plan = local_comp_report_ext_generate_study_plan($prompt);

// Convert any markdown tables in the AI response to beautiful HTML tables.
$plan = local_comp_report_ext_markdown_to_html_table($plan);

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'html'    => format_text($plan, FORMAT_HTML, ['context' => $context]),
]);
exit;
