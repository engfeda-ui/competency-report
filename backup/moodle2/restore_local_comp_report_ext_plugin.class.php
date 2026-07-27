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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Restore structure step for local_comp_report_ext.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Restore class for local_comp_report_ext plugin.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_local_comp_report_ext_plugin extends restore_local_plugin {

    /**
     * Define the plugin restore structure.
     *
     * @return restore_path_element[]
     */
    protected function define_course_plugin_structure() {
        $paths = [];

        $paths[] = new restore_path_element('assessment', '/plugin/assessments/assessment');
        if ($this->get_setting_value('users')) {
            $paths[] = new restore_path_element('practical', '/plugin/practicals/practical');
        }

        return $paths;
    }

    /**
     * Process assessment restore element.
     *
     * @param array $data Data from XML.
     */
    public function process_assessment($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->courseid = $this->get_courseid();
        if (!empty($data->quizid)) {
            $data->quizid = $this->get_mappingid('quiz', $data->quizid);
        }
        if (!empty($data->assignid)) {
            $data->assignid = $this->get_mappingid('assign', $data->assignid);
        }

        $newitemid = $DB->insert_record('local_comp_report_ext_asmt', $data);
        $this->set_mapping('local_comp_report_ext_asmt', $oldid, $newitemid);
    }

    /**
     * Process practical score restore element.
     *
     * @param array $data Data from XML.
     */
    public function process_practical($data) {
        global $DB;

        $data = (object)$data;

        $data->courseid     = $this->get_courseid();
        $data->assessmentid = $this->get_mappingid('local_comp_report_ext_asmt', $data->assessmentid);
        $data->studentid    = $this->get_mappingid('user', $data->studentid);
        $data->trainerid    = $this->get_mappingid('user', $data->trainerid);

        if ($data->assessmentid && $data->studentid) {
            $DB->insert_record('local_comp_report_ext_prac', $data);
        }
    }
}
