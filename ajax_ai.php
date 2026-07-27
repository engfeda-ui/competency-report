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
 * Legacy AJAX Endpoint for on-demand AI pedagogical commentary.
 * Delegates to External Service local_comp_report_ext\external\ai.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/ai.php');

$courseid     = optional_param('courseid', 0, PARAM_INT);
$userid       = optional_param('userid', 0, PARAM_INT);
$groupid      = optional_param('groupid', 0, PARAM_INT);
$quizid       = optional_param('quizid', 0, PARAM_INT);
$customprompt = optional_param('custom_prompt', '', PARAM_TEXT);
$contexttype  = optional_param('context_type', 'student', PARAM_ALPHAEXT);
$focustype    = optional_param('focus_type', 'competency', PARAM_ALPHA);

try {
    $res = \local_comp_report_ext\external\ai::generate_comment(
        $courseid,
        $userid,
        $groupid,
        $quizid,
        $contexttype,
        $focustype,
        $customprompt
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
