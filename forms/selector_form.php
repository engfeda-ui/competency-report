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
 * Modular selector form for competency and quiz reports.
 *
 * @package    local_competency_report
 * @copyright  2026 Hakan Ã‡iÄŸci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Selector form class for local_competency_report reports.
 *
 * @package    local_competency_report
 * @copyright  2026 Hakan Ã‡iÄŸci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_competency_report_selector_form extends moodleform {
    /**
     * Define the form elements based on custom data flags.
     */
    protected function definition() {
        global $DB;

        $mform = $this->_form;
        $courseid = $this->_customdata['courseid'];

        // Control flags for different report types.
        $showcompetency = isset($this->_customdata['showcompetency']) ? $this->_customdata['showcompetency'] : true;
        $showquiz = isset($this->_customdata['showquiz']) ? $this->_customdata['showquiz'] : false;

        $context = context_course::instance($courseid);

        // 1. Student Selection (Always visible).
        $users = [0 => get_string('selectuser', 'local_competency_report')];
        $enrolled = get_enrolled_users($context, '', 0, 'u.id, u.firstname, u.lastname, u.department');

        if (!empty($enrolled)) {
            foreach ($enrolled as $u) {
                if (is_siteadmin($u->id) || has_capability('moodle/course:update', $context, $u->id)) {
                    continue;
                }
                $name = fullname($u);
                if (!empty($u->department)) {
                    $name .= " (" . $u->department . ")";
                }
                $users[$u->id] = $name;
            }
        }

        $mform->addElement('autocomplete', 'userid', get_string('selectuser', 'local_competency_report'), $users, [
            'placeholder' => get_string('searchuserorprept', 'local_competency_report'),
            'multiple' => false,
        ]);
        $mform->setType('userid', PARAM_INT);

        // 2. Competency Selection.
        if ($showcompetency) {
            $this->add_competency_selector($courseid);
        }

        // 3. Quiz Selection.
        if ($showquiz) {
            $this->add_quiz_selector($courseid);
        }

        // Hidden course ID.
        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);

        // Action buttons.
        $this->add_action_buttons(false, get_string('filter', 'local_competency_report'));
    }

    /**
     * Helper to add competency autocomplete element.
     *
     * @param int $courseid The course ID to filter competencies.
     * @return void
     */
    protected function add_competency_selector($courseid) {
        global $DB;
        $mform = $this->_form;

        $competencies = [0 => get_string('allcompetencies', 'local_competency_report')];
        $sql = "SELECT DISTINCT c.id, c.shortname
                FROM {competency} c
                JOIN {qbank_competency_qmap} m ON m.competencyid = c.id
                WHERE m.courseid = :courseid";

        $records = $DB->get_records_sql($sql, ['courseid' => $courseid]);
        if ($records) {
            foreach ($records as $record) {
                $competencies[$record->id] = $record->shortname;
            }
        }

        $mform->addElement(
            'autocomplete',
            'competencyid',
            get_string('selectcompetency', 'local_competency_report'),
            $competencies,
            [
                'placeholder' => get_string('searchcompetency', 'local_competency_report'),
                'multiple' => false,
            ]
        );
        $mform->setType('competencyid', PARAM_INT);
    }

    /**
     * Helper to add quiz autocomplete element.
     *
     * @param int $courseid The course ID to fetch quizzes.
     * @return void
     */
    protected function add_quiz_selector($courseid) {
        global $DB;
        $mform = $this->_form;

        $quizzes = [0 => get_string('selectquiz', 'local_competency_report')];
        $records = $DB->get_records('quiz', ['course' => $courseid], 'name ASC');

        if ($records) {
            foreach ($records as $record) {
                $quizzes[$record->id] = format_string($record->name);
            }
        }

        $mform->addElement('autocomplete', 'quizid', get_string('selectquiz', 'local_competency_report'), $quizzes, [
            'placeholder' => get_string('searchquiz', 'local_competency_report'),
            'multiple' => false,
        ]);
        $mform->setType('quizid', PARAM_INT);
    }
}
