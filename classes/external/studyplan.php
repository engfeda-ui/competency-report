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
 * External Study Plan Service for local_comp_report_ext.
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
 * Class studyplan
 *
 * External API web service endpoints for remedial study plan generation.
 *
 * @package local_comp_report_ext
 */
class studyplan extends external_api {
    /**
     * Parameter definition for generate_study_plan.
     *
     * @return external_function_parameters
     */
    public static function generate_study_plan_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid'   => new external_value(PARAM_INT, 'Course ID'),
            'userid'     => new external_value(PARAM_INT, 'User ID'),
            'language'   => new external_value(PARAM_ALPHAEXT, 'Language code (ar/en)', VALUE_DEFAULT, 'ar'),
            'numsessions' => new external_value(PARAM_INT, 'Number of 1-hour sessions', VALUE_DEFAULT, 4),
        ]);
    }

    /**
     * Execute generate_study_plan external web service call.
     *
     * @param int $courseid
     * @param int $userid
     * @param string $language
     * @param int $numsessions
     * @return array
     */
    public static function generate_study_plan(
        int $courseid,
        int $userid,
        string $language = 'ar',
        int $numsessions = 4
    ): array {
        $params = self::validate_parameters(self::generate_study_plan_parameters(), [
            'courseid'   => $courseid,
            'userid'     => $userid,
            'language'   => $language,
            'numsessions' => $numsessions,
        ]);

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);

        require_capability('moodle/course:view', $context);

        $graderid = \local_comp_report_ext\competency_sync::resolve_grader_id();
        $rates    = \local_comp_report_ext\competency_sync::sync_user_competency(
            $params['userid'],
            $params['courseid'],
            $graderid
        );

        $contextdetails = local_comp_report_ext_build_context_details($params['courseid'], $params['userid']);
        $fullprompt     = local_comp_report_ext_build_studyplan_prompt(
            $rates,
            $params['language'],
            $params['numsessions'],
            $contextdetails
        );

        $planmarkdown = local_comp_report_ext_generate_study_plan($fullprompt);
        $planhtml     = local_comp_report_ext_markdown_to_html_table($planmarkdown);

        return [
            'status' => 'success',
            'html'   => $planhtml,
        ];
    }

    /**
     * Return definition for generate_study_plan.
     *
     * @return external_single_structure
     */
    public static function generate_study_plan_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_ALPHA, 'Status of execution'),
            'html'   => new external_value(PARAM_RAW, 'Generated HTML content'),
        ]);
    }
}
