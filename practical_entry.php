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
 * Practical exam result entry page.
 *
 * Allows trainers to manually enter the competency achievement percentage
 * for each student in a practical (workshop) assessment.
 *
 * @package    local_competency_report
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);

require_login($courseid);
$context = context_course::instance($courseid);
require_capability('local/competency_report:enterpractical', $context);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

$PAGE->set_url('/local/competency_report/practical_entry.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_pagelayout('course');
$PAGE->set_title(get_string('practicalentry', 'local_competency_report'));
$PAGE->set_heading($course->fullname . ' — ' . get_string('practicalentry', 'local_competency_report'));

// Get selected assessment and competency from request.
$assessmentid = optional_param('assessmentid', 0, PARAM_INT);
$competencyid = optional_param('competencyid', 0, PARAM_INT);

// Handle POST — save results.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    $studentids = optional_param_array('studentid', [], PARAM_INT);
    $percents   = optional_param_array('competency_percent', [], PARAM_FLOAT);
    $postassid  = required_param('assessmentid', PARAM_INT);
    $postcompid = required_param('competencyid', PARAM_INT);
    $now        = time();

    foreach ($studentids as $idx => $sid) {
        $pct = isset($percents[$idx]) ? (float)$percents[$idx] : null;
        if ($pct === null || $pct < 0 || $pct > 100) {
            continue;
        }

        $existing = $DB->get_record('local_competency_report_prac', [
            'assessmentid' => $postassid,
            'courseid'     => $courseid,
            'competencyid' => $postcompid,
            'studentid'    => $sid,
        ]);

        if ($existing) {
            $existing->competency_percent = $pct;
            $existing->trainerid          = $USER->id;
            $existing->timemodified       = $now;
            $DB->update_record('local_competency_report_prac', $existing);
        } else {
            $record                   = new stdClass();
            $record->assessmentid     = $postassid;
            $record->courseid         = $courseid;
            $record->competencyid     = $postcompid;
            $record->studentid        = $sid;
            $record->trainerid        = $USER->id;
            $record->competency_percent = $pct;
            $record->timecreated      = $now;
            $record->timemodified     = $now;
            $DB->insert_record('local_competency_report_prac', $record);
        }
    }

    // Sync with Moodle Assignment Gradebook.
    $asmt = $DB->get_record('local_competency_report_asmt', ['id' => $postassid]);
    if ($asmt && $asmt->type === 'practical' && !empty($asmt->assignid)) {
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $cm = get_coursemodule_from_instance('assign', $asmt->assignid, $courseid, false);
        if ($cm) {
            $context = context_module::instance($cm->id);
            $assign = new assign($context, $cm, $course);
            $maxgrade = (float)$assign->get_instance()->grade;

            foreach ($studentids as $sid) {
                // Fetch all competency grades for this student and this practical assessment.
                $competencygrades = $DB->get_records('local_competency_report_prac', [
                    'assessmentid' => $postassid,
                    'studentid'    => $sid,
                ], '', 'competency_percent');

                if (!empty($competencygrades)) {
                    $sum = 0.0;
                    foreach ($competencygrades as $cg) {
                        $sum += (float)$cg->competency_percent;
                    }
                    $averagepercent = $sum / count($competencygrades);

                    // Scale to Assignment's maximum grade if it is a positive numeric grade.
                    if ($maxgrade > 0) {
                        $finalgrade = ($averagepercent / 100.0) * $maxgrade;
                    } else {
                        $finalgrade = $averagepercent;
                    }

                    // Prepare detailed competency breakdown for feedback comments.
                    $breakdown = [];
                    $allrows = $DB->get_records_sql("
                        SELECT c.shortname, p.competency_percent
                          FROM {local_competency_report_prac} p
                          JOIN {competency} c ON c.id = p.competencyid
                         WHERE p.assessmentid = :assessmentid AND p.studentid = :studentid
                      ORDER BY c.shortname ASC
                    ", ['assessmentid' => $postassid, 'studentid' => $sid]);

                    foreach ($allrows as $row) {
                        $breakdown[] = "• " . s($row->shortname) . ": " . round($row->competency_percent, 1) . "%";
                    }
                    $summarystr = get_string('summaryreport', 'local_competency_report');
                    $feedbacktext = "<strong>" . $summarystr . ":</strong><br>" .
                        implode("<br>", $breakdown);

                    // Push grade and feedback to Moodle Assignment.
                    $gradedata = new stdClass();
                    $gradedata->grade = $finalgrade;
                    $gradedata->feedbacktext = $feedbacktext;
                    $gradedata->feedbackformat = FORMAT_HTML;

                    $assign->save_grade($sid, $gradedata);
                }
            }
        }
    }

    redirect(
        new moodle_url('/local/competency_report/practical_entry.php', [
            'courseid'     => $courseid,
            'assessmentid' => $postassid,
            'competencyid' => $postcompid,
        ]),
        get_string('practicalsaved', 'local_competency_report'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// -----------------------------------------------------------------------
// Data for the view.
// -----------------------------------------------------------------------
// All practical assessments configured for this course.
$practicalassessments = array_values($DB->get_records(
    'local_competency_report_asmt',
    ['courseid' => $courseid, 'type' => 'practical'],
    'name ASC'
));

// If assessment is selected, load competencies linked to it (via Moodle Assignment settings).
$competencies = [];
if ($assessmentid) {
    $asmt = $DB->get_record('local_competency_report_asmt', ['id' => $assessmentid]);
    if ($asmt && $asmt->type === 'practical' && $asmt->assignid) {
        $cm = get_coursemodule_from_instance('assign', $asmt->assignid, $courseid, false);
        if ($cm) {
            $competencies = array_values($DB->get_records_sql("
                SELECT DISTINCT c.id, c.shortname
                  FROM {competency_modulecomp} mc
                  JOIN {competency} c ON c.id = mc.competencyid
                 WHERE mc.cmid = :cmid
                 ORDER BY c.shortname ASC
            ", ['cmid' => $cm->id]));
        }
    }
}

// Fallback 1: Load all competencies linked to the course.
if (empty($competencies)) {
    $competencies = array_values($DB->get_records_sql("
        SELECT DISTINCT c.id, c.shortname
          FROM {competency_coursecomp} cc
          JOIN {competency} c ON c.id = cc.competencyid
         WHERE cc.courseid = :courseid
         ORDER BY c.shortname ASC
    ", ['courseid' => $courseid]));
}

// Fallback 2: Load competencies that have question mappings in this course.
if (empty($competencies)) {
    $competencies = array_values($DB->get_records_sql("
        SELECT DISTINCT c.id, c.shortname
          FROM {qbank_competency_qmap} m
          JOIN {competency} c ON c.id = m.competencyid
         WHERE m.courseid = :courseid
         ORDER BY c.shortname ASC
    ", ['courseid' => $courseid]));
}

// Students enrolled in the course.
$students = array_values(get_enrolled_users(
    $context,
    'mod/quiz:attempt',
    0,
    'u.id, u.firstname, u.lastname, u.idnumber',
    'u.lastname ASC'
));

// If assessment and competency are selected, load existing results.
$existingresults = [];
if ($assessmentid && $competencyid) {
    $rows = $DB->get_records('local_competency_report_prac', [
        'assessmentid' => $assessmentid,
        'courseid'     => $courseid,
        'competencyid' => $competencyid,
    ]);
    foreach ($rows as $r) {
        $existingresults[$r->studentid] = (float)$r->competency_percent;
    }
}

// Output.
echo $OUTPUT->header();

$renderdata                    = new stdClass();
$renderdata->courseid          = $courseid;
$renderdata->assessmentid      = $assessmentid;
$renderdata->competencyid      = $competencyid;
$renderdata->assessments       = $practicalassessments;
$renderdata->competencies      = $competencies;
$renderdata->students          = $students;
$renderdata->existingresults   = $existingresults;
$renderdata->sesskey           = sesskey();

$page = new \local_competency_report\output\practical_entry_page($renderdata);
echo $OUTPUT->render($page);

echo $OUTPUT->footer();
