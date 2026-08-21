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
 * Competency distribution report by group and weighted assessment setup.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

// 1. Parameter Acquisition.
$courseid = required_param('courseid', PARAM_INT);
$groupid  = optional_param('groupid', 0, PARAM_INT);
$selectedasmtids = optional_param_array('assessmentids', [], PARAM_INT);

// 2. Security and Access Controls.
require_login($courseid);
$context = context_course::instance($courseid);
$canviewext = has_capability('local/comp_report_ext:viewreports', $context);
$canviewold = has_capability('local/competency_report:viewreports', $context);
if (!$canviewext && !$canviewold) {
    require_capability('local/comp_report_ext:viewreports', $context);
}

// 3. Page Settings.
$PAGE->set_url('/local/comp_report_ext/group_assessment_distribution.php', [
    'courseid' => $courseid,
    'groupid'  => $groupid,
]);
$PAGE->set_title(get_string('groupperformance', 'local_comp_report_ext'));
$PAGE->set_heading(format_string($course->fullname) . ' — ' . get_string('groupperformance', 'local_comp_report_ext'));
$PAGE->set_pagelayout('course');
$PAGE->set_context($context);

global $DB;

// 4. Load configured assessments for this course.
$allasmts = $DB->get_records(
    'local_comp_report_ext_asmt',
    ['courseid' => $courseid],
    'id ASC'
);

$hasconfiguredasmts = !empty($allasmts);

// Filter chosen assessment IDs. If none selected, default to all configured assessments.
if (empty($selectedasmtids) && $hasconfiguredasmts) {
    $selectedasmtids = array_keys($allasmts);
}

// Sanitize selected IDs to ensure they belong to this course.
$validasmtids = [];
foreach ($selectedasmtids as $aid) {
    if (isset($allasmts[$aid])) {
        $validasmtids[] = (int)$aid;
    }
}

// Filtered list of selected assessment objects.
$selectedasmts = [];
foreach ($validasmtids as $aid) {
    $selectedasmts[$aid] = $allasmts[$aid];
}

// 5. Prepare Groups for filter.
$groups = groups_get_all_groups($courseid);
$groupoptions = [
    [
        'id' => 0,
        'name' => get_string('allgroups', 'local_comp_report_ext'),
        'selected' => ($groupid == 0),
    ],
];
if ($groups) {
    foreach ($groups as $g) {
        $groupoptions[] = [
            'id' => $g->id,
            'name' => format_string($g->name),
            'selected' => ($g->id == $groupid),
        ];
    }
}

// Prepare Assessment checkboxes list for filter.
$asmtcheckboxes = [];
foreach ($allasmts as $asmt) {
    $asmtcheckboxes[] = [
        'id'       => (int)$asmt->id,
        'name'     => format_string($asmt->name),
        'weight'   => (float)$asmt->weight,
        'type'     => $asmt->type,
        'checked'  => in_array((int)$asmt->id, $validasmtids),
    ];
}

// 6. Fetch Students and Calculate Scores if assessments are configured.
$rows = [];
$asmtheaders = [];
foreach ($selectedasmts as $asmt) {
    $asmtheaders[] = [
        'id'     => (int)$asmt->id,
        'name'   => format_string($asmt->name),
        'weight' => (float)$asmt->weight,
        'type'   => $asmt->type,
    ];
}

if ($hasconfiguredasmts && !empty($validasmtids)) {
    // Fetch students.
    if ($groupid > 0) {
        $groupobj = $DB->get_record('groups', ['id' => $groupid, 'courseid' => $courseid]);
        $gname = $groupobj ? format_string($groupobj->name) : '';
        $students = (array)$DB->get_records_sql(
            "SELECT u.*, :gname AS groupname, :gid AS student_groupid
               FROM {groups_members} gm
               JOIN {user} u ON u.id = gm.userid
               JOIN {role_assignments} ra ON ra.userid = u.id
               JOIN {context} ctx ON ctx.id = ra.contextid
              WHERE gm.groupid = :groupid
                AND ctx.instanceid = :courseid
                AND ra.roleid = (SELECT id FROM {role} WHERE shortname = 'student')
           ORDER BY u.idnumber ASC, u.lastname ASC, u.firstname ASC",
            ['groupid' => $groupid, 'courseid' => $courseid, 'gname' => $gname, 'gid' => $groupid]
        );
    } else {
        $students = (array)$DB->get_records_sql(
            "SELECT DISTINCT u.*, g.name AS groupname, g.id AS student_groupid
               FROM {groups} g
               JOIN {groups_members} gm ON gm.groupid = g.id
               JOIN {user} u ON u.id = gm.userid
               JOIN {role_assignments} ra ON ra.userid = u.id
               JOIN {context} ctx ON ctx.id = ra.contextid
              WHERE g.courseid = :courseid
                AND ctx.instanceid = :courseid2
                AND ra.roleid = (SELECT id FROM {role} WHERE shortname = 'student')
           ORDER BY g.name ASC, u.idnumber ASC, u.lastname ASC, u.firstname ASC",
            ['courseid' => $courseid, 'courseid2' => $courseid]
        );
    }

    $calculator = new \local_comp_report_ext\competency_calculator($courseid);
    $threshold  = (int)(get_config('local_comp_report_ext', 'success_threshold') ?: 60);

    $studentindex = 0;
    foreach ($students as $student) {
        $scores = $calculator->get_student_scores((int)$student->id);
        if (empty($scores)) {
            continue;
        }

        $comprows = [];
        foreach ($scores as $compid => $data) {
            $breakdown = isset($data['breakdown']) ? $data['breakdown'] : [];

            // Filter breakdown to selected assessments only.
            $filteredbreakdown = array_filter($breakdown, function ($b) use ($validasmtids) {
                return isset($b['assessmentid']) && in_array((int)$b['assessmentid'], $validasmtids);
            });

            // Calculate weighted score for selected assessments.
            $totweighted = 0.0;
            $totweight   = 0.0;
            foreach ($filteredbreakdown as $b) {
                $totweighted += (float)$b['weighted_contribution'];
                $totweight   += (float)$b['weight'];
            }

            $totalpercent = ($totweight > 0) ? round(($totweighted / $totweight) * 100.0, 1) : null;

            // Prepare assessment cells matching $asmtheaders order.
            $asmtcells = [];
            foreach ($selectedasmts as $asmt) {
                $foundcell = null;
                foreach ($filteredbreakdown as $b) {
                    if (isset($b['assessmentid']) && (int)$b['assessmentid'] === (int)$asmt->id) {
                        $foundcell = $b;
                        break;
                    }
                }
                $asmtcells[] = [
                    'score_pct' => $foundcell ? number_format((float)$foundcell['score_pct'], 1) : null,
                    'has_score' => ($foundcell !== null),
                ];
            }

            $ratecolor = ($totalpercent !== null)
                ? \local_comp_report_ext\competency_calculator::rate_color($totalpercent)
                : 'grey';

            $comprows[] = [
                'competencyid'  => $compid,
                'shortname'     => format_string($data['competency']->shortname, true, ['escape' => false]),
                'asmt_cells'    => $asmtcells,
                'total_percent' => ($totalpercent !== null) ? number_format($totalpercent, 1) : null,
                'has_total'     => ($totalpercent !== null),
                'color'         => $ratecolor,
                'passed'        => ($totalpercent !== null && $totalpercent >= $threshold),
            ];
        }

        if (empty($comprows)) {
            continue;
        }

        $studentindex++;
        $iseven = ($studentindex % 2 === 0);

        // Setup Option C simulated rowspan for first row of this student.
        $comprows[0]['first_row']      = true;
        $comprows[0]['studentname']    = fullname($student);
        $comprows[0]['studentlink']    = html_writer::link(
            new moodle_url('/local/comp_report_ext/student_competency_detail.php', [
                'courseid' => $courseid,
                'userid'   => $student->id,
            ]),
            fullname($student),
            ['target' => '_blank']
        );
        $comprows[0]['groupname']      = format_string($student->groupname);
        $comprows[0]['rowspan']        = count($comprows);

        foreach ($comprows as &$cr) {
            $cr['is_even']         = $iseven;
            $cr['show_group_col']  = ($groupid === 0);
        }
        unset($cr);

        $rows = array_merge($rows, $comprows);
    }
}

// 7. Render Page.
$renderdata = new stdClass();
$renderdata->courseid           = $courseid;
$renderdata->groupid            = $groupid;
$renderdata->groups             = $groupoptions;
$renderdata->asmtcheckboxes     = $asmtcheckboxes;
$renderdata->selectedasmtids    = $validasmtids;
$renderdata->validasmtids_json  = json_encode($validasmtids);
$renderdata->asmtheaders        = $asmtheaders;
$renderdata->rows               = $rows;
$renderdata->has_data           = !empty($rows);
$renderdata->show_group_col     = ($groupid === 0);
$renderdata->has_configured_asmts = $hasconfiguredasmts;
$renderdata->context_type       = 'group';

echo $OUTPUT->header();

$page = new \local_comp_report_ext\output\group_assessment_distribution_page($renderdata);
echo $OUTPUT->render($page);

echo $OUTPUT->footer();
