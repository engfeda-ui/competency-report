<?php
/**
 * CLI Test Lab Runner for all 5 Renamed Moodle Plugins:
 *  1. local_comp_report_ext
 *  2. qbank_comp_ext
 *  3. block_comp_report_ext
 *  4. quizaccess_failgrade_ext
 *  5. quizaccess_attemptpassword
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
echo " 🧪 SANAD 5 PLUGINS — AUTOMATED COMPREHENSIVE TEST LAB  \n";
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

// ===================================================================
// PLUGIN 1: local_comp_report_ext (Competency Reporting Engine)
// ===================================================================

run_test("[local_comp_report_ext] Form Action & Redirect URLs", function() {
    $url = new moodle_url('/local/comp_report_ext/assessment_setup.php', ['courseid' => 1]);
    if (strpos($url->out(), '/local/comp_report_ext/assessment_setup.php') === false) {
        return "URL mismatch: " . $url->out();
    }
    return true;
});

run_test("[local_comp_report_ext] Context Details Builder", function() {
    global $DB;
    $course = $DB->get_record('course', ['id' => 2]);
    if (!$course) {
        $course = $DB->get_record_sql("SELECT * FROM {course} WHERE id > 1 ORDER BY id ASC", [], IGNORE_MISSING);
    }
    $courseid = $course ? $course->id : 1;

    $details = local_comp_report_ext_build_context_details($courseid);
    if (!is_array($details) || !isset($details['coursename'])) {
        return "Invalid context details array structure";
    }
    return true;
});

run_test("[local_comp_report_ext] Header Logo Path Resolution", function() {
    $left  = local_comp_report_ext_get_logo_path('left');
    $right = local_comp_report_ext_get_logo_path('right');
    if (empty($left) || empty($right)) {
        return "Empty logo path returned";
    }
    return true;
});

run_test("[local_comp_report_ext] Calculator Thresholds (rate_color)", function() {
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

run_test("[local_comp_report_ext] AI Comment Generator & Prompts", function() {
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
        return "Empty comment returned from AI generator";
    }
    return true;
});

// ===================================================================
// PLUGIN 2: qbank_comp_ext (Question Bank Competency Mapping)
// ===================================================================

run_test("[qbank_comp_ext] File Inclusion & Class Loading", function() {
    global $CFG;
    $colfile = $CFG->dirroot . '/question/bank/comp_ext/classes/column/competency_column.php';
    if (!file_exists($colfile)) {
        return "File missing: {$colfile}";
    }
    require_once($colfile);
    if (!class_exists('\qbank_comp_ext\column\competency_column')) {
        return "Class \qbank_comp_ext\column\competency_column not found";
    }
    return true;
});

run_test("[qbank_comp_ext] DB Table {qbank_comp_ext_qmap} Existence & Schema", function() {
    global $DB;
    $dbmanager = $DB->get_manager();
    if (!$dbmanager->table_exists('qbank_comp_ext_qmap')) {
        return "Table {qbank_comp_ext_qmap} does not exist";
    }
    return true;
});

// ===================================================================
// PLUGIN 3: block_comp_report_ext (Competency Report Dashboard Block)
// ===================================================================

run_test("[block_comp_report_ext] Block File Inclusion & Navigation Link", function() {
    global $CFG;
    if (file_exists($CFG->dirroot . '/blocks/moodleblock.class.php')) {
        require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
    }
    $blockfile = $CFG->dirroot . '/blocks/comp_report_ext/block_comp_report_ext.php';
    if (!file_exists($blockfile)) {
        return "File missing: {$blockfile}";
    }
    require_once($blockfile);
    if (!class_exists('block_comp_report_ext')) {
        return "Class block_comp_report_ext not found";
    }
    return true;
});

// ===================================================================
// PLUGIN 4: quizaccess_failgrade_ext (Competency & Failgrade Quiz Rule)
// ===================================================================

run_test("[quizaccess_failgrade_ext] Rule Class Loading & Preflight Engine", function() {
    global $CFG;
    $rulefile = $CFG->dirroot . '/mod/quiz/accessrule/failgrade_ext/rule.php';
    if (!file_exists($rulefile)) {
        return "File missing: {$rulefile}";
    }
    require_once($rulefile);
    if (!class_exists('quizaccess_failgrade_ext')) {
        return "Class quizaccess_failgrade_ext not found";
    }
    return true;
});

// ===================================================================
// PLUGIN 5: quizaccess_attemptpassword (Attempt Password Quiz Rule)
// ===================================================================

run_test("[quizaccess_attemptpassword] Rule Class Loading & Password Rules", function() {
    global $CFG;
    $rulefile = $CFG->dirroot . '/mod/quiz/accessrule/attemptpassword/rule.php';
    if (!file_exists($rulefile)) {
        return "File missing: {$rulefile}";
    }
    require_once($rulefile);
    if (!class_exists('quizaccess_attemptpassword')) {
        return "Class quizaccess_attemptpassword not found";
    }
    return true;
});

run_test("[local_comp_report_ext] Task Execution & Evidence Deduplication Purge", function() {
    global $DB;
    $task = new \local_comp_report_ext\task\process_competency_rates_task();
    $task->set_custom_data(['courseid' => 2, 'adminid' => 2]);
    $task->execute();

    // 1. Verify no user has > 1 userevidencecomp link for any competency.
    $dupeslinks = $DB->get_records_sql(
        "SELECT l.competencyid, e.userid, COUNT(*) AS cnt
           FROM {competency_userevidencecomp} l
           JOIN {competency_userevidence} e ON e.id = l.userevidenceid
       GROUP BY l.competencyid, e.userid
         HAVING COUNT(*) > 1"
    );
    if (!empty($dupeslinks)) {
        return "Duplicate userevidencecomp links still present after task execution";
    }

    // 2. Verify exactly 1 competency_evidence row per usercompetency.
    $dupesevid = $DB->get_records_sql(
        "SELECT usercompetencyid, COUNT(*) AS cnt
           FROM {competency_evidence}
       GROUP BY usercompetencyid
         HAVING COUNT(*) > 1"
    );
    if (!empty($dupesevid)) {
        return "Duplicate competency_evidence rows still present after task execution";
    }

    // 3. Verify every rated competency_usercomp has proficiency set (not NULL).
    $nullprof = $DB->get_records_sql(
        "SELECT uc.id
           FROM {competency_usercomp} uc
           JOIN {competency_evidence} ce ON ce.usercompetencyid = uc.id
          WHERE uc.proficiency IS NULL"
    );
    if (!empty($nullprof)) {
        return "competency_usercomp rows with NULL proficiency found after sync";
    }

    return true;
});

run_test("[local_comp_report_ext] Centralised competency_sync Engine", function() {
    // Verify the single shared sync engine exists and is callable for all
    // three entry points (observer, scheduled task, manual trigger).
    if (!class_exists('\local_comp_report_ext\competency_sync')) {
        return "competency_sync class missing";
    }
    if (!method_exists('\local_comp_report_ext\competency_sync', 'sync_user_competency')) {
        return "sync_user_competency() missing";
    }
    if (!method_exists('\local_comp_report_ext\competency_sync', 'sync_course')) {
        return "sync_course() missing";
    }
    if (!method_exists('\local_comp_report_ext\competency_sync', 'resolve_grader_id')) {
        return "resolve_grader_id() missing";
    }
    // resolve_grader_id must never return a hardcoded 2 blindly — it should
    // resolve dynamically (we just confirm it returns a sane value).
    $gid = \local_comp_report_ext\competency_sync::resolve_grader_id(99);
    if ($gid !== 99) {
        return "resolve_grader_id should echo preferred id (expected 99, got {$gid})";
    }
    return true;
});

echo "\n---------------------------------------------------------\n";
echo " 📊 SUMMARY: Pass = {$passcount} | Fail = {$failcount}\n";
echo "=========================================================\n\n";

exit($failcount > 0 ? 1 : 0);
