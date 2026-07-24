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
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @copyright  based on work by 2026 Hakan Ã‡iÄŸci {@link https://hakancigci.com.tr}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // 1. Define the Settings Page.
    $settings = new admin_settingpage('local_comp_report_ext', get_string('pluginname', 'local_comp_report_ext'));

    if ($ADMIN->fulltree) {
        // AI integration toggle (enable/disable).
        $settings->add(new admin_setting_configcheckbox(
            'local_comp_report_ext/enable_ai',
            get_string('enable_ai', 'local_comp_report_ext'),
            get_string('enable_ai_desc', 'local_comp_report_ext'),
            0
        ));

        // AI Provider.
        $settings->add(new admin_setting_configselect(
            'local_comp_report_ext/ai_provider',
            get_string('ai_provider', 'local_comp_report_ext'),
            get_string('ai_provider_desc', 'local_comp_report_ext'),
            'openai',
            [
                'openai'     => get_string('ai_provider_openai', 'local_comp_report_ext'),
                'openrouter' => get_string('ai_provider_openrouter', 'local_comp_report_ext'),
                'deepseek'   => get_string('ai_provider_deepseek', 'local_comp_report_ext'),
                'groq'       => get_string('ai_provider_groq', 'local_comp_report_ext'),
                'local'      => get_string('ai_provider_local', 'local_comp_report_ext'),
            ]
        ));

        // Local LLM Endpoint.
        $settings->add(new admin_setting_configtext(
            'local_comp_report_ext/local_endpoint',
            get_string('local_endpoint', 'local_comp_report_ext'),
            get_string('local_endpoint_desc', 'local_comp_report_ext'),
            'http://localhost:11434/v1',
            PARAM_RAW
        ));


        // API Key.
        $settings->add(new admin_setting_configtext(
            'local_comp_report_ext/apikey',
            get_string('apikey', 'local_comp_report_ext'),
            get_string('apikey_desc', 'local_comp_report_ext'),
            '',
            PARAM_TEXT
        ));

        // Model name.
        $settings->add(new admin_setting_configtext(
            'local_comp_report_ext/model',
            get_string('model', 'local_comp_report_ext'),
            get_string('model_desc', 'local_comp_report_ext'),
            'gpt-4',
            PARAM_RAW
        ));

        // Maximum number of rows.
        $settings->add(new admin_setting_configtext(
            'local_comp_report_ext/maxrows',
            get_string('maxrows', 'local_comp_report_ext'),
            get_string('maxrows_desc', 'local_comp_report_ext'),
            100,
            PARAM_INT
        ));

        // Success threshold for competency colour coding and failgrade integration.
        $settings->add(new admin_setting_configtext(
            'local_comp_report_ext/success_threshold',
            get_string('success_threshold', 'local_comp_report_ext'),
            get_string('success_threshold_desc', 'local_comp_report_ext'),
            60,
            PARAM_INT
        ));

        // PDF Header Logo Settings.
        $settings->add(new admin_setting_heading(
            'local_comp_report_ext/pdf_logo_heading',
            get_string('pdf_logo_heading', 'local_comp_report_ext'),
            get_string('pdf_logo_heading_desc', 'local_comp_report_ext')
        ));

        // Left logo stored file.
        $settings->add(new admin_setting_configstoredfile(
            'local_comp_report_ext/logo_left',
            get_string('logo_left', 'local_comp_report_ext'),
            get_string('logo_left_desc', 'local_comp_report_ext'),
            'logo_left',
            0,
            ['maxfiles' => 1, 'accepted_types' => ['image']]
        ));

        // Left logo URL / path fallback.
        $settings->add(new admin_setting_configtext(
            'local_comp_report_ext/logo_left_url',
            get_string('logo_left_url', 'local_comp_report_ext'),
            get_string('logo_left_url_desc', 'local_comp_report_ext'),
            '',
            PARAM_RAW
        ));

        // Right logo stored file.
        $settings->add(new admin_setting_configstoredfile(
            'local_comp_report_ext/logo_right',
            get_string('logo_right', 'local_comp_report_ext'),
            get_string('logo_right_desc', 'local_comp_report_ext'),
            'logo_right',
            0,
            ['maxfiles' => 1, 'accepted_types' => ['image']]
        ));

        // Right logo URL / path fallback.
        $settings->add(new admin_setting_configtext(
            'local_comp_report_ext/logo_right_url',
            get_string('logo_right_url', 'local_comp_report_ext'),
            get_string('logo_right_url_desc', 'local_comp_report_ext'),
            '',
            PARAM_RAW
        ));
    }

    // At-Risk Student Alert Settings.
    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_heading(
            'local_comp_report_ext/alerts_heading',
            get_string('enable_alerts', 'local_comp_report_ext'),
            ''
        ));

        $settings->add(new admin_setting_configcheckbox(
            'local_comp_report_ext/enable_alerts',
            get_string('enable_alerts', 'local_comp_report_ext'),
            get_string('enable_alerts_desc', 'local_comp_report_ext'),
            0
        ));

        $settings->add(new admin_setting_configtext(
            'local_comp_report_ext/alert_threshold',
            get_string('alert_threshold', 'local_comp_report_ext'),
            get_string('alert_threshold_desc', 'local_comp_report_ext'),
            40,
            PARAM_INT
        ));

        // Manual Competency Sync heading and link.
        $url = new moodle_url('/local/comp_report_ext/add_success_to_evidence.php');
        $settings->add(new admin_setting_heading(
            'local_comp_report_ext/manual_process_heading',
            get_string('manual_process_heading', 'local_comp_report_ext'),
            get_string('manual_process_desc', 'local_comp_report_ext', ['url' => $url->out()])
        ));
    }

    // 2. Add the Settings Page under "Local Plugins".
    $ADMIN->add('localplugins', $settings);

    // 3. Add External Report Pages under the "Reports" menu.
    $ADMIN->add('reports', new admin_externalpage(
        'local_comp_report_ext_schoolreport',
        get_string('schoolreport', 'local_comp_report_ext'),
        new moodle_url('/local/comp_report_ext/school_report.php'),
        'moodle/site:config'
    ));

    $ADMIN->add('reports', new admin_externalpage(
        'local_comp_report_ext_schoolpdf',
        get_string('schoolpdf', 'local_comp_report_ext'),
        new moodle_url('/local/comp_report_ext/school_pdf.php'),
        'moodle/site:config'
    ));
}
