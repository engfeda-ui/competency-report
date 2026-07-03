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
 * English strings for local_competency_report plugin.
 *
 * @package    local_competency_report
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['action'] = 'Action';
$string['ai_failed'] = 'AI request failed.';
$string['ai_not_configured'] = 'AI Integration is enabled but settings are incomplete.';
$string['ai_prompt_school'] = 'Write a pedagogical analysis and development strategy for the school based on the following competency percentages:';
$string['ai_prompt_student'] = 'Write a brief pedagogical analysis for the student based on the following competency percentages:';
$string['ai_system_prompt'] = 'You are an educational assistant. Provide motivational and pedagogical feedback for students or schools.';
$string['ai_provider'] = 'AI Provider';
$string['ai_provider_desc'] = 'Choose between the official OpenAI Cloud service or a Local LLM (like Ollama or vLLM).';
$string['ai_provider_openai'] = 'OpenAI Cloud API';
$string['ai_provider_local'] = 'Local LLM (OpenAI-compatible API)';
$string['local_endpoint'] = 'Local LLM Endpoint URL';
$string['local_endpoint_desc'] = 'Enter the base URL for your local LLM server (e.g., http://localhost:11434/v1 for Ollama).';
$string['allcompetencies'] = 'All Competencies';
$string['alltime'] = 'All time';
$string['allusers'] = 'All students';
$string['analysisfor'] = 'Competency Analysis: {$a}';
$string['apikey'] = 'API Key';
$string['apikey_desc'] = 'Enter your OpenAI or Azure OpenAI API key. <a href="https://platform.openai.com/account/api-keys" target="_blank">Click here to get an OpenAI key</a>.';
$string['attempts'] = 'Attempts';
$string['averagescore'] = 'Average score';
$string['bluelegend'] = 'Blue: Mostly achieved (60-79%)';
$string['btn_process_now'] = 'Process Success Rates Now';
$string['classavg'] = 'Class Average';
$string['classinfo'] = 'Class: {$a}';
$string['classreport'] = 'Class Report';
$string['colorlegend'] = 'Color Legend:';
$string['comment'] = 'Comment';
$string['comment_blue'] = 'Mostly achieved topics: {$a}';
$string['comment_green'] = 'Fully achieved topics: {$a}';
$string['comment_orange'] = 'Partially achieved topics: {$a}';
$string['comment_red'] = 'Not yet achieved topics: {$a}';
$string['compareinfo'] = 'In this report, you can compare your performance with the overall course average and your class average.';
$string['competency'] = 'Competency';
$string['competencycode'] = 'Competency Code';
$string['competencyname'] = 'Competency / Skill';
$string['correct'] = 'Correct';
$string['correctcount'] = 'Correct count';
$string['courseavg'] = 'Course Average';
$string['creation_date'] = 'Creation date';
$string['enable_ai'] = 'Enable AI Integration';
$string['enable_ai_desc'] = 'Enable AI-driven pedagogical feedback. Requires API key and Model selection below.';
$string['error_no_enrolment'] = 'You are not enrolled in this course, so you cannot view this report.';
$string['evidence'] = 'Evidence';
$string['evidence_description'] = 'Success in competency {$a->competency}: %{$a->rate}';
$string['evidence_note'] = 'Success in competency {$a->competency}: %{$a->rate}';
$string['filter'] = 'Filter';
$string['filterlabel'] = 'Filter';
$string['generalcomment'] = 'General Comment';
$string['greenlegend'] = 'Green: Fully achieved (80%+)';
$string['groupcompetency'] = 'Group competency analysis';
$string['groupquizcompetency'] = 'Group quiz competency analysis';
$string['last30days'] = 'Last 30 days';
$string['last90days'] = 'Last 90 days';
$string['maxrows'] = 'Maximum Rows';
$string['maxrows_desc'] = 'The maximum number of rows displayed in tables.';
$string['model'] = 'Model';
$string['model_desc'] = 'Enter the model name (e.g., gpt-4).';
$string['myavg'] = 'My achievement';
$string['mycompetencies'] = 'My competency reports';
$string['mycompetencyexams'] = 'My competency exams';
$string['mycompetencystate'] = 'My competency state';
$string['myexamanalysis'] = 'My exam analysis';
$string['myreportcard'] = 'My Report Card';
$string['nocompetencies'] = 'No competencies found.';
$string['nocompetencyexamdata'] = 'No exam data found for this competency.';
$string['nodatafound'] = 'No completed exam data found for analysis in this course yet.';
$string['nodatastudentcompetency'] = 'No exam data found for this student in this competency.';
$string['noexamdata'] = 'No competency data found for this exam.';
$string['orangelegend'] = 'Orange: Partially achieved (40-59%)';
$string['pdfmystudent'] = '📄 Export PDF Report';
$string['pdfreport'] = '📄 PDF Report';
$string['pluginname'] = 'Competency Report System';
$string['privacy:metadata'] = 'The Competency Report plugin does not store any personal data.';
$string['privacy:metadata:openai:answertext'] = 'The student answer is sent to the AI model for evaluation.';
$string['privacy:metadata:openai:externalpurpose'] = 'The plugin sends question texts and user answers to the OpenAI API to provide AI feedback and competency analysis.';
$string['privacy:metadata:openai:questiontext'] = 'The question text is sent to provide context for AI analysis.';
$string['process_queued'] = 'Success rates task added to queue. It will complete in the background.';
$string['process_success_desc'] = 'This process calculates student success rates in quiz questions and adds them as competency evidence.';
$string['process_success_heading'] = 'Transfer Success Rates to Evidence';
$string['process_success_title'] = 'Background Success Rate Processing';
$string['manual_process_heading'] = 'Manual Competency Sync';
$string['manual_process_desc'] = 'Although competency success rates are synced automatically, you can trigger a manual sync for any course. Click <a href="{$a->url}">here to select a course and run sync now</a>.';
$string['select_course_process'] = 'Select course to process';
$string['select_course_option'] = 'Choose a course...';
$string['btn_select'] = 'Select';
$string['question'] = 'Question';
$string['questioncount'] = 'Question count';
$string['questionlinks'] = 'Linked questions details';
$string['questionname'] = 'Question title';
$string['quiz'] = 'Quiz';
$string['recordupdated'] = 'Record updated successfully';
$string['redlegend'] = 'Red: Not achieved (0-39%)';
$string['report_heading'] = 'Detailed Competency Analysis Report';
$string['report_title'] = 'Detailed Competency Report: {$a}';
$string['savechanges'] = 'Save changes';
$string['schoolpdf'] = 'School PDF Report';
$string['schoolpdfreport'] = 'School Overall Achievement Report';
$string['schoolreport'] = 'Overall School Report';
$string['searchcompetency'] = 'Search competency';
$string['searchquiz'] = 'Search quiz';
$string['searchuserorprept'] = 'Search student or report';
$string['selectcompetency'] = 'Select competency';
$string['selectgroup'] = 'Select group';
$string['selectquiz'] = 'Select quiz';
$string['selectstudent'] = 'Select student';
$string['selectuser'] = 'Select student';
$string['show'] = 'Show';
$string['structured_blue'] = '{$a->shortname}: Success rate %{$a->rate}. Mostly achieved. Recommendation: Review missing points.';
$string['structured_green'] = '{$a->shortname}: Success rate %{$a->rate}. Fully achieved. Recommendation: Move to advanced activities.';
$string['structured_orange'] = '{$a->shortname}: Success rate %{$a->rate}. Partially achieved. Recommendation: Practice more sample questions.';
$string['structured_red'] = '{$a->shortname}: Success rate %{$a->rate}. Insufficient progress yet. Recommendation: Review the topic and use extra resources.';
$string['student'] = 'Student';
$string['studentanalysis'] = 'My competency comparison report';
$string['studentavg'] = 'Student Average';
$string['studentclass'] = 'Competency analysis';
$string['studentcompetencydetail'] = 'Student competency details';
$string['studentcompetencyexams'] = 'Student competency exams';
$string['studentexam'] = 'My exam competency analysis';
$string['studentexamanalysis'] = 'Student exam analysis';
$string['studentpdfreport'] = 'Competency Report';
$string['studentreport'] = 'My competency report';
$string['success'] = 'Success';
$string['success_threshold'] = 'Success threshold';
$string['success_threshold_desc'] = 'Default success percentage for color coding.';
$string['successpercent'] = 'Success %';
$string['successrate'] = 'Success rate (%)';
$string['summaryreport'] = 'Competency success summary';
$string['teacherstudentcompetency'] = 'Student competency analysis';
$string['timeline'] = 'Timeline';
$string['timelineheading'] = 'Competency evolution over time';
$string['total'] = 'Total';
$string['user'] = 'Student';
$string['viewattempt'] = 'Review';
$string['visual_report'] = 'Visual Report';
$string['competency_report:manage'] = 'Manage question to competency links';
$string['competency_report:viewownreport'] = 'View own competency analysis report';
$string['competency_report:viewreports'] = 'View all student competency reports';

// AI Commentary and Premium UI strings.
$string['student_banner_title'] = '🎓 Student Competency Report';
$string['student_banner_desc'] = 'This is your competency profile showing your academic progress and achievements in this course.';
$string['teacher_banner_title'] = '👨‍🏫 Teacher Dashboard: Detailed Review';
$string['teacher_banner_desc'] = 'Review student competency achievements, overall exam results, and AI-powered pedagogical analysis.';
$string['ai_analysis_focus'] = 'AI Analysis Focus';
$string['ai_focus_competency'] = 'Competency Achievements';
$string['ai_focus_grades'] = 'Overall Grades & Exam Results';
$string['opt_instructions'] = 'Special Instructions (Optional):';
$string['custom_prompt_placeholder'] = 'Example: Keep it short, focus on weaknesses...';
$string['btn_generate_ai'] = 'Generate AI Analysis';
$string['exportpdf'] = 'Export PDF Report';

// Radar Gap Analysis Chart strings.
$string['radar_chart_title']   = '📊 Competency Gap Analysis — Your Profile vs Class Average';
$string['radar_chart_desc']    = 'The blue area represents your personal mastery level. The dotted gray line represents the class average. Areas where your profile dips below the gray line indicate competencies needing focus.';
$string['radar_legend_student'] = 'Your Performance';
$string['radar_legend_class']   = 'Class Average';

// AI Personal Study Plan strings.
$string['btn_studyplan']            = '📋 Generate Personal Study Plan';
$string['studyplan_title']          = '🎯 Personal Remedial Study Plan';
$string['studyplan_generating']     = 'Generating your personal study plan, please wait...';
$string['studyplan_error']          = 'Could not generate study plan. Please try again.';
$string['studyplan_language']       = 'Language';
$string['studyplan_desc']           = 'AI analyzes your weak competencies and generates a detailed, session-by-session remedial study plan—each session is 1 hour, distributed according to your study schedule.';
$string['studyplan_sessions_label'] = 'Number of Sessions';
$string['studyplan_sessions_unit']  = 'Session';
$string['studyplan_session_hint']   = 'Each session = 1 hour. Enter the number of available sessions in the student\'s schedule.';
$string['studyplan_session_hint_short'] = 'Hour';
$string['btn_studyplan_pdf']        = '📄 Export Study Plan as PDF';
$string['studyplan_pdf_title']      = 'AI Personal Study Plan';

// At-Risk Notification strings.
$string['enable_alerts']        = 'Enable At-Risk Student Alerts';
$string['enable_alerts_desc']   = 'When enabled, teachers receive an automatic notification when a student has 2 or more competencies below the alert threshold.';
$string['alert_threshold']      = 'At-Risk Alert Threshold (%)';
$string['alert_threshold_desc'] = 'Students with competency rates below this percentage (default: 40%) will trigger an alert for enrolled teachers.';
$string['alert_subject']        = '⚠️ At-Risk Student Alert: {$a}';
$string['alert_body']           = 'Dear Teacher,

This is an automated notification from the Competency Report System.

Student "{$a->student}" in course "{$a->course}" has 2 or more competencies below the alert threshold:

{$a->weaklist}
Please review the student\'s full report here:
{$a->url}

This notification was automatically sent upon exam submission.';
$string['messageprovider:studentatrisk'] = 'Student At-Risk Competency Alert';
$string['coursemasterreport'] = 'Comprehensive Course Master Report';
$string['coursemasterreport_desc'] = 'An integrated administrative report summarizing overall course statistics, exam grades, competency achievement, and group comparison.';
$string['group_comparison_grid'] = 'Group Competency Comparison Grid';
$string['exam_grades_summary'] = 'Overall Grades & Exams Summary';
$string['course_stats'] = 'Overall Course Statistics';
$string['studentdashboard'] = 'Student Performance Dashboard';
$string['groupperformance'] = 'Group Performance Analysis';
$string['tab_by_competency'] = 'By General Competencies';
$string['tab_by_quiz'] = 'By Quiz Competencies';

// ── Weighted Assessment System ──────────────────────────────────────────────
$string['assessmentsetup']             = 'Assessment Weight Setup';
$string['assessmentsaved']             = 'Assessment settings saved successfully.';
$string['assessmentdeleted']           = 'Assessment deleted.';
$string['configuredassessments']       = 'Configured Assessments';
$string['addnewassessment']            = 'Add New Assessment';
$string['addquizassessment']           = 'Add Quiz Assessment';
$string['addpracticalassessment']      = 'Add Practical Assessment';
$string['assessmentname']              = 'Assessment Name';
$string['assessmentnamepholder']       = 'e.g., Theory Quiz 1, Practical Exam';
$string['assessmenttype']              = 'Type';
$string['typequiz']                    = 'Quiz (Auto)';
$string['typepractical']               = 'Practical (Manual)';
$string['weight']                      = 'Weight';
$string['totalweight']                 = 'Total Weight';
$string['totalweightlabel']            = 'Total Weight';
$string['weighttotal_ok']              = '✅ Total weight is 100% - configuration is valid.';
$string['weightwarning']               = '⚠️ Total assessment weight is {$a}%. It should equal 100% for correct calculations.';
$string['assessmentweighthint']        = 'Total assessment weights must equal 100%. Each quiz or practical exam contributes its specified percentage to the final competency score.';
$string['noassessments']               = 'No assessments configured yet. Add a quiz or practical assessment below.';
$string['confirmdelete']               = 'Are you sure you want to delete this assessment? All associated practical grades will also be deleted.';
$string['updateassessments']           = 'Update Names & Weights';
$string['invaliddata']                 = 'Invalid data submitted.';

// Practical Entry.
$string['practicalentry']              = 'Practical Exam Entry';
$string['practicalsaved']              = 'Practical results saved successfully.';
$string['selectpracticalassessment']   = 'Select Practical Assessment';
$string['competencypercent']           = 'Competency Achievement (%)';
$string['showstudents']                = 'Show Students';
$string['enterstudentresults']         = 'Enter Student Results';
$string['nostudentsenrolled']          = 'No students enrolled in this course.';
$string['nopracticalassessments']      = 'No practical assessments configured for this course yet.';
$string['goassessmentsetup']           = 'Go to Assessment Setup';
$string['practicalentryhintsave']      = 'Leave field empty to skip a student. Saving will overwrite any previous result for that student.';

// Capability strings.
$string['competency_report:manageassessments'] = 'Configure assessment weights for competencies';
$string['competency_report:enterpractical']    = 'Enter practical exam results for students';

// ── Student Score Card (new) ─────────────────────────────────────────────────
$string['scorecard_title']             = '🎓 My Full Report Card';
$string['scorecard_desc']              = 'Comprehensive view of exam results and competency achievement, including how each exam contributed to each competency score.';
$string['scorecard_exams_heading']     = '📝 Exam Results';
$string['scorecard_exams_desc']        = 'Your grade in each assessment configured for this course.';
$string['scorecard_comp_heading']      = '🏆 Competency Breakdown';
$string['scorecard_comp_desc']         = 'Competency scores are calculated from a weighted combination of your exam results.';
$string['scorecard_exam_col_name']     = 'Assessment';
$string['scorecard_exam_col_type']     = 'Type';
$string['scorecard_exam_col_grade']    = 'Your Grade';
$string['scorecard_exam_col_max']      = 'Max Grade';
$string['scorecard_exam_col_pct']      = 'Score %';
$string['scorecard_exam_col_pass']     = 'Result';
$string['scorecard_exam_col_weight']   = 'Weight';
$string['scorecard_pass']              = '✅ Pass';
$string['scorecard_fail']              = '❌ Fail';
$string['scorecard_notsat']            = '— Not Sat';
$string['scorecard_comp_col_name']     = 'Competency';
$string['scorecard_comp_col_score']    = 'Weighted Score';
$string['scorecard_comp_col_passed']   = 'Status';
$string['scorecard_comp_col_detail']   = 'Assessment Detail';
$string['scorecard_breakdown_row']     = '{$a->name} ({$a->weight}%): {$a->score_pct}% ← contributes {$a->weighted_contribution}%';
$string['scorecard_nodata']            = 'No assessment data available yet. Complete a quiz or ask your teacher to enter your practical results.';
$string['scorecard_noweights']         = 'Assessment weights have not been configured for this course yet. Unweighted question averages are shown.';
$string['scorecard_practical']         = 'Practical';
$string['scorecard_quiz']              = 'Quiz';
$string['weighted_score']              = 'Weighted Score';
$string['contribution']                = 'Contribution';