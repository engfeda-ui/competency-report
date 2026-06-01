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
$groupid      = optional_param('groupid', 0, PARAM_INT);
$quizid       = optional_param('quizid', 0, PARAM_INT);
$customprompt = optional_param('custom_prompt', '', PARAM_TEXT);

$contexttype  = optional_param('context_type', 'student', PARAM_ALPHA); // 'student', 'school', 'group', 'quiz'
$focustype    = optional_param('focus_type', 'competency', PARAM_ALPHA); // 'competency', 'grades'

// 2. Authentication & Access Controls.
require_login();

if ($contexttype === 'school') {
    if ($courseid) {
        $context = context_course::instance($courseid);
        $PAGE->set_context($context);
        require_capability('moodle/course:view', $context);
    } else {
        $context = context_system::instance();
        $PAGE->set_context($context);
        require_capability('moodle/site:config', $context);
    }
} else if ($contexttype === 'group') {
    if (empty($courseid) || empty($groupid)) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'error' => 'Missing course or group parameters']);
        exit;
    }
    $context = context_course::instance($courseid);
    $PAGE->set_context($context);
    require_capability('mod/quiz:viewreports', $context);
} else if ($contexttype === 'quiz') {
    if (empty($courseid) || empty($quizid)) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'error' => 'Missing course or quiz parameters']);
        exit;
    }
    $quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('quiz', $quiz->id, $courseid, false, MUST_EXIST);
    $context = context_module::instance($cm->id);
    $PAGE->set_context($context);
    require_login($courseid, false, $cm);

    // If viewing own quiz report vs teacher viewing class performance.
    if ($userid && $userid != $USER->id) {
        require_capability('mod/quiz:viewreports', $context);
    }
} else {
    // Student context.
    if (empty($courseid)) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'error' => 'Missing course ID']);
        exit;
    }
    $context = context_course::instance($courseid);
    $PAGE->set_context($context);
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

if ($focustype === 'grades') {
    // GENERAL GRADES MODE.
    if ($contexttype === 'school') {
        $sql = "SELECT q.id, q.name, AVG(qa.grade) as avggrade, q.grade as maxgrade
                FROM {quiz_attempts} qa
                JOIN {quiz} q ON q.id = qa.quiz
                WHERE q.course = :courseid AND qa.state = 'finished'
                GROUP BY q.id, q.name, q.grade";
        $rows = $DB->get_records_sql($sql, ['courseid' => $courseid]);
        foreach ($rows as $r) {
            $rates[$r->name] = ($r->maxgrade > 0) ? ($r->avggrade / $r->maxgrade) * 100 : 0;
        }
    } else if ($contexttype === 'group') {
        $sql = "SELECT q.id, q.name, AVG(qa.grade) as avggrade, q.grade as maxgrade
                FROM {quiz_attempts} qa
                JOIN {quiz} q ON q.id = qa.quiz
                JOIN {groups_members} gm ON gm.userid = qa.userid
                WHERE q.course = :courseid AND gm.groupid = :groupid AND qa.state = 'finished'
                GROUP BY q.id, q.name, q.grade";
        $rows = $DB->get_records_sql($sql, ['courseid' => $courseid, 'groupid' => $groupid]);
        foreach ($rows as $r) {
            $rates[$r->name] = ($r->maxgrade > 0) ? ($r->avggrade / $r->maxgrade) * 100 : 0;
        }
    } else if ($contexttype === 'quiz') {
        if ($userid) {
            $sql = "SELECT quiza.id, quiz.name, quiza.grade, quiz.grade as maxgrade
                    FROM {quiz_attempts} quiza
                    JOIN {quiz} quiz ON quiz.id = quiza.quiz
                    WHERE quiza.quiz = :quizid AND quiza.userid = :userid AND quiza.state = 'finished'";
            $rows = $DB->get_records_sql($sql, ['quizid' => $quizid, 'userid' => $userid]);
            foreach ($rows as $r) {
                $rates["Your score on " . $r->name] = ($r->maxgrade > 0) ? ($r->grade / $r->maxgrade) * 100 : 0;
            }
        } else {
            $sql = "SELECT 1 as id, AVG(qa.grade) as avggrade, MAX(qa.grade) as maxgrade, MIN(qa.grade) as mingrade
                    FROM {quiz_attempts} qa
                    WHERE qa.quiz = :quizid AND qa.state = 'finished'";
            $rows = $DB->get_records_sql($sql, ['quizid' => $quizid]);
            $quiz = $DB->get_record('quiz', ['id' => $quizid], 'grade', MUST_EXIST);
            foreach ($rows as $r) {
                if ($quiz->grade > 0) {
                    $rates["Class average grade"] = ($r->avggrade / $quiz->grade) * 100;
                    $rates["Highest score in class"] = ($r->maxgrade / $quiz->grade) * 100;
                    $rates["Lowest score in class"] = ($r->mingrade / $quiz->grade) * 100;
                }
            }
        }
    } else {
        // student context.
        $sql = "SELECT q.id, q.name, qa.grade, q.grade as maxgrade
                FROM {quiz_attempts} qa
                JOIN {quiz} q ON q.id = qa.quiz
                WHERE q.course = :courseid AND qa.userid = :userid AND qa.state = 'finished'";
        $rows = $DB->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $userid]);
        foreach ($rows as $r) {
            $rates[$r->name] = ($r->maxgrade > 0) ? ($r->grade / $r->maxgrade) * 100 : 0;
        }
    }
} else {
    // COMPETENCY ACHIEVEMENTS MODE.
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
    } else if ($contexttype === 'group') {
        $sql = "SELECT c.id, c.shortname,
                       SUM(qa.maxfraction) AS attempts,
                       SUM(qas.fraction) AS correct
                FROM {quiz_attempts} quiza
                JOIN {quiz} quiz ON quiz.id = quiza.quiz
                JOIN {question_usages} qu ON qu.id = quiza.uniqueid
                JOIN {question_attempts} qa ON qa.questionusageid = qu.id
                JOIN {qbank_competency_qmap} m ON m.questionid = qa.questionid
                JOIN {competency} c ON c.id = m.competencyid
                JOIN {groups_members} gm ON gm.userid = quiza.userid
                JOIN (
                    SELECT MAX(fraction) AS fraction, questionattemptid
                    FROM {question_attempt_steps}
                    GROUP BY questionattemptid
                ) qas ON qas.questionattemptid = qa.id
                WHERE quiz.course = :courseid AND gm.groupid = :groupid AND quiza.state = 'finished'
                GROUP BY c.id, c.shortname";

        $rows = $DB->get_records_sql($sql, ['courseid' => $courseid, 'groupid' => $groupid]);
        foreach ($rows as $r) {
            $rates[$r->shortname] = $r->attempts ? ($r->correct / $r->attempts) * 100 : 0;
        }
    } else if ($contexttype === 'quiz') {
        if ($userid) {
            $sql = "SELECT c.id, c.shortname,
                           CAST(SUM(qa.maxfraction) AS DECIMAL(12, 1)) AS attempts,
                           CAST(SUM(qas.fraction) AS DECIMAL(12, 1)) AS correct
                    FROM {quiz_attempts} quiza
                    JOIN {question_usages} qu ON qu.id = quiza.uniqueid
                    JOIN {question_attempts} qa ON qa.questionusageid = qu.id
                    JOIN {qbank_competency_qmap} m ON m.questionid = qa.questionid
                    JOIN {competency} c ON c.id = m.competencyid
                    JOIN (
                        SELECT MAX(fraction) AS fraction, questionattemptid
                        FROM {question_attempt_steps}
                        GROUP BY questionattemptid
                    ) qas ON qas.questionattemptid = qa.id
                    WHERE quiza.quiz = :quizid AND quiza.userid = :userid AND quiza.state = 'finished'
                    GROUP BY c.id, c.shortname";
            $rows = $DB->get_records_sql($sql, ['quizid' => $quizid, 'userid' => $userid]);
        } else {
            $sql = "SELECT c.id, c.shortname,
                           SUM(qa.maxfraction) AS attempts,
                           SUM(qas.fraction) AS correct
                    FROM {quiz_attempts} quiza
                    JOIN {question_usages} qu ON qu.id = quiza.uniqueid
                    JOIN {question_attempts} qa ON qa.questionusageid = qu.id
                    JOIN {qbank_competency_qmap} m ON m.questionid = qa.questionid
                    JOIN {competency} c ON c.id = m.competencyid
                    JOIN (
                        SELECT MAX(fraction) AS fraction, questionattemptid
                        FROM {question_attempt_steps}
                        GROUP BY questionattemptid
                    ) qas ON qas.questionattemptid = qa.id
                    WHERE quiza.quiz = :quizid AND quiza.state = 'finished'
                    GROUP BY c.id, c.shortname";
            $rows = $DB->get_records_sql($sql, ['quizid' => $quizid]);
        }
        foreach ($rows as $r) {
            $rates[$r->shortname] = $r->attempts ? ($r->correct / $r->attempts) * 100 : 0;
        }
    } else {
        // student competency stats.
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
}

// 4. Generate AI Commentary.
if (empty($rates)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => get_string('nodatafound', 'local_competency_report')]);
    exit;
}

$comment = local_competency_report_generate_comment($rates, $contexttype, $customprompt, $focustype);

// 5. JSON Response Output.
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'html' => format_text($comment, FORMAT_HTML, ['context' => $context])
]);
exit;
