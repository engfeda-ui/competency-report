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
 * Library functions for the local_competency_report plugin.
 *
 * @package    local_competency_report
  * @copyright  2026 Mahmoud Salem
 * @copyright  based on work by 2026 Hakan Ã‡iÄŸci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extend course navigation with competency analysis links.
 *
 * @param global_navigation $navigation The navigation object.
 * @param stdClass $course The course object.
 * @param context_course $context The course context.
 * @return void
 */
function local_competency_report_extend_navigation_course($navigation, $course, $context) {
    // 1. Teacher Reports Section.
    if (has_capability('mod/quiz:viewreports', $context)) {
        // General class report.
        if (!$navigation->find('competency_report_teacher', navigation_node::TYPE_SETTING)) {
            $url = new moodle_url('/local/competency_report/class_report.php', ['courseid' => $course->id]);
            $navigation->add(
                get_string('classreport', 'local_competency_report'),
                $url,
                navigation_node::TYPE_SETTING,
                null,
                'competency_report_teacher',
                new pix_icon('i/report', '')
            );
        }

        // Student analysis (General).
        if (!$navigation->find('competency_report_teacher_student', navigation_node::TYPE_SETTING)) {
            $url = new moodle_url('/local/competency_report/teacher_student_competency.php', ['courseid' => $course->id]);
            $navigation->add(
                get_string('studentanalysis', 'local_competency_report'),
                $url,
                navigation_node::TYPE_SETTING,
                null,
                'competency_report_teacher_student',
                new pix_icon('i/users', '')
            );
        }

        // Student exam analysis (Newly added).
        if (!$navigation->find('competency_report_teacher_student_exam', navigation_node::TYPE_SETTING)) {
            $url = new moodle_url('/local/competency_report/teacher_student_exam.php', ['courseid' => $course->id]);
            $navigation->add(
                get_string('studentexamanalysis', 'local_competency_report'),
                $url,
                navigation_node::TYPE_SETTING,
                null,
                'competency_report_teacher_student_exam',
                new pix_icon('i/search', '')
            );
        }
    }

    // 2. Group & Course Management Analysis.
    if (has_capability('moodle/course:update', $context)) {
        if (!$navigation->find('groupcompetency', navigation_node::TYPE_SETTING)) {
            $url = new moodle_url('/local/competency_report/group_competency.php', ['courseid' => $course->id]);
            $navigation->add(
                get_string('groupcompetency', 'local_competency_report'),
                $url,
                navigation_node::TYPE_SETTING,
                null,
                'groupcompetency',
                new pix_icon('i/group', '')
            );
        }

        if (!$navigation->find('groupquizcompetency', navigation_node::TYPE_SETTING)) {
            $url = new moodle_url('/local/competency_report/group_quiz_competency.php', ['courseid' => $course->id]);
            $navigation->add(
                get_string('groupquizcompetency', 'local_competency_report'),
                $url,
                navigation_node::TYPE_SETTING,
                null,
                'groupquizcompetency',
                new pix_icon('i/quiz', '')
            );
        }
    }

    // 3. Admin Only: Background Tasks.
    if (has_capability('moodle/site:config', context_system::instance())) {
        if (!$navigation->find('competency_report_admin_process', navigation_node::TYPE_SETTING)) {
            $url = new moodle_url('/local/competency_report/add_success_to_evidence.php', ['courseid' => $course->id]);
            $navigation->add(
                get_string('process_success_title', 'local_competency_report'),
                $url,
                navigation_node::TYPE_SETTING,
                null,
                'competency_report_admin_process',
                new pix_icon('i/settings', '')
            );
        }
    }

    // 4. Student Specific Menus.
    if (isloggedin() && !isguestuser()) {
        $studentnode = $navigation->find('competency_report_student_parent', navigation_node::TYPE_CUSTOM);
        if (!$studentnode) {
            $studentnode = $navigation->add(
                get_string('mycompetencies', 'local_competency_report'),
                null,
                navigation_node::TYPE_CUSTOM,
                null,
                'competency_report_student_parent',
                new pix_icon('i/stats', '')
            );
        }

        // Student report (Karnem).
        $studentnode->add(
            get_string('myreportcard', 'local_competency_report'),
            new moodle_url('/local/competency_report/student_report.php', ['courseid' => $course->id]),
            navigation_node::TYPE_CUSTOM,
            null,
            'competency_report_student'
        );

        // Exam analysis (SÄ±nav KazanÄ±m Analizim).
        $studentnode->add(
            get_string('myexamanalysis', 'local_competency_report'),
            new moodle_url('/local/competency_report/student_exam.php', ['courseid' => $course->id]),
            navigation_node::TYPE_CUSTOM,
            null,
            'competency_report_student_exam'
        );

        // Competency based exams (Competency Report BazlÄ± SÄ±navlarÄ±m).
        $studentnode->add(
            get_string('mycompetencyexams', 'local_competency_report'),
            new moodle_url('/local/competency_report/student_competency_exams.php', ['courseid' => $course->id]),
            navigation_node::TYPE_CUSTOM,
            null,
            'competency_report_student_competency'
        );

        // Competency state (Competency Report Durumu).
        $studentnode->add(
            get_string('mycompetencystate', 'local_competency_report'),
            new moodle_url('/local/competency_report/student_class.php', ['courseid' => $course->id]),
            navigation_node::TYPE_CUSTOM,
            null,
            'competency_report_student_state'
        );

        // Timeline.
        $studentnode->add(
            get_string('timeline', 'local_competency_report'),
            new moodle_url('/local/competency_report/timeline.php', ['courseid' => $course->id]),
            navigation_node::TYPE_CUSTOM,
            null,
            'competency_report_timeline',
            new pix_icon('i/calendar', '')
        );
    }
}
