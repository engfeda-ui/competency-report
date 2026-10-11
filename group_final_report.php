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
 * Course Final Comprehensive Evaluation Report Page.
 *
 * Displays side-by-side:
 * 1. Final Exam Score (value) + its individual competencies (percentage %).
 * 2. Oral/Practical Exam Score (value) + its individual competencies (percentage %).
 * 3. Weighted total score & weighted competencies based on configured assessment weights.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$courseid    = required_param('courseid', PARAM_INT);
$groupid     = optional_param('groupid', 0, PARAM_INT);
$finalasmtid = optional_param('finalasmtid', 0, PARAM_INT);
$oralasmtid  = optional_param('oralasmtid', 0, PARAM_INT);

require_login($courseid);
$context = context_course::instance($courseid);
$canviewext = has_capability('local/comp_report_ext:viewreports', $context);
$canviewold = has_capability('local/competency_report:viewreports', $context);
if (!$canviewext && !$canviewold) {
    require_capability('local/comp_report_ext:viewreports', $context);
}

global $DB, $PAGE, $OUTPUT;

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

$urlparams = ['courseid' => $courseid];
if ($groupid > 0) {
    $urlparams['groupid'] = $groupid;
}
if ($finalasmtid > 0) {
    $urlparams['finalasmtid'] = $finalasmtid;
}
if ($oralasmtid > 0) {
    $urlparams['oralasmtid'] = $oralasmtid;
}

$PAGE->set_url('/local/comp_report_ext/group_final_report.php', $urlparams);
$PAGE->set_context($context);
$PAGE->set_pagelayout('course');
$PAGE->set_title(get_string('tab_final_report', 'local_comp_report_ext'));
$PAGE->set_heading(format_string($course->fullname) . ' — ' . get_string('tab_final_report', 'local_comp_report_ext'));

// 1. Fetch Configured Assessments for this course.
$allasmts = $DB->get_records('local_comp_report_ext_asmt', ['courseid' => $courseid], 'weight DESC, id ASC');
$hasconfiguredasmts = !empty($allasmts);

// 2. Autodetect Final and Oral/Practical assessments if not set.
if ($hasconfiguredasmts) {
    if ($finalasmtid <= 0 || !isset($allasmts[$finalasmtid])) {
        // Find best candidate for Final Exam (type quiz or name containing final/نهائي, or highest weight).
        foreach ($allasmts as $aid => $a) {
            $namelower = core_text::strtolower($a->name);
            if (strpos($namelower, 'final') !== false || strpos($namelower, 'نهائي') !== false) {
                $finalasmtid = (int)$aid;
                break;
            }
        }
        if ($finalasmtid <= 0) {
            foreach ($allasmts as $aid => $a) {
                if ($a->type === 'quiz') {
                    $finalasmtid = (int)$aid;
                    break;
                }
            }
        }
        if ($finalasmtid <= 0) {
            $firstasmt = reset($allasmts);
            $finalasmtid = (int)$firstasmt->id;
        }
    }

    if ($oralasmtid <= 0 || !isset($allasmts[$oralasmtid]) || $oralasmtid === $finalasmtid) {
        // Find best candidate for Oral/Practical exam.
        foreach ($allasmts as $aid => $a) {
            if ((int)$aid === $finalasmtid) {
                continue;
            }
            $namelower = core_text::strtolower($a->name);
            if (in_array($a->type, ['oral', 'practical', 'assign'])
                || strpos($namelower, 'oral') !== false
                || strpos($namelower, 'شفوي') !== false
                || strpos($namelower, 'عملي') !== false
                || strpos($namelower, 'practical') !== false) {
                $oralasmtid = (int)$aid;
                break;
            }
        }
        if ($oralasmtid <= 0 || $oralasmtid === $finalasmtid) {
            foreach ($allasmts as $aid => $a) {
                if ((int)$aid !== $finalasmtid) {
                    $oralasmtid = (int)$aid;
                    break;
                }
            }
        }
    }
}

// 3. Build Filter Options.
$groups = groups_get_all_groups($courseid);
$groupoptions = [
    [
        'id'       => 0,
        'name'     => get_string('allgroups', 'local_comp_report_ext'),
        'selected' => ($groupid == 0),
    ],
];
if ($groups) {
    foreach ($groups as $g) {
        $groupoptions[] = [
            'id'       => $g->id,
            'name'     => format_string($g->name),
            'selected' => ($g->id == $groupid),
        ];
    }
}

$finaloptions = [];
$oraloptions  = [];
if ($hasconfiguredasmts) {
    foreach ($allasmts as $aid => $a) {
        $label = format_string($a->name) . ' (' . (float)$a->weight . '%)';
        $finaloptions[] = [
            'id'       => (int)$aid,
            'name'     => $label,
            'selected' => ((int)$aid === $finalasmtid),
        ];
        $oraloptions[] = [
            'id'       => (int)$aid,
            'name'     => $label,
            'selected' => ((int)$aid === $oralasmtid),
        ];
    }
}

$finalasmt = ($finalasmtid > 0 && isset($allasmts[$finalasmtid])) ? $allasmts[$finalasmtid] : null;
$oralasmt  = ($oralasmtid > 0 && isset($allasmts[$oralasmtid])) ? $allasmts[$oralasmtid] : null;

// 4. Fetch Enrolled Students.
$studentrole = $DB->get_record('role', ['shortname' => 'student'], 'id');
if (!$studentrole) {
    $studentrole = $DB->get_record('role', ['archetype' => 'student'], 'id');
}
$roleid = $studentrole ? (int)$studentrole->id : 5;

if ($groupid > 0) {
    $groupobj = $DB->get_record('groups', ['id' => $groupid, 'courseid' => $courseid]);
    $gname = $groupobj ? format_string($groupobj->name) : '';
    $students = (array)$DB->get_records_sql(
        "SELECT u.id, u.firstname, u.lastname, u.username, u.idnumber, u.email,
                :gname AS groupname, :gid AS student_groupid
           FROM {groups_members} gm
           JOIN {user} u ON u.id = gm.userid
           JOIN {role_assignments} ra ON ra.userid = u.id
           JOIN {context} ctx ON ctx.id = ra.contextid
          WHERE gm.groupid = :groupid
            AND ctx.instanceid = :courseid
            AND ra.roleid = :roleid
            AND u.deleted = 0
       ORDER BY u.username ASC, u.idnumber ASC, u.lastname ASC, u.firstname ASC",
        ['groupid' => $groupid, 'courseid' => $courseid, 'roleid' => $roleid, 'gname' => $gname, 'gid' => $groupid]
    );
} else {
    $students = (array)$DB->get_records_sql(
        "SELECT DISTINCT u.id, u.firstname, u.lastname, u.username, u.idnumber, u.email,
                COALESCE(g.name, '') AS groupname, COALESCE(g.id, 0) AS student_groupid
           FROM {role_assignments} ra
           JOIN {context} ctx ON ctx.id = ra.contextid
           JOIN {user} u ON u.id = ra.userid
      LEFT JOIN {groups_members} gm ON gm.userid = u.id
      LEFT JOIN {groups} g ON g.id = gm.groupid AND g.courseid = :courseid
          WHERE ctx.instanceid = :courseid2
            AND ra.roleid = :roleid
            AND u.deleted = 0
       ORDER BY g.name ASC, u.username ASC, u.idnumber ASC, u.lastname ASC, u.firstname ASC",
        ['courseid' => $courseid, 'courseid2' => $courseid, 'roleid' => $roleid]
    );
}

$hasdata = !empty($students) && ($finalasmt !== null || $oralasmt !== null);
$studentids = !empty($students) ? array_map('intval', array_keys($students)) : [];

// 5. Data Computation Engine for Exams and Competencies.
$threshold = (float)(get_config('local_comp_report_ext', 'success_threshold') ?: 60.0);

// Helper function to extract competencies for an assessment.
$getassessmentcompetencies = function ($asmt) use ($DB, $courseid) {
    if (!$asmt) {
        return [];
    }
    if (!empty($asmt->quizid)) {
        // Quiz linked.
        $sql = "SELECT DISTINCT c.id, c.shortname, c.idnumber
                  FROM {quiz_attempts} quiza
                  JOIN {question_usages} qu    ON qu.id = quiza.uniqueid
                  JOIN {question_attempts} qa  ON qa.questionusageid = qu.id
                  JOIN {qbank_comp_ext_qmap} m ON m.questionid = qa.questionid
                  JOIN {competency} c          ON c.id = m.competencyid
                 WHERE quiza.quiz = :quizid
              ORDER BY c.shortname ASC";
        $comps = $DB->get_records_sql($sql, ['quizid' => (int)$asmt->quizid]);
        if (empty($comps)) {
            // Fallback: mappings in course.
            $fallbacksql = "SELECT DISTINCT c.id, c.shortname, c.idnumber
                              FROM {qbank_comp_ext_qmap} m
                              JOIN {competency} c ON c.id = m.competencyid
                             WHERE m.courseid = :courseid
                          ORDER BY c.shortname ASC";
            $comps = $DB->get_records_sql($fallbacksql, ['courseid' => $courseid]);
        }
        return (array)$comps;
    } else {
        // Manual practical marks.
        $pracsql = "SELECT DISTINCT c.id, c.shortname, c.idnumber
                      FROM {local_comp_report_ext_prac} p
                      JOIN {competency} c ON c.id = p.competencyid
                     WHERE p.assessmentid = :asmtid AND p.courseid = :courseid
                  ORDER BY c.shortname ASC";
        return (array)$DB->get_records_sql($pracsql, ['asmtid' => (int)$asmt->id, 'courseid' => $courseid]);
    }
};

$finalcomps = $getassessmentcompetencies($finalasmt);
$oralcomps  = $getassessmentcompetencies($oralasmt);

// Combined unique competencies list for the weighted summary.
$alluniquecomps = [];
foreach ($finalcomps as $cid => $comp) {
    $alluniquecomps[$cid] = $comp;
}
foreach ($oralcomps as $cid => $comp) {
    if (!isset($alluniquecomps[$cid])) {
        $alluniquecomps[$cid] = $comp;
    }
}

// 5. Bulk Preload Exam Scores, Retake Policy Resolution, and Competency Mastery.
$allcoursequizzes = $DB->get_records('quiz', ['course' => $courseid], 'id ASC', 'id, name, grade, sumgrades');

[$finalrawgrades, $finalpctgrades, $finalretakeinfo, $finalcompgrades, $finalmaxgrade] =
    local_comp_report_ext_resolve_assessment_data($finalasmt, $students, $allcoursequizzes, $threshold, $courseid);

[$oralrawgrades, $oralpctgrades, $oralretakeinfo, $oralcompgrades, $oralmaxgrade] =
    local_comp_report_ext_resolve_assessment_data($oralasmt, $students, $allcoursequizzes, $threshold, $courseid);


// 6. Build Rows and Aggregations.
$rows = [];
$finalweight = $finalasmt ? (float)$finalasmt->weight : 0.0;
$oralweight  = $oralasmt ? (float)$oralasmt->weight : 0.0;
$totalweight = $finalweight + $oralweight;
if ($totalweight <= 0.0) {
    $finalweight = 50.0;
    $oralweight  = 50.0;
    $totalweight = 100.0;
}

$statfinalscores = [];
$statoralscores  = [];
$statweighted    = [];
$passedcount     = 0;

$studentidx = 0;
foreach ($students as $student) {
    $studentidx++;
    $uid = (int)$student->id;

    // Final Exam Score (value).
    $hasfinalscore = isset($finalrawgrades[$uid]);
    $finalraw      = $hasfinalscore ? $finalrawgrades[$uid] : null;
    $finalvalstr   = ($finalraw !== null) ? number_format($finalraw, 1) : null;
    $finalpct      = isset($finalpctgrades[$uid]) ? $finalpctgrades[$uid] : null;
    if ($finalpct !== null) {
        $statfinalscores[] = $finalpct;
    }

    // Final Exam Competency Cells (percentage %).
    $finalcompcells = [];
    foreach ($finalcomps as $cid => $comp) {
        $hascomp = isset($finalcompgrades[$uid][$cid]);
        $pct = $hascomp ? $finalcompgrades[$uid][$cid] : null;
        $color = ($pct !== null) ? \local_comp_report_ext\competency_calculator::rate_color($pct) : 'grey';
        $finalcompcells[] = [
            'has_score'  => $hascomp,
            'percent'    => ($pct !== null) ? number_format($pct, 1) : null,
            'color'      => $color,
            'shortname'  => format_string($comp->shortname),
        ];
    }

    // Oral Exam Score (value).
    $hasoralscore = isset($oralrawgrades[$uid]);
    $oralraw      = $hasoralscore ? $oralrawgrades[$uid] : null;
    $oralvalstr   = ($oralraw !== null) ? number_format($oralraw, 1) : null;
    $oralpct      = isset($oralpctgrades[$uid]) ? $oralpctgrades[$uid] : null;
    if ($oralpct !== null) {
        $statoralscores[] = $oralpct;
    }

    // Oral Exam Competency Cells (percentage %).
    $oralcompcells = [];
    foreach ($oralcomps as $cid => $comp) {
        $hascomp = isset($oralcompgrades[$uid][$cid]);
        $pct = $hascomp ? $oralcompgrades[$uid][$cid] : null;
        $color = ($pct !== null) ? \local_comp_report_ext\competency_calculator::rate_color($pct) : 'grey';
        $oralcompcells[] = [
            'has_score'  => $hascomp,
            'percent'    => ($pct !== null) ? number_format($pct, 1) : null,
            'color'      => $color,
            'shortname'  => format_string($comp->shortname),
        ];
    }

    // Weighted Overall Grade.
    $weightedgrade = null;
    if ($finalpct !== null && $oralpct !== null) {
        $weightedgrade = (($finalpct * $finalweight) + ($oralpct * $oralweight)) / $totalweight;
    } else if ($finalpct !== null) {
        $weightedgrade = $finalpct;
    } else if ($oralpct !== null) {
        $weightedgrade = $oralpct;
    }

    if ($weightedgrade !== null) {
        $statweighted[] = $weightedgrade;
        if ($weightedgrade >= $threshold) {
            $passedcount++;
        }
    }

    $gradepassed = ($weightedgrade !== null && $weightedgrade >= $threshold);
    $gradecolor  = ($weightedgrade !== null) ? \local_comp_report_ext\competency_calculator::rate_color($weightedgrade) : 'grey';

    // Weighted Competencies (percentage %).
    $weightedcompcells = [];
    foreach ($alluniquecomps as $cid => $comp) {
        $p1 = isset($finalcompgrades[$uid][$cid]) ? $finalcompgrades[$uid][$cid] : null;
        $p2 = isset($oralcompgrades[$uid][$cid]) ? $oralcompgrades[$uid][$cid] : null;
        $wcomp = null;
        if ($p1 !== null && $p2 !== null) {
            $wcomp = (($p1 * $finalweight) + ($p2 * $oralweight)) / $totalweight;
        } else if ($p1 !== null) {
            $wcomp = $p1;
        } else if ($p2 !== null) {
            $wcomp = $p2;
        }

        $wcolor = ($wcomp !== null) ? \local_comp_report_ext\competency_calculator::rate_color($wcomp) : 'grey';
        $weightedcompcells[] = [
            'has_score'  => ($wcomp !== null),
            'percent'    => ($wcomp !== null) ? number_format($wcomp, 1) : null,
            'color'      => $wcolor,
            'shortname'  => format_string($comp->shortname),
        ];
    }

    $studentlink = html_writer::link(
        new moodle_url('/local/comp_report_ext/student_competency_detail.php', [
            'courseid' => $courseid,
            'userid'   => $student->id,
        ]),
        fullname($student),
        ['target' => '_blank', 'class' => 'font-weight-bold text-dark']
    );

    $finalinfo = $finalretakeinfo[$uid] ?? ['is_retake' => false, 'label' => '', 'title' => ''];
    $oralinfo  = $oralretakeinfo[$uid] ?? ['is_retake' => false, 'label' => '', 'title' => ''];

    $rows[] = [
        'idx'                => $studentidx,
        'is_even'            => ($studentidx % 2 === 0),
        'studentid'          => $student->id,
        'studentname'        => fullname($student),
        'studentlink'        => $studentlink,
        'idnumber'           => !empty($student->username) ? $student->username : (!empty($student->idnumber) ? $student->idnumber : '—'),
        'groupname'          => !empty($student->groupname) ? $student->groupname : '—',
        'has_final_score'    => $hasfinalscore,
        'final_score_val'    => $finalvalstr,
        'final_score_pct'    => ($finalpct !== null) ? number_format($finalpct, 1) : null,
        'final_is_retake'    => $finalinfo['is_retake'],
        'final_retake_label' => $finalinfo['label'],
        'final_retake_title' => $finalinfo['title'],
        'final_comp_cells'   => $finalcompcells,
        'has_oral_score'     => $hasoralscore,
        'oral_score_val'     => $oralvalstr,
        'oral_score_pct'     => ($oralpct !== null) ? number_format($oralpct, 1) : null,
        'oral_is_retake'     => $oralinfo['is_retake'],
        'oral_retake_label'  => $oralinfo['label'],
        'oral_retake_title'  => $oralinfo['title'],
        'oral_comp_cells'    => $oralcompcells,
        'has_weighted_grade' => ($weightedgrade !== null),
        'weighted_grade'     => ($weightedgrade !== null) ? number_format($weightedgrade, 1) : null,
        'grade_color'        => $gradecolor,
        'is_passed'          => $gradepassed,
        'weighted_comp_cells'=> $weightedcompcells,
    ];
}

// 7. KPI Metrics.
$totalstudents = count($rows);
$avgfinal = !empty($statfinalscores) ? array_sum($statfinalscores) / count($statfinalscores) : 0.0;
$avgoral  = !empty($statoralscores) ? array_sum($statoralscores) / count($statoralscores) : 0.0;
$avgtotal = !empty($statweighted) ? array_sum($statweighted) / count($statweighted) : 0.0;
$passrate = ($totalstudents > 0) ? ($passedcount / $totalstudents) * 100.0 : 0.0;

$kpis = [
    'avg_final'      => number_format($avgfinal, 1),
    'avg_oral'       => number_format($avgoral, 1),
    'avg_total'      => number_format($avgtotal, 1),
    'pass_rate'      => number_format($passrate, 1),
    'total_students' => $totalstudents,
    'passed_students'=> $passedcount,
];

// Colspans for top header tier.
$finalcompcount = count($finalcomps);
$oralcompcount  = count($oralcomps);
$weightcompcount = count($alluniquecomps);

$finalcolspan   = 1 + $finalcompcount;
$oralcolspan    = 1 + $oralcompcount;
$summarycolspan = 2 + $weightcompcount;

$renderdata = new stdClass();
$renderdata->courseid            = $courseid;
$renderdata->groupid             = $groupid;
$renderdata->finalasmtid         = $finalasmtid;
$renderdata->oralasmtid          = $oralasmtid;
$renderdata->groups              = $groupoptions;
$renderdata->final_options       = $finaloptions;
$renderdata->oral_options        = $oraloptions;
$renderdata->has_configured_asmts= $hasconfiguredasmts;
$renderdata->has_data            = $hasdata;
$renderdata->rows                = $rows;
$renderdata->kpis                = $kpis;
$renderdata->final_name          = $finalasmt ? format_string($finalasmt->name) : get_string('final_exam', 'local_comp_report_ext');
$renderdata->final_weight        = $finalweight;
$renderdata->final_maxgrade      = number_format($finalmaxgrade, 1);
$renderdata->final_comps         = array_values($finalcomps);
$renderdata->final_colspan       = $finalcolspan;
$renderdata->oral_name           = $oralasmt ? format_string($oralasmt->name) : get_string('oral_practical_exam', 'local_comp_report_ext');
$renderdata->oral_weight         = $oralweight;
$renderdata->oral_maxgrade       = number_format($oralmaxgrade, 1);
$renderdata->oral_comps          = array_values($oralcomps);
$renderdata->oral_colspan        = $oralcolspan;
$renderdata->weighted_comps      = array_values($alluniquecomps);
$renderdata->summary_colspan     = $summarycolspan;
$renderdata->threshold           = $threshold;

echo $OUTPUT->header();
$page = new \local_comp_report_ext\output\group_final_report_page($renderdata);
echo $OUTPUT->render($page);
echo $OUTPUT->footer();
