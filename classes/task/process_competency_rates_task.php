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
 * Class Report for Competency Matching.
 *
 * @package    local_yetkinlik
 * @copyright  2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_yetkinlik\task;

/**
 * Class process_competency_rates_task
 *
 * Background task to calculate quiz-based competency success rates.
 *
 * @package    local_yetkinlik
 */
class process_competency_rates_task extends \core\task\adhoc_task {
    /**
     * Run the task to process competency rates.
     */
    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        $courseid = $data->courseid;
        $adminid = $data->adminid;
        $contextid = \context_course::instance($courseid)->id;

        $sql = "SELECT DISTINCT c.id, c.shortname
                  FROM {qbank_yetkinlik_qmap} m
                  JOIN {competency} c ON c.id = m.competencyid
              ORDER BY c.shortname";
        $competencies = $DB->get_records_sql($sql);

        $students = $DB->get_records('user', ['deleted' => 0, 'suspended' => 0]);

        foreach ($students as $student) {
            foreach ($competencies as $c) {
                $rate = $this->get_user_competency_rate($student->id, $c->id, $courseid);

                if ($rate === null) {
                    continue;
                }

                // Let's retrieve the text from the language file.
                $ratestr = number_format($rate, 1);
                $a = new \stdClass();
                $a->competency = $c->shortname;
                $a->rate = $ratestr;

                $evidence = new \stdClass();
                $evidence->userid = $student->id;
                $evidence->name = get_string('process_success_title', 'local_yetkinlik') . " (" . date('d.m.Y') . ")";
                $evidence->description = get_string('evidence_description', 'local_yetkinlik', $a);
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
                $cevidence->desccomponent = 'local_yetkinlik';
                $cevidence->desca = null;
                $cevidence->url = '';
                $cevidence->grade = (int)$rate;
                $cevidence->note = get_string('evidence_note', 'local_yetkinlik', $a);
                $cevidence->timecreated = time();
                $cevidence->timemodified = time();
                $cevidence->usermodified = $adminid;
                $DB->insert_record('competency_evidence', $cevidence);
            }
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
    private function get_user_competency_rate($userid, $competencyid, $courseid) {
        global $DB;
        $sql = "SELECT CAST(SUM(qa.maxfraction) AS DECIMAL(12,1)) AS questions,
                       CAST(SUM(qas.fraction) AS DECIMAL(12,1)) AS correct
                  FROM {quiz_attempts} quiza
                  JOIN {question_usages} qu ON qu.id = quiza.uniqueid
                  JOIN {question_attempts} qa ON qa.questionusageid = qu.id
                  JOIN {quiz} quiz ON quiz.id = quiza.quiz
                  JOIN {qbank_yetkinlik_qmap} m ON m.questionid = qa.questionid
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
