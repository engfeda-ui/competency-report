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
 * @package    local_competency_report
 * @copyright  2026 Mahmoud Salem
 * @copyright  based on work by 2026 Hakan Ã‡iÄŸci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_competency_report\competency_calculator;

$courseid = required_param('courseid', PARAM_INT);
$groupid = optional_param('groupid', 0, PARAM_INT);

require_login($courseid);
$context = context_course::instance($courseid);
require_capability('local/competency_report:viewreports', $context);

// Page definitions and navigation.
$PAGE->set_url('/local/competency_report/group_competency.php', ['courseid' => $courseid]);
$PAGE->set_title(get_string('groupcompetency', 'local_competency_report'));
$PAGE->set_heading(get_string('groupcompetency', 'local_competency_report'));
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

    // 2. Retrieve student list (filtered by the selected group and student role).
    $students = (array) $DB->get_records_sql("
        SELECT u.*
        FROM {groups_members} gm
        JOIN {user} u ON u.id = gm.userid
        JOIN {role_assignments} ra ON ra.userid = u.id
        JOIN {context} ctx ON ctx.id = ra.contextid
        WHERE gm.groupid = :groupid
          AND ctx.instanceid = :courseid
          AND ra.roleid = (SELECT id FROM {role} WHERE shortname = 'student')
        ORDER BY u.idnumber ASC
    ", ['groupid' => $groupid, 'courseid' => $courseid]);

    // 3. Use the weighted calculator to get per-student scores.
    $calculator = new competency_calculator($courseid);
    $userids    = array_keys($students);
    $groupscores = $calculator->get_group_scores($userids); // [userid][compid] = pct

    // 4. Collect competency list from all student scores + question mappings.
    $competencies = (array) $DB->get_records_sql("
        SELECT DISTINCT c.id, c.shortname
        FROM {qbank_competency_qmap} m
        JOIN {competency} c ON c.id = m.competencyid
        WHERE m.courseid = :courseid
        ORDER BY c.shortname ASC
    ", ['courseid' => $courseid]);
    $renderdata->competencies = array_values($competencies);

    // 5. Build student rows using weighted scores.
    $renderdata->students = [];
    $grouptotals = []; // [compid] => [sum, count]

    foreach ($students as $s) {
        $row = new stdClass();
        $detailurl = new moodle_url(
            '/local/competency_report/student_report.php',
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

            if (isset($groupscores[$s->id][$c->id])) {
                $rate = (float)$groupscores[$s->id][$c->id];
                $scoreobj->rate  = number_format($rate, 1);
                $scoreobj->color = competency_calculator::rate_color($rate);

                $grouptotals[$c->id]['sum']   = ($grouptotals[$c->id]['sum']   ?? 0) + $rate;
                $grouptotals[$c->id]['count'] = ($grouptotals[$c->id]['count'] ?? 0) + 1;
            } else {
                $scoreobj->rate  = null;
                $scoreobj->color = 'muted';
            }
            $row->scores[] = $scoreobj;
        }
        $renderdata->students[] = $row;
    }

    // 6. Calculate group averages for the report footer.
    $renderdata->totals = [];
    foreach ($renderdata->competencies as $c) {
        $total = new stdClass();
        if (!empty($grouptotals[$c->id]['count'])) {
            $trate = $grouptotals[$c->id]['sum'] / $grouptotals[$c->id]['count'];
            $total->rate  = number_format($trate, 1);
            $total->color = competency_calculator::rate_color($trate);
        } else {
            $total->rate  = null;
            $total->color = 'muted';
        }
        $renderdata->totals[] = $total;
    }
}

// 7. Output rendering.
echo $OUTPUT->header();

$page = new \local_competency_report\output\group_competency_page($courseid, $groupid, $renderdata);
echo $OUTPUT->render($page);

echo $OUTPUT->footer();
