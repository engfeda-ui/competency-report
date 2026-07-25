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
 * Event observer for quiz attempts inside local_comp_report_ext.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_comp_report_ext;

defined('MOODLE_INTERNAL') || die();

/**
 * Class observer
 *
 * Reacts to quiz submissions: syncs the student's competency proficiency +
 * evidence (real-time, deduplicated) and evaluates at-risk alerts.
 *
 * All DB writes are delegated to competency_sync so the quiz observer, the
 * nightly cron task and the manual admin trigger share one code path.
 *
 * @package local_comp_report_ext
 */
class observer {
    /**
     * Listener for quiz attempt submission.
     *
     * Syncs competency proficiency + evidence instantly, then evaluates whether
     * the student should trigger an at-risk teacher notification.
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

        // 1. Sync this student's competency proficiency + evidence (deduped,
        //    weighted, respects configured assessment weights).
        $graderid = competency_sync::resolve_grader_id();
        $rates = competency_sync::sync_user_competency($userid, $courseid, $graderid);

        // 2. Evaluate at-risk alerts across ALL the student's competencies.
        if (!empty($rates)) {
            local_comp_report_ext_check_and_notify($userid, $courseid, $rates);
        }
    }
}
