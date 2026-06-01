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
 * Settings for the competency_report plugin.
 *
 * @package    local_competency_report
 * @copyright  2026 Mahmoud Salem
 * @copyright  based on work by 2026 Hakan Ã‡iÄŸci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // 1. Define the Settings Page.
    $settings = new admin_settingpage('local_competency_report', get_string('pluginname', 'local_competency_report'));

    if ($ADMIN->fulltree) {
        // AI integration toggle (enable/disable).
        $settings->add(new admin_setting_configcheckbox(
            'local_competency_report/enable_ai',
            get_string('enable_ai', 'local_competency_report'),
            get_string('enable_ai_desc', 'local_competency_report'),
            0
        ));

        // AI Provider.
        $settings->add(new admin_setting_configselect(
            'local_competency_report/ai_provider',
            get_string('ai_provider', 'local_competency_report'),
            get_string('ai_provider_desc', 'local_competency_report'),
            'openai',
            [
                'openai' => get_string('ai_provider_openai', 'local_competency_report'),
                'local'  => get_string('ai_provider_local', 'local_competency_report'),
            ]
        ));

        // Local LLM Endpoint.
        $settings->add(new admin_setting_configtext(
            'local_competency_report/local_endpoint',
            get_string('local_endpoint', 'local_competency_report'),
            get_string('local_endpoint_desc', 'local_competency_report'),
            'http://localhost:11434/v1',
            PARAM_RAW
        ));


        // API Key.
        $settings->add(new admin_setting_configtext(
            'local_competency_report/apikey',
            get_string('apikey', 'local_competency_report'),
            get_string('apikey_desc', 'local_competency_report'),
            '',
            PARAM_TEXT
        ));

        // Model name.
        $settings->add(new admin_setting_configtext(
            'local_competency_report/model',
            get_string('model', 'local_competency_report'),
            get_string('model_desc', 'local_competency_report'),
            'gpt-4',
            PARAM_RAW
        ));

        // Maximum number of rows.
        $settings->add(new admin_setting_configtext(
            'local_competency_report/maxrows',
            get_string('maxrows', 'local_competency_report'),
            get_string('maxrows_desc', 'local_competency_report'),
            100,
            PARAM_INT
        ));

        // Success threshold for competency colour coding and failgrade integration.
        $settings->add(new admin_setting_configtext(
            'local_competency_report/success_threshold',
            get_string('success_threshold', 'local_competency_report'),
            get_string('success_threshold_desc', 'local_competency_report'),
            60,
            PARAM_INT
        ));
    }

    // At-Risk Student Alert Settings.
    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_heading(
            'local_competency_report/alerts_heading',
            get_string('enable_alerts', 'local_competency_report'),
            ''
        ));

        $settings->add(new admin_setting_configcheckbox(
            'local_competency_report/enable_alerts',
            get_string('enable_alerts', 'local_competency_report'),
            get_string('enable_alerts_desc', 'local_competency_report'),
            0
        ));

        $settings->add(new admin_setting_configtext(
            'local_competency_report/alert_threshold',
            get_string('alert_threshold', 'local_competency_report'),
            get_string('alert_threshold_desc', 'local_competency_report'),
            40,
            PARAM_INT
        ));
    }

    // 2. Add the Settings Page under "Local Plugins".
    $ADMIN->add('localplugins', $settings);



    // 3. Add External Report Pages under the "Reports" menu.
    $ADMIN->add('reports', new admin_externalpage(
        'local_competency_report_schoolreport',
        get_string('schoolreport', 'local_competency_report'),
        new moodle_url('/local/competency_report/school_report.php'),
        'moodle/site:config'
    ));

    $ADMIN->add('reports', new admin_externalpage(
        'local_competency_report_schoolpdf',
        get_string('schoolpdf', 'local_competency_report'),
        new moodle_url('/local/competency_report/school_pdf.php'),
        'moodle/site:config'
    ));
}
