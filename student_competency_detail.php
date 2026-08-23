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
 * Detailed competency report for a specific student.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @copyright  based on work by 2026 Hakan Ã‡iÄŸci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/ai.php'); // Include for AI commentary generation.

$courseid = required_param('courseid', PARAM_INT);
$userid   = optional_param('userid', $USER->id, PARAM_INT);

// Basic login check for the course.
require_login($courseid);
$context = context_course::instance($courseid);

// Permission check: if the user is looking at someone else's report, they must have the report viewing capability.
if ($userid != $USER->id) {
    require_capability('mod/quiz:viewreports', $context);
}

$course  = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$student = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

// Page definitions and setup.
$PAGE->set_url('/local/comp_report_ext/student_competency_detail.php', ['courseid' => $courseid, 'userid' => $userid]);
$PAGE->set_context($context);
$PAGE->set_pagelayout('course');
$PAGE->set_title(get_string('studentreport', 'local_comp_report_ext'));
$PAGE->set_heading(fullname($student) . ' - ' . $course->fullname);

// 1. Data Preparation.
// Fetch student performance broken down by competency.
$sql = "SELECT c.id, c.shortname, c.description,
               CAST(SUM(qa.maxfraction) AS DECIMAL(12, 1)) AS questions,
               CAST(SUM(qas.fraction) AS DECIMAL(12, 1)) AS correct
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

// 2. Prepare success rates for AI processing.
$rates = [];
foreach ($rows as $r) {
    $rates[$r->shortname] = $r->questions ? ($r->correct / $r->questions) * 100 : 0;
}

// 3. Fetch and prepare all course exams and attempts for this student.
$allcoursequizzes = $DB->get_records('quiz', ['course' => $courseid], 'id ASC', 'id, name, grade, sumgrades');
$retake1quizzes = [];
$retake2quizzes = [];
$primaryquizzes = [];

foreach ($allcoursequizzes as $cq) {
    $cname = $cq->name;
    $isretake1 = preg_match(
        '/(retake[\s\-]*1|1[\s]*st[\s]*retake|first[\s\-]*retake|'
        . 'إعادة[\s]*1|الإعادة[\s]*الأولى|الدور[\s]*الثاني|محاولة[\s]*2)/iu',
        $cname
    );
    $isretake2 = preg_match(
        '/(retake[\s\-]*2|2[\s]*nd[\s]*retake|second[\s\-]*retake|'
        . 'إعادة[\s]*2|الإعادة[\s]*الثانية|الدور[\s]*الثالث|محاولة[\s]*3)/iu',
        $cname
    );

    if ($isretake1) {
        $retake1quizzes[$cq->id] = $cq;
    } else if ($isretake2) {
        $retake2quizzes[$cq->id] = $cq;
    } else {
        $primaryquizzes[$cq->id] = $cq;
    }
}

$qslabel = get_string('questions_abbr', 'local_comp_report_ext');
$examrows = [];

foreach ($primaryquizzes as $pq) {
    $sumgradesmax = (float)($pq->sumgrades > 0 ? $pq->sumgrades : 100.0);
    $quizmaxgrade = (float)($pq->grade > 0 ? $pq->grade : $sumgradesmax);
    $hasdiffmax   = (abs($quizmaxgrade - $sumgradesmax) > 0.01);

    // Fetch student finished attempts on primary quiz.
    $attempts = $DB->get_records_sql(
        "SELECT id, attempt, sumgrades, timefinish
           FROM {quiz_attempts}
          WHERE quiz = :quizid AND userid = :userid AND state = 'finished'
       ORDER BY attempt ASC",
        ['quizid' => $pq->id, 'userid' => $userid]
    );

    $attscores = [];
    $attraws   = [];
    $attmaxs   = [];

    if (!empty($attempts)) {
        foreach ($attempts as $att) {
            if ($att->sumgrades !== null) {
                $attnum = (int)$att->attempt;
                $attscores[$attnum] = round(((float)$att->sumgrades / $sumgradesmax) * 100.0, 1);
                $attraws[$attnum]   = (float)$att->sumgrades;
                $attmaxs[$attnum]   = (float)$sumgradesmax;
            }
        }
    }

    $att1score = $attscores[1] ?? null;
    $att2score = $attscores[2] ?? null;
    $att3score = $attscores[3] ?? null;

    $att1raw = $attraws[1] ?? null;
    $att1max = $attmaxs[1] ?? null;
    $att2raw = $attraws[2] ?? null;
    $att2max = $attmaxs[2] ?? null;
    $att3raw = $attraws[3] ?? null;
    $att3max = $attmaxs[3] ?? null;

    // Check separate Retake 1 quiz fallback.
    if ($att2score === null && !empty($retake1quizzes)) {
        foreach ($retake1quizzes as $r1q) {
            $r1attempts = $DB->get_records_sql(
                "SELECT id, attempt, sumgrades, timefinish
                   FROM {quiz_attempts}
                  WHERE quiz = :quizid AND userid = :userid AND state = 'finished'
               ORDER BY attempt ASC",
                ['quizid' => $r1q->id, 'userid' => $userid]
            );
            if (!empty($r1attempts)) {
                $r1att = reset($r1attempts);
                if ($r1att->sumgrades !== null) {
                    $r1max = (float)($r1q->sumgrades > 0 ? $r1q->sumgrades : $sumgradesmax);
                    $att2score = round(((float)$r1att->sumgrades / $r1max) * 100.0, 1);
                    $att2raw   = (float)$r1att->sumgrades;
                    $att2max   = $r1max;
                    break;
                }
            }
        }
    }

    // Check separate Retake 2 quiz fallback.
    if ($att3score === null && !empty($retake2quizzes)) {
        foreach ($retake2quizzes as $r2q) {
            $r2attempts = $DB->get_records_sql(
                "SELECT id, attempt, sumgrades, timefinish
                   FROM {quiz_attempts}
                  WHERE quiz = :quizid AND userid = :userid AND state = 'finished'
               ORDER BY attempt ASC",
                ['quizid' => $r2q->id, 'userid' => $userid]
            );
            if (!empty($r2attempts)) {
                $r2att = reset($r2attempts);
                if ($r2att->sumgrades !== null) {
                    $r2max = (float)($r2q->sumgrades > 0 ? $r2q->sumgrades : $sumgradesmax);
                    $att3score = round(((float)$r2att->sumgrades / $r2max) * 100.0, 1);
                    $att3raw   = (float)$r2att->sumgrades;
                    $att3max   = $r2max;
                    break;
                }
            }
        }
    }

    // Only include exams where student has at least one attempt.
    if ($att1score === null && $att2score === null && $att3score === null) {
        continue;
    }

    $validscores = array_filter([$att1score, $att2score, $att3score], fn($s) => $s !== null);
    $retakecount = ($att2score !== null ? 1 : 0) + ($att3score !== null ? 1 : 0);

    $scorepct = 0.0;
    $finalraw = 0.0;
    $retakestatuslabel = '—';
    $retakestatusbadge = 'badge-secondary';

    if ($att1score !== null && $att1score >= 60.0) {
        $scorepct = $att1score;
        $finalraw = (float)$att1raw;
        $retakestatuslabel = get_string('passed_first_attempt', 'local_comp_report_ext');
        $retakestatusbadge = 'badge-success';
    } else if ($att2score !== null && $att2score >= 60.0) {
        $scorepct = 60.0;
        $finalraw = round(0.60 * $sumgradesmax, 2);
        $retakestatuslabel = get_string('passed_retake_1', 'local_comp_report_ext');
        $retakestatusbadge = 'badge-info';
    } else if ($att3score !== null && $att3score >= 60.0) {
        $scorepct = 60.0;
        $finalraw = round(0.60 * $sumgradesmax, 2);
        $retakestatuslabel = get_string('passed_retake_2', 'local_comp_report_ext');
        $retakestatusbadge = 'badge-primary';
    } else {
        $scorepct = !empty($validscores) ? max($validscores) : 0.0;
        if ($att1score !== null && $att1score === $scorepct) {
            $finalraw = (float)$att1raw;
        } else if ($att2score !== null && $att2score === $scorepct) {
            $finalraw = (float)$att2raw;
        } else if ($att3score !== null && $att3score === $scorepct) {
            $finalraw = (float)$att3raw;
        } else {
            $finalraw = (float)($att1raw ?? ($att2raw ?? ($att3raw ?? 0.0)));
        }
        $retakestatuslabel = get_string('failed_status', 'local_comp_report_ext');
        $retakestatusbadge = 'badge-danger';
    }

    // Format Attempt 1.
    $att1grade = '';
    $att1items = '';
    if ($att1raw !== null && $att1max !== null) {
        $att1scaled = round(($att1raw / $att1max) * $quizmaxgrade, 2);
        $att1grade  = (0 + $att1scaled) . ' / ' . (0 + round($quizmaxgrade, 2));
        if ($hasdiffmax) {
            $att1items = (0 + round($att1raw, 2)) . '/' . (0 + round($att1max, 2)) . ' ' . $qslabel;
        }
    }

    // Format Retake 1.
    $att2grade = '';
    $att2items = '';
    if ($att2raw !== null && $att2max !== null) {
        $att2scaled = round(($att2raw / $att2max) * $quizmaxgrade, 2);
        $att2grade  = (0 + $att2scaled) . ' / ' . (0 + round($quizmaxgrade, 2));
        if ($hasdiffmax) {
            $att2items = (0 + round($att2raw, 2)) . '/' . (0 + round($att2max, 2)) . ' ' . $qslabel;
        }
    }

    // Format Retake 2.
    $att3grade = '';
    $att3items = '';
    if ($att3raw !== null && $att3max !== null) {
        $att3scaled = round(($att3raw / $att3max) * $quizmaxgrade, 2);
        $att3grade  = (0 + $att3scaled) . ' / ' . (0 + round($quizmaxgrade, 2));
        if ($hasdiffmax) {
            $att3items = (0 + round($att3raw, 2)) . '/' . (0 + round($att3max, 2)) . ' ' . $qslabel;
        }
    }

    // Format Final Grade.
    $finalgrade = '';
    if ($quizmaxgrade > 0 && $sumgradesmax > 0) {
        if ($scorepct == 60.0 && ($att1score === null || $att1score < 60.0)) {
            $finalscaled = round(0.60 * $quizmaxgrade, 2);
        } else {
            $finalscaled = round(($finalraw / $sumgradesmax) * $quizmaxgrade, 2);
        }
        $finalgrade = (0 + $finalscaled) . ' / ' . (0 + round($quizmaxgrade, 2));
    }
    $finalitems = '';
    if ($hasdiffmax && $sumgradesmax > 0) {
        if ($scorepct == 60.0 && ($att1score === null || $att1score < 60.0)) {
            $finalitems = (0 + round(0.60 * $sumgradesmax, 1)) . '/' . (0 + round($sumgradesmax, 2)) . ' ' . $qslabel;
        } else {
            $finalitems = (0 + round($finalraw, 2)) . '/' . (0 + round($sumgradesmax, 2)) . ' ' . $qslabel;
        }
    }

    $cm = get_coursemodule_from_instance('quiz', $pq->id, $courseid);
    $quizurl = $cm ? (new moodle_url('/mod/quiz/view.php', ['id' => $cm->id]))->out(false) : '#';

    $examrows[] = [
        'index'               => count($examrows) + 1,
        'quiz_id'             => (int)$pq->id,
        'quiz_name'           => format_string($pq->name),
        'quiz_url'            => $quizurl,
        'attempt1_score'      => ($att1score !== null) ? number_format($att1score, 1) . '%' : '—',
        'attempt1_grade'      => $att1grade,
        'attempt1_items'      => $att1items,
        'retake1_score'       => ($att2score !== null) ? number_format($att2score, 1) . '%' : '—',
        'retake1_grade'       => $att2grade,
        'retake1_items'       => $att2items,
        'retake2_score'       => ($att3score !== null) ? number_format($att3score, 1) . '%' : '—',
        'retake2_grade'       => $att3grade,
        'retake2_items'       => $att3items,
        'retakes_count'       => $retakecount,
        'final_score'         => number_format($scorepct, 1) . '%',
        'final_grade'         => $finalgrade,
        'final_items'         => $finalitems,
        'status_label'        => $retakestatuslabel,
        'status_badge'        => $retakestatusbadge,
    ];
}

$renderdata = new stdClass();
$renderdata->rows = $rows;
$renderdata->exam_rows = $examrows;
$renderdata->courseid = $courseid;
$renderdata->userid = $userid;
$pdfurl = new moodle_url('/local/comp_report_ext/parent_pdf.php', ['courseid' => $courseid, 'userid' => $userid]);
$renderdata->pdf_url = $pdfurl->out(false);

// AI feedback is now loaded on-demand via AJAX to avoid slow page loads.
$renderdata->ai_comment = null;

// 4. Output Generation.
echo $OUTPUT->header();

$page = new \local_comp_report_ext\output\student_competency_detail_page($renderdata);
echo $OUTPUT->render($page);

echo $OUTPUT->footer();
