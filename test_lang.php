<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/config.php');
echo "pluginname: " . get_string('pluginname', 'local_comp_report_ext') . "\n";
echo "enable_ai: " . get_string('enable_ai', 'local_comp_report_ext') . "\n";
echo "ai_provider_openrouter: " . get_string('ai_provider_openrouter', 'local_comp_report_ext') . "\n";
