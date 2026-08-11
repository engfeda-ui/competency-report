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
 * PDF export for the AI personalized remedial study plan (session-based).
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/tcpdf/tcpdf.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/ai.php');

// 1. Parameters.
$courseid = required_param('courseid', PARAM_INT);
$userid   = optional_param('userid', 0, PARAM_INT);
$language = optional_param('language', 'English', PARAM_ALPHA);
$sessions = optional_param('sessions', 10, PARAM_INT);

// Clamp sessions.
$sessions = max(1, min(60, $sessions));
$maxwords = max(200, min(1200, $sessions * 60));
$midpoint = (int)round($sessions / 2);

// 2. Auth.
require_login($courseid);
$context = context_course::instance($courseid);

if (empty($userid)) {
    $userid = $USER->id;
}
if (
    !has_capability('local/comp_report_ext:viewownreport', $context)
    && !has_capability('local/comp_report_ext:viewreports', $context)
    && !has_capability('local/competency_report:viewownreport', $context)
    && !has_capability('local/competency_report:viewreports', $context)
) {
    require_capability('local/comp_report_ext:viewownreport', $context);
}

// 3. Check AI is enabled.
if (!get_config('local_comp_report_ext', 'enable_ai')) {
    throw new moodle_exception('ai_not_configured', 'local_comp_report_ext');
}

// 4. Fetch competency data.
$sql = "SELECT c.id, c.shortname, c.description,
               CAST(SUM(qa.maxfraction) AS DECIMAL(12,1)) AS questions,
               CAST(SUM(qas.fraction) AS DECIMAL(12,1)) AS correct
        FROM {quiz_attempts} quiza
        JOIN {question_usages} qu ON qu.id = quiza.uniqueid
        JOIN {question_attempts} qa ON qa.questionusageid = qu.id
        JOIN {quiz} quiz ON quiz.id = quiza.quiz
        JOIN {qbank_comp_ext_qmap} m ON m.questionid = qa.questionid
        JOIN {competency} c ON c.id = m.competencyid
        JOIN (
            SELECT MAX(fraction) AS fraction, questionattemptid
            FROM {question_attempt_steps}
            GROUP BY questionattemptid
        ) qas ON qas.questionattemptid = qa.id
        WHERE quiz.course = :courseid AND quiza.userid = :userid AND quiza.state = 'finished'
        GROUP BY c.id, c.shortname, c.description";

$rows = $DB->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $userid]);

if (empty($rows)) {
    throw new moodle_exception('nodatafound', 'local_comp_report_ext');
}

// 5. Separate weak vs strong using configurable success threshold.
$threshold = (int)(get_config('local_comp_report_ext', 'success_threshold') ?: 60);
$weak   = [];
$strong = [];
foreach ($rows as $r) {
    $rate  = $r->questions ? round(($r->correct / $r->questions) * 100, 1) : 0;
    $clean = html_entity_decode(strip_tags($r->description), ENT_QUOTES, 'UTF-8');
    if ($rate < $threshold) {
        $weak[$r->shortname] = ['desc' => $clean, 'rate' => $rate];
    } else {
        $strong[$r->shortname] = ['desc' => $clean, 'rate' => $rate];
    }
}

$student     = \core_user::get_user($userid);
$studentname = fullname($student);
$course      = $DB->get_record('course', ['id' => $courseid], 'fullname');

// 6. Build context + session-based prompt via shared helper (same logic as AJAX endpoint).
$contextdetailspdf = local_comp_report_ext_build_context_details($courseid, $userid);
$contextdetailspdf['studentname'] = $studentname;

// Build rates array from PDF rows in the format accepted by build_studyplan_prompt().
$pdfrates = [];
foreach ($rows as $r) {
    $rate = $r->questions ? round(($r->correct / $r->questions) * 100, 1) : 0;
    $clean = html_entity_decode(strip_tags($r->description), ENT_QUOTES, 'UTF-8');
    $pdfrates[$r->shortname] = [
        'competency' => (object)['shortname' => $r->shortname, 'description' => $clean],
        'percent'    => $rate,
    ];
}

$prompt = local_comp_report_ext_build_studyplan_prompt($pdfrates, $language, $sessions, $contextdetailspdf);

// 7. Generate plan via AI.
$planhtml = local_comp_report_ext_generate_study_plan($prompt);
// Convert any markdown tables in the AI response to beautiful HTML tables.
$planhtml = local_comp_report_ext_markdown_to_html_table($planhtml);
// Strip any non-BMP unicode characters (emojis) to prevent TCPDF font warnings.
$planhtml = preg_replace('/[^\x{0000}-\x{FFFF}]/u', '', $planhtml);

// 8. Determine RTL for Arabic language.
$isrtl = ($language === 'Arabic');

// 9. Build PDF.
$pdf = new TCPDF(($isrtl ? 'RTL' : 'LTR'), 'mm', 'A4', true, 'UTF-8');
$pdf->SetCreator('Moodle Competency Report');
$pdf->SetAuthor($studentname);
$pdf->SetTitle(get_string('studyplan_pdf_title', 'local_comp_report_ext') . ' — ' . $studentname);
$pdf->SetRTL($isrtl);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();
local_comp_report_ext_render_pdf_header_logos($pdf);
$pdf->SetFont('freeserif', '', 12);

// Branded Header.
$pdf->SetFillColor(0, 90, 160);
$pdf->Rect(0, 0, 210, 22, 'F');
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('freeserif', 'B', 15);
$pdf->SetXY(10, 5);
$pdf->Cell(0, 12, get_string('studyplan_pdf_title', 'local_comp_report_ext'), 0, 1, $isrtl ? 'R' : 'L');
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(8);

// Student and Course info block.
$pdf->SetFillColor(240, 248, 255);
$pdf->SetFont('freeserif', 'B', 12);
$pdf->Cell(0, 8, $studentname, 0, 1, 'C', false);
$pdf->SetFont('freeserif', '', 11);
$pdf->SetTextColor(80, 80, 80);
$pdf->Cell(0, 6, $course->fullname, 0, 1, 'C');

// Plan meta: sessions, language, total hours.
$totalhours = $sessions;
$infotext = get_string('studyplan_sessions_label', 'local_comp_report_ext') . ': ' . $sessions
    . ' × 1 ' . get_string('studyplan_session_hint_short', 'local_comp_report_ext')
    . ' = ' . $totalhours . ' h'
    . '   |   Language: ' . $language;
$pdf->Cell(0, 6, $infotext, 0, 1, 'C');
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(3);

// Divider.
$pdf->SetDrawColor(0, 90, 160);
$pdf->SetLineWidth(0.6);
$pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
$pdf->Ln(5);

// Weak Competency Summary Table.
if (!empty($weak)) {
    $pdf->SetFont('freeserif', 'B', 10);
    $pdf->SetFillColor(220, 235, 255);
    $title = '(!)  ' . ($language === 'Arabic' ? 'الكفايات التي تحتاج علاجاً' : 'Competencies Requiring Remediation');
    $pdf->Cell(0, 8, $title, 1, 1, 'L', true);

    $pdf->SetFont('freeserif', 'B', 9);
    $pdf->SetFillColor(200, 218, 255);
    $pdf->Cell(30, 7, ($language === 'Arabic' ? 'الكود' : 'Code'), 1, 0, 'C', true);
    $pdf->Cell(110, 7, ($language === 'Arabic' ? 'الوصف' : 'Description'), 1, 0, 'L', true);
    $pdf->Cell(25, 7, ($language === 'Arabic' ? 'الإتقان %' : 'Mastery %'), 1, 0, 'C', true);
    $pdf->Cell(25, 7, ($language === 'Arabic' ? 'الحصص المقترحة' : 'Suggested Sessions'), 1, 1, 'C', true);

    $pdf->SetFont('freeserif', '', 9);
    $totalweak = count($weak);
    $sessionsassigned = 0;
    $i = 0;
    foreach ($weak as $code => $info) {
        $i++;
        $rate = $info['rate'];
        // Proportional session allocation: weaker gets more.
        $weight = max(1, (int)round((1 - ($rate / 100)) * $sessions / $totalweak * 1.5));
        if ($i === $totalweak) {
            $weight = $sessions - $sessionsassigned; // Last takes the remainder.
        }
        $sessionsassigned += $weight;

        if ($rate < 40) {
            $pdf->SetFillColor(255, 205, 205); // Red.
        } else {
            $pdf->SetFillColor(255, 238, 195); // Orange.
        }

        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $descheight = max(7, $pdf->getStringHeight(110, $info['desc']));
        $pdf->MultiCell(30, $descheight, $code, 1, 'C', true, 0, $x, $y, true);
        $pdf->MultiCell(110, $descheight, $info['desc'], 1, 'L', true, 0, $x + 30, $y, true);
        $pdf->MultiCell(25, $descheight, '%' . $rate, 1, 'C', true, 0, $x + 140, $y, true);
        $pdf->MultiCell(25, $descheight, $weight, 1, 'C', true, 1, $x + 165, $y, true);
    }
    $pdf->Ln(5);
}

// AI Study Plan Content.
$pdf->SetFillColor(235, 255, 240);
$pdf->SetFont('freeserif', 'B', 11);
$pdf->Cell(0, 9, '[*] ' . get_string('studyplan_pdf_title', 'local_comp_report_ext'), 1, 1, 'L', true);
$pdf->Ln(2);
$pdf->SetFont('freeserif', '', 10);
$pdf->writeHTML($planhtml, true, false, true, false, $isrtl ? 'R' : '');

// Footer.
$pdf->Ln(8);
$pdf->SetDrawColor(180, 180, 180);
$pdf->SetLineWidth(0.3);
$pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
$pdf->Ln(2);
$pdf->SetFont('freeserif', 'I', 8);
$pdf->SetTextColor(140, 140, 140);
$pdf->Cell(0, 5, 'Generated by Competency Report AI System — ' . date('d M Y, H:i'), 0, 1, 'C');

// Clear output buffer to prevent PHP warnings/headers-already-sent errors from corrupting the PDF.
if (ob_get_length()) {
    ob_end_clean();
}

// 10. Output PDF inline.
$safefilename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $studentname);
$pdf->Output("studyplan_{$safefilename}_{$sessions}sessions.pdf", 'I');
exit;
