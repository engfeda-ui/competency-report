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
 * Landscape PDF generator for Course Final Comprehensive Evaluation Report.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/tcpdf/tcpdf.php');
require_once(__DIR__ . '/lib.php');

$courseid    = required_param('courseid', PARAM_INT);
$groupid     = optional_param('groupid', 0, PARAM_INT);
$finalasmtid = optional_param('finalasmtid', 0, PARAM_INT);
$oralasmtid  = optional_param('oralasmtid', 0, PARAM_INT);

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
    $grouprow  = $DB->get_record('groups', ['id' => $groupid, 'courseid' => $courseid]);
    $groupname = $grouprow ? format_string($grouprow->name) : $groupname;
}

$allasmts = $DB->get_records('local_comp_report_ext_asmt', ['courseid' => $courseid], 'weight DESC, id ASC');

if (!empty($allasmts)) {
    if ($finalasmtid <= 0 || !isset($allasmts[$finalasmtid])) {
        foreach ($allasmts as $aid => $a) {
            $namelower = core_text::strtolower($a->name);
            if (strpos($namelower, 'final') !== false || strpos($namelower, 'نهائي') !== false) {
                $finalasmtid = (int)$aid;
                break;
            }
        }
        if ($finalasmtid <= 0) {
            $firstasmt = reset($allasmts);
            $finalasmtid = (int)$firstasmt->id;
        }
    }

    if ($oralasmtid <= 0 || !isset($allasmts[$oralasmtid]) || $oralasmtid === $finalasmtid) {
        foreach ($allasmts as $aid => $a) {
            if ((int)$aid === $finalasmtid) {
                continue;
            }
            $namelower = core_text::strtolower($a->name);
            if (in_array($a->type, ['oral', 'practical', 'assign'])
                || strpos($namelower, 'oral') !== false
                || strpos($namelower, 'شفوي') !== false
                || strpos($namelower, 'عملي') !== false
                || strpos($namelower, 'practical') !== false) {
                $oralasmtid = (int)$aid;
                break;
            }
        }
        if ($oralasmtid <= 0 || $oralasmtid === $finalasmtid) {
            foreach ($allasmts as $aid => $a) {
                if ((int)$aid !== $finalasmtid) {
                    $oralasmtid = (int)$aid;
                    break;
                }
            }
        }
    }
}

$finalasmt = ($finalasmtid > 0 && isset($allasmts[$finalasmtid])) ? $allasmts[$finalasmtid] : null;
$oralasmt  = ($oralasmtid > 0 && isset($allasmts[$oralasmtid])) ? $allasmts[$oralasmtid] : null;

// Students.
$studentrole = $DB->get_record('role', ['shortname' => 'student'], 'id');
if (!$studentrole) {
    $studentrole = $DB->get_record('role', ['archetype' => 'student'], 'id');
}
$roleid = $studentrole ? (int)$studentrole->id : 5;

if ($groupid > 0) {
    $students = (array)$DB->get_records_sql(
        "SELECT u.id, u.firstname, u.lastname, u.username, u.idnumber, :gname AS groupname
           FROM {groups_members} gm
           JOIN {user} u ON u.id = gm.userid
           JOIN {role_assignments} ra ON ra.userid = u.id
           JOIN {context} ctx ON ctx.id = ra.contextid
          WHERE gm.groupid = :groupid
            AND ctx.instanceid = :courseid
            AND ra.roleid = :roleid
            AND u.deleted = 0
       ORDER BY u.username ASC, u.idnumber ASC, u.lastname ASC, u.firstname ASC",
        ['groupid' => $groupid, 'courseid' => $courseid, 'roleid' => $roleid, 'gname' => $groupname]
    );
} else {
    $students = (array)$DB->get_records_sql(
        "SELECT DISTINCT u.id, u.firstname, u.lastname, u.username, u.idnumber,
                COALESCE(g.name, '') AS groupname
           FROM {role_assignments} ra
           JOIN {context} ctx ON ctx.id = ra.contextid
           JOIN {user} u ON u.id = ra.userid
      LEFT JOIN {groups_members} gm ON gm.userid = u.id
      LEFT JOIN {groups} g ON g.id = gm.groupid AND g.courseid = :courseid
          WHERE ctx.instanceid = :courseid2
            AND ra.roleid = :roleid
            AND u.deleted = 0
       ORDER BY g.name ASC, u.username ASC, u.idnumber ASC, u.lastname ASC, u.firstname ASC",
        ['courseid' => $courseid, 'courseid2' => $courseid, 'roleid' => $roleid]
    );
}

$studentids = !empty($students) ? array_map('intval', array_keys($students)) : [];

// Competencies.
$getcomps = function ($asmt) use ($DB, $courseid) {
    if (!$asmt) {
        return [];
    }
    if (!empty($asmt->quizid)) {
        $comps = $DB->get_records_sql(
            "SELECT DISTINCT c.id, c.shortname
               FROM {quiz_attempts} quiza
               JOIN {question_usages} qu    ON qu.id = quiza.uniqueid
               JOIN {question_attempts} qa  ON qa.questionusageid = qu.id
               JOIN {qbank_comp_ext_qmap} m ON m.questionid = qa.questionid
               JOIN {competency} c          ON c.id = m.competencyid
              WHERE quiza.quiz = :quizid
           ORDER BY c.shortname ASC",
            ['quizid' => (int)$asmt->quizid]
        );
        if (empty($comps)) {
            $comps = $DB->get_records_sql(
                "SELECT DISTINCT c.id, c.shortname
                   FROM {qbank_comp_ext_qmap} m
                   JOIN {competency} c ON c.id = m.competencyid
                  WHERE m.courseid = :courseid
               ORDER BY c.shortname ASC",
                ['courseid' => $courseid]
            );
        }
        return (array)$comps;
    } else {
        return (array)$DB->get_records_sql(
            "SELECT DISTINCT c.id, c.shortname
               FROM {local_comp_report_ext_prac} p
               JOIN {competency} c ON c.id = p.competencyid
              WHERE p.assessmentid = :asmtid AND p.courseid = :courseid
           ORDER BY c.shortname ASC",
            ['asmtid' => (int)$asmt->id, 'courseid' => $courseid]
        );
    }
};

$finalcomps = $getcomps($finalasmt);
$oralcomps  = $getcomps($oralasmt);

$threshold = (float)(get_config('local_comp_report_ext', 'success_threshold') ?: 60.0);
$allcoursequizzes = $DB->get_records('quiz', ['course' => $courseid], 'id ASC', 'id, name, grade, sumgrades');

[$finalrawgrades, $finalpctgrades, $finalretakeinfo, $finalcompgrades, $finalmaxgrade] =
    local_comp_report_ext_resolve_assessment_data($finalasmt, $students, $allcoursequizzes, $threshold, $courseid);

[$oralrawgrades, $oralpctgrades, $oralretakeinfo, $oralcompgrades, $oralmaxgrade] =
    local_comp_report_ext_resolve_assessment_data($oralasmt, $students, $allcoursequizzes, $threshold, $courseid);


// Combined unique competencies list for the weighted summary.
$alluniquecomps = [];
foreach ($finalcomps as $cid => $comp) {
    $alluniquecomps[$cid] = $comp;
}
foreach ($oralcomps as $cid => $comp) {
    if (!isset($alluniquecomps[$cid])) {
        $alluniquecomps[$cid] = $comp;
    }
}

$threshold   = (float)(get_config('local_comp_report_ext', 'success_threshold') ?: 60.0);
$finalweight = $finalasmt ? (float)$finalasmt->weight : 50.0;
$oralweight  = $oralasmt ? (float)$oralasmt->weight : 50.0;
$totalweight = $finalweight + $oralweight;
if ($totalweight <= 0) {
    $totalweight = 100.0;
}

// Compute summary KPIs.
$statfinal    = [];
$statoral     = [];
$statweighted = [];
$passedcount  = 0;

foreach ($students as $student) {
    $uid = (int)$student->id;
    $finalraw = isset($finalrawgrades[$uid]) ? $finalrawgrades[$uid] : null;
    $finalpct = isset($finalpctgrades[$uid]) ? $finalpctgrades[$uid] : null;
    if ($finalpct !== null) {
        $statfinal[] = $finalpct;
    }

    $oralraw = isset($oralrawgrades[$uid]) ? $oralrawgrades[$uid] : null;
    $oralpct = isset($oralpctgrades[$uid]) ? $oralpctgrades[$uid] : null;
    if ($oralpct !== null) {
        $statoral[] = $oralpct;
    }

    $weightedgrade = null;
    if ($finalpct !== null && $oralpct !== null) {
        $weightedgrade = (($finalpct * $finalweight) + ($oralpct * $oralweight)) / $totalweight;
    } else if ($finalpct !== null) {
        $weightedgrade = $finalpct;
    } else if ($oralpct !== null) {
        $weightedgrade = $oralpct;
    }

    if ($weightedgrade !== null) {
        $statweighted[] = $weightedgrade;
        if ($weightedgrade >= $threshold) {
            $passedcount++;
        }
    }
}

$totalstudents = count($students);
$avgfinal = !empty($statfinal) ? array_sum($statfinal) / count($statfinal) : 0.0;
$avgoral  = !empty($statoral) ? array_sum($statoral) / count($statoral) : 0.0;
$avgtotal = !empty($statweighted) ? array_sum($statweighted) / count($statweighted) : 0.0;
$passrate = ($totalstudents > 0) ? ($passedcount / $totalstudents) * 100.0 : 0.0;

// Dynamic sizing and percentage column width distribution.
$nfinalcomps    = count($finalcomps);
$noralcomps     = count($oralcomps);
$nweightedcomps = count($alluniquecomps);

$finalcolscount   = 1 + $nfinalcomps;
$oralcolscount    = 1 + $noralcomps;
$summarycolscount = 2 + $nweightedcomps;
$totalremcols     = $finalcolscount + $oralcolscount + $summarycolscount;
$totalcols        = 3 + $totalremcols;

if ($totalcols <= 12) {
    $fontsize    = '7.5pt';
    $cellpadding = '3';
    $wid         = 8.0;
    $wname       = 14.0;
    $wgroup      = 8.0;
} else if ($totalcols <= 16) {
    $fontsize    = '6.8pt';
    $cellpadding = '2';
    $wid         = 7.0;
    $wname       = 12.0;
    $wgroup      = 7.0;
} else {
    $fontsize    = '6.0pt';
    $cellpadding = '1.5';
    $wid         = 6.0;
    $wname       = 10.0;
    $wgroup      = 6.0;
}

$wstudenttotal = $wid + $wname + $wgroup;
$remtotalwidth = 100.0 - $wstudenttotal;

$wpercol       = round($remtotalwidth / max(1, $totalremcols), 2);
$wfinaltotal   = round($wpercol * $finalcolscount, 2);
$woraltotal    = round($wpercol * $oralcolscount, 2);
$wsummarytotal = round(100.0 - $wstudenttotal - $wfinaltotal - $woraltotal, 2);
$wstatus       = round($wsummarytotal - ($wpercol * (1 + $nweightedcomps)), 2);

$fname = $finalasmt ? format_string($finalasmt->name) : get_string('final_exam', 'local_comp_report_ext');
$oname = $oralasmt ? format_string($oralasmt->name) : get_string('oral_practical_exam', 'local_comp_report_ext');

// TCPDF Generation.
$pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator('Moodle');
$pdf->SetTitle(get_string('tab_final_report', 'local_comp_report_ext'));
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);

$isrtl = right_to_left();
$pdf->setRTL($isrtl);

$pdf->SetMargins(8, 8, 8);
$pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);
$pdf->AddPage();

local_comp_report_ext_render_pdf_header_logos($pdf, true);

// Title.
$pdf->SetFont('freeserif', 'B', 13);
$pdf->Cell(0, 7, get_string('tab_final_report', 'local_comp_report_ext') . ' — ' . format_string($course->fullname), 0, 1, 'C');
$pdf->SetFont('freeserif', '', 8.5);
$pdf->Cell(0, 5, get_string('group', 'local_comp_report_ext') . ': ' . $groupname . ' | ' . userdate(time()), 0, 1, 'C');
$pdf->Ln(2);

// KPI summary row.
if ($totalstudents > 0) {
    $kpihtml = '<table cellpadding="3" style="margin-bottom: 6px; font-size: 7.5pt; text-align: center;"><tr>';
    $kpihtml .= '<td width="25%" bgcolor="#eff6ff" style="border: 1px solid #bfdbfe; color: #1e40af;"><b>'
        . s($fname) . ' (' . $finalweight . '%)</b><br><span style="font-size: 10pt; font-weight: bold;">'
        . number_format($avgfinal, 1) . '%</span></td>';
    $kpihtml .= '<td width="25%" bgcolor="#f0fdfa" style="border: 1px solid #99f6e4; color: #0f766e;"><b>'
        . s($oname) . ' (' . $oralweight . '%)</b><br><span style="font-size: 10pt; font-weight: bold;">'
        . number_format($avgoral, 1) . '%</span></td>';
    $kpihtml .= '<td width="25%" bgcolor="#eef2ff" style="border: 1px solid #c7d2fe; color: #4338ca;"><b>'
        . s(get_string('final_weighted_total', 'local_comp_report_ext'))
        . ' (100%)</b><br><span style="font-size: 10pt; font-weight: bold;">'
        . number_format($avgtotal, 1) . '%</span></td>';
    $kpihtml .= '<td width="25%" bgcolor="#f0fdf4" style="border: 1px solid #bbf7d0; color: #15803d;"><b>'
        . s(get_string('overall_status', 'local_comp_report_ext'))
        . '</b><br><span style="font-size: 10pt; font-weight: bold;">'
        . number_format($passrate, 1) . '%</span> (' . $passedcount . ' / ' . $totalstudents . ')</td>';
    $kpihtml .= '</tr></table>';
    $pdf->writeHTML($kpihtml, true, false, false, false, '');
    $pdf->Ln(1);
}

// Build HTML table.
$tablehtml = '<table border="1" cellpadding="' . $cellpadding
    . '" style="border-collapse: collapse; font-size: ' . $fontsize . '; text-align: center;">';
$tablehtml .= '<thead>';

// Header Tier 1.
$tablehtml .= '<tr style="font-weight: bold; color: #ffffff;">';
$tablehtml .= '<th colspan="3" width="' . $wstudenttotal . '%" bgcolor="#1e293b" align="center">'
    . s(get_string('student', 'local_comp_report_ext')) . '</th>';
$tablehtml .= '<th colspan="' . $finalcolscount . '" width="' . $wfinaltotal . '%" bgcolor="#1e40af" align="center">'
    . s($fname) . ' (' . $finalweight . '%)</th>';
$tablehtml .= '<th colspan="' . $oralcolscount . '" width="' . $woraltotal . '%" bgcolor="#0f766e" align="center">'
    . s($oname) . ' (' . $oralweight . '%)</th>';
$tablehtml .= '<th colspan="' . $summarycolscount . '" width="' . $wsummarytotal . '%" bgcolor="#4338ca" align="center">'
    . s(get_string('final_report_title', 'local_comp_report_ext')) . ' (100%)</th>';
$tablehtml .= '</tr>';

// Header Tier 2.
$tablehtml .= '<tr bgcolor="#f1f5f9" style="font-weight: bold;">';
$tablehtml .= '<th width="' . $wid . '%">' . s(get_string('idnumber', 'local_comp_report_ext')) . '</th>';
$tablehtml .= '<th width="' . $wname . '%" align="left">' . s(get_string('studentname', 'local_comp_report_ext')) . '</th>';
$tablehtml .= '<th width="' . $wgroup . '%">' . s(get_string('group', 'local_comp_report_ext')) . '</th>';

$tablehtml .= '<th width="' . $wpercol . '%">' . s(get_string('exam_score_value', 'local_comp_report_ext'))
    . '<br>(/' . number_format($finalmaxgrade, 1) . ')</th>';
foreach ($finalcomps as $comp) {
    $tablehtml .= '<th width="' . $wpercol . '%">' . s($comp->shortname) . '<br>(%)</th>';
}

$tablehtml .= '<th width="' . $wpercol . '%">' . s(get_string('exam_score_value', 'local_comp_report_ext'))
    . '<br>(/' . number_format($oralmaxgrade, 1) . ')</th>';
foreach ($oralcomps as $comp) {
    $tablehtml .= '<th width="' . $wpercol . '%">' . s($comp->shortname) . '<br>(%)</th>';
}

$tablehtml .= '<th width="' . $wpercol . '%">' . s(get_string('final_weighted_total', 'local_comp_report_ext')) . '<br>(%)</th>';
foreach ($alluniquecomps as $comp) {
    $tablehtml .= '<th width="' . $wpercol . '%">' . s($comp->shortname) . '<br>(%)</th>';
}
$tablehtml .= '<th width="' . $wstatus . '%">' . s(get_string('overall_status', 'local_comp_report_ext')) . '</th>';
$tablehtml .= '</tr>';
$tablehtml .= '</thead><tbody>';

foreach ($students as $student) {
    $uid = (int)$student->id;
    $finalraw = isset($finalrawgrades[$uid]) ? $finalrawgrades[$uid] : null;
    $finalpct = isset($finalpctgrades[$uid]) ? $finalpctgrades[$uid] : null;

    $oralraw = isset($oralrawgrades[$uid]) ? $oralrawgrades[$uid] : null;
    $oralpct = isset($oralpctgrades[$uid]) ? $oralpctgrades[$uid] : null;

    $weightedgrade = null;
    if ($finalpct !== null && $oralpct !== null) {
        $weightedgrade = (($finalpct * $finalweight) + ($oralpct * $oralweight)) / $totalweight;
    } else if ($finalpct !== null) {
        $weightedgrade = $finalpct;
    } else if ($oralpct !== null) {
        $weightedgrade = $oralpct;
    }

    $ispass = ($weightedgrade !== null && $weightedgrade >= $threshold);

    $academicid = !empty($student->username) ? $student->username : (!empty($student->idnumber) ? $student->idnumber : '-');
    $tablehtml .= '<tr>';
    $tablehtml .= '<td width="' . $wid . '%">' . s($academicid) . '</td>';
    $tablehtml .= '<td width="' . $wname . '%" align="left"><b>' . s(fullname($student)) . '</b></td>';
    $tablehtml .= '<td width="' . $wgroup . '%">' . s($student->groupname ?: '-') . '</td>';

    // Final score.
    $finalcell = '-';
    if ($finalraw !== null) {
        $finalcell = '<b>' . number_format($finalraw, 1) . '</b>';
        if (!empty($finalretakeinfo[$uid]['is_retake'])) {
            $finalcell .= '<br><span style="color:#b45309;font-size:6.5pt;font-weight:bold;">['
                . s($finalretakeinfo[$uid]['label']) . ']</span>';
        }
    }
    $tablehtml .= '<td width="' . $wpercol . '%">' . $finalcell . '</td>';
    foreach ($finalcomps as $cid => $comp) {
        $pct = isset($finalcompgrades[$uid][$cid]) ? $finalcompgrades[$uid][$cid] : null;
        $tablehtml .= '<td width="' . $wpercol . '%">' . (($pct !== null) ? number_format($pct, 1) . '%' : '-') . '</td>';
    }

    // Oral score.
    $oralcell = '-';
    if ($oralraw !== null) {
        $oralcell = '<b>' . number_format($oralraw, 1) . '</b>';
        if (!empty($oralretakeinfo[$uid]['is_retake'])) {
            $oralcell .= '<br><span style="color:#b45309;font-size:6.5pt;font-weight:bold;">['
                . s($oralretakeinfo[$uid]['label']) . ']</span>';
        }
    }
    $tablehtml .= '<td width="' . $wpercol . '%">' . $oralcell . '</td>';
    foreach ($oralcomps as $cid => $comp) {
        $pct = isset($oralcompgrades[$uid][$cid]) ? $oralcompgrades[$uid][$cid] : null;
        $tablehtml .= '<td width="' . $wpercol . '%">' . (($pct !== null) ? number_format($pct, 1) . '%' : '-') . '</td>';
    }

    // Weighted grade.
    $color = $ispass ? '#15803d' : '#b91c1c';
    $tablehtml .= '<td width="' . $wpercol . '%" style="color:' . $color . ';"><b>'
        . (($weightedgrade !== null) ? number_format($weightedgrade, 1) . '%' : '-') . '</b></td>';

    // Weighted comps.
    foreach ($alluniquecomps as $cid => $comp) {
        $p1 = isset($finalcompgrades[$uid][$cid]) ? $finalcompgrades[$uid][$cid] : null;
        $p2 = isset($oralcompgrades[$uid][$cid]) ? $oralcompgrades[$uid][$cid] : null;
        $wcomp = null;
        if ($p1 !== null && $p2 !== null) {
            $wcomp = (($p1 * $finalweight) + ($p2 * $oralweight)) / $totalweight;
        } else if ($p1 !== null) {
            $wcomp = $p1;
        } else if ($p2 !== null) {
            $wcomp = $p2;
        }
        $tablehtml .= '<td width="' . $wpercol . '%">' . (($wcomp !== null) ? number_format($wcomp, 1) . '%' : '-') . '</td>';
    }

    // Status.
    $statuslabel = ($weightedgrade !== null)
        ? ($ispass ? get_string('status_passed', 'local_comp_report_ext') : get_string('status_atrisk', 'local_comp_report_ext'))
        : '-';
    $tablehtml .= '<td width="' . $wstatus . '%" style="color:' . $color . '; font-weight: bold;">' . s($statuslabel) . '</td>';
    $tablehtml .= '</tr>';
}

$tablehtml .= '</tbody></table>';

$pdf->SetFont('freeserif', '', 8);
$pdf->writeHTML($tablehtml, true, false, false, false, '');

$cleanfilename = 'Final_Report_' . clean_filename(format_string($course->shortname)) . '_' . date('Ymd_His') . '.pdf';
$pdf->Output($cleanfilename, 'I');
exit;
