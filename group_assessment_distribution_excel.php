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
 * Excel export for Assessment Distribution Report.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/excellib.class.php');
require_once(__DIR__ . '/lib.php');

$courseid        = required_param('courseid', PARAM_INT);
$groupid         = optional_param('groupid', 0, PARAM_INT);
$selectedasmtids = optional_param_array('assessmentids', [], PARAM_INT);

require_login($courseid);
$context = context_course::instance($courseid);
$canviewext = has_capability('local/comp_report_ext:viewreports', $context);
$canviewold = has_capability('local/competency_report:viewreports', $context);
if (!$canviewext && !$canviewold) {
    require_capability('local/comp_report_ext:viewreports', $context);
}

global $DB;

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
if ($groupid > 0) {
    $grouprow  = $DB->get_record('groups', ['id' => $groupid, 'courseid' => $courseid]);
    $groupname = $grouprow ? format_string($grouprow->name) : get_string('allgroups', 'local_comp_report_ext');
} else {
    $groupname = get_string('allgroups', 'local_comp_report_ext');
}

// 1. Load configured assessments for this course.
$allasmts = $DB->get_records(
    'local_comp_report_ext_asmt',
    ['courseid' => $courseid],
    'id ASC'
);

if (empty($selectedasmtids) && !empty($allasmts)) {
    $selectedasmtids = array_keys($allasmts);
}

$validasmtids = [];
foreach ($selectedasmtids as $aid) {
    if (isset($allasmts[$aid])) {
        $validasmtids[] = (int)$aid;
    }
}

$selectedasmts = [];
foreach ($validasmtids as $aid) {
    $selectedasmts[$aid] = $allasmts[$aid];
}

// 2. Fetch Students.
if ($groupid > 0) {
    $groupobj = $DB->get_record('groups', ['id' => $groupid, 'courseid' => $courseid]);
    $gname = $groupobj ? format_string($groupobj->name) : '';
    $students = (array)$DB->get_records_sql(
        "SELECT u.*, :gname AS groupname
           FROM {groups_members} gm
           JOIN {user} u ON u.id = gm.userid
           JOIN {role_assignments} ra ON ra.userid = u.id
           JOIN {context} ctx ON ctx.id = ra.contextid
          WHERE gm.groupid = :groupid
            AND ctx.instanceid = :courseid
            AND ra.roleid = (SELECT id FROM {role} WHERE shortname = 'student')
       ORDER BY u.idnumber ASC, u.lastname ASC, u.firstname ASC",
        ['groupid' => $groupid, 'courseid' => $courseid, 'gname' => $gname]
    );
} else {
    $students = (array)$DB->get_records_sql(
        "SELECT DISTINCT u.*, g.name AS groupname
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

/**
 * Sanitize cell values against CSV/Excel Formula Injection.
 *
 * Delegates to the canonical lib helper.
 *
 * @param mixed $val
 * @return string
 */
function safe_excel_str($val): string {
    return local_comp_report_ext_safe_excel_str($val);
}

// 3. Create Excel Workbook.
$cleanfilename = 'Assessment_Distribution_' . clean_filename(format_string($course->shortname)) . '_' . date('Ymd_His') . '.xlsx';
$workbook = new MoodleExcelWorkbook($cleanfilename);
$worksheet = $workbook->add_worksheet(mb_substr(clean_param(format_string($course->shortname), PARAM_TEXT), 0, 31));

// Styles.
$formattitle = $workbook->add_format([
    'bold' => 1,
    'size' => 14,
    'align' => 'left',
    'color' => 'navy',
]);
$formatmeta = $workbook->add_format([
    'size' => 10,
    'color' => 'gray',
]);
$formatheader = $workbook->add_format([
    'bold' => 1,
    'color' => 'white',
    'bg_color' => 'navy',
    'border' => 1,
    'align' => 'center',
    'valign' => 'vcenter',
]);
$formatheaderleft = $workbook->add_format([
    'bold' => 1,
    'color' => 'white',
    'bg_color' => 'navy',
    'border' => 1,
    'align' => 'left',
    'valign' => 'vcenter',
]);
$formatcell = $workbook->add_format([
    'border' => 1,
    'align' => 'center',
    'valign' => 'vcenter',
]);
$formatcellleft = $workbook->add_format([
    'border' => 1,
    'align' => 'left',
    'valign' => 'vcenter',
]);
$formatcellbold = $workbook->add_format([
    'bold' => 1,
    'border' => 1,
    'align' => 'left',
    'valign' => 'vcenter',
]);

// Write Metadata.
$row = 0;
$titlestr = format_string($course->fullname) . ' — '
    . get_string('tab_assessment_distribution', 'local_comp_report_ext');
$worksheet->write_string($row, 0, $titlestr, $formattitle);
$row++;

$metastr = get_string('group', 'local_comp_report_ext') . ': '
    . $groupname . '  |  ' . userdate(time());
$worksheet->write_string($row, 0, $metastr, $formatmeta);
$row += 2;

// Headers.
$col = 0;
$worksheet->write_string($row, $col++, get_string('student', 'local_comp_report_ext'), $formatheaderleft);
$worksheet->write_string($row, $col++, get_string('group', 'local_comp_report_ext'), $formatheaderleft);
$worksheet->write_string($row, $col++, get_string('competency', 'local_comp_report_ext'), $formatheaderleft);

foreach ($selectedasmts as $asmt) {
    $asmtlabel = format_string($asmt->name) . ' (' . (float)$asmt->weight . '%)';
    $worksheet->write_string($row, $col++, $asmtlabel, $formatheader);
}
$worksheet->write_string($row, $col++, get_string('weightedtotal', 'local_comp_report_ext'), $formatheader);
$row++;

// Set Column Widths.
$worksheet->set_column(0, 0, 28);
$worksheet->set_column(1, 1, 20);
$worksheet->set_column(2, 2, 24);
$cidx = 3;
foreach ($selectedasmts as $asmt) {
    $worksheet->set_column($cidx, $cidx, max(16, mb_strlen(format_string($asmt->name)) + 6));
    $cidx++;
}
$worksheet->set_column($cidx, $cidx, 18);

// Rows.
foreach ($students as $student) {
    $scores = $calculator->get_student_scores((int)$student->id);
    if (empty($scores)) {
        continue;
    }

    $sname = fullname($student);
    $gname = !empty($student->groupname) ? format_string($student->groupname) : '—';

    foreach ($scores as $compid => $data) {
        $comp = $data['competency'];
        $breakdown = isset($data['breakdown']) ? $data['breakdown'] : [];

        $filteredbreakdown = array_filter($breakdown, function ($b) use ($validasmtids) {
            return isset($b['assessmentid']) && in_array((int)$b['assessmentid'], $validasmtids);
        });

        $totweighted = 0.0;
        $totweight   = 0.0;
        foreach ($filteredbreakdown as $b) {
            $totweighted += (float)$b['weighted_contribution'];
            $totweight   += (float)$b['weight'];
        }
        $totalpercent = ($totweight > 0) ? round(($totweighted / $totweight) * 100.0, 1) : null;

        $col = 0;
        $worksheet->write_string($row, $col++, safe_excel_str($sname), $formatcellbold);
        $worksheet->write_string($row, $col++, safe_excel_str($gname), $formatcellleft);
        $cleancompname = format_string($comp->shortname);
        $worksheet->write_string($row, $col++, safe_excel_str($cleancompname), $formatcellleft);

        foreach ($selectedasmts as $asmt) {
            $foundcell = null;
            foreach ($filteredbreakdown as $b) {
                if (isset($b['assessmentid']) && (int)$b['assessmentid'] === (int)$asmt->id) {
                    $foundcell = $b;
                    break;
                }
            }
            if ($foundcell !== null) {
                $cellval = '%' . number_format((float)$foundcell['score_pct'], 1);
                $worksheet->write_string($row, $col++, $cellval, $formatcell);
            } else {
                $worksheet->write_string($row, $col++, '—', $formatcell);
            }
        }

        if ($totalpercent !== null) {
            $totstr = '%' . number_format($totalpercent, 1);
            $worksheet->write_string($row, $col++, $totstr, $formatcellbold);
        } else {
            $worksheet->write_string($row, $col++, '—', $formatcell);
        }
        $row++;
    }
}

$workbook->close();
