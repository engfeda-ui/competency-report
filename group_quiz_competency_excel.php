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
 * Excel report generator for Quiz Competency Analysis report.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/excellib.class.php');

$courseid = required_param('courseid', PARAM_INT);
$groupid  = optional_param('groupid', 0, PARAM_INT);
$quizid   = optional_param('quizid', 0, PARAM_INT);

require_login($courseid);
$context = context_course::instance($courseid);
require_capability('mod/quiz:viewreports', $context);

global $DB;

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
if ($groupid > 0) {
    $grouprow  = $DB->get_record('groups', ['id' => $groupid, 'courseid' => $courseid]);
    $groupname = $grouprow ? format_string($grouprow->name) : get_string('allgroups', 'local_comp_report_ext');
} else {
    $groupname = get_string('allgroups', 'local_comp_report_ext');
}

$quizname = '—';
$maxgrade = 0.0;
if ($quizid > 0) {
    $quizrecord = $DB->get_record('quiz', ['id' => $quizid, 'course' => $courseid]);
    if ($quizrecord) {
        $quizname = format_string($quizrecord->name);
        $maxgrade = (float)($quizrecord->grade ?? 0);
    }
}

// 1. Retrieve students (students only).
$studentrole = $DB->get_record('role', ['shortname' => 'student'], 'id');
if (!$studentrole) {
    $studentrole = $DB->get_record('role', ['archetype' => 'student'], 'id');
}
$students = [];
if ($studentrole) {
    $students = (array) get_role_users(
        $studentrole->id,
        $context,
        false,
        'u.*',
        'u.idnumber ASC, u.lastname ASC, u.firstname ASC',
        false,
        ($groupid > 0 ? $groupid : '')
    );
}

// 2. Fetch mapped competencies list - scoped to this quiz.
$competencies = [];
if ($quizid > 0) {
    $competencies = (array)$DB->get_records_sql("
        SELECT DISTINCT c.id, c.shortname
          FROM {quiz_attempts} quiza
          JOIN {question_usages} qu ON qu.id = quiza.uniqueid
          JOIN {question_attempts} qa ON qa.questionusageid = qu.id
          JOIN {qbank_comp_ext_qmap} m ON m.questionid = qa.questionid
          JOIN {competency} c ON c.id = m.competencyid
         WHERE quiza.quiz = :quizid
      ORDER BY c.shortname ASC", ['quizid' => $quizid]);
}

if (empty($competencies)) {
    throw new moodle_exception('nocompetencies', 'local_comp_report_ext');
}

// 3. Performance data query & group memberships.
$usergroups = [];
$scoremap   = [];
$graderecords = [];

if (!empty($students)) {
    $studentids = array_keys($students);
    [$insql, $inparams] = $DB->get_in_or_equal($studentids, SQL_PARAMS_NAMED, 'uid');
    $inparams['quizid'] = $quizid;

    // Groups.
    $gmparams = array_merge(['courseid' => $courseid], $inparams);
    unset($gmparams['quizid']);
    $gmrecords = $DB->get_records_sql("
        SELECT gm.id, gm.userid, g.id AS groupid, g.name AS groupname
          FROM {groups_members} gm
          JOIN {groups} g ON g.id = gm.groupid
         WHERE g.courseid = :courseid AND gm.userid $insql
      ORDER BY g.name ASC
    ", $gmparams);

    foreach ($gmrecords as $gm) {
        $usergroups[$gm->userid][] = format_string($gm->groupname);
    }

    $inparams['subquizid'] = $quizid;
    // Question fraction scores.
    $rawscores = (array)$DB->get_records_sql("
        SELECT
            CONCAT(quiza.userid, '_', m.competencyid) as unique_key,
            quiza.userid, m.competencyid,
            SUM(qa.maxfraction) AS total_max, SUM(qas.fraction) AS total_fraction
        FROM {quiz_attempts} quiza
        JOIN {question_usages} qu ON qu.id = quiza.uniqueid
        JOIN {question_attempts} qa ON qa.questionusageid = qu.id
        JOIN {qbank_comp_ext_qmap} m ON m.questionid = qa.questionid
        JOIN (
            SELECT s.questionattemptid, MAX(s.fraction) AS fraction
            FROM {question_attempt_steps} s
            JOIN {question_attempts} qa2 ON qa2.id = s.questionattemptid
            JOIN {question_usages} qu2 ON qu2.id = qa2.questionusageid
            JOIN {quiz_attempts} qa3 ON qa3.uniqueid = qu2.id
            WHERE qa3.quiz = :subquizid AND qa3.state = 'finished'
            GROUP BY s.questionattemptid
        ) qas ON qas.questionattemptid = qa.id
        WHERE quiza.quiz = :quizid AND quiza.state = 'finished'
          AND quiza.userid $insql
        GROUP BY quiza.userid, m.competencyid", $inparams);

    foreach ($rawscores as $rs) {
        $scoremap[$rs->userid][$rs->competencyid] = [
            'att' => (float)$rs->total_max,
            'cor' => (float)$rs->total_fraction,
        ];
    }

    // Quiz grades.
    [$ginsql, $ginparams] = $DB->get_in_or_equal($studentids, SQL_PARAMS_NAMED, 'gid');
    $ginparams['gquizid'] = $quizid;
    $graderecords = $DB->get_records_sql(
        "SELECT userid, grade FROM {quiz_grades} WHERE quiz = :gquizid AND userid $ginsql",
        $ginparams
    );
}

/**
 * Sanitize cell values against CSV/Excel Formula Injection.
 *
 * @param mixed $val
 * @return string
 */
function safe_excel_str($val): string {
    $str = (string)$val;
    if ($str !== '' && in_array($str[0], ['=', '+', '-', '@', "\t", "\r"])) {
        return "'" . $str;
    }
    return $str;
}

// 4. Create Excel Workbook.
$cleanfilename = 'Quiz_Competency_' . clean_filename($quizname) . '_' . date('Ymd_His') . '.xlsx';
$workbook = new MoodleExcelWorkbook($cleanfilename);
$worksheet = $workbook->add_worksheet(mb_substr(clean_param($quizname, PARAM_TEXT), 0, 31));

// Formatting styles.
$format_title = $workbook->add_format([
    'bold' => 1,
    'size' => 14,
    'align' => 'left',
    'color' => 'navy',
]);
$format_meta = $workbook->add_format([
    'size' => 10,
    'color' => 'gray',
]);
$format_header = $workbook->add_format([
    'bold' => 1,
    'color' => 'white',
    'bg_color' => 'navy',
    'border' => 1,
    'align' => 'center',
    'valign' => 'vcenter',
]);
$format_header_left = $workbook->add_format([
    'bold' => 1,
    'color' => 'white',
    'bg_color' => 'navy',
    'border' => 1,
    'align' => 'left',
    'valign' => 'vcenter',
]);
$format_cell = $workbook->add_format([
    'border' => 1,
    'align' => 'center',
    'valign' => 'vcenter',
]);
$format_cell_left = $workbook->add_format([
    'border' => 1,
    'align' => 'left',
    'valign' => 'vcenter',
]);
$format_cell_bold = $workbook->add_format([
    'bold' => 1,
    'border' => 1,
    'align' => 'left',
    'valign' => 'vcenter',
]);
$format_total = $workbook->add_format([
    'bold' => 1,
    'bg_color' => 'silver',
    'border' => 1,
    'align' => 'center',
    'valign' => 'vcenter',
]);
$format_total_left = $workbook->add_format([
    'bold' => 1,
    'bg_color' => 'silver',
    'border' => 1,
    'align' => 'left',
    'valign' => 'vcenter',
]);

// Write Title & Metadata.
$row = 0;
$worksheet->write_string($row, 0, format_string($course->fullname) . ' — ' . $quizname, $format_title);
$row++;
$worksheet->write_string($row, 0, get_string('group', 'local_comp_report_ext') . ': ' . $groupname . '  |  ' . userdate(time()), $format_meta);
$row += 2;

// Table Headers.
$col = 0;
$worksheet->write_string($row, $col++, '#', $format_header);
$worksheet->write_string($row, $col++, get_string('student', 'local_comp_report_ext'), $format_header_left);
$worksheet->write_string($row, $col++, get_string('group', 'local_comp_report_ext'), $format_header_left);
$worksheet->write_string($row, $col++, get_string('grade', 'local_comp_report_ext') . ' / ' . number_format($maxgrade, 1), $format_header);

foreach ($competencies as $c) {
    $worksheet->write_string($row, $col++, format_string($c->shortname), $format_header);
}
$row++;

// Set initial column widths.
$worksheet->set_column(0, 0, 5);
$worksheet->set_column(1, 1, 30);
$worksheet->set_column(2, 2, 20);
$worksheet->set_column(3, 3, 16);
$col_idx = 4;
foreach ($competencies as $c) {
    $worksheet->set_column($col_idx, $col_idx, max(14, mb_strlen(format_string($c->shortname)) + 4));
    $col_idx++;
}

// Table Rows.
$index = 1;
$grouptotals  = [];
$totalgrades  = [];

foreach ($students as $s) {
    $col = 0;
    $worksheet->write_number($row, $col++, $index++, $format_cell);
    $worksheet->write_string($row, $col++, safe_excel_str(fullname($s)), $format_cell_bold);

    $gtext = !empty($usergroups[$s->id]) ? implode(', ', $usergroups[$s->id]) : '—';
    $worksheet->write_string($row, $col++, safe_excel_str($gtext), $format_cell_left);

    // Quiz Grade.
    if (isset($graderecords[$s->id])) {
        $sgrade = (float)$graderecords[$s->id]->grade;
        $worksheet->write_string($row, $col++, number_format($sgrade, 1) . ' / ' . number_format($maxgrade, 1), $format_cell);
        $totalgrades[] = $sgrade;
    } else {
        $worksheet->write_string($row, $col++, '—', $format_cell);
    }

    // Competencies.
    foreach ($competencies as $c) {
        if (isset($scoremap[$s->id][$c->id])) {
            $att = $scoremap[$s->id][$c->id]['att'];
            $cor = $scoremap[$s->id][$c->id]['cor'];
            if ($att > 0) {
                $rate = ($cor / $att) * 100;
                $worksheet->write_string($row, $col++, '%' . number_format($rate, 1), $format_cell);
                $grouptotals[$c->id]['att'] = ($grouptotals[$c->id]['att'] ?? 0) + $att;
                $grouptotals[$c->id]['cor'] = ($grouptotals[$c->id]['cor'] ?? 0) + $cor;
            } else {
                $worksheet->write_string($row, $col++, '—', $format_cell);
            }
        } else {
            $worksheet->write_string($row, $col++, '—', $format_cell);
        }
    }
    $row++;
}

// Total / Summary Row.
$col = 0;
$worksheet->write_string($row, $col++, '', $format_total);
$worksheet->write_string($row, $col++, get_string('total', 'local_comp_report_ext'), $format_total_left);
$worksheet->write_string($row, $col++, '', $format_total);

if (!empty($totalgrades)) {
    $avggrade = round(array_sum($totalgrades) / count($totalgrades), 1);
    $worksheet->write_string($row, $col++, number_format($avggrade, 1) . ' / ' . number_format($maxgrade, 1), $format_total);
} else {
    $worksheet->write_string($row, $col++, '—', $format_total);
}

foreach ($competencies as $c) {
    $tatt = $grouptotals[$c->id]['att'] ?? 0;
    $tcor = $grouptotals[$c->id]['cor'] ?? 0;
    if ($tatt > 0) {
        $trate = round(($tcor / $tatt) * 100, 1);
        $worksheet->write_string($row, $col++, '%' . number_format($trate, 1), $format_total);
    } else {
        $worksheet->write_string($row, $col++, '—', $format_total);
    }
}

$workbook->close();
