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



/**
 * Extend course navigation with competency analysis links.
 *
 * @param global_navigation $navigation The navigation object.
 * @param stdClass $course The course object.
 * @param context_course $context The course context.
 * @return void
 */
function local_competency_report_extend_navigation_course($navigation, $course, $context) {
    // 1. Teacher & Administrator Section.
    if (has_capability('mod/quiz:viewreports', $context)) {
        // Unified Course Master Report.
        if (!$navigation->find('coursemasterreport', navigation_node::TYPE_SETTING)) {
            $url = new moodle_url('/local/competency_report/course_master_report.php', ['courseid' => $course->id]);
            $navigation->add(
                get_string('coursemasterreport', 'local_competency_report'),
                $url,
                navigation_node::TYPE_SETTING,
                null,
                'coursemasterreport',
                new pix_icon('i/stats', '')
            );
        }

        // Student Performance Dashboard (consolidated class report).
        if (!$navigation->find('competency_report_teacher', navigation_node::TYPE_SETTING)) {
            $url = new moodle_url('/local/competency_report/class_report.php', ['courseid' => $course->id]);
            $navigation->add(
                get_string('studentdashboard', 'local_competency_report'),
                $url,
                navigation_node::TYPE_SETTING,
                null,
                'competency_report_teacher',
                new pix_icon('i/report', '')
            );
        }

        // Group Performance Analysis (consolidated group report).
        if (has_capability('moodle/course:update', $context)) {
            if (!$navigation->find('groupcompetency', navigation_node::TYPE_SETTING)) {
                $url = new moodle_url('/local/competency_report/group_competency.php', ['courseid' => $course->id]);
                $navigation->add(
                    get_string('groupperformance', 'local_competency_report'),
                    $url,
                    navigation_node::TYPE_SETTING,
                    null,
                    'groupcompetency',
                    new pix_icon('i/group', '')
                );
            }
        }

        // Assessment weight configuration (editing teachers / managers).
        if (has_capability('local/competency_report:manageassessments', $context)) {
            if (!$navigation->find('competency_assessment_setup', navigation_node::TYPE_SETTING)) {
                $url = new moodle_url('/local/competency_report/assessment_setup.php', ['courseid' => $course->id]);
                $navigation->add(
                    get_string('assessmentsetup', 'local_competency_report'),
                    $url,
                    navigation_node::TYPE_SETTING,
                    null,
                    'competency_assessment_setup',
                    new pix_icon('i/settings', '')
                );
            }
        }

        // Practical exam result entry (teachers / trainers).
        if (has_capability('local/competency_report:enterpractical', $context)) {
            if (!$navigation->find('competency_practical_entry', navigation_node::TYPE_SETTING)) {
                $url = new moodle_url('/local/competency_report/practical_entry.php', ['courseid' => $course->id]);
                $navigation->add(
                    get_string('practicalentry', 'local_competency_report'),
                    $url,
                    navigation_node::TYPE_SETTING,
                    null,
                    'competency_practical_entry',
                    new pix_icon('i/edit', '')
                );
            }
        }
    }

    // 2. Student Specific Menus.
    if (isloggedin() && !isguestuser() && !has_capability('mod/quiz:viewreports', $context)) {
        if (!$navigation->find('competency_report_student_parent', navigation_node::TYPE_CUSTOM)) {
            $url = new moodle_url('/local/competency_report/student_report.php', ['courseid' => $course->id]);
            $navigation->add(
                get_string('mycompetencies', 'local_competency_report'),
                $url,
                navigation_node::TYPE_CUSTOM,
                null,
                'competency_report_student_parent',
                new pix_icon('i/stats', '')
            );
        }
    }
}

/**
 * Check if a student is at risk (multiple weak competencies) and send an alert to course teachers.
 *
 * @param int   $userid    The student user ID.
 * @param int   $courseid  The course ID.
 * @param array $rates     Associative array of competency shortname => rate (0-100).
 * @return void
 */
function local_competency_report_check_and_notify($userid, $courseid, array $rates) {
    global $DB, $CFG;

    // Read alert threshold from settings (default: 40%).
    $threshold = (int)(get_config('local_competency_report', 'alert_threshold') ?: 40);
    $alertenabled = get_config('local_competency_report', 'enable_alerts');

    if (!$alertenabled) {
        return;
    }

    $weakcompetencies = array_filter($rates, function ($r) use ($threshold) {
        return $r < $threshold;
    });

    // Only alert if 2 or more competencies are weak.
    if (count($weakcompetencies) < 2) {
        return;
    }

    $student = $DB->get_record('user', ['id' => $userid], 'id, firstname, lastname, email');
    $course  = $DB->get_record('course', ['id' => $courseid], 'id, fullname');
    if (!$student || !$course) {
        return;
    }

    // Fetch all teachers enrolled in the course.
    $context = context_course::instance($courseid);
    $teachers = get_enrolled_users($context, 'mod/quiz:viewreports', 0, 'u.id, u.firstname, u.lastname, u.email');

    if (empty($teachers)) {
        return;
    }

    // Build weak competency list for the message body.
    $weaklist = '';
    foreach ($weakcompetencies as $code => $rate) {
        $weaklist .= "• {$code}: " . round($rate, 1) . "%\n";
    }

    $reporturl = (new moodle_url('/local/competency_report/student_competency_detail.php', [
        'courseid' => $courseid,
        'userid'   => $userid,
    ]))->out(false);

    foreach ($teachers as $teacher) {
        $message = new \core\message\message();
        $message->component         = 'local_competency_report';
        $message->name              = 'studentatrisk';
        $message->userfrom          = \core_user::get_noreply_user();
        $message->userto            = $teacher;
        $message->subject           = get_string('alert_subject', 'local_competency_report', fullname($student));
        $message->fullmessage       = get_string('alert_body', 'local_competency_report', (object)[
            'student'  => fullname($student),
            'course'   => $course->fullname,
            'weaklist' => $weaklist,
            'url'      => $reporturl,
        ]);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml   = '<p>' . str_replace("\n", '<br>', $message->fullmessage) . '</p>';
        $message->smallmessage      = get_string('alert_subject', 'local_competency_report', fullname($student));
        $message->notification      = 1;
        $message->contexturl        = $reporturl;
        $message->contexturlname    = get_string('studentcompetencydetail', 'local_competency_report');

        message_send($message);
    }
}
