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
 * Report for competency.
 *
 * @package    local_yetkinlik
 * @copyright  2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$groupid = optional_param('groupid', 0, PARAM_INT);

require_login($courseid);
$context = context_course::instance($courseid);
require_capability('local/yetkinlik:viewreports', $context);

// Page definitions and navigation.
$PAGE->set_url('/local/yetkinlik/group_competency.php', ['courseid' => $courseid]);
$PAGE->set_title(get_string('groupcompetency', 'local_yetkinlik'));
$PAGE->set_heading(get_string('groupcompetency', 'local_yetkinlik'));
$PAGE->set_pagelayout('course');
$PAGE->set_context($context);

$renderdata = new stdClass();
$renderdata->courseid = $courseid;
$renderdata->groupid = $groupid;

// 1. Fetch available groups for the selection filter.
$groups = groups_get_all_groups($courseid);
$renderdata->groups = $groups ? array_values($groups) : [];
foreach ($renderdata->groups as $g) {
    $g->selected = ($g->id == $groupid);
}

if ($groupid) {
    global $DB;

    // 2. Retrieve student list (Filtered by the selected group and student role).
    $students = (array) $DB->get_records_sql("
        SELECT u.id, u.idnumber, u.firstname, u.lastname
        FROM {groups_members} gm
        JOIN {user} u ON u.id = gm.userid
        JOIN {role_assignments} ra ON ra.userid = u.id
        JOIN {context} ctx ON ctx.id = ra.contextid
        WHERE gm.groupid = :groupid
          AND ctx.instanceid = :courseid
          AND ra.roleid = (SELECT id FROM {role} WHERE shortname = 'student')
        ORDER BY u.idnumber ASC
    ", ['groupid' => $groupid, 'courseid' => $courseid]);

    // 3. Fetch mapped competencies list.
    $competencies = (array) $DB->get_records_sql("
        SELECT DISTINCT c.id, c.shortname
        FROM {qbank_yetkinlik_qmap} m
        JOIN {competency} c ON c.id = m.competencyid
        ORDER BY c.shortname ASC
    ");
    $renderdata->competencies = array_values($competencies);

    // 4. Performance data query optimized with unique key for easier mapping.
    $scoremap = [];
    $rawscores = (array) $DB->get_records_sql("
        SELECT
            CONCAT(quiza.userid, '_', m.competencyid) as unique_key,
            quiza.userid,
            m.competencyid,
            SUM(qa.maxfraction) AS total_max,
            SUM(qas.fraction) AS total_fraction
        FROM {quiz_attempts} quiza
        JOIN {question_usages} qu ON qu.id = quiza.uniqueid
        JOIN {question_attempts} qa ON qa.questionusageid = qu.id
        JOIN {qbank_yetkinlik_qmap} m ON m.questionid = qa.questionid
        JOIN (
            SELECT questionattemptid, MAX(fraction) AS fraction
            FROM {question_attempt_steps}
            GROUP BY questionattemptid
        ) qas ON qas.questionattemptid = qa.id
        WHERE quiza.state = 'finished'
          AND quiza.userid IN (SELECT userid FROM {groups_members} WHERE groupid = :groupid)
        GROUP BY quiza.userid, m.competencyid
    ", ['groupid' => $groupid]);

    // Construct the score map: $scoremap[userid][competencyid].
    foreach ($rawscores as $rs) {
        $scoremap[$rs->userid][$rs->competencyid] = [
            'att' => (float)$rs->total_max,
            'cor' => (float)$rs->total_fraction,
        ];
    }

    // 5. Prepare student rows and calculate group competency rates for the template.
    $renderdata->students = [];
    $grouptotals = [];

    foreach ($students as $s) {
        $row = new stdClass();
        $detailurl = new moodle_url(
            '/local/yetkinlik/student_competency_detail.php',
            ['courseid' => $courseid, 'userid' => $s->id]
        );
        $row->studentlink = html_writer::link(
            $detailurl,
            fullname($s),
            ['target' => '_blank']
        );
        $row->scores = [];

        foreach ($renderdata->competencies as $c) {
            $scoreobj = new stdClass();

            if (isset($scoremap[$s->id][$c->id])) {
                $att = $scoremap[$s->id][$c->id]['att'];
                $cor = $scoremap[$s->id][$c->id]['cor'];

                if ($att > 0) {
                    $rate = number_format(($cor / $att) * 100, 1);
                    $scoreobj->rate = $rate;

                    // Logic for visual indicator colors based on performance.
                    if ($rate >= 80) {
                        $scoreobj->color = 'green';
                    } else if ($rate >= 60) {
                        $scoreobj->color = 'blue';
                    } else if ($rate >= 40) {
                        $scoreobj->color = 'orange';
                    } else {
                        $scoreobj->color = 'red';
                    }

                    // Aggregate totals for the group average.
                    $grouptotals[$c->id]['att'] = ($grouptotals[$c->id]['att'] ?? 0) + $att;
                    $grouptotals[$c->id]['cor'] = ($grouptotals[$c->id]['cor'] ?? 0) + $cor;
                } else {
                    $scoreobj->rate = null;
                }
            } else {
                $scoreobj->rate = null; // No attempts recorded for this competency.
            }
            $row->scores[] = $scoreobj;
        }
        $renderdata->students[] = $row;
    }

    // 6. Calculate average totals for the report footer.
    $renderdata->totals = [];
    foreach ($renderdata->competencies as $c) {
        $total = new stdClass();
        $tatt = $grouptotals[$c->id]['att'] ?? 0;
        $tcor = $grouptotals[$c->id]['cor'] ?? 0;

        if ($tatt > 0) {
            $trate = number_format(($tcor / $tatt) * 100, 1);
            $total->rate = $trate;
            $total->color = ($trate >= 80) ? 'green' : (($trate >= 60) ? 'blue' : (($trate >= 40) ? 'orange' : 'red'));
        } else {
            $total->rate = null;
        }
        $renderdata->totals[] = $total;
    }
}

// 7. Output rendering.
echo $OUTPUT->header();

$page = new \local_yetkinlik\output\group_competency_page($courseid, $groupid, $renderdata);
echo $OUTPUT->render($page);

echo $OUTPUT->footer();
