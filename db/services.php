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
 * External web service functions declaration for local_comp_report_ext.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_comp_report_ext_generate_ai_comment' => [
        'classname'   => 'local_comp_report_ext\external\ai',
        'methodname'  => 'generate_comment',
        'description' => 'Generates AI competency and grade analysis commentary.',
        'type'        => 'read',
        'ajax'        => true,
    ],
    'local_comp_report_ext_generate_study_plan' => [
        'classname'   => 'local_comp_report_ext\external\studyplan',
        'methodname'  => 'generate_study_plan',
        'description' => 'Generates personalized remedial study plans.',
        'type'        => 'read',
        'ajax'        => true,
    ],
];
