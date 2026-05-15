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
 * @package    local_yetkinlik
 * @copyright  2026 Hakan Çiğci {@link https://hakancigci.com.tr}
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
$PAGE->set_url('/local/yetkinlik/class_report.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_pagelayout('course');
$PAGE->set_title(get_string('report_title', 'local_yetkinlik'));
$PAGE->set_heading($course->fullname . " - " . get_string('report_heading', 'local_yetkinlik'));

// 1. Parameter Management and Form.
$userid     = optional_param('userid', 0, PARAM_INT);
$competency = optional_param('competencyid', 0, PARAM_INT);

$mform = new local_yetkinlik_selector_form(null, ['courseid' => $courseid]);
if ($data = $mform->get_data()) {
    $userid     = $data->userid;
    $competency = $data->competencyid;
}
$mform->set_data(['userid' => $userid, 'competencyid' => $competency]);

// 2. Data Preparation.
$renderdata = new stdClass();
$renderdata->courseid = $courseid;
$renderdata->rows = [];

// Course General SQL.
$coursesql = "SELECT c.id, c.shortname,
                     SUM(qa.maxfraction) AS attempts,
                     SUM(qas.fraction) AS correct
              FROM {quiz_attempts} quiza
              JOIN {question_usages} qu ON qu.id = quiza.uniqueid
              JOIN {question_attempts} qa ON qa.questionusageid = qu.id
              JOIN {quiz} quiz ON quiz.id = quiza.quiz
              JOIN {qbank_yetkinlik_qmap} m ON m.questionid = qa.questionid
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
        $userdept = $DB->get_field('user', 'department', ['id' => $userid]);
        if (!empty($userdept)) {
            // Fetch class/department data by joining with user table and filtering by department.
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
            'course' => get_string('courseavg', 'local_yetkinlik'),
            'class'  => get_string('classavg', 'local_yetkinlik'),
            'my'     => get_string('studentavg', 'local_yetkinlik'),
        ],
    ];
}

// 3. Output.
echo $OUTPUT->header();

$page = new \local_yetkinlik\output\class_report_page($renderdata, $mform);
echo $OUTPUT->render($page);

echo $OUTPUT->footer();
