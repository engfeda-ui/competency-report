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
 * PDF Export for competency.
 *
 * @package    local_yetkinlik
 * @copyright  2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/tcpdf/tcpdf.php');
require_once(__DIR__ . '/ai.php');

require_login();

// Parameter validation.
$courseid = optional_param('courseid', 0, PARAM_INT);
global $DB;

if ($courseid) {
    $context = context_course::instance($courseid);
    require_capability('moodle/course:view', $context);
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

    // Fetch course-specific title from the language file.
    $reporttitle = get_string('report_title', 'local_yetkinlik', $course->fullname);

    $wheresql = "WHERE quiz.course = :courseid AND quiza.state = 'finished'";
    $params = ['courseid' => $courseid];
} else {
    $context = context_system::instance();
    require_capability('moodle/site:config', $context);

    // Fetch general title from the language file.
    $reporttitle = get_string('report_title', 'local_yetkinlik');

    $wheresql = "WHERE quiza.state = 'finished'";
    $params = [];
}

// SQL for Data Retrieval.
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

// Generate AI comment.
$comment = local_yetkinlik_generate_comment($rates);

/* PDF Preparation. */
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator('Moodle');
$pdf->SetTitle($reporttitle);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);
$pdf->AddPage();

// Font settings (for UTF-8 / Turkish character support).
$pdf->SetFont('freeserif', '', 12);

// Header section.
$pdf->SetFont('freeserif', 'B', 16);
$pdf->Cell(0, 10, $reporttitle, 0, 1, 'C');
$pdf->SetFont('freeserif', '', 9);
$pdf->Cell(0, 5, get_string('creation_date', 'local_yetkinlik') . ": " . date('d.m.Y H:i'), 0, 1, 'R');
$pdf->Ln(5);

// HTML table headers fetched from language file.
$html = '
<table border="0.5" cellpadding="6" style="width: 100%;">
    <thead>
        <tr style="background-color: #f2f2f2; font-weight: bold; text-align: center;">
            <th width="15%">' . get_string('competencycode', 'local_yetkinlik') . '</th>
            <th width="45%">' . get_string('competencyname', 'local_yetkinlik') . '</th>
            <th width="12%">' . get_string('questioncount', 'local_yetkinlik') . '</th>
            <th width="12%">' . get_string('correctcount', 'local_yetkinlik') . '</th>
            <th width="16%">' . get_string('successrate', 'local_yetkinlik') . '</th>
        </tr>
    </thead>
    <tbody>';

foreach ($rows as $r) {
    $rate = $r->attempts ? number_format(($r->correct / $r->attempts) * 100, 1) : 0;

    // Clean HTML tags.
    $cleandesc = html_entity_decode(strip_tags($r->description), ENT_QUOTES, 'UTF-8');

    // Color scaling based on success rate.
    $bgcolor = $rate >= 70 ? '#e6ffec' : ($rate >= 50 ? '#fff9e6' : '#ffe6e6');

    $html .= '
        <tr bgcolor="' . $bgcolor . '">
            <td width="15%" style="text-align: center;"><b>' . $r->shortname . '</b></td>
            <td width="45%">' . $cleandesc . '</td>
            <td width="12%" style="text-align: center;">' . $r->attempts . '</td>
            <td width="12%" style="text-align: center;">' . $r->correct . '</td>
            <td width="16%" style="text-align: center; font-weight: bold;">%' . $rate . '</td>
        </tr>';
}

$html .= '</tbody></table>';

// Render table to PDF.
$pdf->writeHTML($html, true, false, true, false, '');

// AI analysis note (if a comment exists).
if (!empty($comment)) {
    $cleancomment = html_entity_decode(strip_tags($comment), ENT_QUOTES, 'UTF-8');

    $pdf->Ln(8);
    $pdf->SetFont('freeserif', 'B', 12);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(0, 10, " " . get_string('generalcomment', 'local_yetkinlik'), 0, 1, 'L', true);

    $pdf->Ln(2);
    $pdf->SetFont('freeserif', '', 11);
    $pdf->MultiCell(0, 7, $cleancomment, 0, 'L', false, 1);
}

// Output.
$pdf->Output("competency_report.pdf", "I");
exit;
