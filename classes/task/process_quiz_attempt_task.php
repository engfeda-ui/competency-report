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
 * Adhoc task: process quiz attempt submission competency sync.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_comp_report_ext\task;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../lib.php');

/**
 * Class process_quiz_attempt_task
 *
 * Adhoc background task to sync competency proficiency and evaluate at-risk alerts asynchronously.
 *
 * Expected custom_data: { userid: int, courseid: int, quizid: int }
 *
 * @package local_comp_report_ext
 */
class process_quiz_attempt_task extends \core\task\adhoc_task {
    /**
     * Run the task to process quiz attempt submission.
     */
    public function execute() {
        $data = $this->get_custom_data();
        if (empty($data->userid) || empty($data->courseid)) {
            return;
        }

        $userid   = (int)$data->userid;
        $courseid = (int)$data->courseid;

        $graderid = \local_comp_report_ext\competency_sync::resolve_grader_id();
        $rates    = \local_comp_report_ext\competency_sync::sync_user_competency($userid, $courseid, $graderid);

        if (!empty($rates)) {
            local_comp_report_ext_check_and_notify($userid, $courseid, $rates);
        }
    }
}
