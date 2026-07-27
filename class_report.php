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
 * Class Report for Competency Matching.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @copyright  based on work by 2026 Hakan Ã‡iÄŸci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/forms/selector_form.php');

$courseid = required_param('courseid', PARAM_INT);
$context  = context_course::instance($courseid);

require_login($courseid);
require_capability('moodle/course:view', $context);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

// Page settings.
$PAGE->set_url('/local/comp_report_ext/class_report.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_pagelayout('course');
$PAGE->set_title(get_string('report_title', 'local_comp_report_ext', $course->fullname));
$PAGE->set_heading($course->fullname . " - " . get_string('report_heading', 'local_comp_report_ext'));

// 1. Parameter Management and Form.
$userid     = optional_param('userid', 0, PARAM_INT);
$competency = optional_param('competencyid', 0, PARAM_INT);

$mform = new local_comp_report_ext_selector_form(null, ['courseid' => $courseid]);
if ($data = $mform->get_data()) {
    $userid     = $data->userid;
    $competency = $data->competencyid;
}
$mform->set_data(['userid' => $userid, 'competencyid' => $competency]);

// Check capability if requesting another student's report.
if ($userid > 0 && $userid != $USER->id) {
    if (!has_capability('local/comp_report_ext:viewreports', $context)
            && !has_capability('mod/quiz:viewreports', $context)
            && !has_capability('moodle/competency:usercompetencyview', $context)) {
        require_capability('local/comp_report_ext:viewreports', $context);
    }
}

// 2. Data Preparation.
$renderdata = new stdClass();
$renderdata->courseid = $courseid;
$renderdata->userid = $userid;
$renderdata->rows = [];

// Course General SQL.
$coursesql = "SELECT c.id, c.shortname,
                     SUM(qa.maxfraction) AS attempts,
                     SUM(qas.fraction) AS correct
              FROM {quiz_attempts} quiza
              JOIN {question_usages} qu ON qu.id = quiza.uniqueid
              JOIN {question_attempts} qa ON qa.questionusageid = qu.id
              JOIN {quiz} quiz ON quiz.id = quiza.quiz
              JOIN {qbank_comp_ext_qmap} m ON m.questionid = qa.questionid
              JOIN {competency} c ON c.id = m.competencyid
              JOIN (SELECT MAX(fraction) AS fraction, questionattemptid
                      FROM {question_attempt_steps}
                  GROUP BY questionattemptid) qas ON qas.questionattemptid = qa.id
              WHERE quiz.course = :courseid AND quiza.state = 'finished'";

if ($competency) {
    $coursesql .= " AND c.id = :competencyid";
}
$coursesql .= " GROUP BY c.id, c.shortname";

$params = ['courseid' => $courseid, 'competencyid' => $competency];
$coursedata = $DB->get_records_sql($coursesql, $params);

if (!empty($coursedata)) {
    $classdata = [];
    $studentdata = [];

    if ($userid) {
        // 1. Check if user belongs to group(s) in this course.
        $usergroups = $DB->get_fieldset_sql("
            SELECT gm.groupid
            FROM {groups_members} gm
            JOIN {groups} g ON g.id = gm.groupid
            WHERE g.courseid = :courseid AND gm.userid = :userid
        ", ['courseid' => $courseid, 'userid' => $userid]);

        if (!empty($usergroups)) {
            [$groupinsql, $groupparams] = $DB->get_in_or_equal($usergroups, SQL_PARAMS_NAMED, 'grp');
            $classsql = str_replace(
                "FROM {quiz_attempts} quiza",
                "FROM {quiz_attempts} quiza JOIN {groups_members} gm ON gm.userid = quiza.userid",
                $coursesql
            );
            $classsql = str_replace(
                "WHERE quiz.course = :courseid",
                "WHERE quiz.course = :courseid AND gm.groupid " . $groupinsql,
                $classsql
            );
            $classparams = array_merge(['courseid' => $courseid, 'competencyid' => $competency], $groupparams);
            $classdata = $DB->get_records_sql($classsql, $classparams);
        } else {
            // 2. Fallback: check user department if set.
            $userdept = $DB->get_field('user', 'department', ['id' => $userid]);
            if (!empty($userdept)) {
                $classsql = str_replace(
                    "FROM {quiz_attempts} quiza",
                    "FROM {quiz_attempts} quiza JOIN {user} u ON quiza.userid = u.id",
                    $coursesql
                );
                $classsql = str_replace(
                    "WHERE quiz.course",
                    "WHERE u.department = :dept AND quiz.course",
                    $classsql
                );
                $classdata = $DB->get_records_sql($classsql, [
                    'courseid' => $courseid,
                    'dept' => $userdept,
                    'competencyid' => $competency,
                ]);
            }
        }

        // 3. If classdata is still empty, fallback to coursedata so Class Average is not %0.
        if (empty($classdata)) {
            $classdata = $coursedata;
        }

        // Fetch specific student data by filtering by userid.
        $studentsql = str_replace(
            "WHERE quiz.course",
            "WHERE quiza.userid = :userid AND quiz.course",
            $coursesql
        );
        $studentdata = $DB->get_records_sql($studentsql, [
            'courseid' => $courseid,
            'userid' => $userid,
            'competencyid' => $competency,
        ]);
    }

    // Chart lists.
    $labels = [];
    $courserates = [];
    $classrates = [];
    $studentrates = [];

    foreach ($coursedata as $cid => $c) {
        $courserate = $c->attempts ? number_format(($c->correct / $c->attempts) * 100, 1) : 0;
        $classrate  = (isset($classdata[$cid]) && $classdata[$cid]->attempts) ?
            number_format(($classdata[$cid]->correct / $classdata[$cid]->attempts) * 100, 1) : 0;
        $studrate   = (isset($studentdata[$cid]) && $studentdata[$cid]->attempts) ?
            number_format(($studentdata[$cid]->correct / $studentdata[$cid]->attempts) * 100, 1) : 0;

        $renderdata->rows[] = [
            'shortname' => $c->shortname,
            'courserate' => $courserate,
            'classrate' => $classrate,
            'studentrate' => $studrate,
        ];

        $labels[] = $c->shortname;
        $courserates[] = $courserate;
        $classrates[] = $classrate;
        $studentrates[] = $studrate;
    }

    $renderdata->chart_params = [
        'labels'     => $labels,
        'courseData' => $courserates,
        'classData'  => $classrates,
        'myData'     => $studentrates,
        'labelNames' => [
            'course' => get_string('courseavg', 'local_comp_report_ext'),
            'class'  => get_string('classavg', 'local_comp_report_ext'),
            'my'     => get_string('studentavg', 'local_comp_report_ext'),
        ],
    ];
}

// 3. Output.
echo $OUTPUT->header();

$page = new \local_comp_report_ext\output\class_report_page($renderdata, $mform);
echo $OUTPUT->render($page);

echo $OUTPUT->footer();
