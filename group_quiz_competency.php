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
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @copyright  based on work by 2026 Hakan Ã‡iÄŸci {@link https://hakancigci.com.tr}
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
$PAGE->set_url('/local/comp_report_ext/group_quiz_competency.php', [
    'courseid' => $courseid,
    'groupid'  => $groupid,
    'quizid'   => $quizid,
]);
$PAGE->set_title(get_string('groupquizcompetency', 'local_comp_report_ext'));
$PAGE->set_heading(get_string('groupquizcompetency', 'local_comp_report_ext'));
$PAGE->set_pagelayout('course');
$PAGE->set_context($context);

// 4. Data Preparation Logic.
global $DB;
$renderdata = new stdClass();
$renderdata->courseid = $courseid;

// Prepare Groups for filter.
$groups = groups_get_all_groups($courseid);
$renderdata->groups = [[
    'id' => 0,
    'name' => get_string('allgroups', 'local_comp_report_ext'),
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
    'name' => get_string('selectquiz', 'local_comp_report_ext'),
    'selected' => ($quizid == 0),
]];
foreach ($quizzes as $q) {
    $renderdata->quizzes[] = [
        'id' => $q->id,
        'name' => format_string($q->name),
        'selected' => ($q->id == $quizid),
    ];
}

if ($quizid > 0) {
    // Fetch Students (Filtering by selected group or all enrolled students if groupid=0).
    $students = (array) get_enrolled_users(
        $context,
        '',
        $groupid,
        'u.*',
        'u.idnumber ASC, u.lastname ASC, u.firstname ASC',
        0,
        0,
        true
    );

    $renderdata->has_data = !empty($students);

    if (!empty($students)) {
        // Fetch Competencies linked to the specific quiz questions.
        $competencies = (array)$DB->get_records_sql("
            SELECT DISTINCT c.id, c.shortname
            FROM {quiz_attempts} quiza
            JOIN {question_usages} qu ON qu.id = quiza.uniqueid
            JOIN {question_attempts} qa ON qa.questionusageid = qu.id
            JOIN {qbank_comp_ext_qmap} m ON m.questionid = qa.questionid
            JOIN {competency} c ON c.id = m.competencyid
            WHERE quiza.quiz = :quizid
            ORDER BY c.shortname", ['quizid' => $quizid]);
        $renderdata->competencies = array_values($competencies);

        // Performance Data Calculation.
        $studentids = array_keys($students);
        list($insql, $inparams) = $DB->get_in_or_equal($studentids, SQL_PARAMS_NAMED, 'uid');
        $inparams['quizid'] = $quizid;

        $scoremap = [];
        $rawscores = (array)$DB->get_records_sql("
            SELECT
                CONCAT(quiza.userid, '_', m.competencyid) as unique_key,
                quiza.userid, m.competencyid,
                SUM(qa.maxfraction) AS total_max, SUM(qas.fraction) AS total_fraction
            FROM {quiz_attempts} quiza
            JOIN {question_usages} qu ON qu.id = quiza.uniqueid
            JOIN {question_attempts} qa ON qa.questionusageid = qu.id
            JOIN {qbank_comp_ext_qmap} m ON m.questionid = qa.questionid
            JOIN (
                SELECT questionattemptid, MAX(fraction) AS fraction
                FROM {question_attempt_steps}
                GROUP BY questionattemptid
            ) qas ON qas.questionattemptid = qa.id
            WHERE quiza.quiz = :quizid AND quiza.state = 'finished'
              AND quiza.userid $insql
            GROUP BY quiza.userid, m.competencyid", $inparams);

        foreach ($rawscores as $rs) {
            $scoremap[$rs->userid][$rs->competencyid] = ['att' => $rs->total_max, 'cor' => $rs->total_fraction];
        }

        // Process rows for each student and their competency rates.
        $renderdata->students = [];
        $grouptotals = [];
        foreach ($students as $s) {
            $row = new stdClass();
            $detailurl = new moodle_url('/local/comp_report_ext/student_competency_detail.php', [
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
}

// 5. OUTPUT START.
echo $OUTPUT->header();

$page = new \local_comp_report_ext\output\group_quiz_competency_page($courseid, $groupid, $quizid, $renderdata);
echo $OUTPUT->render($page);

echo $OUTPUT->footer();
