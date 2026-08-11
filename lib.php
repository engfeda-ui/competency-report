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
 * Library functions for the local_comp_report_ext plugin.
 *
 * @package    local_comp_report_ext
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
function local_comp_report_ext_extend_navigation_course($navigation, $course, $context) {
    $canview = has_capability('local/comp_report_ext:viewreports', $context)
        || has_capability('mod/quiz:viewreports', $context)
        || has_capability('moodle/site:config', $context);

    // 1. Teacher & Administrator Section.
    if ($canview) {
        // Unified Course Master Report.
        if (!$navigation->find('coursemasterreport', navigation_node::TYPE_SETTING)) {
            $url = new moodle_url('/local/comp_report_ext/course_master_report.php', ['courseid' => $course->id]);
            $navigation->add(
                get_string('coursemasterreport', 'local_comp_report_ext'),
                $url,
                navigation_node::TYPE_SETTING,
                null,
                'coursemasterreport',
                new pix_icon('i/stats', '')
            );
        }

        // Student Performance Dashboard (consolidated class report).
        if (!$navigation->find('competency_report_teacher', navigation_node::TYPE_SETTING)) {
            $url = new moodle_url('/local/comp_report_ext/class_report.php', ['courseid' => $course->id]);
            $navigation->add(
                get_string('studentdashboard', 'local_comp_report_ext'),
                $url,
                navigation_node::TYPE_SETTING,
                null,
                'competency_report_teacher',
                new pix_icon('i/report', '')
            );
        }

        // Group Performance Analysis (consolidated group report).
        if ($canview) {
            if (!$navigation->find('groupcompetency', navigation_node::TYPE_SETTING)) {
                $url = new moodle_url('/local/comp_report_ext/group_competency.php', ['courseid' => $course->id]);
                $navigation->add(
                    get_string('groupperformance', 'local_comp_report_ext'),
                    $url,
                    navigation_node::TYPE_SETTING,
                    null,
                    'groupcompetency',
                    new pix_icon('i/group', '')
                );
            }
        }

        // Assessment weight configuration (editing teachers / managers).
        if (has_capability('local/comp_report_ext:manageassessments', $context) || has_capability('moodle/site:config', $context)) {
            if (!$navigation->find('competency_assessment_setup', navigation_node::TYPE_SETTING)) {
                $url = new moodle_url('/local/comp_report_ext/assessment_setup.php', ['courseid' => $course->id]);
                $navigation->add(
                    get_string('assessmentsetup', 'local_comp_report_ext'),
                    $url,
                    navigation_node::TYPE_SETTING,
                    null,
                    'competency_assessment_setup',
                    new pix_icon('i/settings', '')
                );
            }
        }

        // Practical exam result entry (teachers / trainers).
        if (has_capability('local/comp_report_ext:enterpractical', $context) || has_capability('moodle/site:config', $context)) {
            if (!$navigation->find('competency_practical_entry', navigation_node::TYPE_SETTING)) {
                $url = new moodle_url('/local/comp_report_ext/practical_entry.php', ['courseid' => $course->id]);
                $navigation->add(
                    get_string('practicalentry', 'local_comp_report_ext'),
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
    if (isloggedin() && !isguestuser() && !$canview) {
        if (!$navigation->find('competency_report_student_parent', navigation_node::TYPE_CUSTOM)) {
            $url = new moodle_url('/local/comp_report_ext/student_report.php', ['courseid' => $course->id]);
            $navigation->add(
                get_string('mycompetencies', 'local_comp_report_ext'),
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
 * Inject competency reports directly into the Course Reports navigation node.
 *
 * @param navigation_node $navigation The reports navigation node.
 * @param stdClass $course The course object.
 * @param context_course $context The course context.
 * @return void
 */
function local_comp_report_ext_extend_navigation_reports($navigation, $course, $context) {
    $canview = has_capability('local/comp_report_ext:viewreports', $context)
        || has_capability('mod/quiz:viewreports', $context)
        || has_capability('moodle/site:config', $context);

    if ($canview) {
        $url1 = new moodle_url('/local/comp_report_ext/course_master_report.php', ['courseid' => $course->id]);
        $navigation->add(
            get_string('coursemasterreport', 'local_comp_report_ext'),
            $url1,
            navigation_node::TYPE_SETTING,
            null,
            'coursemasterreport_rep'
        );

        $url2 = new moodle_url('/local/comp_report_ext/class_report.php', ['courseid' => $course->id]);
        $navigation->add(
            get_string('studentdashboard', 'local_comp_report_ext'),
            $url2,
            navigation_node::TYPE_SETTING,
            null,
            'competency_report_teacher_rep'
        );

        $url3 = new moodle_url('/local/comp_report_ext/group_competency.php', ['courseid' => $course->id]);
        $navigation->add(
            get_string('groupperformance', 'local_comp_report_ext'),
            $url3,
            navigation_node::TYPE_SETTING,
            null,
            'groupcompetency_rep'
        );

        $url4 = new moodle_url('/local/comp_report_ext/group_assessment_distribution.php', ['courseid' => $course->id]);
        $navigation->add(
            get_string('groupassessmentdistribution', 'local_comp_report_ext'),
            $url4,
            navigation_node::TYPE_SETTING,
            null,
            'groupassessmentdistribution_rep'
        );

        $url5 = new moodle_url('/local/comp_report_ext/group_analytics_dashboard.php', ['courseid' => $course->id]);
        $navigation->add(
            get_string('group_analytics_dashboard', 'local_comp_report_ext'),
            $url5,
            navigation_node::TYPE_SETTING,
            null,
            'groupanalyticsdashboard_rep'
        );
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
function local_comp_report_ext_check_and_notify($userid, $courseid, array $rates) {
    global $DB, $CFG;

    // Read alert threshold from settings (default: 40%).
    $threshold = (int)(get_config('local_comp_report_ext', 'alert_threshold') ?: 40);
    $alertenabled = get_config('local_comp_report_ext', 'enable_alerts');

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

    $reporturl = (new moodle_url('/local/comp_report_ext/student_competency_detail.php', [
        'courseid' => $courseid,
        'userid'   => $userid,
    ]))->out(false);

    foreach ($teachers as $teacher) {
        $message = new \core\message\message();
        $message->component         = 'local_comp_report_ext';
        $message->name              = 'studentatrisk';
        $message->userfrom          = \core_user::get_noreply_user();
        $message->userto            = $teacher;
        $message->subject           = get_string('alert_subject', 'local_comp_report_ext', fullname($student));
        $message->fullmessage       = get_string('alert_body', 'local_comp_report_ext', (object)[
            'student'  => fullname($student),
            'course'   => $course->fullname,
            'weaklist' => $weaklist,
            'url'      => $reporturl,
        ]);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml   = '<p>' . str_replace("\n", '<br>', $message->fullmessage) . '</p>';
        $message->smallmessage      = get_string('alert_subject', 'local_comp_report_ext', fullname($student));
        $message->notification      = 1;
        $message->contexturl        = $reporturl;
        $message->contexturlname    = get_string('studentcompetencydetail', 'local_comp_report_ext');

        message_send($message);
    }
}

/**
 * Get the local filesystem path for a PDF logo image based on position.
 *
 * @param string $type Position: 'left' or 'right'.
 * @return string|null Local path to image file or null if not found.
 */
function local_comp_report_ext_get_logo_path($type = 'left') {
    global $CFG;

    $filearea = ($type === 'left') ? 'logo_left' : 'logo_right';

    // 1. Check stored file upload in Moodle file storage.
    try {
        $fs = get_file_storage();
        $syscontext = context_system::instance();
        $files = $fs->get_area_files($syscontext->id, 'local_comp_report_ext', $filearea, 0, 'itemid, filepath, filename', false);
        if (!empty($files)) {
            $file = reset($files);
            $tempdir = make_temp_directory('local_comp_report_ext');
            $temppath = $tempdir . '/' . $filearea . '_' . clean_filename($file->get_filename());
            $file->copy_content_to($temppath);
            if (file_exists($temppath) && filesize($temppath) > 0) {
                return $temppath;
            }
        }
    } catch (\Throwable $e) {
        // Fallthrough to URL check if file storage fails.
        unset($e);
    }

    // 2. Check URL or path text setting.
    $urlsetting = ($type === 'left') ? 'logo_left_url' : 'logo_right_url';
    $url = get_config('local_comp_report_ext', $urlsetting);
    if (!empty($url)) {
        $url = trim($url);
        if (file_exists($url)) {
            return $url;
        }
        // If it starts with http/https, download/cache to temp directory.
        if (preg_match('/^https?:\/\//i', $url)) {
            try {
                $tempdir = make_temp_directory('local_comp_report_ext');
                $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'png';
                $temppath = $tempdir . '/' . $filearea . '_web.' . $ext;
                if (!file_exists($temppath) || (time() - filemtime($temppath) > 3600)) {
                    $content = @file_get_contents($url);
                    if ($content) {
                        file_put_contents($temppath, $content);
                    }
                }
                if (file_exists($temppath) && filesize($temppath) > 0) {
                    return $temppath;
                }
            } catch (\Throwable $e) {
                unset($e);
                return null;
            }
        }
    }

    $fallback = $CFG->dirroot . '/pix/moodlelogo.png';
    if (file_exists($fallback)) {
        return $fallback;
    }

    return null;
}

/**
 * Render configured left and right logos in the top header of a PDF document.
 *
 * @param TCPDF $pdf The TCPDF instance.
 * @param bool $islandscape Whether the page is in landscape mode.
 * @return void
 */
function local_comp_report_ext_render_pdf_header_logos(&$pdf, $islandscape = false) {
    $leftpath = local_comp_report_ext_get_logo_path('left');
    $rightpath = local_comp_report_ext_get_logo_path('right');

    $hasleft = !empty($leftpath) && file_exists($leftpath);
    $hasright = !empty($rightpath) && file_exists($rightpath);

    if (!$hasleft && !$hasright) {
        return;
    }

    $pagewidth = $pdf->getPageWidth();
    $margin = 15;
    $y = 8;
    $maxheight = 16; // Height in mm.
    $logowidth = 45; // Max width in mm.

    if ($hasleft) {
        $pdf->Image(
            $leftpath,
            $margin,
            $y,
            $logowidth,
            $maxheight,
            '',
            '',
            '',
            false,
            300,
            'L',
            false,
            false,
            0,
            false,
            false,
            false
        );
    }

    if ($hasright) {
        $rightx = $pagewidth - $margin - $logowidth;
        $pdf->Image(
            $rightpath,
            $rightx,
            $y,
            $logowidth,
            $maxheight,
            '',
            '',
            '',
            false,
            300,
            'R',
            false,
            false,
            0,
            false,
            false,
            false
        );
    }

    $pdf->SetY($y + $maxheight + 4);
}

/**
 * Build rich context details for domain-specific AI analysis.
 *
 * @param int $courseid
 * @param int $userid
 * @param int $quizid
 * @return array
 */
function local_comp_report_ext_build_context_details($courseid, $userid = 0, $quizid = 0) {
    global $DB;
    $contextdetails = [];

    if ($courseid > 0) {
        $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname, shortname');
        if ($course) {
            $contextdetails['coursename'] = $course->fullname;
            $contextdetails['course_fullname'] = $course->fullname;
        }
    }

    if ($quizid > 0) {
        $quiz = $DB->get_record('quiz', ['id' => $quizid], 'id, name');
        if ($quiz) {
            $contextdetails['quizname'] = $quiz->name;
            $contextdetails['quiz_name'] = $quiz->name;
        }
    }

    if ($userid > 0 && $courseid > 0) {
        $qsql = "SELECT q.id, q.name, qas.fraction, qa.maxfraction
                   FROM {quiz_attempts} quiza
                   JOIN {quiz} quiz ON quiz.id = quiza.quiz
                   JOIN {question_usages} qu ON qu.id = quiza.uniqueid
                   JOIN {question_attempts} qa ON qa.questionusageid = qu.id
                   JOIN {question} q ON q.id = qa.questionid
                   JOIN (
                       SELECT MAX(fraction) AS fraction, questionattemptid
                         FROM {question_attempt_steps}
                        GROUP BY questionattemptid
                   ) qas ON qas.questionattemptid = qa.id
                  WHERE quiz.course = :courseid AND quiza.userid = :userid AND quiza.state = 'finished'
                  ORDER BY qas.fraction ASC";
        $qattempts = $DB->get_records_sql($qsql, ['courseid' => $courseid, 'userid' => $userid]);

        $missed = [];
        $mastered = [];
        foreach ($qattempts as $qa) {
            $cleanname = clean_param(strip_tags($qa->name), PARAM_TEXT);
            if (empty($cleanname)) {
                continue;
            }
            if (strlen($cleanname) > 90) {
                $cleanname = substr($cleanname, 0, 87) . '...';
            }
            if ($qa->fraction < $qa->maxfraction) {
                if (count($missed) < 6 && !in_array($cleanname, $missed)) {
                    $missed[] = $cleanname;
                }
            } else {
                if (count($mastered) < 6 && !in_array($cleanname, $mastered)) {
                    $mastered[] = $cleanname;
                }
            }
        }
        if (!empty($missed)) {
            $contextdetails['missed_questions'] = $missed;
        }
        if (!empty($mastered)) {
            $contextdetails['mastered_questions'] = $mastered;
        }
    }

    $contextdetails['lang'] = current_language();
    return $contextdetails;
}

/**
 * Build the full studyplan prompt string from competency rates + context details.
 *
 * This is the single shared source of truth used by both the AJAX study plan
 * endpoint (external\studyplan::generate_study_plan) and the PDF generator
 * (studyplan_pdf.php) so both paths produce identical prompts.
 *
 * @param array  $rates          Competency rates: [shortname => percent] or
 *                               [competencyid => ['competency' => stdClass, 'percent' => float, ...]].
 * @param string $language       Output language string, e.g. 'English' or 'Arabic'.
 * @param int    $numsessions    Number of 1-hour study sessions.
 * @param array  $contextdetails Rich context from build_context_details().
 * @return string The complete prompt string ready to send to the AI.
 */
function local_comp_report_ext_build_studyplan_prompt(
    array $rates,
    string $language,
    int $numsessions,
    array $contextdetails
): string {
    $threshold   = (int)(get_config('local_comp_report_ext', 'success_threshold') ?: 60);
    $numsessions = max(1, min(60, $numsessions));
    $maxwords    = max(200, min(1200, $numsessions * 60));
    $midpoint    = (int)round($numsessions / 2);

    $studentname = $contextdetails['studentname'] ?? '';
    $coursename  = $contextdetails['coursename']  ?? ($contextdetails['course_fullname'] ?? '');

    // Normalise $rates to [shortname => ['desc' => string, 'rate' => float]].
    $normrates = [];
    foreach ($rates as $key => $value) {
        if (is_array($value) && isset($value['competency'])) {
            // Format returned by competency_calculator::get_student_scores().
            $comp   = $value['competency'];
            $sname  = is_object($comp) ? $comp->shortname : ($comp['shortname'] ?? '#' . $key);
            $desc   = is_object($comp) ? ($comp->description ?? '') : ($comp['description'] ?? '');
            $desc   = html_entity_decode(strip_tags($desc), ENT_QUOTES, 'UTF-8');
            $pct    = (float)$value['percent'];
        } else if (is_numeric($value)) {
            // Simple [shortname => percent] format (returned by competency_sync).
            $sname = (string)$key;
            $desc  = '';
            $pct   = (float)$value;
        } else {
            continue;
        }
        $normrates[$sname] = ['desc' => $desc, 'rate' => round($pct, 1)];
    }

    $weak   = [];
    $strong = [];
    foreach ($normrates as $code => $info) {
        if ($info['rate'] < $threshold) {
            $weak[$code] = $info;
        } else {
            $strong[$code] = $info;
        }
    }

    $prompt = "You are an expert educational psychologist and pedagogical coach.\n"
        . "Create a highly structured, actionable, personalized remedial study plan";

    if (!empty($studentname)) {
        $prompt .= " for the student \"{$studentname}\"";
    }
    if (!empty($coursename)) {
        $prompt .= " enrolled in the course \"{$coursename}\"";
    }
    $prompt .= ".\n\n"
        . "PLAN PARAMETERS:\n"
        . "- Total sessions available: {$numsessions} sessions\n"
        . "- Duration per session: 1 hour (60 minutes)\n"
        . "- Each session is an independent 1-hour block to be scheduled by the teacher/student\n\n"
        . "STUDENT PERFORMANCE DATA:\n";

    if (!empty($weak)) {
        $prompt .= "\nCOMPETENCIES NEEDING INTENSIVE REMEDIATION (below {$threshold}% mastery):\n";
        foreach ($weak as $code => $info) {
            $descpart = !empty($info['desc']) ? " {$info['desc']}" : '';
            $prompt  .= "  - [{$code}]{$descpart} — Current mastery: {$info['rate']}%\n";
        }
    }
    if (!empty($strong)) {
        $prompt .= "\nCOMPETENCIES ALREADY STRONG (above {$threshold}% mastery — for review only):\n";
        foreach ($strong as $code => $info) {
            $descpart = !empty($info['desc']) ? " {$info['desc']}" : '';
            $prompt  .= "  - [{$code}]{$descpart} — Current mastery: {$info['rate']}%\n";
        }
    }

    $prompt .= "
STRICT REQUIREMENTS:
1. Write ENTIRELY in {$language}. No preamble, no meta-commentary.
2. Output clean HTML only (headings, lists, and one schedule table).
3. MANDATORY SECTIONS IN THIS ORDER:

   <h4><strong>\xf0\x9f\x93\x8a Performance Summary</strong></h4>
   2 sentences: overall performance diagnosis and main priority.

   <h4><strong>\xf0\x9f\x8e\xaf Priority Focus Areas</strong></h4>
   Ranked bullet list of weak competencies — one sentence per item explaining WHY it is critical.

   <h4><strong>\xf0\x9f\x93\x85 Session-by-Session Study Schedule ({$numsessions} Sessions x 1 Hour Each)</strong></h4>
   An HTML <table> with these columns:
   | Session # | Competency Code | Session Goal | Suggested Activities | Time Allocation |
   - Distribute the {$numsessions} sessions across ALL weak competencies by priority (weakest gets more sessions).
   - Weaker competencies get more sessions proportionally.
   - Every session must be exactly 1 hour and self-contained (schedulable by the teacher).

   <h4><strong>\xf0\x9f\x93\x9d Learning Strategies per Competency</strong></h4>
   For EACH weak competency: 2-3 specific, named techniques (e.g., spaced repetition, worked examples, retrieval practice).

   <h4><strong>\xe2\x9c\x85 Milestone Checkpoints</strong></h4>
   Define 2-3 measurable checkpoints at Sessions {$midpoint} and {$numsessions} to assess progress.

4. Be SPECIFIC and ACTIONABLE — no generic advice.
5. Maximum {$maxwords} words total.
";

    return $prompt;
}
