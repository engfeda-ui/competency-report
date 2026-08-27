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

// 3. Fetch and prepare all course competencies and matrix data.
$compheaders = [];
$comptotals = [];
$overallallquestions = 0.0;
$overallallcorrect = 0.0;

foreach ($rows as $r) {
    $compheaders[$r->id] = (object)[
        'id'          => (int)$r->id,
        'shortname'   => $r->shortname,
        'description' => strip_tags(html_entity_decode($r->description, ENT_QUOTES, 'UTF-8')),
    ];
    $comptotals[$r->id] = [
        'questions' => (float)$r->questions,
        'correct'   => (float)$r->correct,
    ];
    $overallallquestions += (float)$r->questions;
    $overallallcorrect += (float)$r->correct;
}

// 4. Fetch and prepare all course exams and attempts for this student.
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

// Fetch student's finished attempts breakdown per competency across all quizzes.
$userfinishedattempts = $DB->get_records_sql(
    "SELECT quiz, MAX(id) AS bestattid
       FROM {quiz_attempts}
      WHERE userid = :userid AND state = 'finished'
   GROUP BY quiz",
    ['userid' => $userid]
);

$bestattids = array_map(fn($a) => (int)$a->bestattid, $userfinishedattempts);
$quizcompdata = [];

if (!empty($bestattids)) {
    [$insql, $inparams] = $DB->get_in_or_equal($bestattids, SQL_PARAMS_NAMED, 'att');
    $breakdownsql = "
        SELECT CONCAT(quiza.quiz, '_', c.id) AS quizcompid,
               quiza.quiz AS quizid,
               c.id AS compid,
               CAST(SUM(qa.maxfraction) AS DECIMAL(12, 1)) AS total_q,
               CAST(SUM(qas.fraction) AS DECIMAL(12, 1)) AS correct_q
          FROM {quiz_attempts} quiza
          JOIN {question_usages} qu ON qu.id = quiza.uniqueid
          JOIN {question_attempts} qa ON qa.questionusageid = qu.id
          JOIN {qbank_comp_ext_qmap} m ON m.questionid = qa.questionid
          JOIN {competency} c ON c.id = m.competencyid
          JOIN (
              SELECT MAX(fraction) AS fraction, questionattemptid
                FROM {question_attempt_steps}
            GROUP BY questionattemptid
          ) qas ON qas.questionattemptid = qa.id
         WHERE quiza.id $insql
      GROUP BY quiza.quiz, c.id";
    $quizcompdata = $DB->get_records_sql($breakdownsql, $inparams);
}

$qslabel = get_string('questions_abbr', 'local_comp_report_ext');
$examrows = [];
$matrixrows = [];
$chartexamlabels = [];
$chartcompseries = [];

foreach ($compheaders as $cid => $ch) {
    $chartcompseries[$cid] = [
        'comp_id'   => $cid,
        'comp_name' => $ch->shortname,
        'scores'    => [],
    ];
}

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

    // Build Competency Matrix Row for this quiz.
    $chartexamlabels[] = format_string($pq->name);
    $rowcells = [];
    $rowtotalq = 0.0;
    $rowtotalc = 0.0;

    foreach ($compheaders as $cid => $ch) {
        $key = $pq->id . '_' . $cid;
        if (isset($quizcompdata[$key])) {
            $tq = (float)$quizcompdata[$key]->total_q;
            $cq = (float)$quizcompdata[$key]->correct_q;
            $pct = $tq > 0 ? round(($cq / $tq) * 100.0, 1) : 0.0;
            $color = ($pct >= 80) ? '#28a745' : (($pct >= 60) ? '#0056b3' : (($pct >= 40) ? '#e67e22' : '#dc3545'));

            $rowcells[] = [
                'has_data' => true,
                'pct'      => '%' . number_format($pct, 1),
                'items'    => '(' . (0 + round($cq, 1)) . '/' . (0 + round($tq, 1)) . ' ' . $qslabel . ')',
                'color'    => $color,
            ];

            $rowtotalq += $tq;
            $rowtotalc += $cq;
            $chartcompseries[$cid]['scores'][] = $pct;
        } else {
            $rowcells[] = [
                'has_data' => false,
                'pct'      => '—',
                'items'    => '',
                'color'    => '#6c757d',
            ];
            $chartcompseries[$cid]['scores'][] = null;
        }
    }

    $rowoverallpct = $rowtotalq > 0 ? round(($rowtotalc / $rowtotalq) * 100.0, 1) : 0.0;
    $rowoverallcolor = '#dc3545';
    if ($rowoverallpct >= 80) {
        $rowoverallcolor = '#28a745';
    } else if ($rowoverallpct >= 60) {
        $rowoverallcolor = '#0056b3';
    } else if ($rowoverallpct >= 40) {
        $rowoverallcolor = '#e67e22';
    }

    $matrixrows[] = [
        'index'            => count($matrixrows) + 1,
        'quiz_id'          => (int)$pq->id,
        'quiz_name'        => format_string($pq->name),
        'quiz_url'         => $quizurl,
        'cells'            => $rowcells,
        'row_total_pct'    => '%' . number_format($rowoverallpct, 1),
        'row_total_items'  => '(' . (0 + round($rowtotalc, 1)) . '/' . (0 + round($rowtotalq, 1)) . ' ' . $qslabel . ')',
        'row_color'        => $rowoverallcolor,
    ];
}

// Compute Matrix Footer Total Row.
$footercells = [];
foreach ($compheaders as $cid => $ch) {
    $totq = $comptotals[$cid]['questions'] ?? 0.0;
    $totc = $comptotals[$cid]['correct'] ?? 0.0;
    $totpct = $totq > 0 ? round(($totc / $totq) * 100.0, 1) : 0.0;
    $totcolor = '#dc3545';
    if ($totpct >= 80) {
        $totcolor = '#28a745';
    } else if ($totpct >= 60) {
        $totcolor = '#0056b3';
    } else if ($totpct >= 40) {
        $totcolor = '#e67e22';
    }

    $footercells[] = [
        'pct'   => '%' . number_format($totpct, 1),
        'items' => '(' . (0 + round($totc, 1)) . '/' . (0 + round($totq, 1)) . ' ' . $qslabel . ')',
        'color' => $totcolor,
    ];
}

$overallcoursepct = $overallallquestions > 0 ? round(($overallallcorrect / $overallallquestions) * 100.0, 1) : 0.0;
$overallcoursecolor = '#dc3545';
if ($overallcoursepct >= 80) {
    $overallcoursecolor = '#28a745';
} else if ($overallcoursepct >= 60) {
    $overallcoursecolor = '#0056b3';
} else if ($overallcoursepct >= 40) {
    $overallcoursecolor = '#e67e22';
}

$footertotal = [
    'pct'   => '%' . number_format($overallcoursepct, 1),
    'items' => '(' . (0 + round($overallallcorrect, 1)) . '/' . (0 + round($overallallquestions, 1)) . ' ' . $qslabel . ')',
    'color' => $overallcoursecolor,
];

// Build Chart.js Datasets and Trend KPIs.
$palette = [
    ['border' => '#0d6efd', 'bg' => 'rgba(13, 110, 253, 0.1)'],
    ['border' => '#198754', 'bg' => 'rgba(25, 135, 84, 0.1)'],
    ['border' => '#fd7e14', 'bg' => 'rgba(253, 126, 20, 0.1)'],
    ['border' => '#6f42c1', 'bg' => 'rgba(111, 66, 193, 0.1)'],
    ['border' => '#20c997', 'bg' => 'rgba(32, 201, 151, 0.1)'],
    ['border' => '#d63384', 'bg' => 'rgba(214, 51, 132, 0.1)'],
];

$chartdatasets = [];
$trendkpis = [];
$coloridx = 0;

foreach ($chartcompseries as $cid => $series) {
    $validscores = array_filter($series['scores'], fn($v) => $v !== null);
    $trendlabel = get_string('trend_steady', 'local_comp_report_ext');
    $trendbadge = 'badge-info';
    $trendicon  = 'fa-arrows-h';

    if (count($validscores) >= 2) {
        $firstscore = reset($validscores);
        $lastscore  = end($validscores);
        $diff = $lastscore - $firstscore;

        if ($diff >= 5.0) {
            $trendlabel = get_string('trend_improving', 'local_comp_report_ext');
            $trendbadge = 'badge-success';
            $trendicon  = 'fa-arrow-up';
        } else if ($diff <= -5.0) {
            $trendlabel = get_string('trend_declining', 'local_comp_report_ext');
            $trendbadge = 'badge-danger';
            $trendicon  = 'fa-arrow-down';
        }
    }

    $color = $palette[$coloridx % count($palette)];
    $coloridx++;

    $chartdatasets[] = [
        'label'                => $series['comp_name'],
        'data'                 => $series['scores'],
        'borderColor'          => $color['border'],
        'backgroundColor'      => $color['bg'],
        'borderWidth'          => 2.5,
        'pointBackgroundColor' => $color['border'],
        'pointRadius'          => 5,
        'pointHoverRadius'     => 7,
        'fill'                 => false,
        'tension'              => 0.25,
        'spanGaps'             => true,
    ];

    $totq = $comptotals[$cid]['questions'] ?? 0.0;
    $totc = $comptotals[$cid]['correct'] ?? 0.0;
    $totpct = $totq > 0 ? round(($totc / $totq) * 100.0, 1) : 0.0;

    $trendkpis[] = [
        'comp_name'   => $series['comp_name'],
        'color'       => $color['border'],
        'overall_pct' => '%' . number_format($totpct, 1),
        'trend_label' => $trendlabel,
        'trend_badge' => $trendbadge,
        'trend_icon'  => $trendicon,
    ];
}

// Add 60% Threshold Benchmark Line.
$thresholdpoints = array_fill(0, count($chartexamlabels), 60.0);
$chartdatasets[] = [
    'label'                => get_string('success_threshold_line', 'local_comp_report_ext'),
    'data'                 => $thresholdpoints,
    'borderColor'          => '#dc3545',
    'backgroundColor'      => 'transparent',
    'borderWidth'          => 1.8,
    'borderDash'           => [6, 4],
    'pointRadius'          => 0,
    'fill'                 => false,
    'tension'              => 0,
];

$chartdatajson = json_encode([
    'labels'   => $chartexamlabels,
    'datasets' => $chartdatasets,
]);

$renderdata = new stdClass();
$renderdata->rows = $rows;
$renderdata->comp_headers = array_values($compheaders);
$renderdata->matrix_rows = $matrixrows;
$renderdata->footer_cells = $footercells;
$renderdata->footer_total = $footertotal;
$renderdata->has_matrix = !empty($matrixrows);
$renderdata->chart_data_json = $chartdatajson;
$renderdata->trend_kpis = $trendkpis;
$renderdata->has_trend_chart = (count($chartexamlabels) >= 2);
$renderdata->exam_rows = $examrows;
$renderdata->has_exams = !empty($examrows);
$renderdata->courseid = $courseid;
$renderdata->userid = $userid;
$pdfurl = new moodle_url('/local/comp_report_ext/parent_pdf.php', ['courseid' => $courseid, 'userid' => $userid]);
$renderdata->pdf_url = $pdfurl->out(false);

// AI feedback is now loaded on-demand via AJAX to avoid slow page loads.
$renderdata->ai_comment = null;

// 5. Output Generation.
echo $OUTPUT->header();

$page = new \local_comp_report_ext\output\student_competency_detail_page($renderdata);
echo $OUTPUT->render($page);

echo $OUTPUT->footer();
