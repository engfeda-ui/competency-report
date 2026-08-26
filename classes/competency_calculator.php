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
 * Weighted competency score calculator.
 *
 * This class centralises ALL competency score calculations so that every
 * report page uses the same weighted logic instead of plain question-fraction
 * averages.
 *
 * Algorithm
 * ---------
 * For each assessment (quiz or practical) that has been configured for the
 * course, the calculator fetches:
 *   - Quiz assessments  → student's question-attempt fractions for questions
 *                         mapped to the target competency in that quiz.
 *   - Practical entries → manually entered competency_percent for the student.
 *
 * Weighted score = Σ( assessment_score_pct × assessment_weight ) / 100
 *
 * If NO assessments have been configured (weight setup not done), the
 * calculator falls back to the original plain average across all quizzes
 * so that existing courses continue to work.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_comp_report_ext;

/**
 * Weighted competency score calculator.
 *
 * @package local_comp_report_ext
 */
class competency_calculator {
    /** @var int */
    private $courseid;

    /** @var array|null Cached assessment rows for this course */
    private $assessments = null;

    /** @var array|null Bulk-preloaded quiz rates: [userid][quizid_compid] => percentage */
    private $quizratescache = null;

    /** @var array|null Bulk-preloaded practical rates: [userid][asmtid_compid] => percentage */
    private $practicalratescache = null;

    /** @var array Instance-cached competency lists keyed by filtercompetencyid (0 = unfiltered) */
    private $compscache = [];

    /**
     * Constructor.
     *
     * @param int $courseid The Moodle course ID.
     */
    public function __construct(int $courseid) {
        $this->courseid = $courseid;
    }

    /**
     * Load (and cache) the configured assessments for this course.
     *
     * @return array Array of assessment stdClass records, keyed by id.
     */
    private function get_assessments(): array {
        global $DB;
        if ($this->assessments === null) {
            $this->assessments = $DB->get_records(
                'local_comp_report_ext_asmt',
                ['courseid' => $this->courseid],
                'id ASC'
            );
        }
        return $this->assessments;
    }

    /**
     * Return true if weighted assessments have been configured for this course.
     *
     * @return bool
     */
    public function has_assessments(): bool {
        return !empty($this->get_assessments());
    }

    /**
     * Calculate the weighted competency score for a single student.
     *
     * Returns an associative array keyed by competency ID:
     *   [
     *     competencyid => [
     *       'percent'     => float,   // weighted score 0-100
     *       'passed'      => bool,    // >= success_threshold
     *       'breakdown'   => [        // per-assessment detail
     *         [ 'name', 'type', 'weight', 'score_pct', 'weighted_contribution' ], …
     *       ],
     *     ],
     *     …
     *   ]
     *
     * @param int $userid      Student user ID.
     * @param int|null $filtercompetencyid  Optional: only calculate for this competency.
     * @return array
     */
    public function get_student_scores(int $userid, ?int $filtercompetencyid = null): array {
        global $DB;

        $assessments = $this->get_assessments();

        // Fallback: no assessments configured → plain average (legacy behaviour).
        if (empty($assessments)) {
            return $this->legacy_plain_scores($userid, $filtercompetencyid);
        }

        $threshold = (int)(get_config('local_comp_report_ext', 'success_threshold') ?: 60);

        // Fetch all competencies that have question mappings in this course
        // (instance-cached; identical for every student).
        $compkey = $filtercompetencyid ?: 0;
        if (!isset($this->compscache[$compkey])) {
            $compsql = "SELECT DISTINCT c.id, c.shortname, c.description, c.descriptionformat
                          FROM {qbank_comp_ext_qmap} m
                          JOIN {competency} c ON c.id = m.competencyid
                         WHERE m.courseid = :courseid";
            $params = ['courseid' => $this->courseid];
            if ($filtercompetencyid) {
                $compsql .= ' AND c.id = :compid';
                $params['compid'] = $filtercompetencyid;
            }
            $competencies = $DB->get_records_sql($compsql, $params);

            // Also add competencies that appear only in practical assessments.
            $pracsql = "SELECT DISTINCT c.id, c.shortname, c.description, c.descriptionformat
                          FROM {local_comp_report_ext_prac} p
                          JOIN {competency} c ON c.id = p.competencyid
                         WHERE p.courseid = :courseid";
            $pracparams = ['courseid' => $this->courseid];
            if ($filtercompetencyid) {
                $pracsql .= ' AND c.id = :compid';
                $pracparams['compid'] = $filtercompetencyid;
            }
            $praccomps = $DB->get_records_sql($pracsql, $pracparams);
            foreach ($praccomps as $cid => $comp) {
                if (!isset($competencies[$cid])) {
                    $competencies[$cid] = $comp;
                }
            }
            $this->compscache[$compkey] = $competencies;
        }
        $competencies = $this->compscache[$compkey];

        // Per-user rate maps (served from bulk preloaded caches when available).
        $quizmap      = $this->bulk_quiz_map($userid);
        $practicalmap = $this->bulk_practical_map($userid);

        $result = [];

        foreach ($competencies as $comp) {
            $totalweighted = 0.0;
            $totalweight   = 0.0;
            $breakdown     = [];

            foreach ($assessments as $assessment) {
                $scorepct = null;

                if ($assessment->type === 'quiz' && !empty($assessment->quizid)) {
                    $qkey = ((int)$assessment->quizid) . '_' . ((int)$comp->id);
                    $scorepct = $quizmap[$qkey] ?? null;
                } else if ($assessment->type === 'practical') {
                    $pkey = ((int)$assessment->id) . '_' . ((int)$comp->id);
                    $scorepct = $practicalmap[$pkey] ?? null;
                }

                if ($scorepct !== null) {
                    $weighted = $scorepct * ((float)$assessment->weight / 100.0);
                    $totalweighted += $weighted;
                    $totalweight   += (float)$assessment->weight;

                    $breakdown[] = [
                        'assessmentid'          => (int)$assessment->id,
                        'name'                  => $assessment->name,
                        'type'                  => $assessment->type,
                        'weight'                => (float)$assessment->weight,
                        'score_pct'             => round($scorepct, 1),
                        'weighted_contribution' => round($weighted, 2),
                    ];
                }
            }

            // If student has not yet attempted any assessment for this competency, skip.
            if ($totalweight <= 0) {
                continue;
            }

            // Scale: if not all assessments have been sat, scale the weighted total
            // to 100% so partial progress is still meaningful.
            $percent = ($totalweighted / $totalweight) * 100.0;

            $result[$comp->id] = [
                'competency'  => $comp,
                'percent'     => round($percent, 1),
                'passed'      => $percent >= $threshold,
                'breakdown'   => $breakdown,
            ];
        }

        return $result;
    }

    /**
     * Calculate weighted scores for ALL students in a group (or whole course).
     *
     * Returns a nested array: $result[userid][competencyid] = percent (float).
     *
     * @param array $userids  List of user IDs.
     * @return array
     */
    public function get_group_scores(array $userids): array {
        if (empty($userids)) {
            return [];
        }
        // Bulk-load quiz and practical rates for all users in two queries.
        $this->preload_user_data(array_map('intval', $userids));

        $result = [];
        foreach ($userids as $uid) {
            $scores = $this->get_student_scores((int)$uid);
            foreach ($scores as $compid => $data) {
                $result[$uid][$compid] = $data['percent'];
            }
        }
        return $result;
    }

    /**
     * Bulk-preload quiz and practical rate maps for a set of users so that
     * subsequent get_student_scores() calls avoid per-user heavy queries.
     *
     * @param array $userids
     */
    public function preload_user_data(array $userids): void {
        if (empty($userids)) {
            $this->quizratescache = [];
            $this->practicalratescache = [];
            return;
        }
        $this->quizratescache      = $this->get_quiz_rates_bulk($userids);
        $this->practicalratescache = $this->get_practical_rates_bulk($userids);
    }

    /**
     * Fetch per-user quiz competency rates for many users in ONE query.
     *
     * The MAX(fraction) aggregation over {question_attempt_steps} is scoped
     * to the finished attempts of the requested users in this course, instead
     * of aggregating the entire steps table on every call.
     *
     * @param array $userids
     * @return array [userid][quizid_compid] => percentage float
     */
    private function get_quiz_rates_bulk(array $userids): array {
        global $DB;

        if (empty($userids)) {
            return [];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uida');
        [$insql2, $inparams2] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uidb');

        $sql = "SELECT quiza.userid, quiza.quiz, m.competencyid,
                       SUM(qa.maxfraction) AS maxf,
                       SUM(qas.fraction)   AS gotf
                  FROM {quiz_attempts} quiza
                  JOIN {quiz} q                ON q.id = quiza.quiz
                  JOIN {question_usages} qu    ON qu.id = quiza.uniqueid
                  JOIN {question_attempts} qa  ON qa.questionusageid = qu.id
                  JOIN {qbank_comp_ext_qmap} m ON m.questionid = qa.questionid
                  JOIN (
                      SELECT s.questionattemptid, MAX(s.fraction) AS fraction
                        FROM {question_attempt_steps} s
                        JOIN {question_attempts} qa2 ON qa2.id = s.questionattemptid
                        JOIN {question_usages} qu2   ON qu2.id = qa2.questionusageid
                        JOIN {quiz_attempts} qa3     ON qa3.uniqueid = qu2.id
                        JOIN {quiz} q2               ON q2.id = qa3.quiz
                       WHERE q2.course = :courseid
                         AND qa3.state = 'finished'
                         AND qa3.userid $insql
                       GROUP BY s.questionattemptid
                  ) qas ON qas.questionattemptid = qa.id
                 WHERE q.course       = :courseid2
                   AND quiza.state    = 'finished'
                   AND quiza.userid   $insql2
                   AND m.courseid     = :courseid3
              GROUP BY quiza.userid, quiza.quiz, m.competencyid";

        $rows = $DB->get_records_sql($sql, array_merge($inparams, $inparams2, [
            'courseid'  => $this->courseid,
            'courseid2' => $this->courseid,
            'courseid3' => $this->courseid,
        ]));

        $map = [];
        foreach ($rows as $r) {
            if ($r->maxf > 0) {
                $map[(int)$r->userid][$r->quiz . '_' . $r->competencyid] = ($r->gotf / $r->maxf) * 100.0;
            }
        }
        return $map;
    }

    /**
     * Fetch per-user practical competency rates for many users in one query.
     *
     * @param array $userids
     * @return array [userid][asmtid_compid] => percentage float
     */
    private function get_practical_rates_bulk(array $userids): array {
        global $DB;

        if (empty($userids)) {
            return [];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        $inparams['courseid'] = $this->courseid;

        $rows = $DB->get_records_sql("
            SELECT studentid, assessmentid, competencyid, competency_percent
              FROM {local_comp_report_ext_prac}
             WHERE courseid = :courseid
               AND studentid $insql
        ", $inparams);

        $map = [];
        foreach ($rows as $r) {
            $map[(int)$r->studentid][$r->assessmentid . '_' . $r->competencyid] = (float)$r->competency_percent;
        }
        return $map;
    }

    /**
     * Quiz rate map for one user, served from the bulk cache when preloaded.
     *
     * @param int $userid
     * @return array [quizid_compid] => percentage float
     */
    private function bulk_quiz_map(int $userid): array {
        if ($this->quizratescache !== null) {
            return $this->quizratescache[$userid] ?? [];
        }
        return $this->get_quiz_rates_bulk([$userid])[$userid] ?? [];
    }

    /**
     * Practical rate map for one user, served from the bulk cache when preloaded.
     *
     * @param int $userid
     * @return array [asmtid_compid] => percentage float
     */
    private function bulk_practical_map(int $userid): array {
        if ($this->practicalratescache !== null) {
            return $this->practicalratescache[$userid] ?? [];
        }
        return $this->get_practical_rates_bulk([$userid])[$userid] ?? [];
    }

    /**
     * Legacy fallback: plain (un-weighted) average across ALL quizzes.
     * Used when no assessment weights have been configured for the course.
     *
     * @param int $userid
     * @param int|null $filtercompetencyid
     * @return array  Same structure as get_student_scores() but without breakdown.
     */
    private function legacy_plain_scores(int $userid, ?int $filtercompetencyid): array {
        global $DB;

        $threshold = (int)(get_config('local_comp_report_ext', 'success_threshold') ?: 60);

        $sql = "SELECT c.id, c.shortname, c.description, c.descriptionformat,
                       SUM(qa.maxfraction) AS maxf,
                       SUM(qas.fraction)   AS gotf
                  FROM {quiz_attempts} quiza
                  JOIN {question_usages} qu   ON qu.id = quiza.uniqueid
                  JOIN {question_attempts} qa  ON qa.questionusageid = qu.id
                  JOIN {quiz} quiz             ON quiz.id = quiza.quiz
                  JOIN {qbank_comp_ext_qmap} m ON m.questionid = qa.questionid
                  JOIN {competency} c          ON c.id = m.competencyid
                  JOIN (
                      SELECT questionattemptid, MAX(fraction) AS fraction
                        FROM {question_attempt_steps}
                       GROUP BY questionattemptid
                  ) qas ON qas.questionattemptid = qa.id
                 WHERE quiz.course   = :courseid
                   AND quiza.userid  = :userid
                   AND quiza.state   = 'finished'";

        $params = ['courseid' => $this->courseid, 'userid' => $userid];

        if ($filtercompetencyid) {
            $sql .= ' AND c.id = :compid';
            $params['compid'] = $filtercompetencyid;
        }
        $sql .= ' GROUP BY c.id, c.shortname, c.description, c.descriptionformat';

        $rows = $DB->get_records_sql($sql, $params);

        $result = [];
        foreach ($rows as $row) {
            if ($row->maxf <= 0) {
                continue;
            }
            $pct = ($row->gotf / $row->maxf) * 100.0;
            $result[$row->id] = [
                'competency' => $row,
                'percent'    => round($pct, 1),
                'passed'     => $pct >= $threshold,
                'breakdown'  => [], // No breakdown in legacy mode.
            ];
        }
        return $result;
    }

    /**
     * Helper: return colour code based on percentage.
     *
     * @param float $rate
     * @return string  'green' | 'blue' | 'orange' | 'red'
     */
    public static function rate_color(float $rate): string {
        if ($rate >= 80) {
            return 'green';
        } else if ($rate >= 60) {
            return 'blue';
        } else if ($rate >= 40) {
            return 'orange';
        }
        return 'red';
    }

    /**
     * Calculate aggregate competency rates for all students in the course (or a specific group).
     *
     * Returns an array of objects keyed by competency ID:
     *   [
     *     competencyid => (object)[
     *       'competency'  => stdClass (id, shortname, description...),
     *       'course_rate' => float (average score percentage 0-100),
     *     ],
     *   ]
     *
     * @param int|null $groupid Optional group ID to filter students.
     * @return array
     */
    public function get_all_competencies_data(?int $groupid = 0): array {
        global $DB;

        if ($groupid && $groupid > 0) {
            $sql = "SELECT DISTINCT u.id
                      FROM {groups_members} gm
                      JOIN {user} u ON u.id = gm.userid
                      JOIN {role_assignments} ra ON ra.userid = u.id
                      JOIN {context} ctx ON ctx.id = ra.contextid
                     WHERE gm.groupid = :groupid
                       AND ctx.instanceid = :courseid
                       AND ra.roleid = (SELECT id FROM {role} WHERE shortname = 'student')
                       AND u.deleted = 0";
            $students = $DB->get_records_sql($sql, ['groupid' => $groupid, 'courseid' => $this->courseid]);
        } else {
            $sql = "SELECT DISTINCT u.id
                      FROM {role_assignments} ra
                      JOIN {role} r ON r.id = ra.roleid
                      JOIN {context} ctx ON ctx.id = ra.contextid
                      JOIN {user} u ON u.id = ra.userid
                     WHERE ctx.instanceid = :courseid
                       AND ctx.contextlevel = 50
                       AND r.shortname = 'student'
                       AND u.deleted = 0";
            $students = $DB->get_records_sql($sql, ['courseid' => $this->courseid]);
        }

        if (empty($students)) {
            return [];
        }

        // Bulk-load quiz and practical rates for all students in two queries.
        $this->preload_user_data(array_map('intval', array_keys($students)));

        $compscores = [];
        $compobjects = [];

        foreach ($students as $student) {
            $scores = $this->get_student_scores((int)$student->id);
            foreach ($scores as $compid => $data) {
                if (!isset($compobjects[$compid])) {
                    $compname = is_object($data['competency'])
                        ? $data['competency']->shortname
                        : ($data['competency']['shortname'] ?? 'Comp ' . $compid);
                    $compobjects[$compid] = (object)[
                        'id' => $compid,
                        'shortname' => $compname,
                    ];
                }
                $compscores[$compid][] = (float)$data['percent'];
            }
        }

        $result = [];
        foreach ($compscores as $compid => $scores) {
            if (empty($scores)) {
                continue;
            }
            $avgrate = round(array_sum($scores) / count($scores), 1);
            $item = new \stdClass();
            $item->competency = $compobjects[$compid];
            $item->course_rate = $avgrate;
            $result[$compid] = $item;
        }

        return $result;
    }
}
