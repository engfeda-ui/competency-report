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
 * PDF Export for competency analysis.
 *
 * @package    local_yetkinlik
 * @copyright  2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/tcpdf/tcpdf.php');
require_once(__DIR__ . '/ai.php');

// Parameter check.
$courseid = optional_param('courseid', 0, PARAM_INT);
require_login($courseid);

global $DB, $CFG;

// Access control: Ensure the user has the 'viewreports' capability defined in access.php.
if ($courseid) {
    $context = context_course::instance($courseid);
    require_capability('local/yetkinlik:viewreports', $context);
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    $reporttitle = get_string('report_title', 'local_yetkinlik', $course->fullname);

    $wheresql = "WHERE quiz.course = :courseid AND quiza.state = 'finished'";
    $params = ['courseid' => $courseid];
} else {
    // System-wide report access.
    $context = context_system::instance();
    require_capability('local/yetkinlik:viewreports', $context);

    $reporttitle = get_string('report_title', 'local_yetkinlik');
    $wheresql = "WHERE quiza.state = 'finished'";
    $params = [];
}

// SQL to fetch competency achievement data.
$sql = "
    SELECT c.id, c.shortname, c.description,
           CAST(SUM(qa.maxfraction) AS DECIMAL(12, 1)) AS attempts,
           CAST(SUM(qas.fraction) AS DECIMAL(12, 1)) AS correct
    FROM {quiz_attempts} quiza
    JOIN {quiz} quiz ON quiz.id = quiza.quiz
    JOIN {question_usages} qu ON qu.id = quiza.uniqueid
    JOIN {question_attempts} qa ON qa.questionusageid = qu.id
    JOIN {qbank_yetkinlik_qmap} m ON m.questionid = qa.questionid
    JOIN {competency} c ON c.id = m.competencyid
    JOIN (
        SELECT MAX(fraction) AS fraction, questionattemptid
        FROM {question_attempt_steps}
        GROUP BY questionattemptid
    ) qas ON qas.questionattemptid = qa.id
    $wheresql
    GROUP BY c.id, c.shortname, c.description
    ORDER BY c.shortname ASC
";

$rows = $DB->get_records_sql($sql, $params);
$rates = [];

foreach ($rows as $r) {
    $rate = $r->attempts ? number_format(($r->correct / $r->attempts) * 100, 1) : 0;
    $rates[$r->shortname] = $rate;
}

// Generate AI analysis comment.
$comment = local_yetkinlik_generate_comment($rates);

/* --- PDF Preparation --- */

$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator('Moodle');
$pdf->SetTitle($reporttitle);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);
$pdf->AddPage();

// Font for Unicode support.
$pdf->SetFont('freeserif', '', 12);

// Header section.
$pdf->SetFont('freeserif', 'B', 16);
$pdf->Cell(0, 10, $reporttitle, 0, 1, 'C');
$pdf->SetFont('freeserif', '', 9);

// Use Moodle userdate for localized time.
$dateconfig = get_string('strftimedatetimeshort', 'langconfig');
$dateinfo = get_string('creation_date', 'local_yetkinlik') . ": " . userdate(time(), $dateconfig);
$pdf->Cell(0, 5, $dateinfo, 0, 1, 'R');
$pdf->Ln(5);

// Table structure using align attributes for stability.
$html = '
<table border="1" cellpadding="6">
    <thead>
        <tr bgcolor="#f2f2f2" style="font-weight: bold;">
            <th width="15%" align="center">' . get_string('competencycode', 'local_yetkinlik') . '</th>
            <th width="41%" align="center">' . get_string('competencyname', 'local_yetkinlik') . '</th>
            <th width="14%" align="center">' . get_string('questioncount', 'local_yetkinlik') . '</th>
            <th width="14%" align="center">' . get_string('correctcount', 'local_yetkinlik') . '</th>
            <th width="16%" align="center">' . get_string('successrate', 'local_yetkinlik') . '</th>
        </tr>
    </thead>
    <tbody>';

foreach ($rows as $r) {
    $rate = $r->attempts ? number_format(($r->correct / $r->attempts) * 100, 1) : 0;
    // Formatting and cleaning data for PDF.
    $cleandesc = html_entity_decode(strip_tags($r->description), ENT_QUOTES, 'UTF-8');
    $bgcolor = $rate >= 70 ? '#e6ffec' : ($rate >= 50 ? '#fff9e6' : '#ffe6e6');

    $html .= '
        <tr bgcolor="' . $bgcolor . '">
            <td width="15%" align="center"><b>' . s($r->shortname) . '</b></td>
            <td width="41%">' . $cleandesc . '</td>
            <td width="14%" align="center">' . $r->attempts . '</td>
            <td width="14%" align="center">' . $r->correct . '</td>
            <td width="16%" align="center"><b>%' . $rate . '</b></td>
        </tr>';
}

$html .= '</tbody></table>';

// Render the HTML table.
$pdf->writeHTML($html, true, false, true, false, '');

// AI analysis section (if comment exists).
if (!empty($comment)) {
    $pdf->Ln(5);
    $pdf->SetFont('freeserif', 'B', 12);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(0, 10, " " . get_string('generalcomment', 'local_yetkinlik'), 0, 1, 'L', true);

    $pdf->Ln(2);
    $pdf->SetFont('freeserif', '', 11);
    $cleancomment = html_entity_decode(strip_tags($comment), ENT_QUOTES, 'UTF-8');
    $pdf->MultiCell(0, 7, $cleancomment, 0, 'L', false, 1);
}

// Final PDF output.
$pdf->Output("competency_report.pdf", "I");
exit;
