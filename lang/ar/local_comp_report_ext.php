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
 * Arabic strings for local_comp_report_ext plugin.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['action'] = 'إجراء';
$string['averagegrade'] = 'متوسط الدرجة';
$string['participantcount'] = 'الطلاب المشاركون';
$string['ai_failed'] = 'فشل طلب الذكاء الاصطناعي.';
$string['ai_not_configured'] = 'تكامل الذكاء الاصطناعي مُفعَّل لكن الإعدادات غير مكتملة.';
$string['ai_prompt_school'] = 'اكتب تحليلاً تربوياً واستراتيجية تطوير للمدرسة بناءً على نسب الكفايات التالية:';
$string['ai_prompt_student'] = 'اكتب تحليلاً تربوياً مختصراً للطالب بناءً على نسب الكفايات التالية:';
$string['ai_system_prompt'] = 'أنت مساعد تعليمي. قدِّم تغذية راجعة تحفيزية وتربوية للطلاب أو المدارس.';
$string['ai_provider'] = 'مزود الذكاء الاصطناعي';
$string['ai_provider_desc'] = 'اختر مزود الذكاء الاصطناعي المفضل لديك (OpenAI, OpenRouter, DeepSeek, Groq) أو حدد رابط API مخصص متوافق مع OpenAI.';
$string['ai_provider_openai'] = 'OpenAI Cloud API (api.openai.com)';
$string['ai_provider_openrouter'] = 'OpenRouter API (openrouter.ai)';
$string['ai_provider_deepseek'] = 'DeepSeek API (api.deepseek.com)';
$string['ai_provider_groq'] = 'Groq Cloud API (api.groq.com)';
$string['ai_provider_local'] = 'رابط مخصص / نموذج محلي (Ollama, vLLM, LM Studio... إلخ)';
$string['local_endpoint'] = 'رابط API للنموذج المخصص / المحلي';
$string['local_endpoint_desc'] = 'أدخل الرابط الأساسي لخادم الذكاء الاصطناعي المخصص أو المحلي (مثال: http://localhost:11434/v1 لـ Ollama، أو https://openrouter.ai/api/v1 لـ OpenRouter).';
$string['allcompetencies'] = 'جميع الكفايات';
$string['alltime'] = 'كل الأوقات';
$string['allusers'] = 'جميع الطلاب';
$string['analysisfor'] = 'تحليل الكفايات: {$a}';
$string['apikey'] = 'مفتاح API';
$string['apikey_desc'] = 'أدخل مفتاح OpenAI أو Azure OpenAI API. <a href="https://platform.openai.com/account/api-keys" target="_blank">اضغط هنا للحصول على مفتاح OpenAI</a>.';
$string['bluelegend'] = 'أزرق: محقق إلى حد كبير (60–79%)';
$string['btn_process_now'] = 'معالجة نسب النجاح الآن';
$string['classavg'] = 'متوسط الفصل';
$string['classinfo'] = 'الفصل: {$a}';
$string['classreport'] = 'تقرير الفصل';
$string['colorlegend'] = 'مفتاح الألوان:';
$string['comment'] = 'تعليق';
$string['comment_blue'] = 'موضوعات مُحققة إلى حد كبير: {$a}';
$string['comment_green'] = 'موضوعات مُحققة بالكامل: {$a}';
$string['comment_orange'] = 'موضوعات مُحققة جزئياً: {$a}';
$string['comment_red'] = 'موضوعات لم تُحقَّق بعد: {$a}';
$string['compareinfo'] = 'في هذا التقرير يمكنك مقارنة أدائك مع المتوسط العام للمقرر ومتوسط فصلك.';
$string['competency'] = 'الكفاية';
$string['competencycode'] = 'رمز الكفاية';
$string['competencyname'] = 'الكفاية / المهارة';
$string['correct'] = 'صحيح';
$string['correctcount'] = 'عدد الإجابات الصحيحة';
$string['courseavg'] = 'متوسط المقرر';
$string['creation_date'] = 'تاريخ الإنشاء';
$string['enable_ai'] = 'تفعيل تكامل الذكاء الاصطناعي';
$string['enable_ai_desc'] = 'تفعيل التعليقات التربوية المبنية على الذكاء الاصطناعي. يتطلب مفتاح API واختيار النموذج أدناه.';
$string['error_no_enrolment'] = 'أنت غير مسجل في هذا المقرر، لذلك لا يمكنك عرض هذا التقرير.';
$string['evidence'] = 'دليل';
$string['evidence_description'] = 'نجاح في الكفاية {$a->competency}: %{$a->rate}';
$string['evidence_note'] = 'نجاح في الكفاية {$a->competency}: %{$a->rate}';
$string['filter'] = 'تصفية';
$string['filterlabel'] = 'تصفية';
$string['generalcomment'] = 'التعليق العام';
$string['greenlegend'] = 'أخضر: محقق بالكامل (80%+)';
$string['groupcompetency'] = 'تحليل كفايات المجموعة';
$string['groupquizcompetency'] = 'تحليل كفايات اختبار المجموعة';
$string['last30days'] = 'آخر 30 يوماً';
$string['last90days'] = 'آخر 90 يوماً';
$string['maxrows'] = 'الحد الأقصى للصفوف';
$string['maxrows_desc'] = 'الحد الأقصى لعدد الصفوف المعروضة في الجداول.';
$string['model'] = 'النموذج';
$string['model_desc'] = 'أدخل اسم النموذج (مثل gpt-4).';
$string['myavg'] = 'إنجازي';
$string['mycompetencies'] = 'تقارير كفاياتي';
$string['mycompetencyexams'] = 'اختباراتي حسب الكفايات';
$string['mycompetencystate'] = 'حالة كفاياتي';
$string['myexamanalysis'] = 'تحليل اختباراتي';
$string['myreportcard'] = 'بطاقة تقريري';
$string['nocompetencies'] = 'لا توجد كفايات.';
$string['nocompetencyexamdata'] = 'لا توجد بيانات اختبار لهذه الكفاية.';
$string['nodatafound'] = 'لم يُعثر على بيانات اختبار مكتملة للتحليل في هذا المقرر بعد.';
$string['nodatastudentcompetency'] = 'لم يُعثر على بيانات اختبار لهذا الطالب في هذه الكفاية.';
$string['noexamdata'] = 'لم يُعثر على بيانات كفايات لهذا الاختبار.';
$string['orangelegend'] = 'برتقالي: محقق جزئياً (40–59%)';
$string['pdfmystudent'] = '📄 عرض تقريري بصيغة PDF';
$string['pdfreport'] = '📄 تقرير PDF';
$string['pluginname'] = 'نظام تقارير الكفايات';
$string['privacy:metadata'] = 'لا يخزّن مكوّن تقارير الكفايات أي بيانات شخصية.';
$string['privacy:metadata:openai:answertext'] = 'إجابة الطالب تُرسل إلى نموذج الذكاء الاصطناعي للتقييم.';
$string['privacy:metadata:openai:externalpurpose'] = 'يُرسل المكوّن نصوص الأسئلة وإجابات المستخدمين إلى OpenAI API لتقديم تغذية راجعة بالذكاء الاصطناعي وتحليل الكفايات.';
$string['privacy:metadata:openai:questiontext'] = 'نص السؤال يُرسل لتوفير سياق للتحليل بالذكاء الاصطناعي.';
$string['process_queued'] = 'مهمة حساب نسب النجاح أُضيفت إلى قائمة الانتظار. ستكتمل في الخلفية.';
$string['process_success_desc'] = 'تحسب هذه العملية نسب نجاح الطلاب في أسئلة الاختبار وتضيفها كأدلة للكفايات.';
$string['process_success_heading'] = 'نقل نسب النجاح إلى الأدلة';
$string['process_success_title'] = 'معالجة نسب النجاح في الخلفية';
$string['manual_process_heading'] = 'مزامنة الكفايات يدوياً';
$string['manual_process_desc'] = 'رغم أن نسب نجاح الكفايات تتم مزامنتها تلقائياً، يمكنك بدء مزامنة يدوية لأي مقرر دراسي. اضغط <a href="{$a->url}">هنا لاختيار مقرر وتشغيل المزامنة الآن</a>.';
$string['select_course_process'] = 'اختر المقرر الدراسي لبدء المعالجة';
$string['select_course_option'] = 'اختر مقرراً دراسياً...';
$string['btn_select'] = 'اختر';
$string['question'] = 'سؤال';
$string['questioncount'] = 'عدد الأسئلة';
$string['questionlinks'] = 'تفاصيل الأسئلة المرتبطة';
$string['questionname'] = 'عنوان السؤال';
$string['quiz'] = 'اختبار';
$string['recordupdated'] = 'تم تحديث السجل بنجاح';
$string['redlegend'] = 'أحمر: غير محقق (0–39%)';
$string['report_heading'] = 'التقرير التفصيلي لتحليل الكفايات';
$string['report_title'] = 'تقرير الكفايات التفصيلي: {$a}';
$string['savechanges'] = 'حفظ التغييرات';
$string['schoolpdf'] = 'تقرير المدرسة بصيغة PDF';
$string['schoolpdfreport'] = 'تقرير الإنجاز العام للمدرسة';
$string['schoolreport'] = 'التقرير العام للمدرسة';
$string['searchcompetency'] = 'البحث عن كفاية';
$string['searchquiz'] = 'البحث عن اختبار';
$string['searchuserorprept'] = 'البحث عن طالب أو تقرير';
$string['selectcompetency'] = 'اختر كفاية';
$string['selectgroup'] = 'اختر مجموعة';
$string['selectquiz'] = 'اختر اختباراً';
$string['selectstudent'] = 'اختر طالباً';
$string['selectuser'] = 'اختر طالباً';
$string['show'] = 'عرض';
$string['structured_blue'] = '{$a->shortname}: نسبة النجاح %{$a->rate}. محقق إلى حد كبير. التوصية: راجع النقاط الناقصة.';
$string['structured_green'] = '{$a->shortname}: نسبة النجاح %{$a->rate}. تحقق كامل. التوصية: انتقل إلى أنشطة متقدمة.';
$string['structured_orange'] = '{$a->shortname}: نسبة النجاح %{$a->rate}. محقق جزئياً. التوصية: تدرّب على مزيد من الأسئلة النموذجية.';
$string['structured_red'] = '{$a->shortname}: نسبة النجاح %{$a->rate}. لم يتحقق تقدم كافٍ بعد. التوصية: راجع الموضوع واستخدم مصادر إضافية.';
$string['student'] = 'طالب';
$string['studentanalysis'] = 'تقرير مقارنة كفاياتي';
$string['studentavg'] = 'متوسط الطلاب';
$string['studentclass'] = 'تحليل الكفايات';
$string['studentcompetencydetail'] = 'تفاصيل كفاية الطالب';
$string['studentcompetencyexams'] = 'اختبارات كفايات الطالب';
$string['studentexam'] = 'تحليل كفاياتي في الاختبار';
$string['studentexamanalysis'] = 'تحليل اختبار الطالب';
$string['studentpdfreport'] = 'تقرير الكفايات';
$string['studentreport'] = 'تقرير كفاياتي';
$string['success'] = 'نجاح';
$string['success_threshold'] = 'عتبة النجاح';
$string['success_threshold_desc'] = 'نسبة النجاح الافتراضية لترميز الألوان.';
$string['successpercent'] = 'نسبة النجاح %';
$string['successrate'] = 'معدل النجاح (%)';
$string['summaryreport'] = 'ملخص نجاح الكفايات';
$string['teacherstudentcompetency'] = 'تحليل كفايات الطالب';
$string['timeline'] = 'الجدول الزمني';
$string['timelineheading'] = 'تطور الكفايات عبر الزمن';
$string['total'] = 'الإجمالي';
$string['grade'] = 'الدرجة';
$string['user'] = 'طالب';
$string['viewattempt'] = 'مراجعة';
$string['visual_report'] = 'التقرير المرئي';
$string['comp_report_ext:manage'] = 'إدارة ربط الأسئلة بالكفايات';
$string['comp_report_ext:viewownreport'] = 'عرض تقرير تحليل الكفايات الخاص';
$string['comp_report_ext:viewreports'] = 'عرض جميع تقارير كفايات الطلاب';
$string['comp_report_ext:manageassessments'] = 'إدارة إعداد التقييمات وأوزانها';
$string['comp_report_ext:enterpractical'] = 'إدخال نتائج الاختبارات العملية للطلاب';
$string['competency_report:manage'] = 'إدارة ربط الأسئلة بالكفايات';
$string['competency_report:viewownreport'] = 'عرض تقرير تحليل الكفايات الخاص';
$string['competency_report:viewreports'] = 'عرض جميع تقارير كفايات الطلاب';
$string['competency_report:manageassessments'] = 'إدارة إعداد التقييمات وأوزانها';
$string['competency_report:enterpractical'] = 'إدخال نتائج الاختبارات العملية للطلاب';

// AI Commentary and Premium UI strings.
$string['student_banner_title'] = '🎓 تقرير كفايات الطالب';
$string['student_banner_desc'] = 'هذا ملفك الشخصي للكفايات يُظهر تقدمك الأكاديمي وإنجازاتك في هذا المقرر.';
$string['teacher_banner_title'] = '👨‍🏫 لوحة تحكم المعلم: مراجعة تفصيلية';
$string['teacher_banner_desc'] = 'راجع إنجازات الطالب في الكفايات ونتائج الاختبارات العامة والتحليل التربوي المدعوم بالذكاء الاصطناعي.';
$string['ai_analysis_focus'] = 'محور تحليل الذكاء الاصطناعي';
$string['ai_focus_competency'] = 'إنجازات الكفايات';
$string['ai_focus_grades'] = 'الدرجات العامة ونتائج الاختبارات';
$string['opt_instructions'] = 'تعليمات خاصة (اختياري):';
$string['custom_prompt_placeholder'] = 'مثال: اكتب بالعربية، اجعله مختصراً، ركّز على نقاط الضعف...';
$string['btn_generate_ai'] = 'توليد تحليل الذكاء الاصطناعي';
$string['exportpdf'] = 'تصدير تقرير PDF';

// PDF Header Logo Settings.
$string['pdf_logo_heading'] = 'إعدادات شعارات تقارير PDF';
$string['pdf_logo_heading_desc'] = 'قم بتعيين الشعارات لتظهر في أعلى الترويسة لجميع تقارير الـ PDF المصدّرة.';
$string['logo_left'] = 'شعار الترويسة (اليسار)';
$string['logo_left_desc'] = 'ارفع ملف صورة (PNG/JPG) ليظهر في أعلى يسار تقارير الـ PDF.';
$string['logo_left_url'] = 'رابط / مسار شعار اليسار';
$string['logo_left_url_desc'] = 'بديل: أدخل رابط صورة كامل أو مسار سيرفر لشعار اليسار.';
$string['logo_right'] = 'شعار الترويسة (اليمين)';
$string['logo_right_desc'] = 'ارفع ملف صورة (PNG/JPG) ليظهر في أعلى يمين تقارير الـ PDF.';
$string['logo_right_url'] = 'رابط / مسار شعار اليمين';
$string['logo_right_url_desc'] = 'بديل: أدخل رابط صورة كامل أو مسار سيرفر لشعار اليمين.';

// Radar Gap Analysis Chart strings.
$string['radar_chart_title']   = '📊 تحليل فجوة الكفايات — ملفك مقابل متوسط الفصل';
$string['radar_chart_desc']    = 'المنطقة الزرقاء تُمثّل مستوى إتقانك الشخصي. الخط الرمادي المنقّط يُمثّل متوسط الفصل. المناطق التي ينخفض فيها ملفك عن الخط الرمادي تُشير إلى الكفايات التي تحتاج تركيزاً.';
$string['radar_legend_student'] = 'أداؤك';
$string['radar_legend_class']   = 'متوسط الفصل';

// AI Personal Study Plan strings.
$string['btn_studyplan']            = '📋 توليد خطة الدراسة الشخصية';
$string['studyplan_title']          = '🎯 خطة الدراسة العلاجية الشخصية';
$string['studyplan_generating']     = 'جارٍ توليد خطة دراستك الشخصية، يرجى الانتظار…';
$string['studyplan_error']          = 'تعذّر توليد خطة الدراسة. يرجى المحاولة مرة أخرى.';
$string['studyplan_language']       = 'اللغة';
$string['studyplan_desc']           = 'يحلل الذكاء الاصطناعي كفاياتك الضعيفة ويولّد لك خطة دراسة علاجية مُفصَّلة حصةً بحصة — كل حصة مدتها ساعة واحدة، موزَّعة حسب جدولك الدراسي.';
$string['studyplan_sessions_label'] = 'عدد الحصص';
$string['studyplan_sessions_unit']  = 'حصة';
$string['studyplan_session_hint']   = 'كل حصة = ساعة واحدة. أدخل عدد الحصص المتاحة في جدول الطالب.';
$string['studyplan_session_hint_short'] = 'ساعة';
$string['btn_studyplan_pdf']        = '📄 تصدير خطة الدراسة بصيغة PDF';
$string['studyplan_pdf_title']      = 'خطة الدراسة الشخصية بالذكاء الاصطناعي';

// At-Risk Notification strings.
$string['enable_alerts']        = 'تفعيل تنبيهات الطلاب في خطر';
$string['enable_alerts_desc']   = 'عند التفعيل، يتلقى المعلمون إشعاراً تلقائياً عندما يكون لدى الطالب كفايتان أو أكثر دون عتبة التنبيه.';
$string['alert_threshold']      = 'عتبة تنبيه الخطر (%)';
$string['alert_threshold_desc'] = 'الطلاب الذين تقل نسب كفاياتهم عن هذه النسبة (الافتراضي: 40%) سيُشغّلون تنبيهاً للمعلمين المسجلين.';
$string['alert_subject']        = '⚠️ تنبيه طالب في خطر: {$a}';
$string['alert_body']           = 'عزيزي المعلم،

هذا إشعار تلقائي من نظام تقارير الكفايات.

الطالب "{$a->student}" في المقرر "{$a->course}" لديه كفايتان أو أكثر دون عتبة التنبيه:

{$a->weaklist}
يرجى مراجعة التقرير الكامل للطالب هنا:
{$a->url}

تم إرسال هذا الإشعار تلقائياً عند تسليم الاختبار.';
$string['messageprovider:studentatrisk'] = 'تنبيه كفايات الطالب في خطر';
$string['coursemasterreport'] = 'تقرير المقرر الشامل والموحد';
$string['coursemasterreport_desc'] = 'تقرير إداري متكامل يجمع إحصائيات المقرر العامة، درجات الاختبارات، إنجاز الكفاءات، ومقارنة المجموعات.';
$string['group_comparison_grid'] = 'شبكة مقارنة كفايات المجموعات';
$string['exam_grades_summary'] = 'ملخص الدرجات العامة والاختبارات';
$string['course_stats'] = 'الإحصائيات العامة للمقرر';
$string['studentdashboard'] = 'لوحة تحكم أداء الطلاب';
$string['groupperformance'] = 'تحليل أداء المجموعات';
$string['tab_by_competency'] = 'حسب الكفايات العامة';
$string['tab_by_quiz'] = 'حسب كفايات الاختبارات';
$string['assessmentsetup'] = 'إعداد التقييمات والأوزان';
$string['practicalentry'] = 'إدخال درجات الاختبار العملي';
$string['addpracticalassessment'] = 'إضافة تقييم عملي';
$string['addquizassessment'] = 'إضافة تقييم اختبار قصير';
$string['assessmentdeleted'] = 'تم حذف التقييم بنجاح';
$string['assessmentname'] = 'اسم التقييم';
$string['assessmentsaved'] = 'تم حفظ التقييم بنجاح';
$string['assessmenttype'] = 'نوع التقييم';
$string['competencypercent'] = 'نسبة الجدارة';
$string['invaliddata'] = 'بيانات غير صالحة';
$string['nopracticalassessments'] = 'لم يتم إعداد أي تقييمات عملية لهذا المقرر بعد.';
$string['nostudentsenrolled'] = 'لا يوجد طلاب مسجلون في هذا المقرر.';
$string['practicalsaved'] = 'تم حفظ الدرجات العملية بنجاح';
$string['selectpracticalassessment'] = 'اختر التقييم العملي...';
$string['showstudents'] = 'عرض الطلاب';
$string['totalweight'] = 'الوزن الإجمالي';
$string['typepractical'] = 'تقييم عملي';
$string['typequiz'] = 'تقييم اختبار قصير';
$string['weight'] = 'الوزن (%)';
$string['weightwarning'] = 'تنبيه: يجب أن يكون مجموع أوزان التقييمات مساوياً لـ 100%. الوزن الحالي هو {$a}%.';
$string['addnewassessment'] = 'إضافة تقييم جديد';
$string['assessmentnamepholder'] = 'مثال: الاختبار العملي النصفي';
$string['assessmentweighthint'] = 'اختر نشاطاً (اختباراً قصيراً أو واجباً) وحدد وزنه النسبي في درجات الكفاءة الإجمالية.';
$string['configuredassessments'] = 'التقييمات التي تم إعدادها';
$string['confirmdelete'] = 'هل أنت متأكد من رغبتك في حذف هذا التقييم؟ سيتم حذف جميع الدرجات المرتبطة به.';
$string['enterstudentresults'] = 'إدخال درجات الطلاب';
$string['goassessmentsetup'] = 'ذهاب إلى صفحة إعداد التقييمات';
$string['noassessments'] = 'لم يتم إعداد أي تقييمات لهذا المقرر بعد.';
$string['practicalentryhintsave'] = 'أدخل مستوى الإنجاز بنسبة مئوية (0-100) لكل طالب. تأكد من حفظ التغييرات.';
$string['totalweightlabel'] = 'الوزن الإجمالي';
$string['updateassessments'] = 'حفظ الأوزان والتغييرات';
$string['weighttotal_ok'] = 'مجموع أوزان التقييمات هو 100% تماماً. تم ضبط كل شيء بشكل صحيح.';

// Missing Cache and Privacy strings.
$string['questions_abbr'] = 'أسئلة';
$string['cachedef_ai_feedback'] = 'مخزن المؤقت للتغذية الراجعة للذكاء الاصطناعي';
$string['custominstructionsdesc'] = 'اكتب تعليمات خاصة لتوجيه تقرير الذكاء الاصطناعي (مثل اللغة، الطول، التركيز).';
$string['generalgradesreportgroup'] = 'تقرير الدرجات العامة - المجموعة: {$a}';
$string['privacy:metadata:local_comp_report_ext_prac'] = 'يخزن مدخلات درجات الاختبارات العملية للكفايات المُنَفَّذة بواسطة المدربين.';
$string['privacy:metadata:local_comp_report_ext_prac:studentid'] = 'معرف المستخدم للطالب الجاري تقييمه.';
$string['privacy:metadata:local_comp_report_ext_prac:trainerid'] = 'معرف المستخدم للمدرب الذي أدخل النتيجة.';
$string['privacy:metadata:local_comp_report_ext_prac:competency_percent'] = 'درجة إنجاز الكفاية بنسبة مئوية.';

// Group Assessment Distribution Report strings.
$string['groupassessmentdistribution']     = 'توزيع الكفايات حسب المجموعات والتقييمات';
$string['tab_assessment_distribution']     = 'حسب أوزان التقييمات';
$string['selectassessments']               = 'اختر التقييمات';
$string['allgroups']                       = 'كل المجموعات';
$string['allquizzes']                      = 'جميع الاختبارات';
$string['score_distribution_histogram']     = 'المدرج التكراري لتوزيع درجات الطلاب الإجمالية';
$string['group']                           = 'المجموعة';
$string['weightedtotal']                   = 'المجموع المرجح';
$string['assessmentheader']                = '{$a->name} ({$a->weight}%)';
$string['noassessmentsconfigured']         = 'لا توجد تقييمات مرجحة مُعدَّة لهذه الدورة. يرجى استخدام إعداد التقييمات أولاً.';
$string['groupassessmentdistribution_pdf'] = 'تصدير تقرير التوزيع PDF';
$string['group_analytics_dashboard'] = 'لوحة تحليلات الكفايات';
$string['tab_group_analytics'] = 'تحليلات الكفايات';
$string['tab_analytics_competency'] = 'التحليلات بحسب الكفايات';
$string['tab_analytics_grades']     = 'التحليلات بحسب درجات الاختبار';
$string['group_exam_analytics']     = 'لوحة تحليلات درجات الاختبار النهائي';
$string['exam_grade_histogram']     = 'المدرج التكراري لدرجات الاختبار الخام';
$string['academic_performance_tiers'] = 'توزيع المستويات الأكاديمية ودرجات الاختبار';
$string['question_item_difficulty'] = 'مؤشر صعوبة أسئلة الاختبار (Psychometric p-value)';
$string['question_item_discrimination'] = 'مؤشر تمييز الأسئلة بين الطلاب الأفضل والأدنى أداءً';
$string['grade_tier_failed'] = 'متعثر / بحاجة لدعم (< 60%)';
$string['grade_tier_passing'] = 'مقبول / مرضي (60–74%)';
$string['grade_tier_verygood'] = 'جيد جداً (75–89%)';
$string['grade_tier_outstanding'] = 'ممتاز / متميز (90–100%)';
$string['average_mastery_rate'] = 'نسبة الإتقان العامة';
$string['remediation_rate'] = 'الطلاب المتعثرون (بحاجة لمراجعة)';
$string['top_strength'] = 'نقطة القوة الرئيسية';
$string['critical_gap'] = 'فجوة المهارات الحرجة';
$string['critical_skill_gap'] = 'فجوة المهارات الحرجة';
$string['competency_mastery_radar'] = 'منحنى خريطة الكفايات الإجمالية';
$string['mastery_distribution'] = 'توزيع مستويات الإتقان';
$string['learning_progress_curve'] = 'منحنى التقدم الزمني للتعلم';
$string['theory_vs_practice'] = 'فجوة المعرفة النظرية مقابل التطبيق العملي';
$string['critical_tier'] = 'حرِج (< 40%)';
$string['developing_tier'] = 'مطور (40-59%)';
$string['proficient_tier'] = 'متقن (60-79%)';
$string['exemplary_tier'] = 'متميز (80-100%)';
$string['no_data_dashboard'] = 'لا توجد بيانات تقييمية لطلاب هذه المجموعة لإنشاء لوحة التحليلات البيانية.';
$string['group_analytics_dashboard_pdf'] = 'تصدير لوحة التحليلات بصيغة PDF';
$string['exam_analytics_section'] = 'التحليل التفصيلي لدرجات وسلوك الاختبار النهائي';
$string['exam_avg_score'] = 'متوسط درجات الاختبار النهائي';
$string['exam_pass_rate_label'] = 'نسبة النجاح في الاختبار';
$string['exam_highest_score'] = 'أعلى درجة';
$string['exam_lowest_score'] = 'أدنى درجة';
$string['exam_grade_distribution'] = 'المدرج التكراري لتوزيع الدرجات';
$string['exam_pass_fail_ratio'] = 'نسبة الناجحين إلى المتعثرين';
$string['exam_item_difficulty'] = 'مؤشر صعوبة الأسئلة (p-value)';
$string['exam_item_discrimination'] = 'مؤشر تمييز الأسئلة (أعلى 27% مقابل أدنى 27%)';
$string['student_count'] = 'عدد الطلاب';
$string['passed'] = 'ناجح';
$string['failed'] = 'متعثر';
$string['average_score_pct'] = 'متوسط النسبة المئوية (%)';
$string['top_performers'] = 'أعلى الطلاب إنجازاً';
$string['bottom_performers'] = 'أقل الطلاب إنجازاً';
$string['printreport'] = 'طباعة التقرير';
$string['autodetect'] = 'اكتشاف تلقائي (افتراضي)';
$string['generalgradesreportcourse'] = 'تقرير الدرجات العامة - المقرر: {$a}';
$string['detailedreportgroup']        = 'تقرير الكفايات التفصيلي - المجموعة: {$a}';
$string['detailedreportcourse']       = 'تقرير الكفايات التفصيلي - المقرر: {$a}';
$string['subjectcourse']              = 'المادة / المقرر: {$a}';
$string['groupclass']                 = 'المجموعة / الفصل: {$a}';
$string['generalgradescard']          = 'بطاقة الدرجات العامة والأداء الأكاديمي';
$string['quizexamname']               = 'اسم الاختبار / التقييم';
$string['scoreachieved']              = 'الدرجة المحققة';
$string['aicommentarytitle']          = 'التحليل والتعليق التربوي بالذكاء الاصطناعي';
$string['attempt_1']                = 'المحاولة 1 (الأصلية)';
$string['retake_1']                 = 'الإعادة 1';
$string['retake_2']                 = 'الإعادة 2';
$string['retakes_count']            = 'عدد الإعادات';
$string['final_recorded_grade']     = 'الدرجة المعتمدة النهائية';
$string['passed_first_attempt']     = 'ناجح (محاولة أولى)';
$string['passed_retake_1']          = 'ناجح إعادة 1 (سقف 60%)';
$string['passed_retake_2']          = 'ناجح إعادة 2 (سقف 60%)';
$string['failed_status']            = 'راسب (< 60%)';
$string['exam_results_title']       = 'نتائج وأداء اختبارات المقرر';
$string['exam_results_desc']        = 'تفصيل درجات الطالب ومحاولات الإعادة والدرجة المعتمدة في كل اختبار بالمقرر.';
$string['no_exams_taken']           = 'لا توجد محاولات اختبار مكتملة مسجلة لهذا الطالب في هذا المقرر.';
$string['status']                   = 'الحالة';
$string['competency_matrix_title']  = 'مصفوفة الكفايات حسب كل اختبار';
$string['competency_matrix_desc']   = 'تحليل دقيق لأداء الطالب في كل كفاية داخل كل اختبار مع الإجمالي التراكمي العام للمقرر.';
$string['competency_trend_title']   = 'منحنى التطور الزمني ومؤشرات تحسن الكفايات';
$string['competency_trend_desc']    = 'رسم بياني تفاعلي يوضح تتبع تطور مستوى الطالب في كل كفاية على مدار الاختبارات.';
$string['overall_course_total']     = 'الإجمالي العام للمقرر';
$string['exam_total']               = 'إجمالي الاختبار';
$string['trend_improving']          = 'في تحسن 📈';
$string['trend_steady']             = 'مستقر ⚖️';
$string['trend_declining']          = 'يحتاج تركيز 📉';
$string['success_threshold_line']   = 'حد الاجتياز (60%)';

// Term Comprehensive Report & GPA strings.
$string['tab_term_comprehensive']       = 'التقرير الترمي الشامل';
$string['term_comprehensive_report']    = 'التقرير الترمي الشامل';
$string['term_comprehensive_report_desc'] = 'تقرير تراكمي موحد يجمع الاختبار النهائي (30%)، الاختبار العملي (40%)، المشاركة (20%)، والتكاليف (10%) مع تطبيق سقف الإعادات ومعدل GPA.';
$string['eval_exceptional']             = 'استثنائي (A+)';
$string['eval_excellent']               = 'ممتاز (A)';
$string['eval_superior']                = 'جيد جداً مرتفع (B+)';
$string['eval_verygood']                = 'جيد جداً (B)';
$string['eval_aboveavg']                = 'جيد مرتفع (C+)';
$string['eval_good']                    = 'جيد (C)';
$string['eval_highpass']                = 'مقبول مرتفع (D+)';
$string['eval_pass']                    = 'مقبول (D)';
$string['eval_fail']                    = 'راسب (F)';
$string['letter_grade']                 = 'التقدير الحرفي';
$string['gpa']                          = 'المعدل التراكمي (GPA)';
$string['theory_score_30']              = 'النظري النهائي (/30)';
$string['theory_retake1_18']            = 'إعادة النظري 1 (سقف 18)';
$string['theory_retake2_18']            = 'إعادة النظري 2 (سقف 18)';
$string['best_theory_30']               = 'أفضل نظري معتمد (/30)';
$string['practical_score_40']           = 'العملي الأصلي (/40)';
$string['practical_retake1_24']         = 'إعادة العملي 1 (سقف 24)';
$string['practical_retake2_24']         = 'إعادة العملي 2 (سقف 24)';
$string['best_practical_40']            = 'أفضل عملي معتمد (/40)';
$string['participation_20']             = 'المشاركة والتفاعل (/20)';
$string['assignments_10']               = 'الواجبات والتكاليف (/10)';
$string['expected_participation']       = 'المشاركة المتوقعة قياسياً';
$string['term_total_100']               = 'المجموع الكلي للترم (/100)';
$string['theory_status']                = 'حالة النظري';
$string['practical_status']             = 'حالة العملي';
$string['overall_term_status']          = 'الحالة النهائية';
$string['theory_passed']                = 'مجتاز النظري (≥18)';
$string['theory_failed']                = 'راسب نظري (<18)';
$string['practical_passed']             = 'مجتاز العملي (≥24)';
$string['practical_failed']             = 'راسب عملي (<24)';
$string['practical_none']               = 'لا يوجد سجل عملي';
$string['term_passed']                  = 'ناجح (≥60)';
$string['term_failed']                  = 'راسب (<60)';
$string['gpa_distribution_title']       = 'توزيع الدرجات والمعدلات الأكاديمية (GPA)';
$string['term_kpis_title']              = 'مؤشرات الأداء الترمي ومعدلات النجاح العامة';
$string['upload_part_asgn_file']        = 'رفع ملف المشاركة والتكاليف (CSV / Excel)';
$string['upload_part_asgn_help']        = 'ارفع ملف CSV أو Excel يحتوي على: البريد الإلكتروني أو الرقم الأكاديمي، درجات المشاركة (من 20)، التكاليف (من 10)، ودرجات الإعادات الاختيارية.';
$string['upload_retakes_file']          = 'رفع ملف بيانات الإعادات (اختياري)';
$string['btn_calculate_term']           = 'إعادة احتساب التقرير الترمي';
$string['btn_export_term_excel']        = 'تصدير التقرير الترمي (Excel)';
$string['btn_export_term_pdf']          = 'تصدير التقرير الترمي (PDF)';

// Trainer Analytics strings.
$string['tab_trainer_analytics']        = 'تحليل أداء المدربين';
$string['trainer_analytics']            = 'تحليل أداء المدربين';
$string['trainer_analytics_desc']       = 'تحليل تربوي ومقارن لأداء المدربين ونسب إتقان الكفايات ومعدلات النجاح للطلاب المسندين لكل مدرب.';
$string['trainer_name']                 = 'اسم المدرب';
$string['trainer_cohort_count']         = 'عدد الطلاب';
$string['trainer_avg_mastery']          = 'متوسط إتقان الكفايات';
$string['trainer_pass_rate']            = 'معدل النجاح الإجمالي';
$string['trainer_top_comp']             = 'أعلى كفاية محققة';
$string['trainer_weak_comp']            = 'أدنى كفاية محققة';
$string['trainer_total_exams']          = 'إجمالي الاختبارات المقدمة';
$string['no_trainers_found']            = 'لا توجد سجلات تقييم عملي أو تعيينات مدربين مسجلة في هذا المقرر.';


// Capability language strings (required by Moodle).
$string['comp_report_ext:manage']               = 'إدارة ربط الأسئلة بالكفايات';
$string['comp_report_ext:viewreports']          = 'عرض تقارير الكفايات الكاملة (للمعلم/المدير)';
$string['comp_report_ext:viewownreport']        = 'عرض تقرير الكفايات الشخصي (للطالب)';
$string['comp_report_ext:manageassessments']    = 'إدارة أوزان التقييمات (الاختبارات والعملي)';
$string['comp_report_ext:enterpractical']       = 'إدخال درجات الاختبار العملي للطلاب';

// Legacy capability label aliases (competency_report plugin name).
$string['competency_report:viewreports']        = 'عرض تقارير الكفايات الكاملة (قديم)';
$string['competency_report:viewownreport']      = 'عرض تقرير الكفايات الشخصي (قديم)';
$string['competency_report:manageassessments']  = 'إدارة أوزان التقييمات (قديم)';
$string['competency_report:enterpractical']     = 'إدخال درجات العملي (قديم)';
