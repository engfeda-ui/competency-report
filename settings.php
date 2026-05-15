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
 * Settings for the yetkinlik plugin.
 *
 * @package    local_yetkinlik
 * @copyright  2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // 1. Define the Settings Page.
    $settings = new admin_settingpage('local_yetkinlik', get_string('pluginname', 'local_yetkinlik'));

    if ($ADMIN->fulltree) {
        // AI integration toggle (enable/disable).
        $settings->add(new admin_setting_configcheckbox(
            'local_yetkinlik/enable_ai',
            get_string('enable_ai', 'local_yetkinlik'),
            get_string('enable_ai_desc', 'local_yetkinlik'),
            0
        ));

        // API Key.
        $settings->add(new admin_setting_configtext(
            'local_yetkinlik/apikey',
            get_string('apikey', 'local_yetkinlik'),
            get_string('apikey_desc', 'local_yetkinlik'),
            '',
            PARAM_TEXT
        ));

        // Model name.
        $settings->add(new admin_setting_configtext(
            'local_yetkinlik/model',
            get_string('model', 'local_yetkinlik'),
            get_string('model_desc', 'local_yetkinlik'),
            'gpt-4',
            PARAM_ALPHANUMEXT
        ));

        // Maximum number of rows.
        $settings->add(new admin_setting_configtext(
            'local_yetkinlik/maxrows',
            get_string('maxrows', 'local_yetkinlik'),
            get_string('maxrows_desc', 'local_yetkinlik'),
            100,
            PARAM_INT
        ));
    }

    // 2. Add the Settings Page under "Local Plugins".
    $ADMIN->add('localplugins', $settings);

    // 3. Add External Report Pages under the "Reports" menu.
    $ADMIN->add('reports', new admin_externalpage(
        'local_yetkinlik_schoolreport',
        get_string('schoolreport', 'local_yetkinlik'),
        new moodle_url('/local/yetkinlik/school_report.php'),
        'moodle/site:config'
    ));

    $ADMIN->add('reports', new admin_externalpage(
        'local_yetkinlik_schoolpdf',
        get_string('schoolpdf', 'local_yetkinlik'),
        new moodle_url('/local/yetkinlik/school_pdf.php'),
        'moodle/site:config'
    ));
}
