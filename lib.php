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
 * Library functions for the local_yetkinlik plugin.
 *
 * @package    local_yetkinlik
 * @copyright  2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Extend course navigation with competency analysis links.
 *
 * @param global_navigation $navigation The navigation object.
 * @param stdClass $course The course object.
 * @param context_course $context The course context.
 * @return void
 */
function local_yetkinlik_extend_navigation_course($navigation, $course, $context) {

    // 1. Teacher Reports Section.
    if (has_capability('mod/quiz:viewreports', $context)) {
        // General class report.
        if (!$navigation->find('yetkinlik_teacher', navigation_node::TYPE_SETTING)) {
            $url = new moodle_url('/local/yetkinlik/class_report.php', ['courseid' => $course->id]);
            $navigation->add(
                get_string('classreport', 'local_yetkinlik'),
                $url,
                navigation_node::TYPE_SETTING,
                null,
                'yetkinlik_teacher',
                new pix_icon('i/report', '')
            );
        }

        // Student analysis (General).
        if (!$navigation->find('yetkinlik_teacher_student', navigation_node::TYPE_SETTING)) {
            $url = new moodle_url('/local/yetkinlik/teacher_student_competency.php', ['courseid' => $course->id]);
            $navigation->add(
                get_string('studentanalysis', 'local_yetkinlik'),
                $url,
                navigation_node::TYPE_SETTING,
                null,
                'yetkinlik_teacher_student',
                new pix_icon('i/users', '')
            );
        }

        // Student exam analysis (Newly added).
        if (!$navigation->find('yetkinlik_teacher_student_exam', navigation_node::TYPE_SETTING)) {
            $url = new moodle_url('/local/yetkinlik/teacher_student_exam.php', ['courseid' => $course->id]);
            $navigation->add(
                get_string('studentexamanalysis', 'local_yetkinlik'),
                $url,
                navigation_node::TYPE_SETTING,
                null,
                'yetkinlik_teacher_student_exam',
                new pix_icon('i/search', '')
            );
        }
    }

    // 2. Group & Course Management Analysis.
    if (has_capability('moodle/course:update', $context)) {
        if (!$navigation->find('groupcompetency', navigation_node::TYPE_SETTING)) {
            $url = new moodle_url('/local/yetkinlik/group_competency.php', ['courseid' => $course->id]);
            $navigation->add(
                get_string('groupcompetency', 'local_yetkinlik'),
                $url,
                navigation_node::TYPE_SETTING,
                null,
                'groupcompetency',
                new pix_icon('i/group', '')
            );
        }

        if (!$navigation->find('groupquizcompetency', navigation_node::TYPE_SETTING)) {
            $url = new moodle_url('/local/yetkinlik/group_quiz_competency.php', ['courseid' => $course->id]);
            $navigation->add(
                get_string('groupquizcompetency', 'local_yetkinlik'),
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
        if (!$navigation->find('yetkinlik_admin_process', navigation_node::TYPE_SETTING)) {
            $url = new moodle_url('/local/yetkinlik/add_success_to_evidence.php', ['courseid' => $course->id]);
            $navigation->add(
                get_string('process_success_title', 'local_yetkinlik'),
                $url,
                navigation_node::TYPE_SETTING,
                null,
                'yetkinlik_admin_process',
                new pix_icon('i/settings', '')
            );
        }
    }

    // 4. Student Specific Menus.
    if (isloggedin() && !isguestuser()) {
        $studentnode = $navigation->find('yetkinlik_student_parent', navigation_node::TYPE_CUSTOM);
        if (!$studentnode) {
            $studentnode = $navigation->add(
                get_string('mycompetencies', 'local_yetkinlik'),
                null,
                navigation_node::TYPE_CUSTOM,
                null,
                'yetkinlik_student_parent',
                new pix_icon('i/stats', '')
            );
        }

        // Student report (Karnem).
        $studentnode->add(
            get_string('myreportcard', 'local_yetkinlik'),
            new moodle_url('/local/yetkinlik/student_report.php', ['courseid' => $course->id]),
            navigation_node::TYPE_CUSTOM,
            null,
            'yetkinlik_student'
        );

        // Exam analysis (Sınav Kazanım Analizim).
        $studentnode->add(
            get_string('myexamanalysis', 'local_yetkinlik'),
            new moodle_url('/local/yetkinlik/student_exam.php', ['courseid' => $course->id]),
            navigation_node::TYPE_CUSTOM,
            null,
            'yetkinlik_student_exam'
        );

        // Competency based exams (Yetkinlik Bazlı Sınavlarım).
        $studentnode->add(
            get_string('mycompetencyexams', 'local_yetkinlik'),
            new moodle_url('/local/yetkinlik/student_competency_exams.php', ['courseid' => $course->id]),
            navigation_node::TYPE_CUSTOM,
            null,
            'yetkinlik_student_competency'
        );

        // Competency state (Yetkinlik Durumu).
        $studentnode->add(
            get_string('mycompetencystate', 'local_yetkinlik'),
            new moodle_url('/local/yetkinlik/student_class.php', ['courseid' => $course->id]),
            navigation_node::TYPE_CUSTOM,
            null,
            'yetkinlik_student_state'
        );

        // Timeline.
        $studentnode->add(
            get_string('timeline', 'local_yetkinlik'),
            new moodle_url('/local/yetkinlik/timeline.php', ['courseid' => $course->id]),
            navigation_node::TYPE_CUSTOM,
            null,
            'yetkinlik_timeline',
            new pix_icon('i/calendar', '')
        );
    }
}
