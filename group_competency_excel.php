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
// Excel report generator for Group Competency Analysis report.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/excellib.class.php');

$courseid = required_param('courseid', PARAM_INT);
$groupid  = optional_param('groupid', 0, PARAM_INT);

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

// 1. Retrieve students (students only).
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

// 2. Fetch mapped competencies list - scoped to course.
$competencies = (array) $DB->get_records_sql("
    SELECT DISTINCT c.id, c.shortname
      FROM {competency} c
 LEFT JOIN {qbank_comp_ext_qmap} m ON m.competencyid = c.id AND m.courseid = :courseid1
 LEFT JOIN {local_comp_report_ext_prac} p ON p.competencyid = c.id AND p.courseid = :courseid2
     WHERE m.courseid IS NOT NULL OR p.courseid IS NOT NULL
  ORDER BY c.shortname ASC
", ['courseid1' => $courseid, 'courseid2' => $courseid]);

if (empty($competencies)) {
    throw new moodle_exception('nocompetencies', 'local_comp_report_ext');
}

// 3. Bulk-load student groups and calculate scores.
$usergroups = [];
$groupscores = [];
if (!empty($students)) {
    $studentids = array_keys($students);
    [$insql, $inparams] = $DB->get_in_or_equal($studentids, SQL_PARAMS_NAMED, 'uid');

    $gmrecords = $DB->get_records_sql("
        SELECT gm.id, gm.userid, g.id AS groupid, g.name AS groupname
          FROM {groups_members} gm
          JOIN {groups} g ON g.id = gm.groupid
         WHERE g.courseid = :courseid AND gm.userid $insql
      ORDER BY g.name ASC
    ", array_merge(['courseid' => $courseid], $inparams));

    foreach ($gmrecords as $gm) {
        $usergroups[$gm->userid][] = format_string($gm->groupname);
    }

    $calculator = new \local_comp_report_ext\competency_calculator($courseid);
    $groupscores = $calculator->get_group_scores($studentids);
}

/**
 * Sanitize cell values against CSV/Excel Formula Injection.
 *
 * @param mixed $val
 * @return string
 */
function safe_excel_str($val): string {
    $str = (string)$val;
    if ($str !== '' && in_array($str[0], ['=', '+', '-', '@', "\t", "\r"])) {
        return "'" . $str;
    }
    return $str;
}

// 4. Create Excel Workbook.
$cleanfilename = 'Group_Competency_' . clean_filename(format_string($course->shortname)) . '_' . date('Ymd_His') . '.xlsx';
$workbook = new MoodleExcelWorkbook($cleanfilename);
$worksheet = $workbook->add_worksheet(mb_substr(clean_param(format_string($course->shortname), PARAM_TEXT), 0, 31));

// Formatting styles.
$format_title = $workbook->add_format([
    'bold' => 1,
    'size' => 14,
    'align' => 'left',
    'color' => 'navy',
]);
$format_meta = $workbook->add_format([
    'size' => 10,
    'color' => 'gray',
]);
$format_header = $workbook->add_format([
    'bold' => 1,
    'color' => 'white',
    'bg_color' => 'navy',
    'border' => 1,
    'align' => 'center',
    'valign' => 'vcenter',
]);
$format_header_left = $workbook->add_format([
    'bold' => 1,
    'color' => 'white',
    'bg_color' => 'navy',
    'border' => 1,
    'align' => 'left',
    'valign' => 'vcenter',
]);
$format_cell = $workbook->add_format([
    'border' => 1,
    'align' => 'center',
    'valign' => 'vcenter',
]);
$format_cell_left = $workbook->add_format([
    'border' => 1,
    'align' => 'left',
    'valign' => 'vcenter',
]);
$format_cell_bold = $workbook->add_format([
    'bold' => 1,
    'border' => 1,
    'align' => 'left',
    'valign' => 'vcenter',
]);
$format_total = $workbook->add_format([
    'bold' => 1,
    'bg_color' => 'silver',
    'border' => 1,
    'align' => 'center',
    'valign' => 'vcenter',
]);
$format_total_left = $workbook->add_format([
    'bold' => 1,
    'bg_color' => 'silver',
    'border' => 1,
    'align' => 'left',
    'valign' => 'vcenter',
]);

// Write Title & Metadata.
$row = 0;
$worksheet->write_string($row, 0, format_string($course->fullname) . ' — ' . get_string('groupperformance', 'local_comp_report_ext'), $format_title);
$row++;
$worksheet->write_string($row, 0, get_string('group', 'local_comp_report_ext') . ': ' . $groupname . '  |  ' . userdate(time()), $format_meta);
$row += 2;

// Table Headers.
$col = 0;
$worksheet->write_string($row, $col++, '#', $format_header);
$worksheet->write_string($row, $col++, get_string('student', 'local_comp_report_ext'), $format_header_left);
$worksheet->write_string($row, $col++, get_string('group', 'local_comp_report_ext'), $format_header_left);

foreach ($competencies as $c) {
    $worksheet->write_string($row, $col++, format_string($c->shortname), $format_header);
}
$worksheet->write_string($row, $col++, get_string('averagegrade', 'local_comp_report_ext'), $format_header);
$row++;

// Set initial column widths.
$worksheet->set_column(0, 0, 5);
$worksheet->set_column(1, 1, 30);
$worksheet->set_column(2, 2, 20);
$col_idx = 3;
foreach ($competencies as $c) {
    $worksheet->set_column($col_idx, $col_idx, max(14, mb_strlen(format_string($c->shortname)) + 4));
    $col_idx++;
}
$worksheet->set_column($col_idx, $col_idx, 16);

// Table Rows.
$index = 1;
$grouptotals = [];

foreach ($students as $s) {
    $col = 0;
    $worksheet->write_number($row, $col++, $index++, $format_cell);
    $worksheet->write_string($row, $col++, safe_excel_str(fullname($s)), $format_cell_bold);

    $gtext = !empty($usergroups[$s->id]) ? implode(', ', $usergroups[$s->id]) : '—';
    $worksheet->write_string($row, $col++, safe_excel_str($gtext), $format_cell_left);

    $studentrates = [];
    foreach ($competencies as $c) {
        if (isset($groupscores[$s->id][$c->id])) {
            $rate = (float)$groupscores[$s->id][$c->id];
            $worksheet->write_string($row, $col++, '%' . number_format($rate, 1), $format_cell);

            $grouptotals[$c->id]['sum']   = ($grouptotals[$c->id]['sum'] ?? 0) + $rate;
            $grouptotals[$c->id]['count'] = ($grouptotals[$c->id]['count'] ?? 0) + 1;
            $studentrates[] = $rate;
        } else {
            $worksheet->write_string($row, $col++, '—', $format_cell);
        }
    }

    if (!empty($studentrates)) {
        $stavg = round(array_sum($studentrates) / count($studentrates), 1);
        $worksheet->write_string($row, $col++, '%' . number_format($stavg, 1), $format_cell_bold);
    } else {
        $worksheet->write_string($row, $col++, '—', $format_cell);
    }

    $row++;
}

// Total / Summary Row.
$col = 0;
$worksheet->write_string($row, $col++, '', $format_total);
$worksheet->write_string($row, $col++, get_string('total', 'local_comp_report_ext'), $format_total_left);
$worksheet->write_string($row, $col++, '', $format_total);

$alltotals = [];
foreach ($competencies as $c) {
    $tcount = $grouptotals[$c->id]['count'] ?? 0;
    $tsum   = $grouptotals[$c->id]['sum'] ?? 0;
    if ($tcount > 0) {
        $trate = round($tsum / $tcount, 1);
        $worksheet->write_string($row, $col++, '%' . number_format($trate, 1), $format_total);
        $alltotals[] = $trate;
    } else {
        $worksheet->write_string($row, $col++, '—', $format_total);
    }
}

if (!empty($alltotals)) {
    $grandavg = round(array_sum($alltotals) / count($alltotals), 1);
    $worksheet->write_string($row, $col++, '%' . number_format($grandavg, 1), $format_total);
} else {
    $worksheet->write_string($row, $col++, '—', $format_total);
}

$workbook->close();
