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
 * Unified Course Master Report Page.
 *
 * @package    local_competency_report
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$courseid = required_param('courseid', PARAM_INT);

require_login($courseid);
$context = context_course::instance($courseid);
require_capability('moodle/course:update', $context);

global $DB, $PAGE, $OUTPUT;

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

$PAGE->set_url('/local/competency_report/course_master_report.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_pagelayout('course');
$PAGE->set_title(get_string('coursemasterreport', 'local_competency_report'));
$PAGE->set_heading($course->fullname . " - " . get_string('coursemasterreport', 'local_competency_report'));

$renderdata = new stdClass();
$renderdata->courseid = $courseid;
$renderdata->coursename = $course->fullname;

// 1. Overall Statistics.
$renderdata->stats = new stdClass();
$renderdata->stats->students = $DB->count_records_sql("
    SELECT COUNT(DISTINCT u.id)
    FROM {user} u
    JOIN {role_assignments} ra ON ra.userid = u.id
    JOIN {context} ctx ON ctx.id = ra.contextid
    WHERE ctx.instanceid = :courseid
      AND ra.roleid = (SELECT id FROM {role} WHERE shortname = 'student')
", ['courseid' => $courseid]);

$renderdata->stats->groups = $DB->count_records('groups', ['courseid' => $courseid]);

$renderdata->stats->competencies = $DB->count_records_sql("
    SELECT COUNT(DISTINCT competencyid)
    FROM {qbank_competency_qmap}
    WHERE courseid = :courseid
", ['courseid' => $courseid]);

$renderdata->stats->quizzes = $DB->count_records('quiz', ['course' => $courseid]);

// 2. Exams & General Grades Summary.
$rawquizzes = $DB->get_records_sql("
    SELECT q.id, q.name, AVG(qa.sumgrades) as avggrade, q.sumgrades as maxgrade, COUNT(qa.id) as attempts
    FROM {quiz} q
    LEFT JOIN {quiz_attempts} qa ON qa.quiz = q.id AND qa.state = 'finished'
    WHERE q.course = :courseid
    GROUP BY q.id, q.name, q.sumgrades
    ORDER BY q.name ASC
", ['courseid' => $courseid]);

$renderdata->quizzes = [];
foreach ($rawquizzes as $q) {
    $row = new stdClass();
    $row->name = $q->name;
    $row->attempts = $q->attempts;
    if ($q->attempts > 0 && $q->maxgrade > 0) {
        $rate = ($q->avggrade / $q->maxgrade) * 100;
        $row->rate = number_format($rate, 1);
        $row->score = number_format($q->avggrade, 1) . ' / ' . number_format($q->maxgrade, 1);
        $row->color = ($rate >= 80) ? 'green' : (($rate >= 60) ? 'blue' : (($rate >= 40) ? 'orange' : 'red'));
    } else {
        $row->rate = null;
        $row->score = '-';
        $row->color = 'muted';
    }
    $renderdata->quizzes[] = $row;
}

// 3. Course-Wide Competency rates.
$rawcomps = $DB->get_records_sql("
    SELECT c.id, c.shortname,
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
    WHERE quiz.course = :courseid AND quiza.state = 'finished'
    GROUP BY c.id, c.shortname
    ORDER BY c.shortname ASC
", ['courseid' => $courseid]);

$renderdata->competencies = [];
foreach ($rawcomps as $rc) {
    $row = new stdClass();
    $row->shortname = $rc->shortname;
    $row->attempts = number_format($rc->attempts, 0);
    $row->correct = number_format($rc->correct, 1);
    if ($rc->attempts > 0) {
        $rate = ($rc->correct / $rc->attempts) * 100;
        $row->rate = number_format($rate, 1);
        $row->color = ($rate >= 80) ? 'green' : (($rate >= 60) ? 'blue' : (($rate >= 40) ? 'orange' : 'red'));
    } else {
        $row->rate = null;
        $row->color = 'muted';
    }
    $renderdata->competencies[] = $row;
}

// 4. Group Competency Comparison Matrix.
$compslist = $DB->get_records_sql("
    SELECT DISTINCT c.id, c.shortname
    FROM {qbank_competency_qmap} m
    JOIN {competency} c ON c.id = m.competencyid
    WHERE m.courseid = :courseid
    ORDER BY c.shortname ASC
", ['courseid' => $courseid]);
$renderdata->matrix_headers = array_values($compslist);

$groups = $DB->get_records('groups', ['courseid' => $courseid], 'name ASC');

$groupcompraw = $DB->get_records_sql("
    SELECT
        CONCAT(gm.groupid, '_', m.competencyid) as unique_key,
        gm.groupid,
        m.competencyid,
        SUM(qa.maxfraction) AS total_max,
        SUM(qas.fraction) AS total_fraction
    FROM {quiz_attempts} quiza
    JOIN {question_usages} qu ON qu.id = quiza.uniqueid
    JOIN {question_attempts} qa ON qa.questionusageid = qu.id
    JOIN {qbank_competency_qmap} m ON m.questionid = qa.questionid
    JOIN {groups_members} gm ON gm.userid = quiza.userid
    JOIN (
        SELECT MAX(fraction) AS fraction, questionattemptid
        FROM {question_attempt_steps}
        GROUP BY questionattemptid
    ) qas ON qas.questionattemptid = qa.id
    WHERE quiza.state = 'finished'
      AND quiza.userid IN (
          SELECT userid FROM {groups_members} WHERE groupid IN (SELECT id FROM {groups} WHERE courseid = :courseid)
      )
    GROUP BY gm.groupid, m.competencyid
", ['courseid' => $courseid]);

$groupmap = [];
foreach ($groupcompraw as $gr) {
    $groupmap[$gr->groupid][$gr->competencyid] = [
        'att' => (float)$gr->total_max,
        'cor' => (float)$gr->total_fraction,
    ];
}

$renderdata->matrix_rows = [];
foreach ($groups as $g) {
    $row = new stdClass();
    $row->groupname = $g->name;
    $row->scores = [];
    foreach ($compslist as $c) {
        $scoreobj = new stdClass();
        if (isset($groupmap[$g->id][$c->id])) {
            $att = $groupmap[$g->id][$c->id]['att'];
            $cor = $groupmap[$g->id][$c->id]['cor'];
            if ($att > 0) {
                $rate = ($cor / $att) * 100;
                $scoreobj->rate = number_format($rate, 1);
                $scoreobj->color = ($rate >= 80) ? 'green' : (($rate >= 60) ? 'blue' : (($rate >= 40) ? 'orange' : 'red'));
            } else {
                $scoreobj->rate = null;
                $scoreobj->color = 'muted';
            }
        } else {
            $scoreobj->rate = null;
            $scoreobj->color = 'muted';
        }
        $row->scores[] = $scoreobj;
    }
    $renderdata->matrix_rows[] = $row;
}

// 5. Output rendering.
echo $OUTPUT->header();

$page = new \local_competency_report\output\course_master_report_page($renderdata);
echo $OUTPUT->render($page);

echo $OUTPUT->footer();
