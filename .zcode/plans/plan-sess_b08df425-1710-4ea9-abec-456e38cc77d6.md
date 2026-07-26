## خطة الإصلاح الشاملة — `local_comp_report_ext` v3.5.4

### التشخيص الجذري (مختصر)

| المشكلة | السبب الجذري |
|---------|--------------|
| الـ competency لا يُحقَّق تلقائيًا في واجهة Moodle الأصلية | `observer.php` و `scheduled_task` يُنشئان `competency_usercomp` **بدون تعيين `proficiency`** → دائمًا NULL → يظهر "غير متقن" |
| تكرار الـ evidence | 3 مسارات منفصلة كلها تستخدم `insert_record` أعمى بلا upsert/dedup؛ المهمة الليلية هي المحرّك الرئيسي (تشغيل يومي 1:00 صباحًا) |
| تباين النتائج بين التقارير وواجهة Moodle | المسارات الثلاثة تستخدم متوسط أسئلة بسيط (حتى بلا فلتر `state='finished'`)، بينما التقارير تستخدم `competency_calculator` |
| `adminid=2` مُرمَّز | خطر إذا لم يكن المستخدم 2 أدمن على الخادم |

### الحل: دالة مركزية واحدة + إعادة هيكلة المسارات الثلاثة

**مبدأ:** كل الكتابة لجداول الـ competency تمرّ عبر **دالة واحدة موحَّدة** تقوم بـ upsert + dedup + تعيين proficiency — بدل 3 نسخ متضاربة.

---

### الملفات المُعدَّلة

#### 1️⃣ ملف جديد: `classes/competency_sync.php` (قلب الإصلاح)
فئة `competency_sync` بطريقتين ثابتتين:

- **`sync_user_competency($userid, $courseid, $adminid)`** — لطالب واحد، كل competencies:
  1. يستدعي `competency_calculator::get_student_scores($userid)` (يحترم الأوزان تلقائيًا، وله fallback للمتوسط البسيط، ويفلتر `state='finished'`).
  2. لكل competency: **upsert** `competency_usercomp` مع `proficiency` (1/0 حسب threshold) + `status=0`.
  3. **upsert** `competency_usercompcourse` بنفس الطريقة.
  4. **تنظيف تلقائي للمكررات**: يحذف كل سجلات `competency_userevidence` + `competency_userevidencecomp` القديمة لهذا الطالب/competency، ويُبقي سجلًا واحدًا محدّثًا.
  5. **upsert** `competency_evidence` — يُبقي سجلًا واحدًا فقط لكل `usercompetencyid` (يحدّثه أو يُنشئه).
  6. يعيد مصفوفة النسب للتنبيهات.
  - `$adminid` يُمرَّر دائمًا (لا تثبيت).

- **`sync_course($courseid, $adminid)`** — يلف حلقة على الطلاب المسجَّلين ويستدعي `sync_user_competency` لكل واحد (للمهام المجدولة/اليدوية).

#### 2️⃣ `classes/observer.php` (إصلاح العرض الفوري)
- يحذف كتلة الـ insert المكررة (الأسطر 78-136) ويستبدلها بـ `competency_sync::sync_user_competency($userid, $courseid, $adminid)`.
- يحل `adminid` ديناميكيًا (`get_admin()->id` بدل 2).
- **يُبقي** منطق التنبيه at-risk (الأسطر 138-165) كما هو — ميزة منفصلة تعمل بشكل سليم.
- يحذف `private static get_user_competency_rate()` (لم تعد ضرورية).

#### 3️⃣ `classes/task/scheduled_competency_rates_task.php` (إصلاح المهمة الليلية)
- يستبدل جسم `execute()` بـ حلقة بسيطة على الكورسات النشطة تستدعي `competency_sync::sync_course($courseid, get_admin()->id)`.
- يحذف كل المنطق المكرر + `get_user_competency_rate()`.

#### 4️⃣ `classes/task/process_competency_rates_task.php` (تبسيط)
- `execute()` يستدعي `competency_sync::sync_course($courseid, $adminid)` (يأخذ adminid من custom_data كما هو).
- يحذف كل المنطق المكرر + `get_user_competency_rate()`.

#### 5️⃣ `add_success_to_evidence.php` — **بلا تغيير** (مفوّض بالفعل للمهمة).

#### 6️⃣ `version.php` — رفع الإصدار `2026072518` → `2026072519`، release `3.5.4`.

#### 7️⃣ `tests/cli_test_lab.php` — توسيع Test 11 ليؤكِّد:
- صفر تكرارات في `competency_evidence` لكل usercompetencyid (لا `userevidencecomp` فقط).
- أن كل `competency_usercomp` لطالب اجتاز threshold له `proficiency=1`.

#### 8️⃣ `README.md` — إضافة سجل v3.5.4 يوثّق الإصلاح.

---

### ما الذي يُحلّه هذا تمامًا

| الشكوى | النتيجة بعد الإصلاح |
|--------|---------------------|
| ❌ الـ competency لا يُحقَّق تلقائيًا | ✅ فور تسليم أي امتحان، يُحدَّث `proficiency` في `competency_usercomp` → واجهة Moodle الأصلية تُظهر "متقن" تلقائيًا |
| ❌ تكرار الـ evidence | ✅ دالة مركزية واحدة + dedup مدمج → سجل evidence واحد نظيف لكل طالب/competency دائمًا |
| ❌ تباين بين التقارير وواجهة Moodle | ✅ كلاهما يستخدم `competency_calculator` (نفس النسب بالضبط، مع احترام الأوزان) |
| ❌ `adminid=2` مُرمَّز | ✅ مُحلَّل ديناميكيًا |

---

### مخاطر وضمانات

- **لا حذف/تأشير**: لا تُحذف أي بيانات طلاب فعلية — فقط السجلات المكرّرة الزائدة (upsert يحل محلها). البيانات الأصلية للطالب محفوظة.
- **متوافق مع الكود الحالي**: `competency_calculator` موجود ومُختبَر ويُستخدم بالفعل في التقارير — نعيد استخدامه لا نبتكر.
- **PHP غير مثبّت** في هذه البيئة فلا أستطيع تشغيل `php -l` أو الاختبارات. سأتحقق بصريًا من صحة الصياغة، وعليك تشغيل `php local/comp_report_ext/tests/cli_test_lab.php` على الخادم للتأكيد النهائي.
- **التراجع**: كل التعديلات في commit واحد معزول؛ يسهل الرجوع.

### خطوات التنفيذ
1. إنشاء `classes/competency_sync.php`.
2. إعادة هيكلة `observer.php`.
3. إعادة هيكلة `scheduled_competency_rates_task.php`.
4. إعادة هيكلة `process_competency_rates_task.php`.
5. رفع version.php + README.
6. توسيع cli_test_lab.php Test 11.
7. توثيق ما يجب تشغيله على الخادم للتأكيد.