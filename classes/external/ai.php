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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * External AI Service for local_comp_report_ext.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_comp_report_ext\external;

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use context_course;

defined('MOODLE_INTERNAL') || die();

global $CFG;
if (file_exists($CFG->libdir . '/externallib.php')) {
    require_once($CFG->libdir . '/externallib.php');
}
if (!class_exists('external_api') && class_exists('core_external\external_api')) {
    class_alias('core_external\external_api', 'external_api');
}
if (!class_exists('external_value') && class_exists('core_external\external_value')) {
    class_alias('core_external\external_value', 'external_value');
}
if (!class_exists('external_single_structure') && class_exists('core_external\external_single_structure')) {
    class_alias('core_external\external_single_structure', 'external_single_structure');
}
if (!class_exists('external_function_parameters') && class_exists('core_external\external_function_parameters')) {
    class_alias('core_external\external_function_parameters', 'external_function_parameters');
}

require_once(__DIR__ . '/../../lib.php');
require_once(__DIR__ . '/../../ai.php');

/**
 * Class ai
 *
 * External API web service endpoints for AI commentary generation.
 *
 * @package local_comp_report_ext
 */
class ai extends external_api {
    /**
     * Parameter definition for generate_comment.
     *
     * @return external_function_parameters
     */
    public static function generate_comment_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid'     => new external_value(PARAM_INT, 'Course ID'),
            'userid'       => new external_value(PARAM_INT, 'User ID', VALUE_DEFAULT, 0),
            'groupid'      => new external_value(PARAM_INT, 'Group ID', VALUE_DEFAULT, 0),
            'quizid'       => new external_value(PARAM_INT, 'Quiz ID', VALUE_DEFAULT, 0),
            'contexttype'  => new external_value(
                PARAM_ALPHAEXT,
                'Context type: student, school, group, quiz, course_master',
                VALUE_DEFAULT,
                'student'
            ),
            'focustype'    => new external_value(PARAM_ALPHAEXT, 'Focus type: competency or grades', VALUE_DEFAULT, 'competency'),
            'customprompt' => new external_value(PARAM_TEXT, 'Optional custom prompt instructions', VALUE_DEFAULT, ''),
            'language'     => new external_value(PARAM_TEXT, 'Optional language selection (Arabic, English, auto)', VALUE_DEFAULT, 'auto'),
        ]);
    }

    /**
     * Execute generate_comment external web service call.
     *
     * @param int $courseid
     * @param int $userid
     * @param int $groupid
     * @param int $quizid
     * @param string $contexttype
     * @param string $focustype
     * @param string $customprompt
     * @param string $language
     * @return array
     */
    public static function generate_comment(
        int $courseid,
        int $userid = 0,
        int $groupid = 0,
        int $quizid = 0,
        string $contexttype = 'student',
        string $focustype = 'competency',
        string $customprompt = '',
        string $language = 'auto'
    ): array {
        global $DB;

        $params = self::validate_parameters(self::generate_comment_parameters(), [
            'courseid'     => $courseid,
            'userid'       => $userid,
            'groupid'      => $groupid,
            'quizid'       => $quizid,
            'contexttype'  => $contexttype,
            'focustype'    => $focustype,
            'customprompt' => $customprompt,
            'language'     => $language,
        ]);

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);

        require_capability('moodle/course:view', $context);

        // Fetch context details.
        $contextdetails = local_comp_report_ext_build_context_details($params['courseid'], $params['userid'], $params['quizid']);
        if (!empty($params['language']) && $params['language'] !== 'auto') {
            $contextdetails['language'] = $params['language'];
        }

        // Calculate rates based on focus and context type.
        $rates = [];
        if ($params['focustype'] === 'grades') {
            if ($params['contexttype'] === 'student' && $params['userid'] > 0) {
                // Student specific grades.
                $sql = "SELECT q.id, q.name, qa.sumgrades AS grade, q.sumgrades AS maxgrade
                        FROM {quiz_attempts} qa
                        JOIN {quiz} q ON q.id = qa.quiz
                        WHERE q.course = :courseid AND qa.userid = :userid AND qa.state = 'finished'
                        ORDER BY q.name ASC";
                $rows = $DB->get_records_sql($sql, ['courseid' => $params['courseid'], 'userid' => $params['userid']]);
                foreach ($rows as $r) {
                    $percent = ($r->maxgrade > 0) ? round(($r->grade / $r->maxgrade) * 100, 1) : 0;
                    $rates[$r->name] = $percent;
                }
            } else {
                // Group or course level grades / assessment weights.
                $asmts = $DB->get_records('local_comp_report_ext_asmt', ['courseid' => $params['courseid']], 'id ASC');
                if (!empty($asmts)) {
                    if (!empty($params['groupid'])) {
                        $members = groups_get_members($params['groupid'], 'u.id');
                        $userids = !empty($members) ? array_keys($members) : [0];
                    } else {
                        $enrolled = get_enrolled_users($context, 'mod/quiz:attempt', 0, 'u.id');
                        $userids = !empty($enrolled) ? array_keys($enrolled) : [0];
                    }

                    foreach ($asmts as $asmt) {
                        $asmtname = $asmt->name . ' (Weight: ' . (float)$asmt->weight . '%)';
                        if ($asmt->type === 'quiz' && !empty($asmt->quizid)) {
                            list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
                            $inparams['quizid'] = $asmt->quizid;
                            $sql = "SELECT AVG(qa.sumgrades) AS avggrade, q.sumgrades AS maxgrade
                                    FROM {quiz_attempts} qa
                                    JOIN {quiz} q ON q.id = qa.quiz
                                    WHERE q.id = :quizid AND qa.state = 'finished' AND qa.userid $insql";
                            $res = $DB->get_record_sql($sql, $inparams);
                            $rate = ($res && $res->maxgrade > 0) ? round(($res->avggrade / $res->maxgrade) * 100, 1) : 0;
                            $rates[$asmtname] = $rate;
                        } else if ($asmt->type === 'practical') {
                            list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
                            $inparams['asmtid'] = $asmt->id;
                            $sql = "SELECT AVG(competency_percent) AS avggrade FROM {local_comp_report_ext_prac} WHERE assessmentid = :asmtid AND studentid $insql";
                            $res = $DB->get_record_sql($sql, $inparams);
                            $rate = ($res && $res->avggrade !== null) ? round((float)$res->avggrade, 1) : 0;
                            $rates[$asmtname] = $rate;
                        }
                    }
                }

                if (empty($rates)) {
                    if (!empty($params['groupid'])) {
                        $members = groups_get_members($params['groupid'], 'u.id');
                        $userids = !empty($members) ? array_keys($members) : [0];
                        list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
                        $inparams['courseid'] = $params['courseid'];
                        $sql = "SELECT q.id, q.name, AVG(qa.sumgrades) as avggrade, q.sumgrades as maxgrade
                                FROM {quiz_attempts} qa
                                JOIN {quiz} q ON q.id = qa.quiz
                                WHERE q.course = :courseid AND qa.state = 'finished' AND qa.userid $insql
                                GROUP BY q.id, q.name, q.sumgrades
                                ORDER BY q.name ASC";
                        $rows = $DB->get_records_sql($sql, $inparams);
                    } else {
                        $sql = "SELECT q.id, q.name, AVG(qa.sumgrades) as avggrade, q.sumgrades as maxgrade
                                FROM {quiz_attempts} qa
                                JOIN {quiz} q ON q.id = qa.quiz
                                WHERE q.course = :courseid AND qa.state = 'finished'
                                GROUP BY q.id, q.name, q.sumgrades
                                ORDER BY q.name ASC";
                        $rows = $DB->get_records_sql($sql, ['courseid' => $params['courseid']]);
                    }
                    foreach ($rows as $r) {
                        $rate = ($r->maxgrade > 0) ? round(($r->avggrade / $r->maxgrade) * 100, 1) : 0;
                        $rates[$r->name] = $rate;
                    }
                }
            }
        } else {
            // Competency rates calculation.
            if ($params['contexttype'] === 'student' && $params['userid'] > 0) {
                $graderid = \local_comp_report_ext\competency_sync::resolve_grader_id();
                $rates = \local_comp_report_ext\competency_sync::sync_user_competency(
                    $params['userid'],
                    $params['courseid'],
                    $graderid
                );
            } else {
                $calc = new \local_comp_report_ext\competency_calculator($params['courseid']);
                $compdata = $calc->get_all_competencies_data($params['groupid']);
                foreach ($compdata as $row) {
                    $sname = is_object($row->competency) ? $row->competency->shortname : ('#' . $row->competency->id);
                    $rates[$sname] = (float)$row->course_rate;
                }
            }
        }

        $html = local_comp_report_ext_generate_comment(
            $rates,
            $params['contexttype'],
            $params['customprompt'],
            $params['focustype'],
            $contextdetails
        );

        return [
            'status' => 'success',
            'html'   => $html,
        ];
    }

    /**
     * Return definition for generate_comment.
     *
     * @return external_single_structure
     */
    public static function generate_comment_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_ALPHA, 'Status of execution'),
            'html'   => new external_value(PARAM_RAW, 'Generated HTML content'),
        ]);
    }
}
