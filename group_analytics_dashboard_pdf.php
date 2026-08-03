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
$chart1 = optional_param('chart1', '', PARAM_RAW);
$chart2 = optional_param('chart2', '', PARAM_RAW);
$chart3 = optional_param('chart3', '', PARAM_RAW);
$chart4 = optional_param('chart4', '', PARAM_RAW);
$chart5 = optional_param('chart5', '', PARAM_RAW);
$chart6 = optional_param('chart6', '', PARAM_RAW);
$chart7 = optional_param('chart7', '', PARAM_RAW);
$chart8 = optional_param('chart8', '', PARAM_RAW);

// -----------------------------------------------------------------------
// TCPDF Generation.
// -----------------------------------------------------------------------
$pdf = new TCPDF('P', PDF_UNIT, 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Moodle');
$pdf->SetTitle(get_string('group_analytics_dashboard', 'local_comp_report_ext'));
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();
local_comp_report_ext_render_pdf_header_logos($pdf, false);

// Report title.
$pdf->SetFont('freeserif', 'B', 15);
$pdf->Cell(0, 8, get_string('group_analytics_dashboard', 'local_comp_report_ext'), 0, 1, 'L');
$pdf->SetFont('freeserif', '', 9);
$pdf->Cell(0, 5, 'Course: ' . $course->fullname, 0, 1, 'L');
$pdf->Cell(0, 5, 'Group: '  . $groupname, 0, 1, 'L');
$pdf->Cell(0, 5, $datestr, 0, 1, 'L');
$pdf->Ln(3);

// KPI section.
$pdf->SetFont('freeserif', 'B', 11);
$pdf->SetFillColor(245, 245, 245);
$pdf->Cell(0, 7, ' ' . get_string('course_stats', 'local_comp_report_ext'), 0, 1, 'L', true);
$pdf->Ln(2);
$pdf->SetFont('freeserif', '', 9);
$pdf->writeHTML($kpihtml, true, false, true, false, '');
$pdf->Ln(4);

// Render Base64 Charts Grid if present.
$hascharts = (!empty($chart1) || !empty($chart2) || !empty($chart3) || !empty($chart4));

if ($hascharts) {
    $pdf->SetFont('freeserif', 'B', 11);
    $pdf->Cell(0, 7, ' Dashboard Visual Analytics & Competency Curves', 0, 1, 'L', true);
    $pdf->Ln(2);

    $currentY = $pdf->GetY();
    $chartWidth  = 86; // mm width for 2-column layout on A4 portrait
    $chartHeight = 52; // mm height

    // Row 1: Chart 1 (Radar) & Chart 2 (Distribution)
    if (!empty($chart1)) {
        $img1 = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $chart1));
        if ($img1) {
            $pdf->Image('@' . $img1, 15, $currentY, $chartWidth, $chartHeight, 'PNG');
        }
    }
    if (!empty($chart2)) {
        $img2 = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $chart2));
        if ($img2) {
            $pdf->Image('@' . $img2, 107, $currentY, $chartWidth, $chartHeight, 'PNG');
        }
    }

    $currentY += $chartHeight + 4;

    // Row 2: Chart 3 (Progress) & Chart 4 (Theory vs Practice)
    if (!empty($chart3)) {
        $img3 = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $chart3));
        if ($img3) {
            $pdf->Image('@' . $img3, 15, $currentY, $chartWidth, $chartHeight, 'PNG');
        }
    }
    if (!empty($chart4)) {
        $img4 = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $chart4));
        if ($img4) {
            $pdf->Image('@' . $img4, 107, $currentY, $chartWidth, $chartHeight, 'PNG');
        }
    }

    $pdf->SetY($currentY + $chartHeight + 4);
} else {
    // Fallback: Render Data Tables if PDF accessed directly without JS chart capture.
    if (!empty($comptablehtml)) {
        $pdf->SetFont('freeserif', 'B', 11);
        $pdf->Cell(0, 7, ' ' . get_string('competency_mastery_radar', 'local_comp_report_ext'), 0, 1, 'L', true);
        $pdf->Ln(2);
        $pdf->SetFont('freeserif', '', 9);
        $pdf->writeHTML($comptablehtml, true, false, true, false, '');
        $pdf->Ln(4);
    }

    $pdf->SetFont('freeserif', 'B', 11);
    $pdf->Cell(0, 7, ' ' . get_string('mastery_distribution', 'local_comp_report_ext'), 0, 1, 'L', true);
    $pdf->Ln(2);
    $pdf->SetFont('freeserif', '', 9);
    $pdf->writeHTML($disttablehtml, true, false, true, false, '');
}

// Legend.
$pdf->Ln(3);
$pdf->SetFont('freeserif', 'B', 8);
$pdf->Cell(0, 5, get_string('colorlegend', 'local_comp_report_ext'), 0, 1);
$pdf->SetFont('freeserif', '', 8);
$legend = get_string('redlegend', 'local_comp_report_ext') . ' | '
    . get_string('orangelegend', 'local_comp_report_ext') . ' | '
    . get_string('bluelegend', 'local_comp_report_ext') . ' | '
    . get_string('greenlegend', 'local_comp_report_ext');
$pdf->Cell(0, 5, $legend, 0, 1);

// Page 2: Final Exam & Psychometric Grade Analytics (if charts 5-8 present)
$hasexamcharts = (!empty($chart5) || !empty($chart6) || !empty($chart7) || !empty($chart8));

if ($hasexamcharts) {
    $pdf->AddPage();
    local_comp_report_ext_render_pdf_header_logos($pdf, false);

    $pdf->SetFont('freeserif', 'B', 14);
    $pdf->Cell(0, 8, get_string('exam_analytics_section', 'local_comp_report_ext') . ': ' . $exam_name, 0, 1, 'L');
    $pdf->Ln(2);

    // Exam KPI Summary Table
    $examkpihtml = '<table border="0" cellpadding="8" width="100%"><tr>';
    $examkpihtml .= '<td width="25%" bgcolor="#e6f2ff" align="center"><b>' . get_string('exam_avg_score', 'local_comp_report_ext') . '</b><br/><span style="font-size:16pt; color:#2563EB; font-weight:bold;">' . number_format($exam_avg, 1) . '%</span></td>';
    $examkpihtml .= '<td width="25%" bgcolor="#e6ffec" align="center"><b>' . get_string('exam_pass_rate_label', 'local_comp_report_ext') . '</b><br/><span style="font-size:16pt; color:#059669; font-weight:bold;">' . number_format($exam_pass_rate, 1) . '%</span></td>';
    $examkpihtml .= '<td width="25%" bgcolor="#e6f0fa" align="center"><b>' . get_string('exam_highest_score', 'local_comp_report_ext') . '</b><br/><span style="font-size:16pt; color:#0284C7; font-weight:bold;">' . number_format($exam_max, 1) . '%</span></td>';
    $examkpihtml .= '<td width="25%" bgcolor="#ffe6e6" align="center"><b>' . get_string('exam_lowest_score', 'local_comp_report_ext') . '</b><br/><span style="font-size:16pt; color:#DC2626; font-weight:bold;">' . number_format($exam_min, 1) . '%</span></td>';
    $examkpihtml .= '</tr></table>';

    $pdf->writeHTML($examkpihtml, true, false, true, false, '');
    $pdf->Ln(4);

    $currentY = $pdf->GetY();
    $chartWidth  = 86;
    $chartHeight = 52;

    // Row 1: Chart 5 (Grade Distribution) & Chart 6 (Pass vs Fail)
    if (!empty($chart5)) {
        $img5 = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $chart5));
        if ($img5) {
            $pdf->Image('@' . $img5, 15, $currentY, $chartWidth, $chartHeight, 'PNG');
        }
    }
    if (!empty($chart6)) {
        $img6 = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $chart6));
        if ($img6) {
            $pdf->Image('@' . $img6, 107, $currentY, $chartWidth, $chartHeight, 'PNG');
        }
    }

    $currentY += $chartHeight + 4;

    // Row 2: Chart 7 (Item Difficulty) & Chart 8 (Item Discrimination)
    if (!empty($chart7)) {
        $img7 = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $chart7));
        if ($img7) {
            $pdf->Image('@' . $img7, 15, $currentY, $chartWidth, $chartHeight, 'PNG');
        }
    }
    if (!empty($chart8)) {
        $img8 = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $chart8));
        if ($img8) {
            $pdf->Image('@' . $img8, 107, $currentY, $chartWidth, $chartHeight, 'PNG');
        }
    }
}

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
