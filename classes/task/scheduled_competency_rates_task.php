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
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_comp_report_ext\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Class scheduled_competency_rates_task
 *
 * Nightly safety-net sync: re-syncs competency proficiency + evidence for all
 * active courses. Delegates all DB writes to competency_sync so it never
 * duplicates evidence and stays consistent with the quiz observer and the
 * manual admin trigger.
 *
 * @package local_comp_report_ext
 */
class scheduled_competency_rates_task extends \core\task\scheduled_task {
    /**
     * Get a descriptive name for the scheduled task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('process_success_title', 'local_comp_report_ext') . ' (Automatic Sync)';
    }

    /**
     * Run the scheduled task.
     */
    public function execute() {
        global $DB;

        // Fetch all active courses.
        $courses = $DB->get_records('course', ['visible' => 1]);
        $graderid = \local_comp_report_ext\competency_sync::resolve_grader_id();

        foreach ($courses as $course) {
            if ($course->id == SITEID) {
                continue;
            }
            try {
                \local_comp_report_ext\competency_sync::sync_course((int)$course->id, $graderid);
            } catch (\Throwable $e) {
                // Never let one course break the whole nightly run.
                debugging('competency_sync course ' . $course->id . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
                unset($e);
            }
        }
    }
}
