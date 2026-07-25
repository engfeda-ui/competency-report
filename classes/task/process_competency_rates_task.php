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
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @copyright  based on work by 2026 Hakan Ã‡iÄŸci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_comp_report_ext\task;

/**
 * Class process_competency_rates_task
 *
 * Background task to calculate quiz-based competency success rates.
 *
 * @package    local_comp_report_ext
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
                  FROM {qbank_comp_ext_qmap} m
                  JOIN {competency} c ON c.id = m.competencyid
                 WHERE m.courseid = :courseid
              ORDER BY c.shortname";
        $competencies = $DB->get_records_sql($sql, ['courseid' => $courseid]);

        // Fetch only enrolled, active students in this course — not all site users.
        $context = \context_course::instance($courseid);
        $students = get_enrolled_users($context, 'mod/quiz:attempt', 0, 'u.*', null, 0, 0, true);

        foreach ($students as $student) {
            foreach ($competencies as $c) {
                $rate = $this->get_user_competency_rate($student->id, $c->id, $courseid);

                if ($rate === null) {
                    continue;
                }

                // Retrieve localized strings.
                $ratestr = number_format($rate, 1);
                $a = new \stdClass();
                $a->competency = $c->shortname;
                $a->rate = $ratestr;

                $threshold = (int)(get_config('local_comp_report_ext', 'success_threshold') ?: 60);
                $isproficient = ($rate >= $threshold) ? 1 : 0;

                // 1. Update/create user competency record.
                $uc = $DB->get_record('competency_usercomp', ['userid' => $student->id, 'competencyid' => $c->id]);
                if (!$uc) {
                    $uc = new \stdClass();
                    $uc->userid       = $student->id;
                    $uc->competencyid = $c->id;
                    $uc->status       = 0;
                    $uc->proficiency  = $isproficient;
                    $uc->timecreated  = time();
                    $uc->timemodified = time();
                    $uc->usermodified = $adminid;
                    $uc->id = $DB->insert_record('competency_usercomp', $uc);
                } else {
                    $uc->proficiency  = $isproficient;
                    $uc->timemodified = time();
                    $uc->usermodified = $adminid;
                    $DB->update_record('competency_usercomp', $uc);
                }

                // 2. Update/create course-level user competency status.
                $ucc = $DB->get_record('competency_usercompcourse', [
                    'userid'       => $student->id,
                    'courseid'     => $courseid,
                    'competencyid' => $c->id,
                ]);
                if (!$ucc) {
                    $ucc = new \stdClass();
                    $ucc->userid       = $student->id;
                    $ucc->courseid     = $courseid;
                    $ucc->competencyid = $c->id;
                    $ucc->proficiency  = $isproficient;
                    $ucc->timecreated  = time();
                    $ucc->timemodified = time();
                    $ucc->usermodified = $adminid;
                    $DB->insert_record('competency_usercompcourse', $ucc);
                } else {
                    $ucc->proficiency  = $isproficient;
                    $ucc->timemodified = time();
                    $ucc->usermodified = $adminid;
                    $DB->update_record('competency_usercompcourse', $ucc);
                }

                // 3. Purge all redundant userevidence and userevidencecomp records.
                $userlinks = $DB->get_records_sql(
                    "SELECT l.id AS linkid, e.id AS evidenceid
                       FROM {competency_userevidencecomp} l
                       JOIN {competency_userevidence} e ON e.id = l.userevidenceid
                      WHERE e.userid = :userid AND l.competencyid = :compid",
                    ['userid' => $student->id, 'compid' => $c->id]
                );
                if (!empty($userlinks)) {
                    foreach ($userlinks as $link) {
                        $DB->delete_records('competency_userevidencecomp', ['id' => $link->linkid]);
                        $DB->delete_records('competency_userevidence', ['id' => $link->evidenceid]);
                    }
                }

                // 4. Maintain strictly 1 evidence record in {competency_evidence} per user competency.
                $existingevidences = $DB->get_records_sql(
                    "SELECT id FROM {competency_evidence}
                      WHERE usercompetencyid = :ucid
                   ORDER BY id DESC",
                    ['ucid' => $uc->id]
                );

                if (!empty($existingevidences)) {
                    // Keep ONLY 1 (the newest one), purge all old duplicate evidence logs.
                    $newestcev = array_shift($existingevidences);
                    if (!empty($existingevidences)) {
                        $dupeids = array_keys($existingevidences);
                        $DB->delete_records_list('competency_evidence', 'id', $dupeids);
                    }

                    // Update the single remaining evidence log with current rate and note.
                    $newestcev->grade          = (int)$rate;
                    $newestcev->note           = get_string('evidence_note', 'local_comp_report_ext', $a);
                    $newestcev->contextid      = $contextid;
                    $newestcev->desccomponent  = 'local_comp_report_ext';
                    $newestcev->descidentifier = 'evidence';
                    $newestcev->timemodified   = time();
                    $newestcev->usermodified   = $adminid;
                    $DB->update_record('competency_evidence', $newestcev);
                } else {
                    // Insert single new evidence log.
                    $cevidence = new \stdClass();
                    $cevidence->usercompetencyid = $uc->id;
                    $cevidence->contextid        = $contextid;
                    $cevidence->action           = 1;
                    $cevidence->actionuserid     = $adminid;
                    $cevidence->descidentifier   = 'evidence';
                    $cevidence->desccomponent    = 'local_comp_report_ext';
                    $cevidence->desca            = null;
                    $cevidence->url              = '';
                    $cevidence->grade            = (int)$rate;
                    $cevidence->note             = get_string('evidence_note', 'local_comp_report_ext', $a);
                    $cevidence->timecreated      = time();
                    $cevidence->timemodified     = time();
                    $cevidence->usermodified     = $adminid;
                    $DB->insert_record('competency_evidence', $cevidence);
                }
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
                  JOIN {qbank_comp_ext_qmap} m ON m.questionid = qa.questionid
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
