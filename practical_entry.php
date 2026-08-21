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
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);

require_login($courseid);
$context = context_course::instance($courseid);
$canviewext = has_capability('local/comp_report_ext:enterpractical', $context);
$canviewold = has_capability('local/competency_report:enterpractical', $context);
if (!$canviewext && !$canviewold) {
    require_capability('local/comp_report_ext:enterpractical', $context);
}

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

$PAGE->set_url('/local/comp_report_ext/practical_entry.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_pagelayout('course');
$PAGE->set_title(get_string('practicalentry', 'local_comp_report_ext'));
$PAGE->set_heading($course->fullname . ' — ' . get_string('practicalentry', 'local_comp_report_ext'));

// Get selected assessment, competency, and group from request.
$assessmentid = optional_param('assessmentid', 0, PARAM_INT);
$competencyid = optional_param('competencyid', 0, PARAM_INT);
$groupid      = optional_param('groupid', 0, PARAM_INT);

// Handle POST — save results.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    $studentids  = optional_param_array('studentid', [], PARAM_INT);
    $percents    = optional_param_array('competency_percent', [], PARAM_FLOAT);
    $postassid   = required_param('assessmentid', PARAM_INT);
    $postcompid  = required_param('competencyid', PARAM_INT);
    $postgroupid = optional_param('groupid', 0, PARAM_INT);
    // Preload existing practical records to eliminate N+1 database queries.
    $allexisting = $DB->get_records('local_comp_report_ext_prac', [
        'assessmentid' => $postassid,
        'courseid'     => $courseid,
        'competencyid' => $postcompid,
    ]);
    $existingmap = [];
    foreach ($allexisting as $rec) {
        $existingmap[$rec->studentid] = $rec;
    }

    foreach ($studentids as $idx => $sid) {
        $pct = isset($percents[$idx]) && $percents[$idx] !== '' ? (float)$percents[$idx] : null;
        if ($pct === null || $pct < 0 || $pct > 100) {
            continue;
        }

        $existing = $existingmap[$sid] ?? null;

        if ($existing) {
            $existing->competency_percent = $pct;
            $existing->trainerid          = $USER->id;
            $existing->timemodified       = $now;
            $DB->update_record('local_comp_report_ext_prac', $existing);
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
            $DB->insert_record('local_comp_report_ext_prac', $record);
        }
    }

    // Sync with Moodle Assignment Gradebook.
    $asmt = $DB->get_record('local_comp_report_ext_asmt', ['id' => $postassid]);
    if ($asmt && $asmt->type === 'practical' && !empty($asmt->assignid)) {
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $cm = get_coursemodule_from_instance('assign', $asmt->assignid, $courseid, false);
        if ($cm) {
            $context = context_module::instance($cm->id);
            $assign = new assign($context, $cm, $course);
            $maxgrade = (float)$assign->get_instance()->grade;
            if (!empty($studentids)) {
                [$insql, $inparams] = $DB->get_in_or_equal($studentids, SQL_PARAMS_NAMED, 'sid');
                $params = array_merge(['assid' => $postassid], $inparams);

                $allrows = $DB->get_records_sql("
                    SELECT p.id, p.studentid, c.shortname, p.competency_percent
                      FROM {local_comp_report_ext_prac} p
                      JOIN {competency} c ON c.id = p.competencyid
                     WHERE p.assessmentid = :assid AND p.studentid {$insql}
                  ORDER BY c.shortname ASC
                ", $params);

                $studentrows = [];
                foreach ($allrows as $row) {
                    $studentrows[$row->studentid][] = $row;
                }

                $summarystr = get_string('summaryreport', 'local_comp_report_ext');

                foreach ($studentids as $sid) {
                    if (!empty($studentrows[$sid])) {
                        $rows = $studentrows[$sid];
                        $sum = 0.0;
                        $breakdown = [];
                        foreach ($rows as $row) {
                            $sum += (float)$row->competency_percent;
                            $breakdown[] = "• " . s($row->shortname) . ": " . round($row->competency_percent, 1) . "%";
                        }
                        $averagepercent = $sum / count($rows);
                        $finalgrade = ($maxgrade > 0) ? (($averagepercent / 100.0) * $maxgrade) : $averagepercent;
                        $feedbacktext = "<strong>" . $summarystr . ":</strong><br>" . implode("<br>", $breakdown);

                        $gradedata = new stdClass();
                        $gradedata->grade = $finalgrade;
                        $gradedata->feedbacktext = $feedbacktext;
                        $gradedata->feedbackformat = FORMAT_HTML;

                        try {
                            $assign->save_grade($sid, $gradedata);
                        } catch (\Exception $e) {
                            // Student may not be enrolled in the assignment — skip silently.
                            debugging('practical_entry: save_grade failed for studentid=' .
                                $sid . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
                        }
                    }
                }
            }
        }
    }

    redirect(
        new moodle_url('/local/comp_report_ext/practical_entry.php', [
            'courseid'     => $courseid,
            'assessmentid' => $postassid,
            'competencyid' => $postcompid,
            'groupid'      => $postgroupid,
        ]),
        get_string('practicalsaved', 'local_comp_report_ext'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// -----------------------------------------------------------------------
// Data for the view.
// -----------------------------------------------------------------------
// All practical assessments configured for this course.
$practicalassessments = array_values($DB->get_records(
    'local_comp_report_ext_asmt',
    ['courseid' => $courseid, 'type' => 'practical'],
    'name ASC'
));

// If assessment is selected, load competencies linked to it (via Moodle Assignment settings).
$competencies = [];
if ($assessmentid) {
    $asmt = $DB->get_record('local_comp_report_ext_asmt', ['id' => $assessmentid]);
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
          FROM {qbank_comp_ext_qmap} m
          JOIN {competency} c ON c.id = m.competencyid
         WHERE m.courseid = :courseid
         ORDER BY c.shortname ASC
    ", ['courseid' => $courseid]));
}

// Fetch all groups for this course to construct filter options.
$groups = groups_get_all_groups($courseid);
$groupoptions = [
    [
        'id' => 0,
        'name' => get_string('allgroups', 'local_comp_report_ext'),
        'selected' => ($groupid == 0),
    ],
];
if ($groups) {
    foreach ($groups as $g) {
        $groupoptions[] = [
            'id' => $g->id,
            'name' => format_string($g->name),
            'selected' => ($g->id == $groupid),
        ];
    }
}

// Load group memberships for students in this course.
$studentgroups = $DB->get_records_sql(
    "SELECT gm.id, gm.userid, g.name AS groupname, g.id AS groupid
       FROM {groups} g
       JOIN {groups_members} gm ON gm.groupid = g.id
      WHERE g.courseid = :courseid",
    ['courseid' => $courseid]
);
$usergroupmap = [];
foreach ($studentgroups as $sg) {
    $usergroupmap[$sg->userid][] = [
        'id'   => $sg->groupid,
        'name' => format_string($sg->groupname),
    ];
}

// Students enrolled in the course — only users with the 'student' role.
// Optionally filter by selected group.
if ($groupid > 0) {
    $students = array_values($DB->get_records_sql(
        "SELECT DISTINCT u.id, u.firstname, u.lastname, u.idnumber
           FROM {role_assignments} ra
           JOIN {role} r ON r.id = ra.roleid
           JOIN {context} ctx ON ctx.id = ra.contextid
           JOIN {user} u ON u.id = ra.userid
           JOIN {groups_members} gm ON gm.userid = u.id
          WHERE ctx.instanceid = :courseid
            AND ctx.contextlevel = 50
            AND r.shortname = 'student'
            AND u.deleted = 0
            AND gm.groupid = :groupid
         ORDER BY u.lastname ASC, u.firstname ASC",
        ['courseid' => $courseid, 'groupid' => $groupid]
    ));
} else {
    $students = array_values($DB->get_records_sql(
        "SELECT DISTINCT u.id, u.firstname, u.lastname, u.idnumber
           FROM {role_assignments} ra
           JOIN {role} r ON r.id = ra.roleid
           JOIN {context} ctx ON ctx.id = ra.contextid
           JOIN {user} u ON u.id = ra.userid
          WHERE ctx.instanceid = :courseid
            AND ctx.contextlevel = 50
            AND r.shortname = 'student'
            AND u.deleted = 0
         ORDER BY u.lastname ASC, u.firstname ASC",
        ['courseid' => $courseid]
    ));
}

// If assessment and competency are selected, load existing results.
$existingresults = [];
if ($assessmentid && $competencyid) {
    $rows = $DB->get_records('local_comp_report_ext_prac', [
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
$renderdata->groupid            = $groupid;
$renderdata->groups             = $groupoptions;
$renderdata->usergroupmap      = $usergroupmap;
$renderdata->assessments       = $practicalassessments;
$renderdata->competencies      = $competencies;
$renderdata->students          = $students;
$renderdata->existingresults   = $existingresults;
$renderdata->sesskey           = sesskey();

$page = new \local_comp_report_ext\output\practical_entry_page($renderdata);
echo $OUTPUT->render($page);

echo $OUTPUT->footer();
