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
 * Legacy AJAX Endpoint to generate a personalized AI remedial study plan.
 * Delegates to External Service local_comp_report_ext\external\studyplan.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_login();
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/ai.php');

$courseid = required_param('courseid', PARAM_INT);
$userid   = optional_param('userid', 0, PARAM_INT);
$language = optional_param('language', 'ar', PARAM_ALPHAEXT);
$sessions = optional_param('sessions', 4, PARAM_INT);

try {
    $res = \local_comp_report_ext\external\studyplan::generate_study_plan(
        $courseid,
        $userid,
        $language,
        $sessions
    );

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'html'    => $res['html'],
    ]);
    exit;
} catch (\Exception $e) {
    header('Content-Type: application/json', true, 400);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ]);
    exit;
}
