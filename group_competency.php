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
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @copyright  based on work by 2026 Hakan Ã‡iÄŸci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$groupid = optional_param('groupid', 0, PARAM_INT);

require_login($courseid);
$context = context_course::instance($courseid);
$canviewext = has_capability('local/comp_report_ext:viewreports', $context);
$canviewold = has_capability('local/competency_report:viewreports', $context);
if (!$canviewext && !$canviewold) {
    require_capability('local/comp_report_ext:viewreports', $context);
}

// Page definitions and navigation.
$PAGE->set_url('/local/comp_report_ext/group_competency.php', ['courseid' => $courseid]);
$PAGE->set_title(get_string('groupperformance', 'local_comp_report_ext'));
$PAGE->set_heading(format_string($course->fullname) . ' — ' . get_string('groupperformance', 'local_comp_report_ext'));
$PAGE->set_pagelayout('course');
$PAGE->set_context($context);

$renderdata = new stdClass();
$renderdata->courseid = $courseid;
$renderdata->groupid = $groupid;

// 1. Fetch available groups for the selection filter.
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

global $DB;

// 2. Retrieve STUDENTS ONLY — filter by role shortname/archetype 'student' to exclude teachers/trainers.
$studentrole = $DB->get_record('role', ['shortname' => 'student'], 'id');
if (!$studentrole) {
    $studentrole = $DB->get_record('role', ['archetype' => 'student'], 'id');
}
$students = [];
if ($studentrole) {
    $students = (array) get_role_users(
        $studentrole->id,
        $context,
        false,
        'u.*',
        'u.idnumber ASC, u.lastname ASC, u.firstname ASC',
        false,
        ($groupid > 0 ? $groupid : '')
    );
}

$renderdata->has_group = true;

if (!empty($students)) {
    // 3. Fetch mapped competencies list — scoped to this course (both quiz and practical competencies).
    $competencies = (array) $DB->get_records_sql("
        SELECT DISTINCT c.id, c.shortname
          FROM {competency} c
     LEFT JOIN {qbank_comp_ext_qmap} m ON m.competencyid = c.id AND m.courseid = :courseid1
     LEFT JOIN {local_comp_report_ext_prac} p ON p.competencyid = c.id AND p.courseid = :courseid2
         WHERE m.courseid IS NOT NULL OR p.courseid IS NOT NULL
      ORDER BY c.shortname ASC
    ", ['courseid1' => $courseid, 'courseid2' => $courseid]);
    $renderdata->competencies = array_values($competencies);

    // 4. Bulk-load student groups in this course to avoid N+1 queries.
    $studentids = array_keys($students);
    [$insql, $inparams] = $DB->get_in_or_equal($studentids, SQL_PARAMS_NAMED, 'uid');

    $gmrecords = $DB->get_records_sql("
        SELECT gm.id, gm.userid, g.id AS groupid, g.name AS groupname
          FROM {groups_members} gm
          JOIN {groups} g ON g.id = gm.groupid
         WHERE g.courseid = :courseid AND gm.userid $insql
      ORDER BY g.name ASC
    ", array_merge(['courseid' => $courseid], $inparams));

    $usergroups = [];
    foreach ($gmrecords as $gm) {
        $usergroups[$gm->userid][] = format_string($gm->groupname);
    }

    // 5. Use the central competency calculator to calculate consistent scores (respecting weights & practicals).
    $calculator = new \local_comp_report_ext\competency_calculator($courseid);
    $groupscores = $calculator->get_group_scores($studentids);

    // 6. Prepare student rows and calculate group competency rates for the template.
    $renderdata->students = [];
    $grouptotals = [];

    foreach ($students as $s) {
        $row = new stdClass();
        $detailurl = new moodle_url(
            '/local/comp_report_ext/student_competency_detail.php',
            ['courseid' => $courseid, 'userid' => $s->id]
        );
        $row->studentlink = html_writer::link(
            $detailurl,
            fullname($s),
            ['target' => '_blank']
        );
        $row->groupname = !empty($usergroups[$s->id]) ? implode(', ', $usergroups[$s->id]) : '';
        $row->scores = [];

        foreach ($renderdata->competencies as $c) {
            $scoreobj = new stdClass();

            if (isset($groupscores[$s->id][$c->id])) {
                $rate = (float)$groupscores[$s->id][$c->id];
                $scoreobj->rate = number_format($rate, 1);

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

                // Aggregate totals for group average.
                $grouptotals[$c->id]['sum']   = ($grouptotals[$c->id]['sum'] ?? 0) + $rate;
                $grouptotals[$c->id]['count'] = ($grouptotals[$c->id]['count'] ?? 0) + 1;
            } else {
                $scoreobj->rate = null;
            }
            $row->scores[] = $scoreobj;
        }
        $renderdata->students[] = $row;
    }

    // 7. Calculate average totals for the report footer.
    $renderdata->totals = [];
    foreach ($renderdata->competencies as $c) {
        $total = new stdClass();
        $tcount = $grouptotals[$c->id]['count'] ?? 0;
        $tsum   = $grouptotals[$c->id]['sum'] ?? 0;

        if ($tcount > 0) {
            $trate = number_format($tsum / $tcount, 1);
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

$page = new \local_comp_report_ext\output\group_competency_page($courseid, $groupid, $renderdata);
echo $OUTPUT->render($page);

echo $OUTPUT->footer();
