<?php
/**
 * CLI Test Lab Runner for local_comp_report_ext and related 5 plugins.
 *
 * Usage from CLI inside Docker / Server:
 *   php local/comp_report_ext/tests/cli_test_lab.php
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/../ai.php');

echo "=========================================================\n";
echo " 🧪 SANAD COMPETENCY PLUGINS — AUTOMATED TEST LAB RUNNER \n";
echo "=========================================================\n\n";

$passcount = 0;
$failcount = 0;

function run_test($name, callable $testfunc) {
    global $passcount, $failcount;
    echo "• Testing: {$name} ... ";
    try {
        $result = $testfunc();
        if ($result === true || $result === null) {
            echo "[PASS]\n";
            $passcount++;
        } else {
            echo "[FAIL] — {$result}\n";
            $failcount++;
        }
    } catch (Throwable $e) {
        echo "[EXCEPTION] — " . $e->getMessage() . "\n";
        $failcount++;
    }
}

// -------------------------------------------------------------------
// 1. Assessment Setup Paths & Redirect URLs
// -------------------------------------------------------------------
run_test("Assessment Setup Form Action & Redirect URLs", function() {
    $url = new moodle_url('/local/comp_report_ext/assessment_setup.php', ['courseid' => 1]);
    if (strpos($url->out(), '/local/comp_report_ext/assessment_setup.php') === false) {
        return "URL mismatch: " . $url->out();
    }
    return true;
});

// -------------------------------------------------------------------
// 2. Curriculum & Context Details Builder for AI Engine
// -------------------------------------------------------------------
run_test("Curriculum & Context Details Builder (local_comp_report_ext_build_context_details)", function() {
    global $DB;
    $course = $DB->get_record('course', ['id' => 2]);
    if (!$course) {
        $course = $DB->get_record_sql("SELECT * FROM {course} WHERE id > 1 ORDER BY id ASC", [], IGNORE_MISSING);
    }
    $courseid = $course ? $course->id : 1;

    $details = local_comp_report_ext_build_context_details($courseid);
    if (!is_array($details)) {
        return "Result is not an array";
    }
    if (!isset($details['coursename'])) {
        return "Missing coursename key in context details";
    }
    return true;
});

// -------------------------------------------------------------------
// 3. Header Logo Path Resolution Helper
// -------------------------------------------------------------------
run_test("Header Logo Path Resolution (local_comp_report_ext_get_logo_path)", function() {
    $left  = local_comp_report_ext_get_logo_path('left');
    $right = local_comp_report_ext_get_logo_path('right');
    if (empty($left) || empty($right)) {
        return "Empty logo path returned";
    }
    return true;
});

// -------------------------------------------------------------------
// 4. Competency Calculator & Color Thresholds
// -------------------------------------------------------------------
run_test("Competency Calculator Color Thresholds (rate_color)", function() {
    if (\local_comp_report_ext\competency_calculator::rate_color(85) !== 'green') {
        return "85% should be green";
    }
    if (\local_comp_report_ext\competency_calculator::rate_color(65) !== 'blue') {
        return "65% should be blue";
    }
    if (\local_comp_report_ext\competency_calculator::rate_color(45) !== 'orange') {
        return "45% should be orange";
    }
    if (\local_comp_report_ext\competency_calculator::rate_color(20) !== 'red') {
        return "20% should be red";
    }
    return true;
});

// -------------------------------------------------------------------
// 5. Check 5 Plugins DB Tables & Capabilities
// -------------------------------------------------------------------
run_test("Verify Database Tables for 5 Plugins", function() {
    global $DB;
    $tables = [
        'local_comp_report_ext_asmt',
        'local_comp_report_ext_prac',
        'qbank_comp_ext_qmap'
    ];
    $dbmanager = $DB->get_manager();
    foreach ($tables as $table) {
        if (!$dbmanager->table_exists($table)) {
            return "Table {$table} does not exist in DB";
        }
    }
    return true;
});

// -------------------------------------------------------------------
// 6. AI Integration & Prompt Building Test
// -------------------------------------------------------------------
run_test("AI Comment Generator & Prompt Builder", function() {
    $rates = [
        'COMP101' => [
            'name' => 'Gas Turbine Operations',
            'percent' => 85,
            'passed' => true,
            'color' => 'green',
        ]
    ];
    $contextdetails = [
        'coursename' => 'Power Plant Engineering',
        'quizname' => 'Midterm Exam'
    ];
    $comment = local_comp_report_ext_generate_comment($rates, 'course_master', '', 'competency', $contextdetails);
    if (empty($comment)) {
        return "Empty comment returned from AI comment generator";
    }
    return true;
});

echo "\n---------------------------------------------------------\n";
echo " 📊 SUMMARY: Pass = {$passcount} | Fail = {$failcount}\n";
echo "=========================================================\n\n";

exit($failcount > 0 ? 1 : 0);
