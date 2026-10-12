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
 * Excel export for Course Final Comprehensive Evaluation Report.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/excellib.class.php');
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

// Students fetch.
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

// Competencies extraction.
$getcomps = function ($asmt) use ($DB, $courseid) {
    if (!$asmt) {
        return [];
    }
    if (!empty($asmt->quizid)) {
        $sql = "SELECT DISTINCT c.id, c.shortname
                  FROM {quiz_attempts} quiza
                  JOIN {question_usages} qu    ON qu.id = quiza.uniqueid
                  JOIN {question_attempts} qa  ON qa.questionusageid = qu.id
                  JOIN {qbank_comp_ext_qmap} m ON m.questionid = qa.questionid
                  JOIN {competency} c          ON c.id = m.competencyid
                 WHERE quiza.quiz = :quizid
              ORDER BY c.shortname ASC";
        $comps = $DB->get_records_sql($sql, ['quizid' => (int)$asmt->quizid]);
        if (empty($comps)) {
            $comps = $DB->get_records_sql(
                "SELECT DISTINCT c.id, c.shortname FROM {qbank_comp_ext_qmap} m JOIN {competency} c ON c.id = m.competencyid WHERE m.courseid = :courseid ORDER BY c.shortname ASC",
                ['courseid' => $courseid]
            );
        }
        return (array)$comps;
    } else {
        return (array)$DB->get_records_sql(
            "SELECT DISTINCT c.id, c.shortname FROM {local_comp_report_ext_prac} p JOIN {competency} c ON c.id = p.competencyid WHERE p.assessmentid = :asmtid AND p.courseid = :courseid ORDER BY c.shortname ASC",
            ['asmtid' => (int)$asmt->id, 'courseid' => $courseid]
        );
    }
};

$finalcomps = $getcomps($finalasmt);
$oralcomps  = $getcomps($oralasmt);

$alluniquecomps = [];
foreach ($finalcomps as $cid => $comp) {
    $alluniquecomps[$cid] = $comp;
}
foreach ($oralcomps as $cid => $comp) {
    if (!isset($alluniquecomps[$cid])) {
        $alluniquecomps[$cid] = $comp;
    }
}

$threshold = (float)(get_config('local_comp_report_ext', 'success_threshold') ?: 60.0);
$allcoursequizzes = $DB->get_records('quiz', ['course' => $courseid], 'id ASC', 'id, name, grade, sumgrades');

[$finalrawgrades, $finalpctgrades, $finalretakeinfo, $finalcompgrades, $finalmaxgrade] =
    local_comp_report_ext_resolve_assessment_data($finalasmt, $students, $allcoursequizzes, $threshold, $courseid);

[$oralrawgrades, $oralpctgrades, $oralretakeinfo, $oralcompgrades, $oralmaxgrade] =
    local_comp_report_ext_resolve_assessment_data($oralasmt, $students, $allcoursequizzes, $threshold, $courseid);


$threshold   = (float)(get_config('local_comp_report_ext', 'success_threshold') ?: 60.0);
$finalweight = $finalasmt ? (float)$finalasmt->weight : 50.0;
$oralweight  = $oralasmt ? (float)$oralasmt->weight : 50.0;
$totalweight = $finalweight + $oralweight;
if ($totalweight <= 0) {
    $totalweight = 100.0;
}

// Build Excel File.
$cleanfilename = 'Final_Report_' . clean_filename(format_string($course->shortname)) . '_' . date('Ymd_His') . '.xlsx';
$workbook      = new MoodleExcelWorkbook('-');
$workbook->send($cleanfilename);
$worksheet     = $workbook->add_worksheet(core_text::substr(get_string('tab_final_report', 'local_comp_report_ext'), 0, 31));

// Formats.
$formattitle = $workbook->add_format(['bold' => 1, 'size' => 14, 'align' => 'center', 'v_align' => 'vcenter']);
$formatmeta  = $workbook->add_format(['italic' => 1, 'size' => 10, 'align' => 'left', 'v_align' => 'vcenter']);
$formatfinaltier = $workbook->add_format(['bold' => 1, 'size' => 10, 'align' => 'center', 'v_align' => 'vcenter', 'pattern' => 1, 'bg_color' => '#1e40af', 'color' => '#ffffff']);
$formatoraltier  = $workbook->add_format(['bold' => 1, 'size' => 10, 'align' => 'center', 'v_align' => 'vcenter', 'pattern' => 1, 'bg_color' => '#0f766e', 'color' => '#ffffff']);
$formatsummtier  = $workbook->add_format(['bold' => 1, 'size' => 10, 'align' => 'center', 'v_align' => 'vcenter', 'pattern' => 1, 'bg_color' => '#4338ca', 'color' => '#ffffff']);
$formatinfotier  = $workbook->add_format(['bold' => 1, 'size' => 10, 'align' => 'center', 'v_align' => 'vcenter', 'pattern' => 1, 'bg_color' => '#1e293b', 'color' => '#ffffff']);
$formatsubhdr    = $workbook->add_format(['bold' => 1, 'size' => 9, 'align' => 'center', 'v_align' => 'vcenter', 'pattern' => 1, 'bg_color' => '#f1f5f9', 'border' => 1]);
$formatcell      = $workbook->add_format(['size' => 9, 'align' => 'center', 'v_align' => 'vcenter', 'border' => 1]);
$formatcellleft  = $workbook->add_format(['size' => 9, 'align' => 'left', 'v_align' => 'vcenter', 'border' => 1]);
$formatcellpass  = $workbook->add_format(['size' => 9, 'align' => 'center', 'v_align' => 'vcenter', 'border' => 1, 'bold' => 1, 'color' => '#15803d']);
$formatcellfail  = $workbook->add_format(['size' => 9, 'align' => 'center', 'v_align' => 'vcenter', 'border' => 1, 'bold' => 1, 'color' => '#b91c1c']);

// Title & Meta.
$worksheet->write_string(0, 0, local_comp_report_ext_safe_excel_str(format_string($course->fullname) . ' — ' . get_string('tab_final_report', 'local_comp_report_ext')), $formattitle);
$worksheet->write_string(1, 0, local_comp_report_ext_safe_excel_str(get_string('group', 'local_comp_report_ext') . ': ' . $groupname . ' | ' . userdate(time())), $formatmeta);

// Top Tier Headers.
$currentcol = 0;
// Info.
$worksheet->write_string(3, $currentcol, local_comp_report_ext_safe_excel_str(get_string('student', 'local_comp_report_ext')), $formatinfotier);
$currentcol += 3;

// Final Exam Tier.
$fname = $finalasmt ? format_string($finalasmt->name) : get_string('final_exam', 'local_comp_report_ext');
$worksheet->write_string(3, $currentcol, local_comp_report_ext_safe_excel_str($fname . ' (' . $finalweight . '%)'), $formatfinaltier);
$currentcol += 1 + count($finalcomps);

// Oral Exam Tier.
$oname = $oralasmt ? format_string($oralasmt->name) : get_string('oral_practical_exam', 'local_comp_report_ext');
$worksheet->write_string(3, $currentcol, local_comp_report_ext_safe_excel_str($oname . ' (' . $oralweight . '%)'), $formatoraltier);
$currentcol += 1 + count($oralcomps);

// Summary Tier.
$worksheet->write_string(3, $currentcol, local_comp_report_ext_safe_excel_str(get_string('final_report_title', 'local_comp_report_ext') . ' (100%)'), $formatsummtier);

// Sub-headers.
$col = 0;
$worksheet->write_string(4, $col++, local_comp_report_ext_safe_excel_str(get_string('idnumber', 'local_comp_report_ext')), $formatsubhdr);
$worksheet->write_string(4, $col++, local_comp_report_ext_safe_excel_str(get_string('studentname', 'local_comp_report_ext')), $formatsubhdr);
$worksheet->write_string(4, $col++, local_comp_report_ext_safe_excel_str(get_string('group', 'local_comp_report_ext')), $formatsubhdr);

// Final Exam columns.
$worksheet->write_string(4, $col++, local_comp_report_ext_safe_excel_str(get_string('exam_score_value', 'local_comp_report_ext') . ' (/' . number_format($finalmaxgrade, 1) . ')'), $formatsubhdr);
foreach ($finalcomps as $comp) {
    $worksheet->write_string(4, $col++, local_comp_report_ext_safe_excel_str(format_string($comp->shortname) . ' (%)'), $formatsubhdr);
}

// Oral Exam columns.
$worksheet->write_string(4, $col++, local_comp_report_ext_safe_excel_str(get_string('exam_score_value', 'local_comp_report_ext') . ' (/' . number_format($oralmaxgrade, 1) . ')'), $formatsubhdr);
foreach ($oralcomps as $comp) {
    $worksheet->write_string(4, $col++, local_comp_report_ext_safe_excel_str(format_string($comp->shortname) . ' (%)'), $formatsubhdr);
}

// Summary columns.
$worksheet->write_string(4, $col++, local_comp_report_ext_safe_excel_str(get_string('final_weighted_total', 'local_comp_report_ext') . ' (%)'), $formatsubhdr);
foreach ($alluniquecomps as $comp) {
    $worksheet->write_string(4, $col++, local_comp_report_ext_safe_excel_str(format_string($comp->shortname) . ' (%)'), $formatsubhdr);
}
$worksheet->write_string(4, $col++, local_comp_report_ext_safe_excel_str(get_string('overall_status', 'local_comp_report_ext')), $formatsubhdr);

// Data Rows.
$rowidx = 5;
foreach ($students as $student) {
    $uid = (int)$student->id;
    $c = 0;

    $academicid = !empty($student->username) ? $student->username : (!empty($student->idnumber) ? $student->idnumber : '—');
    $worksheet->write_string($rowidx, $c++, local_comp_report_ext_safe_excel_str($academicid), $formatcell);
    $worksheet->write_string($rowidx, $c++, local_comp_report_ext_safe_excel_str(fullname($student)), $formatcellleft);
    $worksheet->write_string($rowidx, $c++, local_comp_report_ext_safe_excel_str($student->groupname ?: '—'), $formatcell);

    // Final Exam Score (value).
    $finalraw = isset($finalrawgrades[$uid]) ? $finalrawgrades[$uid] : null;
    $finalpct = isset($finalpctgrades[$uid]) ? $finalpctgrades[$uid] : null;
    if ($finalraw !== null) {
        if (!empty($finalretakeinfo[$uid]['is_retake'])) {
            $finalvalstr = number_format($finalraw, 1) . ' (' . $finalretakeinfo[$uid]['label'] . ')';
            $worksheet->write_string($rowidx, $c++, local_comp_report_ext_safe_excel_str($finalvalstr), $formatcell);
        } else {
            $worksheet->write_number($rowidx, $c++, round($finalraw, 1), $formatcell);
        }
    } else {
        $worksheet->write_string($rowidx, $c++, '—', $formatcell);
    }

    // Final Comps (%).
    foreach ($finalcomps as $cid => $comp) {
        $pct = isset($finalcompgrades[$uid][$cid]) ? $finalcompgrades[$uid][$cid] : null;
        $worksheet->write_string($rowidx, $c++, ($pct !== null) ? number_format($pct, 1) . '%' : '—', $formatcell);
    }

    // Oral Exam Score (value).
    $oralraw = isset($oralrawgrades[$uid]) ? $oralrawgrades[$uid] : null;
    $oralpct = isset($oralpctgrades[$uid]) ? $oralpctgrades[$uid] : null;
    if ($oralraw !== null) {
        if (!empty($oralretakeinfo[$uid]['is_retake'])) {
            $oralvalstr = number_format($oralraw, 1) . ' (' . $oralretakeinfo[$uid]['label'] . ')';
            $worksheet->write_string($rowidx, $c++, local_comp_report_ext_safe_excel_str($oralvalstr), $formatcell);
        } else {
            $worksheet->write_number($rowidx, $c++, round($oralraw, 1), $formatcell);
        }
    } else {
        $worksheet->write_string($rowidx, $c++, '—', $formatcell);
    }

    // Oral Comps (%).
    foreach ($oralcomps as $cid => $comp) {
        $pct = isset($oralcompgrades[$uid][$cid]) ? $oralcompgrades[$uid][$cid] : null;
        $worksheet->write_string($rowidx, $c++, ($pct !== null) ? number_format($pct, 1) . '%' : '—', $formatcell);
    }

    // Weighted Grade (%).
    $weightedgrade = null;
    if ($finalpct !== null && $oralpct !== null) {
        $weightedgrade = (($finalpct * $finalweight) + ($oralpct * $oralweight)) / $totalweight;
    } else if ($finalpct !== null) {
        $weightedgrade = $finalpct;
    } else if ($oralpct !== null) {
        $weightedgrade = $oralpct;
    }
    $worksheet->write_string($rowidx, $c++, ($weightedgrade !== null) ? number_format($weightedgrade, 1) . '%' : '—', $formatcell);

    // Weighted Comps (%).
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
        $worksheet->write_string($rowidx, $c++, ($wcomp !== null) ? number_format($wcomp, 1) . '%' : '—', $formatcell);
    }

    // Pass Status.
    if ($weightedgrade !== null) {
        $ispass = ($weightedgrade >= $threshold);
        $worksheet->write_string(
            $rowidx,
            $c++,
            local_comp_report_ext_safe_excel_str($ispass ? get_string('status_passed', 'local_comp_report_ext') : get_string('status_atrisk', 'local_comp_report_ext')),
            $ispass ? $formatcellpass : $formatcellfail
        );
    } else {
        $worksheet->write_string($rowidx, $c++, '—', $formatcell);
    }

    $rowidx++;
}

$workbook->close();
exit;
