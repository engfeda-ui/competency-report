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
 * PDF report generator for Group Competency Analysis report.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/tcpdf/tcpdf.php');
require_once(__DIR__ . '/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$groupid  = optional_param('groupid', 0, PARAM_INT);
$pdfcontent = optional_param('pdf_content', '', PARAM_RAW);

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
    $students  = (array) $DB->get_records_sql("
        SELECT u.*
        FROM {groups_members} gm
        JOIN {user} u ON u.id = gm.userid
        JOIN {role_assignments} ra ON ra.userid = u.id
        JOIN {context} ctx ON ctx.id = ra.contextid
        WHERE gm.groupid = :groupid
          AND ctx.instanceid = :courseid
          AND ra.roleid = (SELECT id FROM {role} WHERE shortname = 'student')
          AND u.deleted = 0
        ORDER BY u.idnumber ASC
    ", ['groupid' => $groupid, 'courseid' => $courseid]);
} else {
    $groupname = get_string('allgroups', 'local_comp_report_ext');
    $students  = (array) $DB->get_records_sql("
        SELECT u.*
        FROM {role_assignments} ra
        JOIN {role} r ON r.id = ra.roleid
        JOIN {context} ctx ON ctx.id = ra.contextid
        JOIN {user} u ON u.id = ra.userid
        WHERE ctx.instanceid = :courseid
          AND ctx.contextlevel = 50
          AND r.shortname = 'student'
          AND u.deleted = 0
        ORDER BY u.idnumber ASC
    ", ['courseid' => $courseid]);
}

// 2. Fetch mapped competencies list - scoped to course.
$competencies = (array) $DB->get_records_sql("
    SELECT DISTINCT c.id, c.shortname
    FROM {qbank_comp_ext_qmap} m
    JOIN {competency} c ON c.id = m.competencyid
    WHERE m.courseid = :courseid
    ORDER BY c.shortname ASC
", ['courseid' => $courseid]);

if (empty($competencies)) {
    throw new moodle_exception('nocompetencies', 'local_comp_report_ext');
}

// 3. Performance data query.
$scoremap = [];
if (!empty($students)) {
    $studentids = array_keys($students);
    list($insql, $inparams) = $DB->get_in_or_equal($studentids, SQL_PARAMS_NAMED, 'uid');

    $rawscores = (array) $DB->get_records_sql("
        SELECT
            CONCAT(quiza.userid, '_', m.competencyid) as unique_key,
            quiza.userid,
            m.competencyid,
            SUM(qa.maxfraction) AS total_max,
            SUM(qas.fraction) AS total_fraction
        FROM {quiz_attempts} quiza
        JOIN {question_usages} qu ON qu.id = quiza.uniqueid
        JOIN {question_attempts} qa ON qa.questionusageid = qu.id
        JOIN {qbank_comp_ext_qmap} m ON m.questionid = qa.questionid
        JOIN (
            SELECT questionattemptid, MAX(fraction) AS fraction
            FROM {question_attempt_steps}
            GROUP BY questionattemptid
        ) qas ON qas.questionattemptid = qa.id
        WHERE quiza.state = 'finished'
          AND quiza.userid $insql
        GROUP BY quiza.userid, m.competencyid
    ", $inparams);

    foreach ($rawscores as $rs) {
        $scoremap[$rs->userid][$rs->competencyid] = [
            'att' => (float)$rs->total_max,
            'cor' => (float)$rs->total_fraction,
        ];
    }
}

// 4. Calculate Column Widths dynamically.
// Landscape page printable width is approx 265mm.
// We dedicate 65mm for Student name, and divide the remaining 200mm among competencies.
$compcount = count($competencies);
$studentwidth = 24; // Width in percentage.
$compwidth = 76 / max(1, $compcount); // Width in percentage.

// 5. Build HTML Table.
$tablehtml = '<table border="1" cellpadding="6" style="border-collapse: collapse; font-size: 8.5pt;">';

// Header Row.
$tablehtml .= '<thead><tr bgcolor="#f2f2f2" style="font-weight: bold;">';
$tablehtml .= '  <th width="' . $studentwidth . '%" align="left"><b>'
    . get_string('student', 'local_comp_report_ext') . '</b></th>';
foreach ($competencies as $c) {
    $tablehtml .= '  <th width="' . $compwidth . '%" align="center"><b>' . s($c->shortname) . '</b></th>';
}
$tablehtml .= '</tr></thead>';

$tablehtml .= '<tbody>';
$grouptotals = [];

foreach ($students as $s) {
    $tablehtml .= '<tr>';
    $tablehtml .= '  <td width="' . $studentwidth . '%"><b>' . s(fullname($s)) . '</b></td>';

    foreach ($competencies as $c) {
        $celltext = '-';
        $bgcolor = '#ffffff';

        if (isset($scoremap[$s->id][$c->id])) {
            $att = $scoremap[$s->id][$c->id]['att'];
            $cor = $scoremap[$s->id][$c->id]['cor'];

            if ($att > 0) {
                $rate = ($cor / $att) * 100;
                $celltext = '%' . number_format($rate, 1);

                // Premium HSL-tailored colors.
                if ($rate >= 80) {
                    $bgcolor = '#e6ffec'; // Green.
                } else if ($rate >= 60) {
                    $bgcolor = '#e6f2ff'; // Blue.
                } else if ($rate >= 40) {
                    $bgcolor = '#fff9e6'; // Orange.
                } else {
                    $bgcolor = '#ffe6e6'; // Red.
                }

                $grouptotals[$c->id]['att'] = ($grouptotals[$c->id]['att'] ?? 0) + $att;
                $grouptotals[$c->id]['cor'] = ($grouptotals[$c->id]['cor'] ?? 0) + $cor;
            }
        }

        $tablehtml .= '  <td width="' . $compwidth . '%" align="center" bgcolor="' . $bgcolor . '" style="font-weight: bold;">'
            . $celltext . '</td>';
    }
    $tablehtml .= '</tr>';
}
$tablehtml .= '</tbody>';

// Footer Total Row.
$tablehtml .= '<tfoot><tr bgcolor="#e9ecef" style="font-weight: bold;">';
$tablehtml .= '  <td width="' . $studentwidth . '%"><b>' . get_string('total', 'local_comp_report_ext') . '</b></td>';
foreach ($competencies as $c) {
    $celltext = '-';
    $bgcolor = '#e9ecef';

    $tatt = $grouptotals[$c->id]['att'] ?? 0;
    $tcor = $grouptotals[$c->id]['cor'] ?? 0;

    if ($tatt > 0) {
        $trate = ($tcor / $tatt) * 100;
        $celltext = '%' . number_format($trate, 1);
        if ($trate >= 80) {
            $bgcolor = '#d4edda';
        } else if ($trate >= 60) {
            $bgcolor = '#cce5ff';
        } else if ($trate >= 40) {
            $bgcolor = '#fff3cd';
        } else {
            $bgcolor = '#f8d7da';
        }
    }
    $tablehtml .= '  <td width="' . $compwidth . '%" align="center" bgcolor="' . $bgcolor . '">' . $celltext . '</td>';
}
$tablehtml .= '</tr></tfoot>';
$tablehtml .= '</table>';

// Strip any non-BMP unicode characters (emojis) to prevent TCPDF font compilation warnings.
$tablehtml = preg_replace('/[^\x{0000}-\x{FFFF}]/u', '', $tablehtml);

// 6. PDF Generation (TCPDF).
// 'L' defines Landscape orientation, which is crucial for multi-column student competency grid reports.
$pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator('Moodle');
$pdf->SetTitle(get_string('groupcompetency', 'local_comp_report_ext'));
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);
$pdf->AddPage();
local_comp_report_ext_render_pdf_header_logos($pdf, true);

// Set font for robust UTF-8 / Arabic support.
$pdf->SetFont('freeserif', '', 10);

// Header Banner.
$pdf->SetFont('freeserif', 'B', 15);
$pdf->Cell(0, 10, get_string('groupcompetency', 'local_comp_report_ext'), 0, 1, 'L');
$pdf->SetFont('freeserif', '', 10);
$pdf->Cell(0, 6, "Subject / Course: " . $course->fullname, 0, 1, 'L');
$pdf->Cell(0, 6, "Group / Class: " . $group->name, 0, 1, 'L');

$dateconfig = get_string('strftimedatetimeshort', 'langconfig');
$dateinfo = get_string('creation_date', 'local_comp_report_ext') . ": " . userdate(time(), $dateconfig);
$pdf->Cell(0, 6, $dateinfo, 0, 1, 'L');
$pdf->Ln(5);

// Render HTML Table.
$pdf->SetFont('freeserif', '', 9);
$pdf->writeHTML($tablehtml, true, false, true, false, '');

// Legend.
$pdf->Ln(8);
$pdf->SetFont('freeserif', 'B', 9);
$pdf->Cell(0, 7, get_string('colorlegend', 'local_comp_report_ext'), 0, 1);
$pdf->SetFont('freeserif', '', 8);
$legend = get_string('redlegend', 'local_comp_report_ext') . " | " .
          get_string('orangelegend', 'local_comp_report_ext') . " | " .
          get_string('bluelegend', 'local_comp_report_ext') . " | " .
          get_string('greenlegend', 'local_comp_report_ext');
$pdf->Cell(0, 5, $legend, 0, 1);

if (empty($pdfcontent)) {
    $contextdetails = local_comp_report_ext_build_context_details($courseid);
    $pdfcontent = local_comp_report_ext_generate_comment($grouptotals, 'group', '', 'competency', $contextdetails);
}

if (!empty($pdfcontent)) {
    // Strip non-BMP characters.
    $pdfcontent = preg_replace('/[^\x{0000}-\x{FFFF}]/u', '', $pdfcontent);
    $pdf->AddPage();
    $pdf->SetFont('freeserif', 'B', 13);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(0, 10, " ✨ Pedagogical AI Analysis Commentary & Strategy", 0, 1, 'L', true);
    $pdf->Ln(2);

    $pdf->SetFont('freeserif', '', 10);
    $pdf->writeHTML($pdfcontent, true, false, true, false, '');
}

// Final PDF output.
$reporttitle = "Group_Competency_Report_" . clean_filename($group->name);
$filename = $reporttitle . ".pdf";

// Clear output buffer.
if (ob_get_length()) {
    ob_end_clean();
}
$pdf->Output($filename, "I");
exit;
