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
 * Main student competency report page.
 *
 * FIX: Now uses competency_calculator so weighted assessment scores are respected.
 *
 * @package    local_competency_report
 * @copyright  2026 Mahmoud Salem
 * @copyright  based on work by 2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/ai.php');

$courseid = required_param('courseid', PARAM_INT);
require_login($courseid);

$course  = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);
$userid  = $USER->id;

// Students can view their own report; teachers can view any student's report.
if (
    !has_capability('local/competency_report:viewownreport', $context)
    && !has_capability('local/competency_report:viewreports', $context)
) {
    require_capability('local/competency_report:viewownreport', $context);
}

// Page Setup.
$PAGE->set_url('/local/competency_report/student_report.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_pagelayout('course');
$PAGE->set_title(get_string('studentreport', 'local_competency_report'));
$PAGE->set_heading($course->fullname);

// ─── 1. Use competency_calculator for WEIGHTED scores ────────────────────────
$calculator    = new \local_competency_report\competency_calculator($courseid);
$studentscores = $calculator->get_student_scores($userid);
$hasweights    = $calculator->has_assessments();

// Build rows from calculator data (replaces old direct SQL query).
$rows = [];
foreach ($studentscores as $compid => $data) {
    $row                    = new stdClass();
    $row->id                = $data['competency']->id;
    $row->shortname         = $data['competency']->shortname;
    $row->description       = $data['competency']->description;
    $row->descriptionformat = $data['competency']->descriptionformat;
    $row->percent           = $data['percent'];
    $row->passed            = $data['passed'];
    $row->breakdown         = $data['breakdown'] ?? [];
    $rows[$compid]          = $row;
}

// ─── 2. Prepare Success Rates for AI Feedback ─────────────────────────────────
$rates = [];
foreach ($rows as $row) {
    $rates[$row->shortname] = $row->percent;
}

// ─── 3. Compute CLASS AVERAGE rates for the Radar Chart ───────────────────────
$classavgrows = $DB->get_records_sql("
    SELECT c.id, c.shortname,
           CAST(SUM(qa.maxfraction) AS DECIMAL(12,1)) AS questions,
           CAST(SUM(qas.fraction) AS DECIMAL(12,1)) AS correct
    FROM {quiz_attempts} quiza
    JOIN {question_usages} qu ON qu.id = quiza.uniqueid
    JOIN {question_attempts} qa ON qa.questionusageid = qu.id
    JOIN {quiz} quiz ON quiz.id = quiza.quiz
    JOIN {qbank_competency_qmap} m ON m.questionid = qa.questionid
    JOIN {competency} c ON c.id = m.competencyid
    JOIN (
        SELECT MAX(fraction) AS fraction, questionattemptid
        FROM {question_attempt_steps}
        GROUP BY questionattemptid
    ) qas ON qas.questionattemptid = qa.id
    WHERE quiz.course = :courseid AND quiza.state = 'finished'
    GROUP BY c.id, c.shortname
", ['courseid' => $courseid]);

$classrates = [];
foreach ($classavgrows as $cr) {
    $classrates[$cr->shortname] = $cr->questions ? round(($cr->correct / $cr->questions) * 100, 1) : 0;
}

// Build radar chart data.
$chartlabels  = [];
$chartstudent = [];
$chartclass   = [];
foreach ($rates as $shortname => $rate) {
    $chartlabels[]  = $shortname;
    $chartstudent[] = round($rate, 1);
    $chartclass[]   = $classrates[$shortname] ?? 0;
}
$chartdata = json_encode([
    'labels'  => $chartlabels,
    'student' => $chartstudent,
    'class'   => $chartclass,
]);

// ─── 4. Build Score Card data ──────────────────────────────────────────────────
// 4a. Exam results table: one row per configured assessment.
$threshold = (int)(get_config('local_competency_report', 'success_threshold') ?: 60);
$assessments = $DB->get_records('local_competency_report_asmt', ['courseid' => $courseid], 'id ASC');

$examrows = [];
foreach ($assessments as $asmt) {
    $examrow              = new stdClass();
    $examrow->name        = $asmt->name;
    $examrow->type        = $asmt->type;
    $examrow->typelabel   = ($asmt->type === 'practical') ? 'scorecard_practical' : 'scorecard_quiz';
    $examrow->weight      = number_format((float)$asmt->weight, 1);
    $examrow->sat         = false;
    $examrow->grade       = '-';
    $examrow->maxgrade    = '-';
    $examrow->scorepct    = null;
    $examrow->passed      = null;
    $examrow->color       = 'muted';

    if ($asmt->type === 'quiz' && !empty($asmt->quizid)) {
        // Fetch best finished attempt grade.
        $quizobj = $DB->get_record('quiz', ['id' => $asmt->quizid, 'course' => $courseid]);
        if ($quizobj) {
            $bestattempt = $DB->get_record_sql("
                SELECT SUM(qa2.maxfraction) AS maxf,
                       SUM(qas2.fraction)   AS gotf
                  FROM {quiz_attempts} qa1
                  JOIN {question_usages} qu2  ON qu2.id = qa1.uniqueid
                  JOIN {question_attempts} qa2 ON qa2.questionusageid = qu2.id
                  JOIN (
                       SELECT questionattemptid, MAX(fraction) AS fraction
                         FROM {question_attempt_steps}
                        GROUP BY questionattemptid
                  ) qas2 ON qas2.questionattemptid = qa2.id
                 WHERE qa1.quiz   = :quizid
                   AND qa1.userid = :userid
                   AND qa1.state  = 'finished'",
                ['quizid' => $asmt->quizid, 'userid' => $userid]
            );

            if ($bestattempt && $bestattempt->maxf > 0) {
                $pct               = ($bestattempt->gotf / $bestattempt->maxf) * 100;
                $examrow->sat      = true;
                $examrow->grade    = number_format($bestattempt->gotf, 1);
                $examrow->maxgrade = number_format($bestattempt->maxf, 1);
                $examrow->scorepct = number_format($pct, 1);
                $examrow->passed   = ($pct >= $threshold);
                $examrow->color    = ($pct >= 80) ? 'green' : (($pct >= 60) ? 'blue' : (($pct >= 40) ? 'orange' : 'red'));
            }
        }
    } else if ($asmt->type === 'practical') {
        // Average of all competency_percent entries for this student in this assessment.
        $practicalrows = $DB->get_records('local_competency_report_prac', [
            'assessmentid' => $asmt->id,
            'studentid'    => $userid,
        ], '', 'competency_percent');
        if (!empty($practicalrows)) {
            $sum = array_sum(array_column($practicalrows, 'competency_percent'));
            $avg = $sum / count($practicalrows);
            $examrow->sat      = true;
            $examrow->grade    = number_format($avg, 1);
            $examrow->maxgrade = '100';
            $examrow->scorepct = number_format($avg, 1);
            $examrow->passed   = ($avg >= $threshold);
            $examrow->color    = ($avg >= 80) ? 'green' : (($avg >= 60) ? 'blue' : (($avg >= 40) ? 'orange' : 'red'));
        }
    }
    $examrows[] = $examrow;
}

// 4b. Competency breakdown rows for score card.
$comprows = [];
foreach ($rows as $compid => $row) {
    $comprow              = new stdClass();
    $comprow->shortname   = $row->shortname;
    $comprow->percent     = number_format($row->percent, 1);
    $comprow->passed      = $row->passed;
    $comprow->color       = \local_competency_report\competency_calculator::rate_color($row->percent);

    // Build per-assessment breakdown sub-rows.
    $comprow->breakdown = [];
    foreach ($row->breakdown as $bd) {
        $bdrow                    = new stdClass();
        $bdrow->name              = $bd['name'];
        $bdrow->typelabel         = ($bd['type'] === 'practical') ? 'scorecard_practical' : 'scorecard_quiz';
        $bdrow->weight            = number_format($bd['weight'], 1);
        $bdrow->score_pct         = number_format($bd['score_pct'], 1);
        $bdrow->weighted_contribution = number_format($bd['weighted_contribution'], 2);
        $comprow->breakdown[]     = $bdrow;
    }
    $comprow->has_breakdown = !empty($comprow->breakdown);
    $comprows[] = $comprow;
}

// ─── 5. Prepare Render Data ────────────────────────────────────────────────────
$renderdata             = new stdClass();
$renderdata->rows       = array_values($rows);   // Legacy radar / old template rows.
$renderdata->comprows   = $comprows;              // New score card competency rows.
$renderdata->examrows   = $examrows;              // New score card exam rows.
$renderdata->hasweights = $hasweights;
$renderdata->has_examdata = !empty($examrows);
$renderdata->courseid   = $courseid;
$renderdata->userid     = $userid;
$renderdata->context    = $context;
$renderdata->pdf_url    = (new moodle_url('/local/competency_report/parent_pdf.php', ['courseid' => $courseid]))->out(false);
$renderdata->chart_data = $chartdata;
$renderdata->has_radar  = count($chartlabels) >= 2;
// AI feedback loaded on-demand via AJAX.
$renderdata->ai_comment = null;

// ─── 6. Output ────────────────────────────────────────────────────────────────
echo $OUTPUT->header();

$page = new \local_competency_report\output\student_report_page($renderdata);
echo $OUTPUT->render($page);

echo $OUTPUT->footer();
