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
// Event observer for quiz attempts inside local_competency_report.
 *
 * @package    local_competency_report
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_competency_report;

defined('MOODLE_INTERNAL') || die();

/**
 * Class observer
 *
 * Handles immediate calculation of competency rates and registers user evidence on quiz submission.
 *
 * @package    local_competency_report
 */
class observer {
    /**
     * Listener for quiz attempt submission.
     * Calculates competency success rates and pushes to evidence.
     *
     * @param \mod_quiz\event\attempt_submitted $event
     * @return void
     */
    public static function quiz_attempt_submitted(\mod_quiz\event\attempt_submitted $event) {
        global $DB;

        // Retrieve the quiz attempt details.
        $eventdata = $event->get_record_snapshot('quiz_attempts', $event->objectid);
        if (!$eventdata || $eventdata->state !== 'finished') {
            return;
        }

        $userid = $eventdata->userid;
        $quizid = $eventdata->quiz;

        $quiz = $DB->get_record('quiz', ['id' => $quizid]);
        if (!$quiz) {
            return;
        }

        $courseid = $quiz->course;
        $contextid = \context_course::instance($courseid)->id;
        $adminid = 2; // Default to Admin user/system context.

        // 1. Fetch competencies mapped to questions inside this completed quiz attempt.
        $sql = "SELECT DISTINCT c.id, c.shortname
                  FROM {quiz_attempts} quiza
                  JOIN {question_usages} qu ON qu.id = quiza.uniqueid
                  JOIN {question_attempts} qa ON qa.questionusageid = qu.id
                  JOIN {qbank_competency_qmap} m ON m.questionid = qa.questionid
                  JOIN {competency} c ON c.id = m.competencyid
                 WHERE quiza.id = :attemptid AND m.courseid = :courseid";
        
        $competencies = $DB->get_records_sql($sql, ['attemptid' => $eventdata->id, 'courseid' => $courseid]);

        if (empty($competencies)) {
            return;
        }

        // 2. Loop through competencies and insert evidence instantly.
        foreach ($competencies as $c) {
            $rate = self::get_user_competency_rate($userid, $c->id, $courseid);

            if ($rate === null) {
                continue;
            }

            $ratestr = number_format($rate, 1);
            $a = new \stdClass();
            $a->competency = $c->shortname;
            $a->rate = $ratestr;

            // Insert user evidence.
            $evidence = new \stdClass();
            $evidence->userid = $userid;
            $evidence->name = get_string('process_success_title', 'local_competency_report') . " (Auto Sync " . date('d.m.Y') . ")";
            $evidence->description = get_string('evidence_description', 'local_competency_report', $a);
            $evidence->descriptionformat = FORMAT_HTML;
            $evidence->url = '';
            $evidence->timecreated = time();
            $evidence->timemodified = time();
            $evidence->usermodified = $adminid;
            $evidenceid = $DB->insert_record('competency_userevidence', $evidence);

            $link = new \stdClass();
            $link->userevidenceid = $evidenceid;
            $link->competencyid = $c->id;
            $link->timecreated = time();
            $link->timemodified = time();
            $link->usermodified = $adminid;
            $DB->insert_record('competency_userevidencecomp', $link);

            $uc = $DB->get_record('competency_usercomp', ['userid' => $userid, 'competencyid' => $c->id]);
            if (!$uc) {
                $uc = new \stdClass();
                $uc->userid = $userid;
                $uc->competencyid = $c->id;
                $uc->timecreated = time();
                $uc->timemodified = time();
                $uc->usermodified = $adminid;
                $uc->id = $DB->insert_record('competency_usercomp', $uc);
            }

            $cevidence = new \stdClass();
            $cevidence->usercompetencyid = $uc->id;
            $cevidence->contextid = $contextid;
            $cevidence->action = 1;
            $cevidence->actionuserid = $adminid;
            $cevidence->descidentifier = 'evidence';
            $cevidence->desccomponent = 'local_competency_report';
            $cevidence->desca = null;
            $cevidence->url = '';
            $cevidence->grade = (int)$rate;
            $cevidence->note = get_string('evidence_note', 'local_competency_report', $a);
            $cevidence->timecreated = time();
            $cevidence->timemodified = time();
            $cevidence->usermodified = $adminid;
            $DB->insert_record('competency_evidence', $cevidence);
        }

        // 3. After syncing all competencies, check if this student is at-risk and notify teachers.
        // Collect ALL competency rates for this student/course (not just this quiz).
        $allratesql = "SELECT c.shortname,
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
                        WHERE quiz.course = :courseid AND quiza.userid = :userid AND quiza.state = 'finished'
                     GROUP BY c.shortname";

        $allrates = [];
        $allraterows = $DB->get_records_sql($allratesql, ['courseid' => $courseid, 'userid' => $userid]);
        foreach ($allraterows as $ar) {
            $allrates[$ar->shortname] = $ar->questions > 0 ? ($ar->correct / $ar->questions) * 100 : 0;
        }

        if (!empty($allrates)) {
            local_competency_report_check_and_notify($userid, $courseid, $allrates);
        }
    }


    /**
     * Calculate user competency rate based on quiz attempts.
     *
     * @param int $userid
     * @param int $competencyid
     * @param int $courseid
     * @return float|null
     */
    private static function get_user_competency_rate($userid, $competencyid, $courseid) {
        global $DB;
        $sql = "SELECT CAST(SUM(qa.maxfraction) AS DECIMAL(12,1)) AS questions,
                       CAST(SUM(qas.fraction) AS DECIMAL(12,1)) AS correct
                  FROM {quiz_attempts} quiza
                  JOIN {question_usages} qu ON qu.id = quiza.uniqueid
                  JOIN {question_attempts} qa ON qa.questionusageid = qu.id
                  JOIN {quiz} quiz ON quiz.id = quiza.quiz
                  JOIN {qbank_competency_qmap} m ON m.questionid = qa.questionid
                  JOIN (
                       SELECT MAX(fraction) AS fraction, questionattemptid
                         FROM {question_attempt_steps}
                     GROUP BY questionattemptid
                  ) qas ON qas.questionattemptid = qa.id
                 WHERE quiz.course = :courseid
                   AND quiza.userid = :userid
                   AND m.competencyid = :competencyid";

        $row = $DB->get_record_sql($sql, ['courseid' => $courseid, 'userid' => $userid, 'competencyid' => $competencyid]);
        if ($row && $row->questions > 0) {
            return ($row->correct / $row->questions) * 100;
        }
        return null;
    }
}
