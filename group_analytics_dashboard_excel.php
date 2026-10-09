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
 * Excel export for Group Analytics Dashboard.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/excellib.class.php');
require_once(__DIR__ . '/lib.php');

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

// 1. Retrieve students.
if ($groupid > 0) {
    $students = $DB->get_records_sql(
        "SELECT DISTINCT u.id, u.firstname, u.lastname
           FROM {groups_members} gm
           JOIN {user} u ON u.id = gm.userid
           JOIN {role_assignments} ra ON ra.userid = u.id
           JOIN {context} ctx ON ctx.id = ra.contextid
          WHERE gm.groupid = :groupid
            AND ctx.instanceid = :courseid
            AND ra.roleid = (SELECT id FROM {role} WHERE shortname = 'student')
            AND u.deleted = 0
          ORDER BY u.lastname, u.firstname ASC",
        ['groupid' => $groupid, 'courseid' => $courseid]
    );
} else {
    $students = $DB->get_records_sql(
        "SELECT DISTINCT u.id, u.firstname, u.lastname
           FROM {role_assignments} ra
           JOIN {role} r ON r.id = ra.roleid
           JOIN {context} ctx ON ctx.id = ra.contextid
           JOIN {user} u ON u.id = ra.userid
          WHERE ctx.instanceid = :courseid
            AND ctx.contextlevel = 50
            AND r.shortname = 'student'
            AND u.deleted = 0
          ORDER BY u.lastname, u.firstname ASC",
        ['courseid' => $courseid]
    );
}

$calculator = new \local_comp_report_ext\competency_calculator($courseid);
if (!empty($students)) {
    $calculator->preload_user_data(array_map('intval', array_keys($students)));
}

$compscores = [];
$studentaverages = [];
$studentlist = [];
$threshold = (int)(get_config('local_comp_report_ext', 'success_threshold') ?: 60);

foreach ($students as $student) {
    $scores = $calculator->get_student_scores((int)$student->id);
    if (empty($scores)) {
        continue;
    }

    $studentsum = 0.0;
    $studentcount = 0;

    foreach ($scores as $compid => $data) {
        $shortname = clean_param(strip_tags($data['competency']->shortname), PARAM_TEXT);
        $compscores[$compid]['shortname'] = $shortname;
        $compscores[$compid]['scores'][]  = (float)$data['percent'];

        $studentsum += (float)$data['percent'];
        $studentcount++;
    }

    if ($studentcount > 0) {
        $avgpct = round($studentsum / $studentcount, 1);
        $studentaverages[] = $avgpct;

        if ($avgpct < 40) {
            $tier = get_string('critical_tier', 'local_comp_report_ext') ?: 'Critical (< 40%)';
        } else if ($avgpct < 60) {
            $tier = get_string('developing_tier', 'local_comp_report_ext') ?: 'Developing (40-59%)';
        } else if ($avgpct < 80) {
            $tier = get_string('proficient_tier', 'local_comp_report_ext') ?: 'Proficient (60-79%)';
        } else {
            $tier = get_string('exemplary_tier', 'local_comp_report_ext') ?: 'Exemplary (80-100%)';
        }

        $studentlist[] = [
            'name'       => fullname($student),
            'avgpct'     => $avgpct,
            'tier'       => $tier,
            'remediate'  => ($avgpct < $threshold) ? get_string('yes') : get_string('no'),
        ];
    }
}

// Aggregated KPIs.
$totalstudents = count($studentaverages);
$cohortavg = ($totalstudents > 0) ? round(array_sum($studentaverages) / $totalstudents, 1) : 0.0;
$remediationcount = 0;
foreach ($studentaverages as $avg) {
    if ($avg < $threshold) {
        $remediationcount++;
    }
}
$remediationpct = ($totalstudents > 0) ? round(($remediationcount / $totalstudents) * 100, 1) : 0.0;

$compstats = [];
foreach ($compscores as $cid => $cdata) {
    $scores = $cdata['scores'];
    $cavg = !empty($scores) ? round(array_sum($scores) / count($scores), 1) : 0.0;
    $compstats[] = [
        'shortname' => $cdata['shortname'],
        'avg'       => $cavg,
        'count'     => count($scores),
    ];
}
usort($compstats, function ($a, $b) {
    return $b['avg'] <=> $a['avg'];
});

$topstrength = !empty($compstats) ? $compstats[0]['shortname'] . ' (' . $compstats[0]['avg'] . '%)' : '—';
$criticalgap = !empty($compstats) ? end($compstats)['shortname'] . ' (' . end($compstats)['avg'] . '%)' : '—';

/**
 * Sanitize string for Excel output.
 *
 * @param mixed $str
 * @return string
 */
function safe_excel_str($str): string {
    return clean_param(strip_tags((string)$str), PARAM_TEXT);
}

$filename = clean_filename('Group_Analytics_' . $course->shortname . '_' . date('Ymd_His') . '.xlsx');
$workbook = new MoodleExcelWorkbook($filename);

$formattitle = $workbook->add_format(['bold' => 1, 'size' => 14, 'align' => 'left']);
$formatmeta  = $workbook->add_format(['italic' => 1, 'size' => 10, 'color' => 'gray']);
$formatheader = $workbook->add_format([
    'bold' => 1,
    'bg_color' => 'navy',
    'color' => 'white',
    'border' => 1,
    'align' => 'center',
    'valign' => 'vcenter',
]);
$formatheaderleft = $workbook->add_format([
    'bold' => 1,
    'bg_color' => 'navy',
    'color' => 'white',
    'border' => 1,
    'align' => 'left',
    'valign' => 'vcenter',
]);
$formatcell = $workbook->add_format(['border' => 1, 'align' => 'center', 'valign' => 'vcenter']);
$formatcellleft = $workbook->add_format(['border' => 1, 'align' => 'left', 'valign' => 'vcenter']);
$formatcellbold = $workbook->add_format(['bold' => 1, 'border' => 1, 'align' => 'left', 'valign' => 'vcenter']);
$formatkpilbl = $workbook->add_format(['bold' => 1, 'bg_color' => 'cyan', 'border' => 1, 'align' => 'center']);
$formatkpival = $workbook->add_format(['bold' => 1, 'size' => 12, 'border' => 1, 'align' => 'center']);

// -------------------------------------------------------------
// Sheet 1: Dashboard & Competencies.
$ws1 = $workbook->add_worksheet('KPIs & Competencies');
$titletext1 = safe_excel_str($course->fullname) . ' — ' .
    get_string('group_analytics_dashboard', 'local_comp_report_ext');
$ws1->write_string(0, 0, $titletext1, $formattitle);

$metatext1 = get_string('group', 'local_comp_report_ext') . ': ' .
    safe_excel_str($groupname) . ' | ' . userdate(time());
$ws1->write_string(1, 0, $metatext1, $formatmeta);

// KPI Overview.
$kpiavg = get_string('kpi_average_mastery', 'local_comp_report_ext') ?: 'Average Mastery';
$ws1->write_string(3, 0, $kpiavg, $formatkpilbl);

$kpiremed = get_string('kpi_remediation_rate', 'local_comp_report_ext') ?: 'Needs Support Rate';
$ws1->write_string(3, 1, $kpiremed, $formatkpilbl);

$kpitop = get_string('kpi_top_strength', 'local_comp_report_ext') ?: 'Top Strength';
$ws1->write_string(3, 2, $kpitop, $formatkpilbl);

$kpicrit = get_string('kpi_critical_gap', 'local_comp_report_ext') ?: 'Critical Gap';
$ws1->write_string(3, 3, $kpicrit, $formatkpilbl);

$ws1->write_string(4, 0, $cohortavg . '%', $formatkpival);
$remedstat = $remediationpct . '% (' . $remediationcount . '/' . $totalstudents . ')';
$ws1->write_string(4, 1, $remedstat, $formatkpival);
$ws1->write_string(4, 2, safe_excel_str($topstrength), $formatkpival);
$ws1->write_string(4, 3, safe_excel_str($criticalgap), $formatkpival);

// Competencies Ranking Table.
$row = 6;
$ws1->write_string($row, 0, '#', $formatheader);
$ws1->write_string($row, 1, get_string('competencycode', 'local_comp_report_ext'), $formatheaderleft);
$countheader = get_string('students_count', 'local_comp_report_ext') ?: 'Students Assessed';
$ws1->write_string($row, 2, $countheader, $formatheader);
$ws1->write_string($row, 3, get_string('averagegrade', 'local_comp_report_ext'), $formatheader);
$row++;

$ws1->set_column(0, 0, 5);
$ws1->set_column(1, 1, 30);
$ws1->set_column(2, 3, 20);

$idx = 1;
foreach ($compstats as $cs) {
    $ws1->write_number($row, 0, $idx++, $formatcell);
    $ws1->write_string($row, 1, safe_excel_str($cs['shortname']), $formatcellbold);
    $ws1->write_number($row, 2, (int)$cs['count'], $formatcell);
    $ws1->write_string($row, 3, $cs['avg'] . '%', $formatcell);
    $row++;
}

// -------------------------------------------------------------
// Sheet 2: Student Roster.
$ws2 = $workbook->add_worksheet('Student Roster');
$titletext2 = safe_excel_str($course->fullname) . ' — ' . get_string('students', 'local_comp_report_ext');
$ws2->write_string(0, 0, $titletext2, $formattitle);

$metatext2 = get_string('group', 'local_comp_report_ext') . ': ' .
    safe_excel_str($groupname) . ' | ' . userdate(time());
$ws2->write_string(1, 0, $metatext2, $formatmeta);

$row = 3;
$ws2->write_string($row, 0, '#', $formatheader);
$ws2->write_string($row, 1, get_string('student', 'local_comp_report_ext'), $formatheaderleft);
$ws2->write_string($row, 2, get_string('averagegrade', 'local_comp_report_ext'), $formatheader);
$tierheader = get_string('performance_tier', 'local_comp_report_ext') ?: 'Performance Tier';
$ws2->write_string($row, 3, $tierheader, $formatheaderleft);
$remedheader = get_string('needs_remediation', 'local_comp_report_ext') ?: 'Needs Support';
$ws2->write_string($row, 4, $remedheader, $formatheader);
$row++;

$ws2->set_column(0, 0, 5);
$ws2->set_column(1, 1, 30);
$ws2->set_column(2, 4, 20);

$idx = 1;
foreach ($studentlist as $st) {
    $ws2->write_number($row, 0, $idx++, $formatcell);
    $ws2->write_string($row, 1, safe_excel_str($st['name']), $formatcellbold);
    $ws2->write_string($row, 2, $st['avgpct'] . '%', $formatcell);
    $ws2->write_string($row, 3, safe_excel_str($st['tier']), $formatcellleft);
    $ws2->write_string($row, 4, safe_excel_str($st['remediate']), $formatcell);
    $row++;
}

$workbook->close();
exit;
