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
 * Version details for the local_comp_report_ext plugin.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @copyright  based on work by 2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/** @var stdClass $plugin */
$plugin->component = 'local_comp_report_ext';       // Full name of the plugin (category_name).
$plugin->version   = 2026082105;              // The current module version (YYYYMMDDXX).
$plugin->requires  = 2024041600;              // Requires Moodle 4.4 or later.
$plugin->maturity  = MATURITY_STABLE;          // Stable release.
$plugin->release   = '3.19.5';                // Human-readable version name.

// Plugin dependencies (Other plugins that must be installed first).
$plugin->dependencies = [
    'qbank_comp_ext' => 2026070500,
];
