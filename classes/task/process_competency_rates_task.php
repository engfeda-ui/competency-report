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
 * Adhoc task: process competency success rates for one course.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @copyright  based on work by 2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_comp_report_ext\task;

/**
 * Class process_competency_rates_task
 *
 * Adhoc background task to sync competency proficiency + evidence for every
 * enrolled student in a single course. All DB writes go through
 * competency_sync, so it is deduped, weighted and consistent with the quiz
 * observer and the nightly scheduled task.
 *
 * Expected custom_data: { courseid: int, adminid: int }
 *
 * @package local_comp_report_ext
 */
class process_competency_rates_task extends \core\task\adhoc_task {
    /**
     * Run the task to process competency rates.
     */
    public function execute() {
        $data = $this->get_custom_data();
        if (empty($data->courseid)) {
            return;
        }

        $courseid = (int)$data->courseid;
        $adminid = isset($data->adminid) ? (int)$data->adminid : 0;

        \local_comp_report_ext\competency_sync::sync_course($courseid, $adminid);
    }
}
