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

use local_competency_report\competency_calculator;

/**
 * Class scheduled_competency_rates_task
 *
 * Scheduled cron task to calculate weighted competency success rates for all active courses
 * and push evidence + at-risk alerts for every enrolled student.
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

        // FIX: Use real admin ID instead of hardcoded 2.
        $adminid = get_admin()->id;

        // Fetch all active courses.
        $courses = $DB->get_records('course', ['visible' => 1]);

        foreach ($courses as $course) {
            if ($course->id == SITEID) {
                continue;
            }

            $courseid  = $course->id;
            $contextid = \context_course::instance($courseid)->id;
            $context   = \context_course::instance($courseid);

            // Check there are competency mappings for this course.
            $hascompetencies = $DB->record_exists('qbank_competency_qmap', ['courseid' => $courseid]);
            if (!$hascompetencies) {
                continue;
            }

            // Fetch only enrolled, active students.
            $students = get_enrolled_users($context, 'mod/quiz:attempt', 0, 'u.*', null, 0, 0, true);
            if (empty($students)) {
                continue;
            }

            // FIX: Use competency_calculator so results respect assessment weights.
            $calculator = new competency_calculator($courseid);

            foreach ($students as $student) {
                $studentscores = $calculator->get_student_scores((int)$student->id);

                if (empty($studentscores)) {
                    continue;
                }

                // FIX: Upsert evidence — delete today's records before inserting fresh ones.
                $todaystart = mktime(0, 0, 0, date('n'), date('j'), date('Y'));

                foreach ($studentscores as $compid => $data) {
                    $rate = $data['percent'];
                    $comp = $data['competency'];

                    $ratestr = number_format($rate, 1);
                    $a = new \stdClass();
                    $a->competency = $comp->shortname;
                    $a->rate       = $ratestr;

                    // Delete today's competency_evidence for this user+competency.
                    $existingevids = $DB->get_fieldset_sql(
                        "SELECT ce.id
                           FROM {competency_evidence} ce
                           JOIN {competency_usercomp} uc ON uc.id = ce.usercompetencyid
                          WHERE uc.userid = :userid AND uc.competencyid = :compid
                            AND ce.timecreated >= :todaystart
                            AND ce.desccomponent = 'local_competency_report'",
                        ['userid' => $student->id, 'compid' => $compid, 'todaystart' => $todaystart]
                    );
                    if (!empty($existingevids)) {
                        $DB->delete_records_list('competency_evidence', 'id', $existingevids);
                    }

                    $uc = $DB->get_record('competency_usercomp', ['userid' => $student->id, 'competencyid' => $compid]);
                    if ($uc) {
                        $lastev = $DB->get_record_sql(
                            "SELECT id, grade
                               FROM {competency_evidence}
                              WHERE usercompetencyid = :usercompid
                                AND desccomponent = 'local_competency_report'
                           ORDER BY timecreated DESC, id DESC",
                            ['usercompid' => $uc->id],
                            IGNORE_MISSING
                        );
                        if ($lastev && $lastev->grade == (int)$rate) {
                            continue; // Skip adding duplicate evidence since the success rate has not changed.
                        }
                    }

                    $evidence = new \stdClass();
                    $evidence->userid            = $student->id;
                    $evidence->name              = get_string('process_success_title', 'local_competency_report')
                                                   . " (Auto Sync " . date('d.m.Y') . ")";
                    $evidence->description       = get_string('evidence_description', 'local_competency_report', $a);
                    $evidence->descriptionformat = FORMAT_HTML;
                    $evidence->url               = '';
                    $evidence->timecreated       = time();
                    $evidence->timemodified      = time();
                    $evidence->usermodified      = $adminid;
                    $evidenceid = $DB->insert_record('competency_userevidence', $evidence);

                    $link                = new \stdClass();
                    $link->userevidenceid = $evidenceid;
                    $link->competencyid  = $compid;
                    $link->timecreated   = time();
                    $link->timemodified  = time();
                    $link->usermodified  = $adminid;
                    $DB->insert_record('competency_userevidencecomp', $link);

                    if (!$uc) {
                        $uc               = new \stdClass();
                        $uc->userid       = $student->id;
                        $uc->competencyid = $compid;
                        $uc->timecreated  = time();
                        $uc->timemodified = time();
                        $uc->usermodified = $adminid;
                        $uc->id = $DB->insert_record('competency_usercomp', $uc);
                    }

                    $cevidence                   = new \stdClass();
                    $cevidence->usercompetencyid = $uc->id;
                    $cevidence->contextid        = $contextid;
                    $cevidence->action           = 1;
                    $cevidence->actionuserid     = $adminid;
                    $cevidence->descidentifier   = 'evidence';
                    $cevidence->desccomponent    = 'local_competency_report';
                    $cevidence->desca            = null;
                    $cevidence->url              = '';
                    $cevidence->grade            = (int)$rate;
                    $cevidence->note             = get_string('evidence_note', 'local_competency_report', $a);
                    $cevidence->timecreated      = time();
                    $cevidence->timemodified     = time();
                    $cevidence->usermodified     = $adminid;
                    $DB->insert_record('competency_evidence', $cevidence);
                }

                // FIX: Send at-risk alerts from the scheduled task too (was missing before).
                $allrates = [];
                foreach ($studentscores as $compid => $data) {
                    $allrates[$data['competency']->shortname] = $data['percent'];
                }
                if (!empty($allrates)) {
                    local_competency_report_check_and_notify($student->id, $courseid, $allrates);
                }
            }
        }
    }
}
