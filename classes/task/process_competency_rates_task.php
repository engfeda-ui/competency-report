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
 * Background adhoc task to calculate quiz-based competency success rates.
 *
 * @package    local_competency_report
 * @copyright  2026 Mahmoud Salem
 * @copyright  based on work by 2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_competency_report\task;

use local_competency_report\competency_calculator;

/**
 * Class process_competency_rates_task
 *
 * Background adhoc task to calculate weighted competency success rates for a single course.
 *
 * @package    local_competency_report
 */
class process_competency_rates_task extends \core\task\adhoc_task {
    /**
     * Run the task to process competency rates.
     */
    public function execute() {
        global $DB;

        $data     = $this->get_custom_data();
        $courseid = $data->courseid;
        // FIX: Use real admin ID instead of hardcoded value.
        $adminid  = get_admin()->id;
        $contextid = \context_course::instance($courseid)->id;

        $context  = \context_course::instance($courseid);
        $students = get_enrolled_users($context, 'mod/quiz:attempt', 0, 'u.*', null, 0, 0, true);

        // FIX: Use competency_calculator so results respect assessment weights.
        $calculator = new competency_calculator($courseid);

        // FIX: Upsert — delete today's records before inserting fresh ones.
        $todaystart = mktime(0, 0, 0, date('n'), date('j'), date('Y'));

        foreach ($students as $student) {
            $studentscores = $calculator->get_student_scores((int)$student->id);

            if (empty($studentscores)) {
                continue;
            }

            foreach ($studentscores as $compid => $scoredata) {
                $rate = $scoredata['percent'];
                $comp = $scoredata['competency'];

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

                $evidence = new \stdClass();
                $evidence->userid            = $student->id;
                $evidence->name              = get_string('process_success_title', 'local_competency_report')
                                               . " (" . date('d.m.Y') . ")";
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

                $uc = $DB->get_record('competency_usercomp', ['userid' => $student->id, 'competencyid' => $compid]);
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
        }
    }
}
