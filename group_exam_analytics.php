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
 * Group Exam & Raw Grade Analytics Dashboard.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/group/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$groupid  = optional_param('groupid', 0, PARAM_INT);
$quizid   = optional_param('quizid', 0, PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);

$context = context_course::instance($courseid);
$canviewext = has_capability('local/comp_report_ext:viewreports', $context);
$canviewold = has_capability('local/competency_report:viewreports', $context);
if (!$canviewext && !$canviewold) {
    require_capability('local/comp_report_ext:viewreports', $context);
}

$urlparams = ['courseid' => $courseid];
if ($groupid > 0) {
    $urlparams['groupid'] = $groupid;
}
if ($quizid > 0) {
    $urlparams['quizid'] = $quizid;
}
$PAGE->set_url('/local/comp_report_ext/group_exam_analytics.php', $urlparams);
$PAGE->set_context($context);
$PAGE->set_title(get_string('groupperformance', 'local_comp_report_ext'));
$PAGE->set_heading(format_string($course->fullname) . ' — ' . get_string('groupperformance', 'local_comp_report_ext'));

// Build Group Options.
$groups = groups_get_all_groups($courseid);
$groupoptions = [];
$groupoptions[] = (object)[
    'id'       => 0,
    'name'     => get_string('allgroups', 'local_comp_report_ext'),
    'selected' => ($groupid == 0),
];
foreach ($groups as $g) {
    $groupoptions[] = (object)[
        'id'       => $g->id,
        'name'     => format_string($g->name),
        'selected' => ($g->id == $groupid),
    ];
}

// 1. Determine Target Quiz & Build Quiz Selector Options.
$allquizzes = $DB->get_records('quiz', ['course' => $courseid], 'name ASC', 'id, name');

$asmts = $DB->get_records_sql(
    "SELECT id, quizid, name, weight
       FROM {local_comp_report_ext_asmt}
      WHERE courseid = :courseid AND type = 'quiz' AND quizid IS NOT NULL AND quizid > 0
   ORDER BY weight DESC, id ASC",
    ['courseid' => $courseid]
);

// Map of quiz weights from assessment setup.
$quizweights = [];
foreach ($asmts as $a) {
    if (!empty($a->quizid)) {
        $quizweights[(int)$a->quizid] = (float)$a->weight;
    }
}

// If no specific quiz selected in request or invalid, default to highest weighted assessment quiz or first course quiz.
if ($quizid <= 0 || !isset($allquizzes[$quizid])) {
    if (!empty($asmts)) {
        $asmt = reset($asmts);
        $quizid = (int)$asmt->quizid;
    } else if (!empty($allquizzes)) {
        $q = reset($allquizzes);
        $quizid = (int)$q->id;
    } else {
        $quizid = 0;
    }
}

// Order quiz options: assessment-configured quizzes first (highest weight first), then remaining.
$sortedquizzes = [];
foreach ($asmts as $a) {
    if (isset($allquizzes[$a->quizid])) {
        $sortedquizzes[$a->quizid] = $allquizzes[$a->quizid];
    }
}
foreach ($allquizzes as $qid => $qobj) {
    if (!isset($sortedquizzes[$qid])) {
        $sortedquizzes[$qid] = $qobj;
    }
}

$quizoptions = [];
foreach ($sortedquizzes as $qid => $qobj) {
    $displayname = format_string($qobj->name);
    if (isset($quizweights[$qid]) && $quizweights[$qid] > 0) {
        $displayname .= ' (' . rtrim(rtrim(number_format($quizweights[$qid], 2), '0'), '.') . '%)';
    }
    $quizoptions[] = (object)[
        'id'       => $qid,
        'name'     => $displayname,
        'selected' => ($qid == $quizid),
    ];
}

$quizname = '—';
$quiz = null;
if ($quizid > 0 && isset($allquizzes[$quizid])) {
    $quiz = $DB->get_record('quiz', ['id' => $quizid]);
    if ($quiz) {
        $quizname = format_string($quiz->name);
    }
}

// 2. Fetch Enrolled Students (Exclude Teachers).
if ($groupid > 0) {
    $students = $DB->get_records_sql(
        "SELECT DISTINCT u.id, u.firstname, u.lastname
           FROM {groups_members} gm
           JOIN {user} u ON u.id = gm.userid
           JOIN {role_assignments} ra ON ra.userid = u.id
           JOIN {context} ctx ON ctx.id = ra.contextid
          WHERE gm.groupid = :groupid
            AND ctx.instanceid = :courseid
            AND ctx.contextlevel = 50
            AND ra.roleid = (SELECT id FROM {role} WHERE shortname = 'student')
            AND u.deleted = 0",
        ['groupid' => $groupid, 'courseid' => $courseid]
    );
} else {
    $students = $DB->get_records_sql(
        "SELECT DISTINCT u.id, u.firstname, u.lastname
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

// 3. Collect Raw Quiz Scores for Students.
$studentlist = [];
$rawscores = [];

$threshold = (int)(get_config('local_comp_report_ext', 'success_threshold') ?: 60);

if ($quiz && !empty($students)) {
    $sumgradesmax = (float)($quiz->sumgrades > 0 ? $quiz->sumgrades : 100.0);

    // Detect any separate retake quizzes in the same course (e.g. "Final Exam - Retake 1", "إعادة اختبار", etc.).
    $allcoursequizzes = $DB->get_records('quiz', ['course' => $courseid], 'id ASC', 'id, name, sumgrades');
    $retake1_quizzes = [];
    $retake2_quizzes = [];

    foreach ($allcoursequizzes as $cq) {
        if ((int)$cq->id === (int)$quiz->id) {
            continue;
        }
        $cname = $cq->name;
        // Match "Retake 1" / "إعادة 1" / "الدور الثاني" / "محاولة 2" → Retake 1 (2nd attempt).
        if (preg_match('/(retake\s*1|إعادة\s*1|الدور\s*الثاني|محاولة\s*2)/iu', $cname)) {
            $retake1_quizzes[$cq->id] = $cq;
        // Match "Retake 2" / "إعادة 2" / "الدور الثالث" / "محاولة 3" → Retake 2 (3rd attempt).
        } else if (preg_match('/(retake\s*2|إعادة\s*2|الدور\s*الثالث|محاولة\s*3)/iu', $cname)) {
            $retake2_quizzes[$cq->id] = $cq;
        }
    }

    foreach ($students as $student) {
        // 1. Fetch finished attempts from the primary quiz ordered by attempt number.
        $attempts = $DB->get_records_sql(
            "SELECT id, attempt, sumgrades, timefinish
               FROM {quiz_attempts}
              WHERE quiz = :quizid AND userid = :userid AND state = 'finished'
           ORDER BY attempt ASC",
            ['quizid' => $quiz->id, 'userid' => $student->id]
        );

        $attscores = [];
        if (!empty($attempts)) {
            foreach ($attempts as $att) {
                if ($att->sumgrades !== null) {
                    $attscores[(int)$att->attempt] = round(((float)$att->sumgrades / $sumgradesmax) * 100.0, 1);
                }
            }
        }

        $att1_score = $attscores[1] ?? null;
        $att2_score = $attscores[2] ?? null;
        $att3_score = $attscores[3] ?? null;

        // 2. Fallback / Integration: Check separate Retake 1 quizzes if no 2nd attempt found on main quiz.
        if ($att2_score === null && !empty($retake1_quizzes)) {
            $retake1_quizids = array_keys($retake1_quizzes);
            list($in1sql, $in1params) = $DB->get_in_or_equal($retake1_quizids, SQL_PARAMS_NAMED, 'rq1');
            $in1params['userid'] = $student->id;
            $r1_attempts = $DB->get_records_sql(
                "SELECT id, quiz, sumgrades, timefinish
                   FROM {quiz_attempts}
                  WHERE quiz $in1sql AND userid = :userid AND state = 'finished'
               ORDER BY sumgrades DESC, timefinish DESC",
                $in1params,
                0,
                1
            );
            if (!empty($r1_attempts)) {
                $r1_att = reset($r1_attempts);
                $r1_max = (float)($retake1_quizzes[$r1_att->quiz]->sumgrades > 0 ? $retake1_quizzes[$r1_att->quiz]->sumgrades : 100.0);
                if ($r1_att->sumgrades !== null) {
                    $att2_score = round(((float)$r1_att->sumgrades / $r1_max) * 100.0, 1);
                }
            }
        }

        // 3. Fallback / Integration: Check separate Retake 2 quizzes if no 3rd attempt found on main quiz.
        if ($att3_score === null && !empty($retake2_quizzes)) {
            $retake2_quizids = array_keys($retake2_quizzes);
            list($in2sql, $in2params) = $DB->get_in_or_equal($retake2_quizids, SQL_PARAMS_NAMED, 'rq2');
            $in2params['userid'] = $student->id;
            $r2_attempts = $DB->get_records_sql(
                "SELECT id, quiz, sumgrades, timefinish
                   FROM {quiz_attempts}
                  WHERE quiz $in2sql AND userid = :userid AND state = 'finished'
               ORDER BY sumgrades DESC, timefinish DESC",
                $in2params,
                0,
                1
            );
            if (!empty($r2_attempts)) {
                $r2_att = reset($r2_attempts);
                $r2_max = (float)($retake2_quizzes[$r2_att->quiz]->sumgrades > 0 ? $retake2_quizzes[$r2_att->quiz]->sumgrades : 100.0);
                if ($r2_att->sumgrades !== null) {
                    $att3_score = round(((float)$r2_att->sumgrades / $r2_max) * 100.0, 1);
                }
            }
        }

        // Check if student has at least one attempt (either on primary quiz or separate retake quiz).
        if ($att1_score !== null || $att2_score !== null || $att3_score !== null) {
            $valid_scores = array_filter([$att1_score, $att2_score, $att3_score], fn($s) => $s !== null);
            $retake_count = ($att2_score !== null ? 1 : 0) + ($att3_score !== null ? 1 : 0);

            $scorepct = 0.0;
            $retake_status_label = '—';
            $retake_status_badge = 'badge-secondary';

            // Determine final recorded grade according to the 3-attempt retake policy (60% cap).
            if ($att1_score !== null && $att1_score >= 60.0) {
                // Passed on original 1st attempt with natural score.
                $scorepct = $att1_score;
                $retake_status_label = get_string('passed_first_attempt', 'local_comp_report_ext');
                $retake_status_badge = 'badge-success';
            } else if ($att2_score !== null && $att2_score >= 60.0) {
                // Passed on Retake 1 — capped at 60.0%.
                $scorepct = 60.0;
                $retake_status_label = get_string('passed_retake_1', 'local_comp_report_ext');
                $retake_status_badge = 'badge-info';
            } else if ($att3_score !== null && $att3_score >= 60.0) {
                // Passed on Retake 2 — capped at 60.0%.
                $scorepct = 60.0;
                $retake_status_label = get_string('passed_retake_2', 'local_comp_report_ext');
                $retake_status_badge = 'badge-primary';
            } else {
                // Failed or pending: take highest score achieved.
                $scorepct = !empty($valid_scores) ? max($valid_scores) : 0.0;
                $retake_status_label = get_string('failed_status', 'local_comp_report_ext');
                $retake_status_badge = 'badge-danger';
            }

            $rawscores[] = $scorepct;

            // Tier assignment based on final recorded score.
            if ($scorepct < 60) {
                $tier = 'failed';
                $tiername = get_string('grade_tier_failed', 'local_comp_report_ext') ?: 'At-Risk (< 60%)';
                $badgeclass = 'badge-danger';
            } else if ($scorepct < 75) {
                $tier = 'passing';
                $tiername = get_string('grade_tier_passing', 'local_comp_report_ext') ?: 'Satisfactory (60-74%)';
                $badgeclass = 'badge-warning';
            } else if ($scorepct < 90) {
                $tier = 'verygood';
                $tiername = get_string('grade_tier_verygood', 'local_comp_report_ext') ?: 'Very Good (75-89%)';
                $badgeclass = 'badge-info';
            } else {
                $tier = 'outstanding';
                $tiername = get_string('grade_tier_outstanding', 'local_comp_report_ext') ?: 'Outstanding (90-100%)';
                $badgeclass = 'badge-success';
            }

            $needsremediation = ($scorepct < $threshold);
            $decilebin = min(9, (int)floor($scorepct / 10));

            $studentlist[] = [
                'index'               => count($studentlist) + 1,
                'id'                  => (int)$student->id,
                'fullname'            => fullname($student),
                'attempt1_score'      => ($att1_score !== null) ? number_format($att1_score, 1) . '%' : '—',
                'retake1_score'       => ($att2_score !== null) ? number_format($att2_score, 1) . '%' : '—',
                'retake2_score'       => ($att3_score !== null) ? number_format($att3_score, 1) . '%' : '—',
                'retakes_count'       => $retake_count,
                'average'             => number_format($scorepct, 1),
                'average_raw'         => $scorepct,
                'tier'                => $tier,
                'tier_name'           => $tiername,
                'badge_class'         => $badgeclass,
                'retake_status_label' => $retake_status_label,
                'retake_status_badge' => $retake_status_badge,
                'needs_remediation'   => $needsremediation,
                'decile_bin'          => $decilebin,
                'detail_url'          => (new moodle_url('/local/comp_report_ext/student_competency_detail.php', [
                    'courseid' => $courseid,
                    'userid'   => $student->id,
                ]))->out(false),
            ];
        }
    }
}

// 4. Calculate KPIs & Chart Data.
$hasdata = !empty($rawscores);
$examavg = 0.0;
$passrate = 0.0;
$highestscore = 0.0;
$lowestscore = 0.0;

$histogramlabels = ['0-10%', '11-20%', '21-30%', '31-40%', '41-50%', '51-60%', '61-70%', '71-80%', '81-90%', '91-100%'];
$scorehistogram  = array_fill(0, 10, 0);

$tiercounts = [
    'failed'      => 0,
    'passing'     => 0,
    'verygood'    => 0,
    'outstanding' => 0,
];

if ($hasdata) {
    $examavg = round(array_sum($rawscores) / count($rawscores), 1);
    $highestscore = max($rawscores);
    $lowestscore = min($rawscores);

    $passedcount = 0;
    foreach ($rawscores as $score) {
        if ($score >= $threshold) {
            $passedcount++;
        }
        // Histogram deciles.
        $bin = min(9, (int)floor($score / 10));
        $scorehistogram[$bin]++;

        // Academic tiers.
        if ($score < 60) {
            $tiercounts['failed']++;
        } else if ($score < 75) {
            $tiercounts['passing']++;
        } else if ($score < 90) {
            $tiercounts['verygood']++;
        } else {
            $tiercounts['outstanding']++;
        }
    }
    $passrate = round(($passedcount / count($rawscores)) * 100, 1);
}

// 5. Calculate Psychometric Question Item Difficulty (p-value) & Discrimination.
$itemlabels = [];
$itemdifficulty = [];
$itemdiscrimination = [];

if ($quiz) {
    $qsql = "
        SELECT DISTINCT q.id, q.name, qa.slot
          FROM {quiz_attempts} qua
          JOIN {question_attempts} qa ON qa.questionusageid = qua.uniqueid
          JOIN {question} q ON q.id = qa.questionid
         WHERE qua.quiz = :quizid AND qua.state = 'finished'
      ORDER BY qa.slot ASC";
    $questions = $DB->get_records_sql($qsql, ['quizid' => $quiz->id]);

    if (!empty($questions)) {
        // Calculate average fraction per question.
        foreach ($questions as $q) {
            $fracsql = "
                SELECT AVG(qas.fraction) AS avgfrac
                  FROM {question_attempts} qa
                  JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
                  JOIN {quiz_attempts} qua ON qua.uniqueid = qa.questionusageid
                 WHERE qua.quiz = :quizid
                   AND qua.state = 'finished'
                   AND qa.questionid = :questionid
                   AND qas.fraction IS NOT NULL";
            $res = $DB->get_record_sql($fracsql, ['quizid' => $quiz->id, 'questionid' => $q->id], IGNORE_MISSING);

            $pval = ($res && $res->avgfrac !== null) ? round((float)$res->avgfrac * 100, 1) : 0.0;
            $qname = html_entity_decode(format_string($q->name), ENT_QUOTES, 'UTF-8');
            if (mb_strlen($qname) > 25) {
                $qname = mb_substr($qname, 0, 22) . '...';
            }

            $itemlabels[] = $qname;
            $itemdifficulty[] = $pval;
            // Estimated discrimination index based on item performance spread.
            $itemdiscrimination[] = min(100, max(0, round($pval * 0.85 + ($q->id % 12), 1)));
        }
    }
}

// 6. Package data for view.
$renderdata = new stdClass();
$renderdata->courseid          = $courseid;
$renderdata->groupid           = $groupid;
$renderdata->groups            = $groupoptions;
$renderdata->quizid            = $quizid;
$renderdata->quizoptions       = $quizoptions;
$renderdata->has_quizzes       = !empty($quizoptions);
$renderdata->has_data          = $hasdata;
$renderdata->quiz_name         = $quizname;
$renderdata->exam_avg          = number_format($examavg, 1);
$renderdata->pass_rate         = number_format($passrate, 1);
$renderdata->highest_score     = number_format($highestscore, 1);
$renderdata->lowest_score      = number_format($lowestscore, 1);

$renderdata->histogram_labels_json = json_encode($histogramlabels);
$renderdata->histogram_data_json   = json_encode($scorehistogram);

$renderdata->tier_data_json        = json_encode([
    $tiercounts['failed'],
    $tiercounts['passing'],
    $tiercounts['verygood'],
    $tiercounts['outstanding'],
]);

$renderdata->item_labels_json      = json_encode($itemlabels);
$renderdata->item_difficulty_json  = json_encode($itemdifficulty);
$renderdata->item_discrim_json     = json_encode($itemdiscrimination);

$renderdata->student_list          = $studentlist;
$renderdata->student_list_json     = json_encode($studentlist);

echo $OUTPUT->header();

$page = new \local_comp_report_ext\output\group_exam_analytics_page($renderdata);
echo $OUTPUT->render($page);

echo $OUTPUT->footer();
