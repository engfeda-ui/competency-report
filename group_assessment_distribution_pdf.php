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
 * Landscape PDF report generator for Group & Assessment Competency Distribution.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/tcpdf/tcpdf.php');
require_once(__DIR__ . '/lib.php');

$courseid           = required_param('courseid', PARAM_INT);
$groupid            = optional_param('groupid', 0, PARAM_INT);
$assessmentidsjson  = optional_param('assessmentids_json', '[]', PARAM_TEXT);
$pdfcontent         = optional_param('pdf_content', '', PARAM_CLEANHTML);

require_login($courseid);
$context = context_course::instance($courseid);
$canviewext = has_capability('local/comp_report_ext:viewreports', $context);
$canviewold = has_capability('local/competency_report:viewreports', $context);
if (!$canviewext && !$canviewold) {
    require_capability('local/comp_report_ext:viewreports', $context);
}

global $DB;

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$groupname = get_string('allgroups', 'local_comp_report_ext');
if ($groupid > 0) {
    $group = $DB->get_record('groups', ['id' => $groupid, 'courseid' => $courseid], '*', MUST_EXIST);
    $groupname = format_string($group->name);
}

$validasmtids = json_decode($assessmentidsjson, true);
if (!is_array($validasmtids)) {
    $validasmtids = [];
}

$allasmts = $DB->get_records('local_comp_report_ext_asmt', ['courseid' => $courseid], 'id ASC');
if (empty($validasmtids) && !empty($allasmts)) {
    $validasmtids = array_keys($allasmts);
}

$selectedasmts = [];
foreach ($validasmtids as $aid) {
    if (isset($allasmts[$aid])) {
        $selectedasmts[$aid] = $allasmts[$aid];
    }
}

if (empty($selectedasmts)) {
    throw new moodle_exception('noassessmentsconfigured', 'local_comp_report_ext');
}

// Fetch Students.
if ($groupid > 0) {
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
        ['groupid' => $groupid, 'courseid' => $courseid, 'gname' => $groupname]
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

// Calculate scores.
$calculator = new \local_comp_report_ext\competency_calculator($courseid);
$threshold  = (int)(get_config('local_comp_report_ext', 'success_threshold') ?: 60);

$rows = [];
$studentindex = 0;
foreach ($students as $student) {
    $scores = $calculator->get_student_scores((int)$student->id);
    if (empty($scores)) {
        continue;
    }

    $comprows = [];
    foreach ($scores as $compid => $data) {
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
            'shortname'     => format_string($data['competency']->shortname),
            'asmt_cells'    => $asmtcells,
            'total_percent' => ($totalpercent !== null) ? number_format($totalpercent, 1) : null,
            'has_total'     => ($totalpercent !== null),
            'color'         => $ratecolor,
        ];
    }

    if (empty($comprows)) {
        continue;
    }

    $studentindex++;
    $iseven = ($studentindex % 2 === 0);

    $comprows[0]['first_row']   = true;
    $comprows[0]['studentname'] = fullname($student);
    $comprows[0]['groupname']   = format_string($student->groupname);
    $comprows[0]['rowspan']     = count($comprows);

    foreach ($comprows as &$cr) {
        $cr['is_even'] = $iseven;
    }
    unset($cr);

    $rows = array_merge($rows, $comprows);
}

if (empty($rows)) {
    throw new moodle_exception('noexamdata', 'local_comp_report_ext');
}

// PDF Generation (TCPDF).
$pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator('Moodle');
$pdf->SetTitle(get_string('groupassessmentdistribution', 'local_comp_report_ext'));
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);

$isrtl = right_to_left();
$pdf->setRTL($isrtl);

$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);

// Add Page.
$pdf->AddPage();
local_comp_report_ext_render_pdf_header_logos($pdf, true);

// Document Title & Meta.
$pdf->SetFont('freeserif', 'B', 14);
$pdf->Cell(0, 8, get_string('groupassessmentdistribution', 'local_comp_report_ext'), 0, 1, 'C');
$pdf->SetFont('freeserif', '', 10);
$pdf->Cell(0, 6, $course->fullname . ' — ' . get_string('group', 'local_comp_report_ext') . ': ' . $groupname, 0, 1, 'C');
$pdf->Ln(4);

// Calculate Column Widths for PDF table (Total Printable Width approx 267mm).
$showgroupcol = ($groupid === 0);
$studentwidth = 22;
$groupwidth   = $showgroupcol ? 16 : 0;
$compwidth    = 18;
$totalwidth   = 16;

$asmtcount    = count($selectedasmts);
$remainingpct = 100 - ($studentwidth + $groupwidth + $compwidth + $totalwidth);
$asmtwidth    = $remainingpct / max(1, $asmtcount);

// Build HTML Table.
$tablehtml = '<table border="1" cellpadding="5" style="border-collapse: collapse; font-size: 8.5pt;">';
$tablehtml .= '<thead><tr bgcolor="#343a40" style="color: #ffffff; font-weight: bold;">';
$tablehtml .= '<th width="' . $studentwidth . '%" align="left"><b>' .
    get_string('student', 'local_comp_report_ext') . '</b></th>';
if ($showgroupcol) {
    $tablehtml .= '<th width="' . $groupwidth . '%" align="left"><b>' .
        get_string('group', 'local_comp_report_ext') . '</b></th>';
}
$tablehtml .= '<th width="' . $compwidth . '%" align="left"><b>' .
    get_string('competency', 'local_comp_report_ext') . '</b></th>';

foreach ($selectedasmts as $asmt) {
    $hdrlabel = s($asmt->name) . '<br><small>(' . (float)$asmt->weight . '%)</small>';
    $tablehtml .= '<th width="' . $asmtwidth . '%" align="center"><b>' . $hdrlabel . '</b></th>';
}
$tablehtml .= '<th width="' . $totalwidth . '%" align="center"><b>' .
    get_string('weightedtotal', 'local_comp_report_ext') . '</b></th>';
$tablehtml .= '</tr></thead><tbody>';

foreach ($rows as $row) {
    $bgcolor = $row['is_even'] ? '#f8f9fa' : '#ffffff';
    $tablehtml .= '<tr bgcolor="' . $bgcolor . '">';

    if (!empty($row['first_row'])) {
        $tablehtml .= '<td width="' . $studentwidth . '%" rowspan="' . $row['rowspan'] .
            '" style="vertical-align: middle;"><b>' . s($row['studentname']) . '</b></td>';
        if ($showgroupcol) {
            $tablehtml .= '<td width="' . $groupwidth . '%" rowspan="' . $row['rowspan'] .
                '" style="vertical-align: middle;">' . s($row['groupname']) . '</td>';
        }
    }

    $tablehtml .= '<td width="' . $compwidth . '%" align="left">' . s($row['shortname']) . '</td>';

    foreach ($row['asmt_cells'] as $cell) {
        $cellval = $cell['has_score'] ? '%' . $cell['score_pct'] : '—';
        $tablehtml .= '<td width="' . $asmtwidth . '%" align="center">' . $cellval . '</td>';
    }

    $colorcode = '#6c757d';
    if ($row['color'] === 'green') {
        $colorcode = '#28a745';
    } else if ($row['color'] === 'blue') {
        $colorcode = '#007bff';
    } else if ($row['color'] === 'orange') {
        $colorcode = '#fd7e14';
    } else if ($row['color'] === 'red') {
        $colorcode = '#dc3545';
    }

    $totalval = $row['has_total'] ? '%' . $row['total_percent'] : '—';
    $tablehtml .= '<td width="' . $totalwidth . '%" align="center" style="font-weight: bold; color: ' .
        $colorcode . ';">' . $totalval . '</td>';
    $tablehtml .= '</tr>';
}

$tablehtml .= '</tbody></table>';

// Clean emojis to prevent font errors.
$tablehtml = preg_replace('/[^\x{0000}-\x{FFFF}]/u', '', $tablehtml);

$pdf->writeHTML($tablehtml, true, false, true, false, '');

// Append AI Commentary content if passed.
if (!empty($pdfcontent)) {
    $pdf->Ln(6);
    $pdf->SetFont('freeserif', 'B', 11);
    $pdf->Cell(0, 6, get_string('ai_analysis_focus', 'local_comp_report_ext'), 0, 1, 'L');
    $pdf->SetFont('freeserif', '', 9.5);
    $aiblock = '<div style="background-color: #f8f9fa; padding: 10px; border: 1px solid #dee2e6;">' .
        $pdfcontent . '</div>';
    $pdf->writeHTML($aiblock, true, false, true, false, '');
}

// Clean output buffer & send PDF.
while (ob_get_level()) {
    ob_end_clean();
}

$filename = 'Competency_Assessment_Distribution_' . clean_filename($course->shortname) . '_' . date('Y-m-d') . '.pdf';
$pdf->Output($filename, 'I');
