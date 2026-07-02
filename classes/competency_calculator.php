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
 * @package    local_competency_report
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_competency_report;

/**
 * Weighted competency score calculator.
 *
 * @package local_competency_report
 */
class competency_calculator {

    /** @var int */
    private $courseid;

    /** @var array|null Cached assessment rows for this course */
    private $assessments = null;

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
                'local_competency_report_asmt',
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

        $threshold = (int)(get_config('local_competency_report', 'success_threshold') ?: 60);

        // Fetch all competencies that have question mappings in this course.
        $compsql = "SELECT DISTINCT c.id, c.shortname, c.description, c.descriptionformat
                      FROM {qbank_competency_qmap} m
                      JOIN {competency} c ON c.id = m.competencyid
                     WHERE m.courseid = :courseid";
        $params = ['courseid' => $this->courseid];
        if ($filtercompetencyid) {
            $compsql .= ' AND c.id = :compid';
            $params['compid'] = $filtercompetencyid;
        }
        $competencies = $DB->get_records_sql($compsql, $params);

        // Also add competencies that appear only in practical assessments.
        foreach ($assessments as $assessment) {
            if ($assessment->type === 'practical') {
                $practicals = $DB->get_records(
                    'local_competency_report_prac',
                    ['assessmentid' => $assessment->id, 'courseid' => $this->courseid],
                    '',
                    'DISTINCT competencyid'
                );
                foreach ($practicals as $p) {
                    if (!isset($competencies[$p->competencyid])) {
                        $comp = $DB->get_record('competency', ['id' => $p->competencyid]);
                        if ($comp && (!$filtercompetencyid || $p->competencyid == $filtercompetencyid)) {
                            $competencies[$comp->id] = $comp;
                        }
                    }
                }
            }
        }

        $result = [];

        foreach ($competencies as $comp) {
            $totalweighted = 0.0;
            $totalweight   = 0.0;
            $breakdown     = [];

            foreach ($assessments as $assessment) {
                $scorepct = null;

                if ($assessment->type === 'quiz' && !empty($assessment->quizid)) {
                    // Get question-attempt score for this quiz + competency + student.
                    $scorepct = $this->get_quiz_score_pct($userid, (int)$assessment->quizid, (int)$comp->id);

                } else if ($assessment->type === 'practical') {
                    // Get latest manual practical result.
                    $record = $DB->get_record(
                        'local_competency_report_prac',
                        [
                            'assessmentid' => $assessment->id,
                            'studentid'    => $userid,
                            'competencyid' => $comp->id,
                        ],
                        'competency_percent',
                        IGNORE_MISSING
                    );
                    if ($record) {
                        $scorepct = (float)$record->competency_percent;
                    }
                }

                if ($scorepct !== null) {
                    $weighted = $scorepct * ((float)$assessment->weight / 100.0);
                    $totalweighted += $weighted;
                    $totalweight   += (float)$assessment->weight;

                    $breakdown[] = [
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
     * Get the raw question-attempt score percentage for a student in a specific
     * quiz for questions mapped to a specific competency.
     *
     * @param int $userid
     * @param int $quizid
     * @param int $competencyid
     * @return float|null  Percentage 0-100, or null if no finished attempt found.
     */
    private function get_quiz_score_pct(int $userid, int $quizid, int $competencyid): ?float {
        global $DB;

        $sql = "SELECT SUM(qa.maxfraction) AS maxf,
                       SUM(qas.fraction)   AS gotf
                  FROM {quiz_attempts} quiza
                  JOIN {question_usages} qu   ON qu.id = quiza.uniqueid
                  JOIN {question_attempts} qa  ON qa.questionusageid = qu.id
                  JOIN {qbank_competency_qmap} m ON m.questionid = qa.questionid
                  JOIN (
                      SELECT questionattemptid, MAX(fraction) AS fraction
                        FROM {question_attempt_steps}
                       GROUP BY questionattemptid
                  ) qas ON qas.questionattemptid = qa.id
                 WHERE quiza.quiz      = :quizid
                   AND quiza.userid    = :userid
                   AND quiza.state     = 'finished'
                   AND m.competencyid  = :competencyid
                   AND m.courseid      = :courseid";

        $row = $DB->get_record_sql($sql, [
            'quizid'       => $quizid,
            'userid'       => $userid,
            'competencyid' => $competencyid,
            'courseid'     => $this->courseid,
        ]);

        if (!$row || $row->maxf <= 0) {
            return null;
        }

        return ($row->gotf / $row->maxf) * 100.0;
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

        $threshold = (int)(get_config('local_competency_report', 'success_threshold') ?: 60);

        $sql = "SELECT c.id, c.shortname, c.description, c.descriptionformat,
                       SUM(qa.maxfraction) AS maxf,
                       SUM(qas.fraction)   AS gotf
                  FROM {quiz_attempts} quiza
                  JOIN {question_usages} qu   ON qu.id = quiza.uniqueid
                  JOIN {question_attempts} qa  ON qa.questionusageid = qu.id
                  JOIN {quiz} quiz             ON quiz.id = quiza.quiz
                  JOIN {qbank_competency_qmap} m ON m.questionid = qa.questionid
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
}
