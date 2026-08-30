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
$string['ai_provider_desc'] = 'Select your preferred AI provider (OpenAI, OpenRouter, DeepSeek, Groq) or specify a custom OpenAI-compatible endpoint URL.';
$string['ai_provider_openai'] = 'OpenAI Cloud API (api.openai.com)';
$string['ai_provider_openrouter'] = 'OpenRouter API (openrouter.ai)';
$string['ai_provider_deepseek'] = 'DeepSeek API (api.deepseek.com)';
$string['ai_provider_groq'] = 'Groq Cloud API (api.groq.com)';
$string['ai_provider_local'] = 'Custom Endpoint / Local LLM (Ollama, vLLM, LM Studio, etc.)';
$string['local_endpoint'] = 'Custom / Local LLM Endpoint URL';
$string['local_endpoint_desc'] = 'Enter the base URL of your custom API or local LLM server (e.g. http://localhost:11434/v1 for Ollama, https://openrouter.ai/api/v1 for OpenRouter).';
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
$string['grade'] = 'Grade';
$string['user'] = 'Student';
$string['viewattempt'] = 'Review';
$string['visual_report'] = 'Visual report';
$string['comp_report_ext:manage'] = 'Manage question-competency mappings';
$string['comp_report_ext:viewownreport'] = 'View own competency analysis report';
$string['comp_report_ext:viewreports'] = 'View all student competency reports';
$string['comp_report_ext:manageassessments'] = 'Manage assessment weights and setup';
$string['comp_report_ext:enterpractical'] = 'Enter practical exam results for students';
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
$string['printreport'] = 'Print Report';

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

// Missing Cache and Privacy strings.
$string['questions_abbr'] = 'Qs';
$string['cachedef_ai_feedback'] = 'AI Feedback Cache';
$string['custominstructionsdesc'] = 'Write custom instructions to guide the AI report (e.g. language, length, focus).';
$string['generalgradesreportgroup'] = 'General Grades Report - Group: {$a}';
$string['privacy:metadata:local_comp_report_ext_prac'] = 'Stores student practical exam competency performance entries entered by trainers.';
$string['privacy:metadata:local_comp_report_ext_prac:studentid'] = 'The user ID of the student being evaluated.';
$string['privacy:metadata:local_comp_report_ext_prac:trainerid'] = 'The user ID of the trainer entering the result.';
$string['privacy:metadata:local_comp_report_ext_prac:competency_percent'] = 'The competency achievement percentage score.';

// Group Assessment Distribution Report strings.
$string['groupassessmentdistribution']     = 'Competency Distribution by Group & Assessment';
$string['tab_assessment_distribution']     = 'By Assessment Weights';
$string['selectassessments']               = 'Select Assessments';
$string['allgroups']                       = 'All Groups';
$string['allquizzes']                      = 'All Exams / Quizzes';
$string['score_distribution_histogram']     = 'Overall Score Distribution Histogram';
$string['group']                           = 'Group';
$string['weightedtotal']                   = 'Weighted Total';
$string['assessmentheader']                = '{$a->name} ({$a->weight}%)';
$string['noassessmentsconfigured']         = 'No weighted assessments have been configured for this course yet. Please use Assessment Setup first.';
$string['groupassessmentdistribution_pdf'] = 'Export Assessment Distribution PDF';
$string['group_analytics_dashboard'] = 'Group Analytics Dashboard';
$string['tab_group_analytics'] = 'Analytics by Competency';
$string['tab_analytics_competency'] = 'Analytics by Competency';
$string['tab_analytics_grades']     = 'Analytics by Grades';
$string['group_exam_analytics']     = 'Group Exam & Grade Analytics';
$string['exam_grade_histogram']     = 'Overall Raw Exam Score Histogram';
$string['academic_performance_tiers'] = 'Academic Grade Performance Tiers';
$string['question_item_difficulty'] = 'Psychometric Item Difficulty Index (p-value)';
$string['question_item_discrimination'] = 'Psychometric Item Discrimination Index';
$string['grade_tier_failed'] = 'At-Risk / Failed (< 60%)';
$string['grade_tier_passing'] = 'Satisfactory (60–74%)';
$string['grade_tier_verygood'] = 'Very Good (75–89%)';
$string['grade_tier_outstanding'] = 'Outstanding (90–100%)';
$string['average_mastery_rate'] = 'Average Mastery Rate';
$string['remediation_rate'] = 'Students Requiring Remediation';
$string['top_strength'] = 'Top Strength';
$string['critical_gap'] = 'Critical Skill Gap';
$string['critical_skill_gap'] = 'Critical Skill Gap';
$string['competency_mastery_radar'] = 'Competency Mastery Radar';
$string['mastery_distribution'] = 'Mastery Distribution';
$string['learning_progress_curve'] = 'Learning Progress Curve';
$string['theory_vs_practice'] = 'Theory vs. Practice Gap';
$string['critical_tier'] = 'Critical (< 40%)';
$string['developing_tier'] = 'Developing (40-59%)';
$string['proficient_tier'] = 'Proficient (60-79%)';
$string['exemplary_tier'] = 'Exemplary (80-100%)';
$string['no_data_dashboard'] = 'No student assessment data found for this group to generate dashboard analytics.';
$string['group_analytics_dashboard_pdf'] = 'Export Analytics Dashboard PDF';
$string['exam_analytics_section'] = 'Final Exam Psychometric & Grade Analytics';
$string['exam_avg_score'] = 'Exam Average Grade';
$string['exam_pass_rate_label'] = 'Exam Pass Rate';
$string['exam_highest_score'] = 'Highest Score';
$string['exam_lowest_score'] = 'Lowest Score';
$string['exam_grade_distribution'] = 'Grade Distribution Frequency Histogram';
$string['exam_pass_fail_ratio'] = 'Pass vs Fail Ratio';
$string['exam_item_difficulty'] = 'Question Difficulty Index (p-value)';
$string['exam_item_discrimination'] = 'Question Discrimination Index (Top 27% vs Bottom 27%)';
$string['student_count'] = 'Student Count';
$string['passed'] = 'Passed';
$string['failed'] = 'Failed';
$string['average_score_pct'] = 'Average Score (%)';
$string['top_performers'] = 'Top Performers';
$string['bottom_performers'] = 'Bottom Performers';
$string['autodetect'] = 'Auto Detect (Default)';
$string['generalgradesreportcourse'] = 'General Grades Report - Course: {$a}';
$string['detailedreportgroup']        = 'Detailed Competency Report - Group: {$a}';
$string['detailedreportcourse']       = 'Detailed Competency Report - Course: {$a}';
$string['subjectcourse']              = 'Subject / Course: {$a}';
$string['groupclass']                 = 'Group / Class: {$a}';
$string['generalgradescard']          = 'General Grades and Academic Performance Card';
$string['quizexamname']               = 'Quiz / Exam Name';
$string['scoreachieved']              = 'Score achieved';
$string['aicommentarytitle']          = 'Pedagogical AI Analysis Commentary';
$string['attempt_1']                = 'Attempt 1 (Original)';
$string['retake_1']                 = 'Retake 1';
$string['retake_2']                 = 'Retake 2';
$string['retakes_count']            = 'Retakes';
$string['final_recorded_grade']     = 'Final Recorded Grade';
$string['passed_first_attempt']     = 'Passed (1st Attempt)';
$string['passed_retake_1']          = 'Passed Retake 1 (60% Cap)';
$string['passed_retake_2']          = 'Passed Retake 2 (60% Cap)';
$string['failed_status']            = 'At-Risk / Failed (< 60%)';
$string['exam_results_title']       = 'Course Examinations & Quiz Performance';
$string['exam_results_desc']        = 'Individual score breakdown across all course examinations, retake attempts, and final recorded grades.';
$string['no_exams_taken']           = 'No finished exam attempts recorded for this student in this course.';
$string['status']                   = 'Status';
$string['competency_matrix_title']  = 'Competency Mastery by Examination';
$string['competency_matrix_desc']   = 'Detailed breakdown of student competency performance across each course examination and overall totals.';
$string['competency_trend_title']   = 'Longitudinal Competency Progress & Mastery Curve';
$string['competency_trend_desc']    = 'Visual progress tracking of student competency development across course examinations over time.';
$string['overall_course_total']     = 'Overall Course Total';
$string['exam_total']               = 'Exam Total';
$string['trend_improving']          = 'Improving';
$string['trend_steady']             = 'Steady';
$string['trend_declining']          = 'Needs Focus';
$string['success_threshold_line']   = 'Success Threshold (60%)';

// Term Comprehensive Report & GPA strings.
$string['tab_term_comprehensive']       = 'Term Comprehensive Report';
$string['term_comprehensive_report']    = 'Term Comprehensive Report';
$string['term_comprehensive_report_desc'] = 'Unified term report combining Final Exam (30%), Practical Exam (40%), Participation (20%), and Assignments (10%) with retake caps and GPA scaling.';
$string['eval_exceptional']             = 'Exceptional';
$string['eval_excellent']               = 'Excellent';
$string['eval_superior']                = 'Superior';
$string['eval_verygood']                = 'Very Good';
$string['eval_aboveavg']                = 'Above Average';
$string['eval_good']                    = 'Good';
$string['eval_highpass']                = 'High Pass';
$string['eval_pass']                    = 'Pass';
$string['eval_fail']                    = 'Fail';
$string['letter_grade']                 = 'Letter Grade';
$string['gpa']                          = 'GPA';
$string['theory_score_30']              = 'Theory Final (/30)';
$string['theory_retake1_18']            = 'Theory Retake 1 (/18 cap)';
$string['theory_retake2_18']            = 'Theory Retake 2 (/18 cap)';
$string['best_theory_30']               = 'Best Theory (/30)';
$string['practical_score_40']           = 'Practical (/40)';
$string['practical_retake1_24']         = 'Practical Retake 1 (/24 cap)';
$string['practical_retake2_24']         = 'Practical Retake 2 (/24 cap)';
$string['best_practical_40']            = 'Best Practical (/40)';
$string['participation_20']             = 'Participation (/20)';
$string['assignments_10']               = 'Assignments (/10)';
$string['expected_participation']       = 'Expected Participation (Benchmarked)';
$string['term_total_100']               = 'Term Total (/100)';
$string['theory_status']                = 'Theory Status';
$string['practical_status']             = 'Practical Status';
$string['overall_term_status']          = 'Overall Status';
$string['theory_passed']                = 'Theory Passed (≥18)';
$string['theory_failed']                = 'Theory Failed (<18)';
$string['practical_passed']             = 'Practical Passed (≥24)';
$string['practical_failed']             = 'Practical Failed (<24)';
$string['practical_none']               = 'No Practical Record';
$string['term_passed']                  = 'Passed (≥60)';
$string['term_failed']                  = 'Failed (<60)';
$string['gpa_distribution_title']       = 'Academic Performance & GPA Distribution';
$string['term_kpis_title']              = 'Term Performance Summary & Cohort Indicators';
$string['upload_part_asgn_file']        = 'Upload Participation & Assignments CSV/Excel';
$string['upload_part_asgn_help']        = 'Upload a CSV or Excel file containing columns: Email or ID, Participation (out of 20), Assignments (out of 10), and optional Retake Theory / Retake Practical scores.';
$string['upload_retakes_file']          = 'Upload Retakes Data File (Optional)';
$string['btn_calculate_term']           = 'Recalculate Term Report';
$string['btn_export_term_excel']        = 'Export Term Report (Excel)';
$string['btn_export_term_pdf']          = 'Export Term Report (PDF)';

// Trainer Analytics strings.
$string['tab_trainer_analytics']        = 'Trainer Performance Analytics';
$string['trainer_analytics']            = 'Trainer Performance Analytics';
$string['trainer_analytics_desc']       = 'Comparative pedagogical analytics across trainers, showing cohort mastery rates, pass percentages, and student distribution.';
$string['trainer_name']                 = 'Trainer Name';
$string['trainer_cohort_count']         = 'Total Students';
$string['trainer_avg_mastery']          = 'Average Competency Mastery';
$string['trainer_pass_rate']            = 'Overall Pass Rate';
$string['trainer_top_comp']             = 'Highest Achieving Competency';
$string['trainer_weak_comp']            = 'Lowest Achieving Competency';
$string['trainer_total_exams']          = 'Total Exams Sat';
$string['no_trainers_found']            = 'No trainer assignments or practical evaluation records found for this course.';
$string['specializations_taught']       = 'Specializations Taught';
$string['all_courses_specializations']  = 'All Courses & Specializations';
$string['filter_by_course']             = 'Course / Specialization';
$string['specializations_count']        = 'Specializations';
$string['select_all']                   = 'Select All';
$string['deselect_all']                 = 'Deselect All';

// Institutional & Multi-Specialization Dashboard strings.
$string['tab_institutional_dashboard']  = 'Institutional Dashboard';
$string['institutional_dashboard']      = 'Institutional & Multi-Specialization Dashboard';
$string['institutional_dashboard_desc']  = 'Cross-specialization and regional performance dashboard aggregating multiple courses, dynamic group-to-region mapping, and master student cohorts.';
$string['select_courses_specializations'] = 'Select Courses / Specializations';
$string['select_courses_help']          = 'Choose one or more courses/specializations to aggregate in the institutional report.';
$string['all_courses']                  = 'All Available Courses';
$string['selected_courses_count']       = 'Selected Courses: {$a}';
$string['map_groups_to_regions']        = 'Map Groups to Regions';
$string['map_groups_desc']              = 'Assign discovered course groups to regions/campuses (e.g., Central, Eastern, Western, Southern, or custom branch names).';
$string['region_name']                  = 'Region / Campus';
$string['group_name']                   = 'Group / Cohort';
$string['btn_save_region_mapping']      = 'Save Region Mapping';
$string['mapping_saved_success']        = 'Group-to-region mapping saved successfully.';
$string['cross_spec_analytics']         = 'Specialization Performance Comparison';
$string['cross_region_analytics']       = 'Regional Performance Comparison';
$string['group_rankings']               = 'Group & Class Breakdown';
$string['master_student_roster']        = 'Master Student Roster';
$string['specialization']               = 'Specialization / Course';
$string['region']                       = 'Region';
$string['group']                        = 'Group';
$string['total_specializations']        = 'Total Specializations';
$string['total_regions']                = 'Total Regions';
$string['total_groups']                 = 'Total Groups';
$string['cohort_total_students']        = 'Total Enrolled Students';
$string['cohort_overall_avg']           = 'Cohort Overall Average';
$string['cohort_pass_rate']             = 'Cohort Pass Rate';
$string['filter_by_spec']               = 'Filter by Specialization';
$string['filter_by_region']             = 'Filter by Region';
$string['filter_by_group']              = 'Filter by Group';
$string['search_student_ph']            = 'Search by student name, ID or email...';
$string['btn_export_master_excel']      = 'Export Master Roster (Excel)';
$string['no_courses_selected']          = 'Please select at least one course/specialization to generate the institutional report.';
$string['auto_suggested_region']        = 'Auto-suggested';
$string['add_custom_region']            = 'Add Custom Region';
$string['region_central']               = 'Central Region';
$string['region_eastern']               = 'Eastern Region';
$string['region_western']               = 'Western Region';
$string['region_southern']              = 'Southern Region';
$string['region_unassigned']            = 'Unassigned Region';


// Capability language strings (required by Moodle — key = shortname of capability after the plugin prefix).
$string['comp_report_ext:manage']               = 'Manage competency-question mapping';
$string['comp_report_ext:viewreports']          = 'View all competency reports (teacher/manager)';
$string['comp_report_ext:viewownreport']        = 'View own competency report (student)';
$string['comp_report_ext:manageassessments']    = 'Manage assessment weights (quizzes & practicals)';
$string['comp_report_ext:enterpractical']       = 'Enter practical exam results for students';

// Legacy capability label aliases (competency_report plugin name).
$string['competency_report:viewreports']        = 'View all competency reports (legacy)';
$string['competency_report:viewownreport']      = 'View own competency report (legacy)';
$string['competency_report:manageassessments']  = 'Manage assessment weights (legacy)';
$string['competency_report:enterpractical']     = 'Enter practical exam results (legacy)';
