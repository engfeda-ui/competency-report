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
 * Report for competency analysis based on group and quiz selection.
 *
 * @package    local_yetkinlik
 * @copyright  2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

// 1. Parameter Acquisition.
$courseid = required_param('courseid', PARAM_INT);
$groupid  = optional_param('groupid', 0, PARAM_INT);
$quizid   = optional_param('quizid', 0, PARAM_INT);

// 2. Security and Access Controls.
require_login($courseid);
$context = context_course::instance($courseid);
require_capability('mod/quiz:viewreports', $context);

// 3. Page Settings (Must be defined before header output).
$PAGE->set_url('/local/yetkinlik/group_quiz_competency.php', [
    'courseid' => $courseid,
    'groupid'  => $groupid,
    'quizid'   => $quizid,
]);
$PAGE->set_title(get_string('groupquizcompetency', 'local_yetkinlik'));
$PAGE->set_heading(get_string('groupquizcompetency', 'local_yetkinlik'));
$PAGE->set_pagelayout('course');
$PAGE->set_context($context);

// 4. Data Preparation Logic.
$renderdata = new stdClass();
$renderdata->courseid = $courseid;

// Prepare Groups for filter.
$groups = groups_get_all_groups($courseid);
$renderdata->groups = [[
    'id' => 0,
    'name' => get_string('selectgroup', 'local_yetkinlik'),
    'selected' => ($groupid == 0),
]];
foreach ($groups as $g) {
    $renderdata->groups[] = [
        'id' => $g->id,
        'name' => format_string($g->name),
        'selected' => ($g->id == $groupid),
    ];
}

// Prepare Quizzes for filter.
$quizzes = $DB->get_records('quiz', ['course' => $courseid], 'name ASC');
$renderdata->quizzes = [[
    'id' => 0,
    'name' => get_string('selectquiz', 'local_yetkinlik'),
    'selected' => ($quizid == 0),
]];
foreach ($quizzes as $q) {
    $renderdata->quizzes[] = [
        'id' => $q->id,
        'name' => format_string($q->name),
        'selected' => ($q->id == $quizid),
    ];
}

if ($groupid && $quizid) {
    global $DB;

    // Fetch Students (Filtering by selected group and student role).
    $students = (array)$DB->get_records_sql("
        SELECT u.id, u.firstname, u.lastname
        FROM {groups_members} gm
        JOIN {user} u ON u.id = gm.userid
        JOIN {role_assignments} ra ON ra.userid = u.id
        JOIN {context} ctx ON ctx.id = ra.contextid
        WHERE gm.groupid = :groupid
          AND ctx.instanceid = :courseid
          AND ra.roleid = (SELECT id FROM {role} WHERE shortname = 'student')
        ORDER BY u.idnumber ASC", ['groupid' => $groupid, 'courseid' => $courseid]);

    // Fetch Competencies linked to the specific quiz questions.
    $competencies = (array)$DB->get_records_sql("
        SELECT DISTINCT c.id, c.shortname
        FROM {quiz_attempts} quiza
        JOIN {question_usages} qu ON qu.id = quiza.uniqueid
        JOIN {question_attempts} qa ON qa.questionusageid = qu.id
        JOIN {qbank_yetkinlik_qmap} m ON m.questionid = qa.questionid
        JOIN {competency} c ON c.id = m.competencyid
        WHERE quiza.quiz = :quizid
        ORDER BY c.shortname", ['quizid' => $quizid]);
    $renderdata->competencies = array_values($competencies);

    // Performance Data Calculation.
    $scoremap = [];
    $rawscores = (array)$DB->get_records_sql("
        SELECT
            CONCAT(quiza.userid, '_', m.competencyid) as unique_key,
            quiza.userid, m.competencyid,
            SUM(qa.maxfraction) AS total_max, SUM(qas.fraction) AS total_fraction
        FROM {quiz_attempts} quiza
        JOIN {question_usages} qu ON qu.id = quiza.uniqueid
        JOIN {question_attempts} qa ON qa.questionusageid = qu.id
        JOIN {qbank_yetkinlik_qmap} m ON m.questionid = qa.questionid
        JOIN (
            SELECT questionattemptid, MAX(fraction) AS fraction
            FROM {question_attempt_steps}
            GROUP BY questionattemptid
        ) qas ON qas.questionattemptid = qa.id
        WHERE quiza.quiz = :quizid AND quiza.state = 'finished'
        GROUP BY quiza.userid, m.competencyid", ['quizid' => $quizid]);

    foreach ($rawscores as $rs) {
        $scoremap[$rs->userid][$rs->competencyid] = ['att' => $rs->total_max, 'cor' => $rs->total_fraction];
    }

    // Process rows for each student and their competency rates.
    $renderdata->students = [];
    $grouptotals = [];
    foreach ($students as $s) {
        $row = new stdClass();
        $detailurl = new moodle_url('/local/yetkinlik/student_competency_detail.php', [
            'courseid' => $courseid,
            'userid' => $s->id,
        ]);
        $row->studentlink = html_writer::link($detailurl, fullname($s), ['target' => '_blank']);
        $row->scores = [];

        foreach ($renderdata->competencies as $c) {
            $scoreobj = new stdClass();
            if (isset($scoremap[$s->id][$c->id])) {
                $att = $scoremap[$s->id][$c->id]['att'];
                $cor = $scoremap[$s->id][$c->id]['cor'];
                $rate = ($att > 0) ? number_format(($cor / $att) * 100, 1) : 0;
                $scoreobj->rate = $rate;
                $scoreobj->color = ($rate >= 80) ? 'green' : (($rate >= 60) ? 'blue' : (($rate >= 40) ? 'orange' : 'red'));

                $grouptotals[$c->id]['att'] = ($grouptotals[$c->id]['att'] ?? 0) + $att;
                $grouptotals[$c->id]['cor'] = ($grouptotals[$c->id]['cor'] ?? 0) + $cor;
            } else {
                $scoreobj->rate = null;
            }
            $row->scores[] = $scoreobj;
        }
        $renderdata->students[] = $row;
    }

    // Finalize report totals for the table footer.
    $renderdata->totals = [];
    foreach ($renderdata->competencies as $c) {
        $total = new stdClass();
        $totalatt = $grouptotals[$c->id]['att'] ?? 0;
        $totalcor = $grouptotals[$c->id]['cor'] ?? 0;
        if ($totalatt > 0) {
            $trate = number_format(($totalcor / $totalatt) * 100, 1);
            $total->rate = $trate;
            $total->color = ($trate >= 80) ? 'green' : (($trate >= 60) ? 'blue' : (($trate >= 40) ? 'orange' : 'red'));
        } else {
            $total->rate = null;
        }
        $renderdata->totals[] = $total;
    }
}

// 5. OUTPUT START.
echo $OUTPUT->header();

$page = new \local_yetkinlik\output\group_quiz_competency_page($courseid, $groupid, $quizid, $renderdata);
echo $OUTPUT->render($page);

echo $OUTPUT->footer();
