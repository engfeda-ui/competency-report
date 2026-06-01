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
 * Scheduled Task to automatically calculate and sync competency rates.
 *
 * @package    local_competency_report
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_competency_report\task;

/**
 * Class scheduled_competency_rates_task
 *
 * Scheduled cron task to calculate quiz-based competency success rates for all active courses.
 *
 * @package    local_competency_report
 */
class scheduled_competency_rates_task extends \core\task\scheduled_task {
    /**
     * Get a descriptive name for the scheduled task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('process_success_title', 'local_competency_report') . ' (Automatic Sync)';
    }

    /**
     * Run the scheduled task.
     */
    public function execute() {
        global $DB;

        // Fetch all active courses.
        $courses = $DB->get_records('course', ['visible' => 1]);
        $adminid = 2; // Default to Admin user.

        foreach ($courses as $course) {
            if ($course->id == SITEID) {
                continue;
            }

            $courseid = $course->id;
            $contextid = \context_course::instance($courseid)->id;

            $sql = "SELECT DISTINCT c.id, c.shortname
                      FROM {qbank_competency_qmap} m
                      JOIN {competency} c ON c.id = m.competencyid
                     WHERE m.courseid = :courseid
                  ORDER BY c.shortname";
            $competencies = $DB->get_records_sql($sql, ['courseid' => $courseid]);

            if (empty($competencies)) {
                continue;
            }

            // Fetch only enrolled, active students in this course.
            $context = \context_course::instance($courseid);
            $students = get_enrolled_users($context, 'mod/quiz:attempt', 0, 'u.*', null, 0, 0, true);

            if (empty($students)) {
                continue;
            }

            foreach ($students as $student) {
                foreach ($competencies as $c) {
                    $rate = $this->get_user_competency_rate($student->id, $c->id, $courseid);

                    if ($rate === null) {
                        continue;
                    }

                    $ratestr = number_format($rate, 1);
                    $a = new \stdClass();
                    $a->competency = $c->shortname;
                    $a->rate = $ratestr;

                    // Insert user evidence.
                    $evidence = new \stdClass();
                    $evidence->userid = $student->id;
                    $evidence->name = get_string('process_success_title', 'local_competency_report')
                        . " (Auto Sync " . date('d.m.Y') . ")";
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

                    $uc = $DB->get_record('competency_usercomp', ['userid' => $student->id, 'competencyid' => $c->id]);
                    if (!$uc) {
                        $uc = new \stdClass();
                        $uc->userid = $student->id;
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
            }
        }
    }

    /**
     * Calculate user competency rate based on quiz attempts.
     */
    private function get_user_competency_rate($userid, $competencyid, $courseid) {
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
