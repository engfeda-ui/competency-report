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
 * English strings for local_comp_report_ext plugin.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @copyright  based on work by 2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['action'] = 'Action';
$string['averagegrade'] = 'Average Score';
$string['participantcount'] = 'Participating Students';
$string['ai_failed'] = 'AI request failed.';
$string['ai_not_configured'] = 'AI integration is active but settings are incomplete.';
$string['ai_prompt_school'] = 'Write a pedagogical analysis and strategy for the school based on the following competency percentages:';
$string['ai_prompt_student'] = 'Write a short pedagogical analysis for the student based on the following competency percentages:';
$string['ai_system_prompt'] = 'You are an educational assistant. Provide motivational and pedagogical feedback for students or schools.';
$string['ai_provider'] = 'AI Provider';
$string['ai_provider_desc'] = 'Select whether to use the official OpenAI cloud service or a locally hosted LLM (like Ollama or vLLM).';
$string['ai_provider_openai'] = 'OpenAI Cloud API';
$string['ai_provider_local'] = 'Local LLM (OpenAI-compatible API)';
$string['local_endpoint'] = 'Local LLM Endpoint URL';
$string['local_endpoint_desc'] = 'Enter the base URL of your locally running LLM server (e.g. http://localhost:11434/v1 for Ollama).';
$string['allcompetencies'] = 'All competencies';
$string['alltime'] = 'All time';
$string['allusers'] = 'All students';
$string['analysisfor'] = 'Competency Analysis: {$a}';
$string['apikey'] = 'API Key';
$string['apikey_desc'] = 'Enter your OpenAI or Azure OpenAI API key. <a href="https://platform.openai.com/account/api-keys" target="_blank">Click here for OpenAI key</a>.';
$string['bluelegend'] = 'Blue: Mostly achieved (60–79%)';
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
$string['orangelegend'] = 'Orange: Partially achieved (40–59%)';
$string['pdfmystudent'] = '📄 View My PDF Report';
$string['pdfreport'] = '📄 PDF Report';
$string['pluginname'] = 'Competency Plugin';
$string['privacy:metadata'] = 'The Competency Report plugin does not store any personal data.';
$string['privacy:metadata:openai:answertext'] = 'The student\'s response is sent to be evaluated by the AI model.';
$string['privacy:metadata:openai:externalpurpose'] = 'The plugin sends question texts and user responses to the OpenAI API to provide AI-generated feedback and competency analysis.';
$string['privacy:metadata:openai:questiontext'] = 'The text of the question is sent to provide context for the AI analysis.';
$string['process_queued'] = 'The success rate calculation task has been queued. It will be completed in the background.';
$string['process_success_desc'] = 'This process calculates students\' success percentages in quiz questions and adds them as competency evidence.';
$string['process_success_heading'] = 'Transfer Percentage Success to Evidence';
$string['process_success_title'] = 'Process Success Rates in Background';
$string['manual_process_heading'] = 'Manual Competency Sync';
$string['manual_process_desc'] = 'Although competency success rates are synchronized automatically, you can trigger a manual sync for any course. Click <a href="{$a->url}">here to select a course and run the sync now</a>.';
$string['select_course_process'] = 'Select Course to Process';
$string['select_course_option'] = 'Choose a course...';
$string['btn_select'] = 'Select';
$string['question'] = 'Question';
$string['questioncount'] = 'Number of Questions';
$string['questionlinks'] = 'Related Question Details';
$string['questionname'] = 'Question Title';
$string['quiz'] = 'Quiz';
$string['recordupdated'] = 'Record updated successfully';
$string['redlegend'] = 'Red: Not achieved (0–39%)';
$string['report_heading'] = 'Competency Analysis Detailed Report';
$string['report_title'] = 'Detailed Competency Report: {$a}';
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
$string['competency_report:manage'] = 'Manage question-competency mappings';
$string['competency_report:viewownreport'] = 'View own competency analysis report';
$string['competency_report:viewreports'] = 'View all student competency reports';
$string['competency_report:manageassessments'] = 'Manage assessment weights and setup';
$string['competency_report:enterpractical'] = 'Enter practical exam results for students';

// AI Commentary and Premium UI strings.
$string['student_banner_title'] = '🎓 Student Competency Report';
$string['student_banner_desc'] = 'This is your personal competency profile showing your academic progress and achievements in this course.';
$string['teacher_banner_title'] = '👨‍🏫 Teacher Dashboard: Detailed Review';
$string['teacher_banner_desc'] = 'Review individual student competency achievements, general quiz results, and direct AI-powered pedagogical analysis.';
$string['ai_analysis_focus'] = 'AI Analysis Focus';
$string['ai_focus_competency'] = 'Competency Achievements';
$string['ai_focus_grades'] = 'General Grades & Exam Results';
$string['opt_instructions'] = 'Special Instructions (Optional):';
$string['custom_prompt_placeholder'] = 'e.g. Write in English, keep it extremely short, focus on weaknesses...';
$string['btn_generate_ai'] = 'Generate AI Analysis';
$string['exportpdf'] = 'Export PDF Report';

// PDF Header Logo Settings.
$string['pdf_logo_heading'] = 'PDF Report Logo Settings';
$string['pdf_logo_heading_desc'] = 'Configure logos to appear in the top header of all exported PDF reports.';
$string['logo_left'] = 'Header Logo (Left)';
$string['logo_left_desc'] = 'Upload an image file (PNG/JPG) to appear on the top-left of PDF reports.';
$string['logo_left_url'] = 'Header Logo (Left) URL / Path';
$string['logo_left_url_desc'] = 'Alternative: Enter a full image URL or server path for the left logo.';
$string['logo_right'] = 'Header Logo (Right)';
$string['logo_right_desc'] = 'Upload an image file (PNG/JPG) to appear on the top-right of PDF reports.';
$string['logo_right_url'] = 'Header Logo (Right) URL / Path';
$string['logo_right_url_desc'] = 'Alternative: Enter a full image URL or server path for the right logo.';

// Radar Gap Analysis Chart strings.
$string['radar_chart_title']   = '📊 Competency Gap Analysis — Your Profile vs Class Average';
$string['radar_chart_desc']    = 'The blue area shows your personal mastery level. The grey dotted line shows the class average. Areas where your profile dips below the grey line indicate competencies to focus on.';
$string['radar_legend_student'] = 'Your Performance';
$string['radar_legend_class']   = 'Class Average';

// AI Personalized Study Plan strings.
$string['btn_studyplan']           = '📋 Generate My Personalized Study Plan';
$string['studyplan_title']         = '🎯 Your Personalized Remedial Study Plan';
$string['studyplan_generating']    = 'Generating your personalized study plan, please wait…';
$string['studyplan_error']         = 'Could not generate the study plan. Please try again.';
$string['studyplan_language']      = 'Language';
$string['studyplan_desc']          = 'AI analyses your weak competencies and generates a personalised, session-by-session remedial plan — each session is 1 hour, scheduled to fit your timetable.';
$string['studyplan_sessions_label'] = 'Number of Sessions';
$string['studyplan_sessions_unit'] = 'sessions';
$string['studyplan_session_hint']  = 'Each session = 1 hour. Enter how many sessions are available in the student\'s timetable.';
$string['studyplan_session_hint_short'] = 'hour';
$string['btn_studyplan_pdf']       = '📄 Export Study Plan as PDF';
$string['studyplan_pdf_title']     = 'AI Personalized Study Plan';

// At-Risk Notification strings.
$string['enable_alerts']        = 'Enable At-Risk Student Alerts';
$string['enable_alerts_desc']   = 'When enabled, teachers receive an automatic notification when a student has 2 or more competencies below the alert threshold.';
$string['alert_threshold']      = 'At-Risk Alert Threshold (%)';
$string['alert_threshold_desc'] = 'Students with competency rates below this percentage (default: 40%) will trigger an alert to enrolled teachers.';
$string['alert_subject']        = '⚠️ At-Risk Student Alert: {$a}';
$string['alert_body']           = 'Dear Teacher,

This is an automated alert from the Competency Report system.

Student "{$a->student}" in the course "{$a->course}" has 2 or more competencies below the alert threshold:

{$a->weaklist}
Please review the student\'s full report here:
{$a->url}

This notification was sent automatically upon quiz submission.';
$string['messageprovider:studentatrisk'] = 'At-risk student competency alert';
$string['coursemasterreport'] = 'Unified Course Master Report';
$string['coursemasterreport_desc'] = 'Comprehensive administrative report aggregating overall course stats, exam grades, competency mastery, and group comparisons.';
$string['group_comparison_grid'] = 'Group Competency Comparison Grid';
$string['exam_grades_summary'] = 'Exams & General Grades Summary';
$string['course_stats'] = 'Overall Course Statistics';
$string['studentdashboard'] = 'Student Performance Dashboard';
$string['groupperformance'] = 'Group Performance Analysis';
$string['tab_by_competency'] = 'By Course Competency';
$string['tab_by_quiz'] = 'By Exam/Quiz';
$string['assessmentsetup'] = 'Assessment Setup';
$string['practicalentry'] = 'Practical Exam Entry';
$string['addpracticalassessment'] = 'Add Practical Assessment';
$string['addquizassessment'] = 'Add Quiz Assessment';
$string['assessmentdeleted'] = 'Assessment deleted successfully';
$string['assessmentname'] = 'Assessment Name';
$string['assessmentsaved'] = 'Assessment saved successfully';
$string['assessmenttype'] = 'Assessment Type';
$string['competencypercent'] = 'Competency Percentage';
$string['invaliddata'] = 'Invalid data provided';
$string['nopracticalassessments'] = 'No practical assessments configured for this course yet.';
$string['nostudentsenrolled'] = 'No students enrolled in this course.';
$string['practicalsaved'] = 'Practical marks saved successfully';
$string['selectpracticalassessment'] = 'Select Practical Assessment...';
$string['showstudents'] = 'Show Students';
$string['totalweight'] = 'Total Weight';
$string['typepractical'] = 'Practical Assessment';
$string['typequiz'] = 'Quiz Assessment';
$string['weight'] = 'Weight (%)';
$string['weightwarning'] = 'Warning: The total weight of all assessments must equal 100%. Currently it is {$a}%.';
$string['addnewassessment'] = 'Add New Assessment';
$string['assessmentnamepholder'] = 'e.g. Midterm Practical Exam';
$string['assessmentweighthint'] = 'Choose an activity (Quiz or Assignment) and specify its weight in the overall competency grade.';
$string['configuredassessments'] = 'Configured Assessments';
$string['confirmdelete'] = 'Are you sure you want to delete this assessment? All associated marks will be removed.';
$string['enterstudentresults'] = 'Enter Student Marks';
$string['goassessmentsetup'] = 'Go to Assessment Setup';
$string['noassessments'] = 'No assessments configured for this course yet.';
$string['practicalentryhintsave'] = 'Enter achievements as percentage (0-100) per student. Remember to save changes.';
$string['totalweightlabel'] = 'Total Weight';
$string['updateassessments'] = 'Save Weights & Changes';
$string['weighttotal_ok'] = 'The total weight of all assessments is exactly 100%. Everything is set correctly.';

// Admin settings strings.
$string['enable_ai'] = 'Enable AI integration';
$string['enable_ai_desc'] = 'Toggle AI-powered feedback on or off.';
$string['model_desc'] = 'Model name (e.g., gpt-4, gpt-4o, llama3.1).';
$string['maxrows'] = 'Maximum Rows';
$string['maxrows_desc'] = 'Maximum rows shown in report tables.';
$string['success_threshold'] = 'Success Threshold (%)';
$string['success_threshold_desc'] = 'Minimum percentage for competency mastery (default: 60%).';
$string['pdf_logo_heading'] = 'PDF Report Logo Settings';
$string['pdf_logo_heading_desc'] = 'Configure left and right logos displayed in top header of PDF exports.';
$string['logo_left'] = 'Header Logo (Left)';
$string['logo_left_desc'] = 'Upload an image file (PNG/JPG) for the left side of the PDF header.';
$string['logo_left_url'] = 'Left Logo URL / Path';
$string['logo_left_url_desc'] = 'Alternative direct image URL or path for the left logo.';
$string['logo_right'] = 'Header Logo (Right)';
$string['logo_right_desc'] = 'Upload an image file (PNG/JPG) for the right side of the PDF header.';
$string['logo_right_url'] = 'Right Logo URL / Path';
$string['logo_right_url_desc'] = 'Alternative direct image URL or path for the right logo.';
