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
 * PDF export for the Group Analytics Dashboard.
 *
 * Generates a structured TCPDF report summarising the KPI overview
 * (average mastery, remediation rate, top strength, critical gap),
 * competency averages table, mastery distribution, and optional
 * AI pedagogical commentary.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/tcpdf/tcpdf.php');
require_once(__DIR__ . '/lib.php');

$courseid   = required_param('courseid', PARAM_INT);
$groupid    = optional_param('groupid', 0, PARAM_INT);
$pdfcontent = optional_param('pdf_content', '', PARAM_RAW);

require_login($courseid);
$context    = context_course::instance($courseid);
$canviewext = has_capability('local/comp_report_ext:viewreports', $context);
$canviewold = has_capability('local/competency_report:viewreports', $context);
if (!$canviewext && !$canviewold) {
    require_capability('local/comp_report_ext:viewreports', $context);
}

global $DB;

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

// Resolve group name (or "All Groups").
if ($groupid > 0) {
    $grouprow  = $DB->get_record('groups', ['id' => $groupid, 'courseid' => $courseid]);
    $groupname = $grouprow ? format_string($grouprow->name) : get_string('allgroups', 'local_comp_report_ext');
} else {
    $groupname = get_string('allgroups', 'local_comp_report_ext');
}

// -----------------------------------------------------------------------
// Recalculate the same KPI data as group_analytics_dashboard.php.
// -----------------------------------------------------------------------
if ($groupid > 0) {
    $students = $DB->get_records_sql("
        SELECT DISTINCT u.id, u.firstname, u.lastname
          FROM {groups_members} gm
          JOIN {user} u ON u.id = gm.userid
          JOIN {role_assignments} ra ON ra.userid = u.id
          JOIN {context} ctx ON ctx.id = ra.contextid
         WHERE gm.groupid = :groupid
           AND ctx.instanceid = :courseid
           AND ra.roleid = (SELECT id FROM {role} WHERE shortname = 'student')
           AND u.deleted = 0",
        ['groupid' => $groupid, 'courseid' => $courseid]
    );
} else {
    $students = $DB->get_records_sql("
        SELECT DISTINCT u.id, u.firstname, u.lastname
          FROM {role_assignments} ra
          JOIN {role} r ON r.id = ra.roleid
          JOIN {context} ctx ON ctx.id = ra.contextid
          JOIN {user} u ON u.id = ra.userid
         WHERE ctx.instanceid = :courseid
           AND ctx.contextlevel = 50
           AND r.shortname = 'student'
           AND u.deleted = 0",
        ['courseid' => $courseid]
    );
}

$calculator              = new \local_comp_report_ext\competency_calculator($courseid);
$compscores              = [];
$studentoverallaverages  = [];
$threshold = (int)(get_config('local_comp_report_ext', 'success_threshold') ?: 60);

foreach ($students as $student) {
    $scores = $calculator->get_student_scores((int)$student->id);
    if (empty($scores)) {
        continue;
    }

    $studentsum   = 0.0;
    $studentcount = 0;

    foreach ($scores as $compid => $data) {
        $compscores[$compid]['shortname'] = html_entity_decode(
            format_string($data['competency']->shortname),
            ENT_QUOTES,
            'UTF-8'
        );
        $compscores[$compid]['scores'][] = (float)$data['percent'];

        $studentsum += (float)$data['percent'];
        $studentcount++;
    }

    if ($studentcount > 0) {
        $studentoverallaverages[] = $studentsum / $studentcount;
    }
}

$hasdata            = !empty($studentoverallaverages);
$avgmastery         = 0.0;
$remediationpercent = 0.0;
$topstrength        = '—';
$criticalgap        = '—';
$distribution       = ['critical' => 0, 'developing' => 0, 'proficient' => 0, 'exemplary' => 0];
$compaverages       = [];

if ($hasdata) {
    $avgmastery         = round(
        array_sum($studentoverallaverages) / count($studentoverallaverages),
        1
    );

    $remediationcount = 0;
    foreach ($studentoverallaverages as $avg) {
        if ($avg < $threshold) {
            $remediationcount++;
        }
    }
    $remediationpercent = round(
        ($remediationcount / count($studentoverallaverages)) * 100,
        1
    );

    foreach ($compscores as $compid => $cdata) {
        $avgscore                  = round(array_sum($cdata['scores']) / count($cdata['scores']), 1);
        $compaverages[$compid] = [
            'shortname' => $cdata['shortname'],
            'average'   => $avgscore,
        ];
    }
    uasort($compaverages, function ($a, $b) {
        return $a['average'] <=> $b['average'];
    });

    if (!empty($compaverages)) {
        $keys      = array_keys($compaverages);
        $firstcomp = $compaverages[$keys[0]];
        $lastcomp  = $compaverages[$keys[count($keys) - 1]];

        $criticalgap = html_entity_decode($firstcomp['shortname'], ENT_QUOTES, 'UTF-8')
            . ' (' . number_format($firstcomp['average'], 1) . '%)';
        $topstrength = html_entity_decode($lastcomp['shortname'], ENT_QUOTES, 'UTF-8')
            . ' (' . number_format($lastcomp['average'], 1) . '%)';
    }

    foreach ($studentoverallaverages as $avg) {
        if ($avg < 40) {
            $distribution['critical']++;
        } else if ($avg < 60) {
            $distribution['developing']++;
        } else if ($avg < 80) {
            $distribution['proficient']++;
        } else {
            $distribution['exemplary']++;
        }
    }
}

// -----------------------------------------------------------------------
// Build KPI summary HTML block.
// -----------------------------------------------------------------------
$dateconfig = get_string('strftimedatetimeshort', 'langconfig');
$datestr    = get_string('creation_date', 'local_comp_report_ext') . ': ' . userdate(time(), $dateconfig);

$kpihtml = '<table border="0" cellpadding="8" width="100%">';
$kpihtml .= '<tr>';

// Average Mastery.
$kpihtml .= '<td width="25%" bgcolor="#e6ffec" align="center" style="border-radius:8px;">';
$kpihtml .= '<b>' . get_string('average_mastery_rate', 'local_comp_report_ext') . '</b><br/>';
$kpihtml .= '<span style="font-size:18pt; color:#059669; font-weight:bold;">'
    . number_format($avgmastery, 1) . '%</span>';
$kpihtml .= '</td>';

// Remediation Rate.
$kpihtml .= '<td width="25%" bgcolor="#ffe6e6" align="center" style="border-radius:8px;">';
$kpihtml .= '<b>' . get_string('remediation_rate', 'local_comp_report_ext') . '</b><br/>';
$kpihtml .= '<span style="font-size:18pt; color:#DC2626; font-weight:bold;">'
    . number_format($remediationpercent, 1) . '%</span>';
$kpihtml .= '</td>';

// Top Strength.
$kpihtml .= '<td width="25%" bgcolor="#e6f2ff" align="center" style="border-radius:8px;">';
$kpihtml .= '<b>' . get_string('top_strength', 'local_comp_report_ext') . '</b><br/>';
$kpihtml .= '<span style="font-size:10pt; color:#2563EB; font-weight:bold;">'
    . htmlspecialchars($topstrength, ENT_QUOTES, 'UTF-8') . '</span>';
$kpihtml .= '</td>';

// Critical Gap.
$kpihtml .= '<td width="25%" bgcolor="#fff9e6" align="center" style="border-radius:8px;">';
$kpihtml .= '<b>' . get_string('critical_skill_gap', 'local_comp_report_ext') . '</b><br/>';
$kpihtml .= '<span style="font-size:10pt; color:#D97706; font-weight:bold;">'
    . htmlspecialchars($criticalgap, ENT_QUOTES, 'UTF-8') . '</span>';
$kpihtml .= '</td>';

$kpihtml .= '</tr>';
$kpihtml .= '</table>';

// Strip non-BMP characters (emojis) to prevent TCPDF warnings.
$kpihtml = preg_replace('/[^\x{0000}-\x{FFFF}]/u', '', $kpihtml);

// -----------------------------------------------------------------------
// Build Competency Averages Table.
// -----------------------------------------------------------------------
$comptablehtml = '';
if (!empty($compaverages)) {
    $comptablehtml = '<table border="1" cellpadding="6" style="border-collapse:collapse; font-size:9pt;" width="100%">';
    $comptablehtml .= '<thead><tr bgcolor="#f2f2f2">';
    $comptablehtml .= '<th width="70%" align="left"><b>' . get_string('competency', 'local_comp_report_ext') . '</b></th>';
    $comptablehtml .= '<th width="30%" align="center"><b>' . get_string('competencypercent', 'local_comp_report_ext') . '</b></th>';
    $comptablehtml .= '</tr></thead><tbody>';

    foreach ($compaverages as $cdata) {
        $avg    = $cdata['average'];
        $bgcolor = '#ffffff';
        if ($avg >= 80) {
            $bgcolor = '#e6ffec';
        } else if ($avg >= 60) {
            $bgcolor = '#e6f2ff';
        } else if ($avg >= 40) {
            $bgcolor = '#fff9e6';
        } else {
            $bgcolor = '#ffe6e6';
        }
        $comptablehtml .= '<tr>';
        $comptablehtml .= '<td width="70%">' . htmlspecialchars($cdata['shortname'], ENT_QUOTES, 'UTF-8') . '</td>';
        $comptablehtml .= '<td width="30%" align="center" bgcolor="' . $bgcolor
            . '" style="font-weight:bold;">%' . number_format($avg, 1) . '</td>';
        $comptablehtml .= '</tr>';
    }
    $comptablehtml .= '</tbody></table>';
    $comptablehtml  = preg_replace('/[^\x{0000}-\x{FFFF}]/u', '', $comptablehtml);
}

// -----------------------------------------------------------------------
// Build Mastery Distribution Table.
// -----------------------------------------------------------------------
$disttablehtml = '<table border="1" cellpadding="6" style="border-collapse:collapse; font-size:9pt;" width="100%">';
$disttablehtml .= '<thead><tr bgcolor="#f2f2f2">';
$disttablehtml .= '<th align="left"><b>' . get_string('mastery_distribution', 'local_comp_report_ext') . '</b></th>';
$disttablehtml .= '<th align="center"><b>' . get_string('studentavg', 'local_comp_report_ext') . '</b></th>';
$disttablehtml .= '</tr></thead><tbody>';

$tiers = [
    ['label' => get_string('critical_tier', 'local_comp_report_ext'),   'key' => 'critical',    'bg' => '#ffe6e6'],
    ['label' => get_string('developing_tier', 'local_comp_report_ext'), 'key' => 'developing',  'bg' => '#fff9e6'],
    ['label' => get_string('proficient_tier', 'local_comp_report_ext'), 'key' => 'proficient',  'bg' => '#e6f2ff'],
    ['label' => get_string('exemplary_tier', 'local_comp_report_ext'),  'key' => 'exemplary',   'bg' => '#e6ffec'],
];
foreach ($tiers as $tier) {
    $count = $distribution[$tier['key']];
    $disttablehtml .= '<tr>';
    $disttablehtml .= '<td bgcolor="' . $tier['bg'] . '">' . $tier['label'] . '</td>';
    $disttablehtml .= '<td align="center" bgcolor="' . $tier['bg'] . '"><b>' . $count . '</b></td>';
    $disttablehtml .= '</tr>';
}
$disttablehtml .= '</tbody></table>';
$disttablehtml  = preg_replace('/[^\x{0000}-\x{FFFF}]/u', '', $disttablehtml);

// -----------------------------------------------------------------------
// Auto-generate AI commentary if not provided by browser POST.
// -----------------------------------------------------------------------
if (empty($pdfcontent) && $hasdata) {
    $contextdetails = local_comp_report_ext_build_context_details($courseid);
    $rates = [];
    foreach ($compaverages as $cdata) {
        $rates[] = [
            'shortname' => $cdata['shortname'],
            'rate'      => $cdata['average'],
        ];
    }
    $pdfcontent = local_comp_report_ext_generate_comment($rates, 'group', '', 'competency', $contextdetails);
}

// -----------------------------------------------------------------------
// TCPDF Generation.
// -----------------------------------------------------------------------
$pdf = new TCPDF('P', PDF_UNIT, 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Moodle');
$pdf->SetTitle(get_string('group_analytics_dashboard', 'local_comp_report_ext'));
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);
$pdf->AddPage();
local_comp_report_ext_render_pdf_header_logos($pdf, false);

// Report title.
$pdf->SetFont('freeserif', 'B', 15);
$pdf->Cell(0, 10, get_string('group_analytics_dashboard', 'local_comp_report_ext'), 0, 1, 'L');
$pdf->SetFont('freeserif', '', 10);
$pdf->Cell(0, 6, 'Course: ' . $course->fullname, 0, 1, 'L');
$pdf->Cell(0, 6, 'Group: '  . $groupname, 0, 1, 'L');
$pdf->Cell(0, 6, $datestr, 0, 1, 'L');
$pdf->Ln(5);

// KPI section.
$pdf->SetFont('freeserif', 'B', 12);
$pdf->SetFillColor(245, 245, 245);
$pdf->Cell(0, 8, ' ' . get_string('course_stats', 'local_comp_report_ext'), 0, 1, 'L', true);
$pdf->Ln(3);
$pdf->SetFont('freeserif', '', 10);
$pdf->writeHTML($kpihtml, true, false, true, false, '');
$pdf->Ln(8);

// Competency Averages.
if (!empty($comptablehtml)) {
    $pdf->SetFont('freeserif', 'B', 12);
    $pdf->Cell(0, 8, ' ' . get_string('competency_mastery_radar', 'local_comp_report_ext'), 0, 1, 'L', true);
    $pdf->Ln(3);
    $pdf->SetFont('freeserif', '', 9);
    $pdf->writeHTML($comptablehtml, true, false, true, false, '');
    $pdf->Ln(8);
}

// Mastery Distribution.
$pdf->SetFont('freeserif', 'B', 12);
$pdf->Cell(0, 8, ' ' . get_string('mastery_distribution', 'local_comp_report_ext'), 0, 1, 'L', true);
$pdf->Ln(3);
$pdf->SetFont('freeserif', '', 9);
$pdf->writeHTML($disttablehtml, true, false, true, false, '');

// Legend.
$pdf->Ln(6);
$pdf->SetFont('freeserif', 'B', 9);
$pdf->Cell(0, 7, get_string('colorlegend', 'local_comp_report_ext'), 0, 1);
$pdf->SetFont('freeserif', '', 8);
$legend = get_string('redlegend', 'local_comp_report_ext') . ' | '
    . get_string('orangelegend', 'local_comp_report_ext') . ' | '
    . get_string('bluelegend', 'local_comp_report_ext') . ' | '
    . get_string('greenlegend', 'local_comp_report_ext');
$pdf->Cell(0, 5, $legend, 0, 1);

// AI Commentary page.
if (!empty($pdfcontent)) {
    $pdfcontent = preg_replace('/[^\x{0000}-\x{FFFF}]/u', '', $pdfcontent);
    $pdf->AddPage();
    $pdf->SetFont('freeserif', 'B', 13);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(0, 10, ' Pedagogical AI Analysis Commentary & Strategy', 0, 1, 'L', true);
    $pdf->Ln(2);
    $pdf->SetFont('freeserif', '', 10);
    $pdf->writeHTML($pdfcontent, true, false, true, false, '');
}

// Output PDF.
$filename = 'Group_Analytics_Dashboard_' . clean_filename($groupname) . '.pdf';
if (ob_get_length()) {
    ob_end_clean();
}
$pdf->Output($filename, 'I');
exit;
