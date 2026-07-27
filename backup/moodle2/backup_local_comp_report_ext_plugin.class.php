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
 * Backup structure step for local_comp_report_ext.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Backup class for local_comp_report_ext plugin.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_local_comp_report_ext_plugin extends backup_local_plugin {

    /**
     * Define the plugin backup structure.
     *
     * @return backup_plugin_element
     */
    protected function define_course_plugin_structure() {
        $plugin = $this->get_plugin_element();

        $pluginwrapper = new backup_nested_element($this->get_recommended_name());

        $assessments = new backup_nested_element('assessments');
        $assessment  = new backup_nested_element('assessment', ['id'], [
            'courseid', 'quizid', 'assignid', 'name', 'type', 'weight',
            'timecreated', 'timemodified',
        ]);

        $practicals  = new backup_nested_element('practicals');
        $practical   = new backup_nested_element('practical', ['id'], [
            'assessmentid', 'courseid', 'competencyid', 'studentid',
            'trainerid', 'competency_percent', 'timecreated', 'timemodified',
        ]);

        $pluginwrapper->add_child($assessments);
        $assessments->add_child($assessment);

        $pluginwrapper->add_child($practicals);
        $practicals->add_child($practical);

        $assessment->set_source_table('local_comp_report_ext_asmt', ['courseid' => backup::VAR_COURSEID]);

        if ($this->get_setting_value('users')) {
            $practical->set_source_table('local_comp_report_ext_prac', ['courseid' => backup::VAR_COURSEID]);
        }

        $practical->annotate_ids('user', 'studentid');
        $practical->annotate_ids('user', 'trainerid');

        return $plugin;
    }
}
