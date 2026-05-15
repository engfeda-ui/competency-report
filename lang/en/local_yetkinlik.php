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
 * English strings for local_yetkinlik plugin.
 *
 * @package    local_yetkinlik
 * @copyright  2026 Hakan Ã‡iÄŸci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['action'] = 'Action';
$string['ai_failed'] = 'AI request failed.';
$string['ai_not_configured'] = 'AI integration is active but settings are incomplete.';
$string['ai_prompt_school'] = 'Write a pedagogical analysis and strategy for the school based on the following competency percentages:';
$string['ai_prompt_student'] = 'Write a short pedagogical analysis for the student based on the following competency percentages:';
$string['ai_system_prompt'] = 'You are an educational assistant. Provide motivational and pedagogical feedback for students or schools.';
$string['allcompetencies'] = 'All competencies';
$string['alltime'] = 'All time';
$string['allusers'] = 'All students';
$string['analysisfor'] = 'Competency Analysis: {$a}';
$string['apikey'] = 'API Key';
$string['apikey_desc'] = 'Enter your OpenAI or Azure OpenAI API key. <a href="https://platform.openai.com/account/api-keys" target="_blank">Click here for OpenAI key</a>.';
$string['bluelegend'] = 'Blue: Mostly achieved (60â€“79%)';
$string['btn_process_now'] = 'Process Success Rates Now';
$string['classavg'] = 'Class Average';
$string['classinfo'] = 'Class: {$a}';
$string['classreport'] = 'Class Report';
$string['colorlegend'] = 'Color Legend:';
$string['comment'] = 'Comment';
$string['comment_blue'] = 'Mostly learned topics: {$a}';
$string['comment_green'] = 'Fully learned topics: {$a}';
$string['comment_orange'] = 'Partially learned topics: {$a}';
$string['comment_red'] = 'Topics not yet achieved: {$a}';
$string['compareinfo'] = 'In this report, you can compare your own performance with the overall course average and your class average.';
$string['competency'] = 'Competency';
$string['competencycode'] = 'Competency Code';
$string['competencyname'] = 'Competency / Skill';
$string['correct'] = 'Correct';
$string['correctcount'] = 'Number of Correct';
$string['courseavg'] = 'Course Average';
$string['creation_date'] = 'Creation Date';
$string['enable_ai'] = 'Enable AI integration';
$string['enable_ai_desc'] = 'Enable AI-based pedagogical comments. API key and model selection are required below.';
$string['error_no_enrolment'] = 'You are not enrolled in this course, therefore you cannot view this report.';
$string['evidence'] = 'Evidence';
$string['evidence_description'] = 'Success for competency {$a->competency}: %{$a->rate}';
$string['evidence_note'] = 'Success for competency {$a->competency}: %{$a->rate}';
$string['filter'] = 'Filter';
$string['filterlabel'] = 'Filter';
$string['generalcomment'] = 'General Comment';
$string['greenlegend'] = 'Green: Fully achieved (80%+)';
$string['groupcompetency'] = 'Group Competency Analysis';
$string['groupquizcompetency'] = 'Group Quiz Competency Analysis';
$string['last30days'] = 'Last 30 days';
$string['last90days'] = 'Last 90 days';
$string['maxrows'] = 'Maximum rows';
$string['maxrows_desc'] = 'Maximum number of rows to display in tables.';
$string['model'] = 'Model';
$string['model_desc'] = 'Enter the model name (e.g., gpt-4).';
$string['myavg'] = 'My Achievement';
$string['mycompetencies'] = 'My Competency Reports';
$string['mycompetencyexams'] = 'My Competency Exams';
$string['mycompetencystate'] = 'My Competency State';
$string['myexamanalysis'] = 'My Exam Analysis';
$string['myreportcard'] = 'My Report Card';
$string['nocompetencies'] = 'No competency.';
$string['nocompetencyexamdata'] = 'No exam data found for this competency.';
$string['nodatafound'] = 'No completed quiz data found for analysis in this course yet.';
$string['nodatastudentcompetency'] = 'No quiz data found for this student in this competency.';
$string['noexamdata'] = 'No competency data found for this exam.';
$string['orangelegend'] = 'Orange: Partially achieved (40â€“59%)';
$string['pdfmystudent'] = 'ðŸ“„ View My PDF Report';
$string['pdfreport'] = 'ðŸ“„ PDF Report';
$string['pluginname'] = 'Competency Plugin';
$string['privacy:metadata'] = 'The Yetkinlik plugin does not store any personal data.';
$string['privacy:metadata:openai:answertext'] = 'The student\'s response is sent to be evaluated by the AI model.';
$string['privacy:metadata:openai:externalpurpose'] = 'The plugin sends question texts and user responses to the OpenAI API to provide AI-generated feedback and competency analysis.';
$string['privacy:metadata:openai:questiontext'] = 'The text of the question is sent to provide context for the AI analysis.';
$string['process_queued'] = 'The success rate calculation task has been queued. It will be completed in the background.';
$string['process_success_desc'] = 'This process calculates students\' success percentages in quiz questions and adds them as competency evidence.';
$string['process_success_heading'] = 'Transfer Percentage Success to Evidence';
$string['process_success_title'] = 'Process Success Rates in Background';
$string['question'] = 'Question';
$string['questioncount'] = 'Number of Questions';
$string['questionlinks'] = 'Related Question Details';
$string['questionname'] = 'Question Title';
$string['quiz'] = 'Quiz';
$string['recordupdated'] = 'Record updated successfully';
$string['redlegend'] = 'Red: Not achieved (0â€“39%)';
$string['report_heading'] = 'Competency Analysis Detailed Report';
$string['report_title'] = 'Detailed Competency Report';
$string['savechanges'] = 'Save changes';
$string['schoolpdf'] = 'School PDF Report';
$string['schoolpdfreport'] = 'School General Achievement Report';
$string['schoolreport'] = 'School General Report';
$string['searchcompetency'] = 'Search competency';
$string['searchquiz'] = 'Search quiz';
$string['searchuserorprept'] = 'Search student or report';
$string['selectcompetency'] = 'Select competency';
$string['selectgroup'] = 'Select group';
$string['selectquiz'] = 'Select exam';
$string['selectstudent'] = 'Select student';
$string['selectuser'] = 'Select student';
$string['show'] = 'Show';
$string['structured_blue'] = '{$a->shortname}: Success rate %{$a->rate}. Mostly learned. Recommendation: reinforce missing points.';
$string['structured_green'] = '{$a->shortname}: Success rate %{$a->rate}. Fully learned. Recommendation: move to advanced activities.';
$string['structured_orange'] = '{$a->shortname}: Success rate %{$a->rate}. Partially learned. Recommendation: practice more sample questions.';
$string['structured_red'] = '{$a->shortname}: Success rate %{$a->rate}. Not enough progress yet. Recommendation: review and use extra resources.';
$string['student'] = 'Student';
$string['studentanalysis'] = 'My Competency Comparison Report';
$string['studentavg'] = 'Student average';
$string['studentclass'] = 'Competency Analysis';
$string['studentcompetencydetail'] = 'Student Competency Detail';
$string['studentcompetencyexams'] = 'Student Competency Exams';
$string['studentexam'] = 'My Exam Competency Analysis';
$string['studentexamanalysis'] = 'Student Exam Analysis';
$string['studentpdfreport'] = 'Competency Report';
$string['studentreport'] = 'My Competency Report';
$string['success'] = 'Success';
$string['success_threshold'] = 'Success threshold';
$string['success_threshold_desc'] = 'Default success percentage for color coding.';
$string['successpercent'] = 'Success %';
$string['successrate'] = 'Success Rate (%)';
$string['summaryreport'] = 'Competency Success Summary';
$string['teacherstudentcompetency'] = 'Student Competency Analysis';
$string['timeline'] = 'Timeline';
$string['timelineheading'] = 'Competency Progress Over Time';
$string['total'] = 'TOTAL';
$string['user'] = 'Student';
$string['viewattempt'] = 'Review';
$string['visual_report'] = 'Visual report';
$string['yetkinlik:manage'] = 'Manage question-competency mappings';
$string['yetkinlik:viewownreport'] = 'View own competency analysis report';
$string['yetkinlik:viewreports'] = 'View all student competency reports';
