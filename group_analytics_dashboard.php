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
 * Group Analytics Dashboard.
 *
 * Visual dashboard displaying aggregate competency performance, learning curves,
 * distribution charts, and gap analysis for a group or whole course cohort.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$groupid  = optional_param('groupid', 0, PARAM_INT);
$quizid   = optional_param('quizid', 0, PARAM_INT);

// Access control.
require_login($courseid);
$context = context_course::instance($courseid);
$canviewext = has_capability('local/comp_report_ext:viewreports', $context);
$canviewold = has_capability('local/competency_report:viewreports', $context);
if (!$canviewext && !$canviewold) {
    require_capability('local/comp_report_ext:viewreports', $context);
}

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

$PAGE->set_url('/local/comp_report_ext/group_analytics_dashboard.php', [
    'courseid' => $courseid,
    'groupid'  => $groupid,
    'quizid'   => $quizid,
]);
$PAGE->set_title(get_string('group_analytics_dashboard', 'local_comp_report_ext'));
$PAGE->set_heading($course->fullname . ' — ' . get_string('group_analytics_dashboard', 'local_comp_report_ext'));
$PAGE->set_pagelayout('course');
$PAGE->set_context($context);

// Group selection options.
$groups = groups_get_all_groups($courseid);
$groupoptions = [
    [
        'id' => 0,
        'name' => get_string('allgroups', 'local_comp_report_ext'),
        'selected' => ($groupid == 0),
    ],
];
foreach ($groups as $g) {
    $groupoptions[] = [
        'id' => $g->id,
        'name' => format_string($g->name),
        'selected' => ($g->id == $groupid),
    ];
}

// -----------------------------------------------------------------------
// Quiz Selection.
// -----------------------------------------------------------------------
$course_quizzes = $DB->get_records('quiz', ['course' => $courseid], 'name ASC');

$quizoptions = [
    [
        'id'       => 0,
        'name'     => get_string('allquizzes', 'local_comp_report_ext') ?: 'All Exams/Quizzes',
        'selected' => ($quizid == 0),
    ],
];
foreach ($course_quizzes as $q) {
    $quizoptions[] = [
        'id'       => $q->id,
        'name'     => format_string($q->name),
        'selected' => ($q->id == $quizid),
    ];
}

$selected_quizid = $quizid;

// Query students (Filtering by selected group and student role).
if ($groupid > 0) {
    $students = $DB->get_records_sql("
        SELECT DISTINCT u.id, u.firstname, u.lastname
          FROM {groups_members} gm
          JOIN {user} u ON u.id = gm.userid
          JOIN {role_assignments} ra ON ra.userid = u.id
          JOIN {context} ctx ON ctx.id = ra.contextid
         WHERE gm.groupid = :groupid
           AND ctx.instanceid = :courseid
           AND ra.roleid = (SELECT id FROM {role} WHERE shortname = 'student')
           AND u.deleted = 0",
        ['groupid' => $groupid, 'courseid' => $courseid]
    );
} else {
    $students = $DB->get_records_sql("
        SELECT DISTINCT u.id, u.firstname, u.lastname
          FROM {role_assignments} ra
          JOIN {role} r ON r.id = ra.roleid
          JOIN {context} ctx ON ctx.id = ra.contextid
          JOIN {user} u ON u.id = ra.userid
         WHERE ctx.instanceid = :courseid
           AND ctx.contextlevel = 50
           AND r.shortname = 'student'
           AND u.deleted = 0",
        ['courseid' => $courseid]
    );
}

$calculator = new \local_comp_report_ext\competency_calculator($courseid);
$comp_scores = [];
$all_attempts_data = [];
$student_overall_averages = [];

$threshold = (int)(get_config('local_comp_report_ext', 'success_threshold') ?: 60);

if ($selected_quizid > 0 && !empty($students)) {
    // Calculate performance filtered to the SELECTED EXAM specifically.
    $comp_records = $DB->get_records_sql("
        SELECT DISTINCT c.id, c.shortname
          FROM {quiz_attempts} quiza
          JOIN {question_usages} qu ON qu.id = quiza.uniqueid
          JOIN {question_attempts} qa ON qa.questionusageid = qu.id
          JOIN {qbank_comp_ext_qmap} m ON m.questionid = qa.questionid
          JOIN {competency} c ON c.id = m.competencyid
         WHERE quiza.quiz = :quizid
         ORDER BY c.shortname",
        ['quizid' => $selected_quizid]
    );

    if (!empty($comp_records)) {
        $student_ids = array_keys($students);
        list($insql, $inparams) = $DB->get_in_or_equal($student_ids, SQL_PARAMS_NAMED, 'uid');
        $inparams['quizid'] = $selected_quizid;

        $rawscores = $DB->get_records_sql("
            SELECT CONCAT(quiza.userid, '_', m.competencyid) as unique_key,
                   quiza.userid, m.competencyid,
                   SUM(qa.maxfraction) AS total_max, SUM(qas.fraction) AS total_fraction
              FROM {quiz_attempts} quiza
              JOIN {question_usages} qu ON qu.id = quiza.uniqueid
              JOIN {question_attempts} qa ON qa.questionusageid = qu.id
              JOIN {qbank_comp_ext_qmap} m ON m.questionid = qa.questionid
              JOIN (
                  SELECT questionattemptid, MAX(fraction) AS fraction
                    FROM {question_attempt_steps}
                   GROUP BY questionattemptid
              ) qas ON qas.questionattemptid = qa.id
             WHERE quiza.quiz = :quizid AND quiza.state = 'finished'
               AND quiza.userid $insql
             GROUP BY quiza.userid, m.competencyid",
            $inparams
        );

        $student_comp_map = [];
        foreach ($rawscores as $rs) {
            $att = (float)$rs->total_max;
            $cor = (float)$rs->total_fraction;
            $pct = ($att > 0) ? ($cor / $att) * 100.0 : 0.0;
            $student_comp_map[$rs->userid][$rs->competencyid] = $pct;
        }

        foreach ($students as $student) {
            if (!isset($student_comp_map[$student->id])) {
                continue;
            }
            $s_scores = $student_comp_map[$student->id];
            $student_sum = 0.0;
            $student_count = 0;

            foreach ($comp_records as $cid => $comp) {
                if (isset($s_scores[$cid])) {
                    $pct = $s_scores[$cid];
                    $shortname = html_entity_decode(format_string($comp->shortname), ENT_QUOTES, 'UTF-8');
                    $comp_scores[$cid]['shortname'] = $shortname;
                    $comp_scores[$cid]['scores'][]  = $pct;

                    $student_sum += $pct;
                    $student_count++;
                }
            }

            if ($student_count > 0) {
                $student_overall_averages[] = $student_sum / $student_count;
            }
        }
    }
} else {
    // Course-wide overall weighted calculation (across ALL assessments).
    foreach ($students as $student) {
        $scores = $calculator->get_student_scores((int)$student->id);
        if (empty($scores)) {
            continue;
        }

        $student_sum = 0.0;
        $student_count = 0;

        foreach ($scores as $compid => $data) {
            $comp_scores[$compid]['shortname'] = html_entity_decode(format_string($data['competency']->shortname), ENT_QUOTES, 'UTF-8');
            $comp_scores[$compid]['scores'][] = (float)$data['percent'];

            $student_sum += (float)$data['percent'];
            $student_count++;

            if (!empty($data['breakdown'])) {
                foreach ($data['breakdown'] as $b) {
                    $asmtid = (int)$b['assessmentid'];
                    $all_attempts_data[$asmtid]['name'] = $b['name'];
                    $all_attempts_data[$asmtid]['type'] = $b['type'];
                    $all_attempts_data[$asmtid]['scores'][] = (float)$b['score_pct'];
                }
            }
        }

        if ($student_count > 0) {
            $student_overall_averages[] = $student_sum / $student_count;
        }
    }
}

// Calculate Dashboard KPIs.
$has_data = !empty($student_overall_averages);
$avg_mastery = 0.0;
$remediation_percent = 0.0;
$top_strength = '—';
$critical_gap = '—';

// Mastery distribution tiers.
$distribution = ['critical' => 0, 'developing' => 0, 'proficient' => 0, 'exemplary' => 0];

// Radar chart labels & data.
$radar_labels = [];
$radar_data = [];

// Learning progress curve labels & data.
$progress_labels = [];
$progress_data = [];

// Theory vs Practice labels & data.
$theory_labels = [];
$practice_labels = [];
$theory_data = [];
$practice_data = [];

if ($has_data) {
    // 1. Average Mastery
    $avg_mastery = round(array_sum($student_overall_averages) / count($student_overall_averages), 1);

    // 2. Remediation rate
    $remediation_count = 0;
    foreach ($student_overall_averages as $avg) {
        if ($avg < $threshold) {
            $remediation_count++;
        }
    }
    $remediation_percent = round(($remediation_count / count($student_overall_averages)) * 100, 1);

    // 3. Strengths and gaps
    $comp_averages = [];
    foreach ($comp_scores as $compid => $cdata) {
        $avg_score = round(array_sum($cdata['scores']) / count($cdata['scores']), 1);
        $comp_averages[$compid] = [
            'shortname' => $cdata['shortname'],
            'average' => $avg_score,
        ];
        // Populate radar chart
        $radar_labels[] = $cdata['shortname'];
        $radar_data[] = $avg_score;
    }
    uasort($comp_averages, function ($a, $b) {
        return $a['average'] <=> $b['average'];
    });

    if (!empty($comp_averages)) {
        $keys = array_keys($comp_averages);
        $first_comp = $comp_averages[$keys[0]];
        $last_comp = $comp_averages[$keys[count($keys) - 1]];

        $critical_gap = html_entity_decode($first_comp['shortname'], ENT_QUOTES, 'UTF-8') . ' (' . number_format($first_comp['average'], 1) . '%)';
        $top_strength = html_entity_decode($last_comp['shortname'], ENT_QUOTES, 'UTF-8') . ' (' . number_format($last_comp['average'], 1) . '%)';
    }

    // 4. Mastery Distribution
    foreach ($student_overall_averages as $avg) {
        if ($avg < 40) {
            $distribution['critical']++;
        } else if ($avg < 60) {
            $distribution['developing']++;
        } else if ($avg < 80) {
            $distribution['proficient']++;
        } else {
            $distribution['exemplary']++;
        }
    }

    // 5. Learning Progress Curve (Assessments in order of setup configuration)
    $asmts_list = $DB->get_records('local_comp_report_ext_asmt', ['courseid' => $courseid], 'id ASC');
    foreach ($asmts_list as $asmt) {
        if (isset($all_attempts_data[$asmt->id])) {
            $progress_labels[] = html_entity_decode(format_string($asmt->name), ENT_QUOTES, 'UTF-8');
            $scores = $all_attempts_data[$asmt->id]['scores'];
            $progress_data[] = round(array_sum($scores) / count($scores), 1);
        }
    }

    // 6. Theory vs Practice Gap Analysis
    foreach ($comp_scores as $compid => $cdata) {
        $t_scores = [];
        $p_scores = [];

        foreach ($students as $student) {
            $student_scores = $calculator->get_student_scores((int)$student->id, $compid);
            if (!empty($student_scores[$compid]['breakdown'])) {
                foreach ($student_scores[$compid]['breakdown'] as $b) {
                    if ($b['type'] === 'quiz') {
                        $t_scores[] = (float)$b['score_pct'];
                    } else if ($b['type'] === 'practical') {
                        $p_scores[] = (float)$b['score_pct'];
                    }
                }
            }
        }

        $theory_data[] = !empty($t_scores) ? round(array_sum($t_scores) / count($t_scores), 1) : 0.0;
        $practice_data[] = !empty($p_scores) ? round(array_sum($p_scores) / count($p_scores), 1) : 0.0;
    }
}

// -----------------------------------------------------------------------
// Selected Exam Grade Analytics (Detailed Psychometric & Score Analysis).
// -----------------------------------------------------------------------
$has_quiz_data = false;
$exam_name     = '—';
$exam_avg      = 0.0;
$exam_pass_rate = 0.0;
$exam_max      = 0.0;
$exam_min      = 0.0;
$exam_grade_dist = [0, 0, 0, 0, 0]; // 0-20, 21-40, 41-60, 61-80, 81-100%
$exam_pass_fail  = [0, 0]; // Passed, Failed

$item_difficulty_labels = [];
$item_difficulty_data   = [];
$item_discrim_top       = [];
$item_discrim_bot       = [];

if ($selected_quizid > 0 && !empty($students)) {
    $student_ids = array_keys($students);
    list($insql, $inparams) = $DB->get_in_or_equal($student_ids, SQL_PARAMS_NAMED, 'uid');
    $inparams['quizid'] = $selected_quizid;

    $quiz_obj = $DB->get_record('quiz', ['id' => $selected_quizid]);
    if ($quiz_obj) {
        $exam_name = format_string($quiz_obj->name);

        $attempts = $DB->get_records_sql("
            SELECT quiza.id, quiza.userid, quiza.sumgrades, q.sumgrades as quizgrade
              FROM {quiz_attempts} quiza
              JOIN {quiz} q ON q.id = quiza.quiz
             WHERE quiza.quiz = :quizid
               AND quiza.state = 'finished'
               AND quiza.userid $insql
             ORDER BY quiza.sumgrades DESC",
            $inparams
        );

        if (!empty($attempts)) {
            $has_quiz_data = true;
            $user_best_scores = [];

            foreach ($attempts as $att) {
                $maxg = (float)$att->quizgrade > 0 ? (float)$att->quizgrade : 100.0;
                $pct = round(((float)$att->sumgrades / $maxg) * 100, 1);
                if (!isset($user_best_scores[$att->userid]) || $pct > $user_best_scores[$att->userid]) {
                    $user_best_scores[$att->userid] = $pct;
                }
            }

            $all_pcts = array_values($user_best_scores);
            $total_takers = count($all_pcts);

            $exam_avg = round(array_sum($all_pcts) / $total_takers, 1);
            $exam_max = max($all_pcts);
            $exam_min = min($all_pcts);

            $passed_count = 0;
            foreach ($all_pcts as $p) {
                if ($p >= $threshold) {
                    $passed_count++;
                }
                if ($p <= 20) {
                    $exam_grade_dist[0]++;
                } else if ($p <= 40) {
                    $exam_grade_dist[1]++;
                } else if ($p <= 60) {
                    $exam_grade_dist[2]++;
                } else if ($p <= 80) {
                    $exam_grade_dist[3]++;
                } else {
                    $exam_grade_dist[4]++;
                }
            }

            $exam_pass_rate = round(($passed_count / $total_takers) * 100, 1);
            $exam_pass_fail = [$passed_count, $total_takers - $passed_count];

            // Item Difficulty (p-value) & Discrimination Index (Top 27% vs Bottom 27%)
            $q_attempts = $DB->get_records_sql("
                SELECT qa.id, qa.questionid, q.name as qname, qa.maxfraction, qas.fraction, quiza.userid
                  FROM {quiz_attempts} quiza
                  JOIN {question_usages} qu ON qu.id = quiza.uniqueid
                  JOIN {question_attempts} qa ON qa.questionusageid = qu.id
                  JOIN {question} q ON q.id = qa.questionid
                  JOIN (
                      SELECT questionattemptid, MAX(fraction) AS fraction
                        FROM {question_attempt_steps}
                       GROUP BY questionattemptid
                  ) qas ON qas.questionattemptid = qa.id
                 WHERE quiza.quiz = :quizid
                   AND quiza.state = 'finished'
                   AND quiza.userid $insql
                 ORDER BY qa.questionid ASC",
                $inparams
            );

            // Group scores by question
            $question_scores = [];
            $question_names  = [];

            // Identify Top 27% and Bottom 27% student IDs for Discrimination Index
            arsort($user_best_scores);
            $top_count = max(1, (int)ceil($total_takers * 0.27));
            $top_user_ids = array_slice(array_keys($user_best_scores), 0, $top_count, true);
            $bot_user_ids = array_slice(array_keys($user_best_scores), -$top_count, $top_count, true);
            $top_users_map = array_fill_keys($top_user_ids, true);
            $bot_users_map = array_fill_keys($bot_user_ids, true);

            foreach ($q_attempts as $qa) {
                $qid = (int)$qa->questionid;
                $question_names[$qid] = html_entity_decode(format_string($qa->qname), ENT_QUOTES, 'UTF-8');
                $maxf = (float)$qa->maxfraction > 0 ? (float)$qa->maxfraction : 1.0;
                $fraction_pct = round(((float)$qa->fraction / $maxf) * 100, 1);

                $question_scores[$qid]['all'][] = $fraction_pct;

                if (isset($top_users_map[$qa->userid])) {
                    $question_scores[$qid]['top'][] = $fraction_pct;
                }
                if (isset($bot_users_map[$qa->userid])) {
                    $question_scores[$qid]['bot'][] = $fraction_pct;
                }
            }

            $q_index = 1;
            foreach ($question_scores as $qid => $qdata) {
                $qlabel = 'Q' . $q_index . ': ' . shorten_text($question_names[$qid], 20);
                $item_difficulty_labels[] = $qlabel;

                $all_q_scores = $qdata['all'] ?? [0];
                $item_difficulty_data[] = round(array_sum($all_q_scores) / count($all_q_scores), 1);

                $top_q_scores = $qdata['top'] ?? [0];
                $bot_q_scores = $qdata['bot'] ?? [0];

                $item_discrim_top[] = round(array_sum($top_q_scores) / count($top_q_scores), 1);
                $item_discrim_bot[] = round(array_sum($bot_q_scores) / count($bot_q_scores), 1);

                $q_index++;
            }
        }
    }
}

// Package data for views.
$renderdata = new stdClass();
$renderdata->courseid          = $courseid;
$renderdata->groupid           = $groupid;
$renderdata->quizid            = $selected_quizid;
$renderdata->groups            = $groupoptions;
$renderdata->quizzes           = $quizoptions;
$renderdata->has_data          = $has_data;
$renderdata->avg_mastery       = number_format($avg_mastery, 1);
$renderdata->remediation_rate  = number_format($remediation_percent, 1);
$renderdata->top_strength      = $top_strength;
$renderdata->critical_gap      = $critical_gap;

// Exam analytics view parameters
$renderdata->has_quiz_data     = $has_quiz_data;
$renderdata->exam_name         = $exam_name;
$renderdata->exam_avg          = number_format($exam_avg, 1);
$renderdata->exam_pass_rate    = number_format($exam_pass_rate, 1);
$renderdata->exam_max          = number_format($exam_max, 1);
$renderdata->exam_min          = number_format($exam_min, 1);

// JSON strings for Chart.js rendering scripts
$renderdata->radar_labels_json = json_encode($radar_labels);
$renderdata->radar_data_json   = json_encode($radar_data);

$renderdata->dist_data_json    = json_encode([
    $distribution['critical'],
    $distribution['developing'],
    $distribution['proficient'],
    $distribution['exemplary']
]);

$renderdata->progress_labels_json = json_encode($progress_labels);
$renderdata->progress_data_json   = json_encode($progress_data);

$renderdata->gap_labels_json    = json_encode($radar_labels);
$renderdata->gap_theory_json    = json_encode($theory_data);
$renderdata->gap_practice_json  = json_encode($practice_data);

// Exam Analytics JSON strings
$renderdata->exam_grade_dist_json = json_encode($exam_grade_dist);
$renderdata->exam_pass_fail_json  = json_encode($exam_pass_fail);

$renderdata->item_difficulty_labels_json = json_encode($item_difficulty_labels);
$renderdata->item_difficulty_data_json   = json_encode($item_difficulty_data);
$renderdata->item_discrim_top_json       = json_encode($item_discrim_top);
$renderdata->item_discrim_bot_json       = json_encode($item_discrim_bot);

// Output rendering.
echo $OUTPUT->header();

$page = new \local_comp_report_ext\output\group_analytics_dashboard_page($renderdata);
echo $OUTPUT->render($page);

echo $OUTPUT->footer();

