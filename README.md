# 📊 Moodle Local Plugin: Competency Analysis & Reporting (`local_comp_report_ext`)

[![Moodle Compatibility](https://img.shields.io/badge/Moodle-4.5%20%7C%205.0%2B-orange.svg?style=flat-square)](https://moodle.org)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%20%7C%208.2%20%7C%208.3-blue.svg?style=flat-square)](https://php.net)
[![Database](https://img.shields.io/badge/Database-PostgreSQL%20%7C%20MySQL%20%7C%20MariaDB-purple.svg?style=flat-square)](https://docs.moodle.org)
[![License](https://img.shields.io/badge/License-GPL%20v3-green.svg?style=flat-square)](http://www.gnu.org/copyleft/gpl.html)
[![Version](https://img.shields.io/badge/Version-v3.5.1-blue.svg?style=flat-square)](https://github.com/engfeda-ui/competency-report)

A professional Moodle reporting engine that calculates and visualises student competency mastery based on historical quiz performance. By analysing student answers to questions mapped via `qbank_comp_ext`, this plugin provides a granular, actionable view of student strengths and learning gaps — with AI-powered feedback, PDF exports, and group-level analytics.

---

## ✨ Features

- **Automated Performance Analysis:** Evaluates student responses to competency-linked quiz questions across all attempts.
- **Skill-Based Progress Tracking:** Computes exact competency mastery percentages dynamically per student, class, and course.
- **AI-Powered Feedback Engine (Optional):** Generates personalised pedagogical comments via OpenAI (or compatible API). Falls back to rule-based colour-coded comments when AI is disabled.
- **Configurable Success Threshold (NEW in v3.0.8):** A global `success_threshold` setting (default 60%) is now exposed in the admin settings page. This value is used by colour-coding, `quizaccess_failgrade_ext` competency mode, and the background evidence task.
- **Multiple Report Views:**
  - Student report card, exam analysis, competency state, timeline
  - Teacher class report, student comparison, exam analysis
  - Group competency and group quiz competency analysis
  - School-wide report and PDF export
- **Background Evidence Processing:** An adhoc task calculates competency success rates and writes them as Moodle competency evidence — now scoped to enrolled course students only (performance improvement).
- **Enterprise PDF Exports:** Students and educators can download structured PDF reports.
- **Responsive Web UI:** Built with Mustache templates, Bootstrap, and Chart.js.
- **Localization Support:** English and Arabic language packs included (with RTL support).

---

## 📋 Requirements & Dependencies

| Dependency | Required Version / Compatibility |
| :--- | :--- |
| **Moodle Framework** | Moodle 4.5 to 5.0+ |
| **PHP Runtime** | PHP 8.1, PHP 8.2, PHP 8.3 |
| **Database System** | PostgreSQL 13+, MySQL 8.0+, or MariaDB 10.5+ |
| **Required Plugin** | [**`qbank_competency`**](https://github.com/engfeda-ui/competency) ≥ 2026070500 |

---

## 🚀 Installation

1. **Prerequisite:** Install [**`qbank_comp_ext`**](https://github.com/engfeda-ui/competency) first.
2. **Download & Extract:** Download the repository and extract the files.
3. **Directory Placement:** Copy the `competency-report` folder into your Moodle `local/` directory:
   ```
   moodle/local/comp_report_ext
   ```
   > The directory name inside `local/` must be exactly `competency_report`.
4. **Run Moodle Upgrade:** Log in as Administrator and navigate to **Site administration > Notifications**.
5. **Alternative Install:** Zip the directory and upload via **Site administration > Plugins > Install plugins**.

---

## ⚙️ Admin Settings

Navigate to **Site administration > Plugins > Local plugins > Competency Plugin** to configure:

| Setting | Description | Default |
| :--- | :--- | :--- |
| **Enable AI integration** | Toggle AI-powered feedback on/off | Off |
| **API Key** | OpenAI (or compatible) API key | — |
| **Model** | Model name (e.g., `gpt-4`, `gpt-4o`) | `gpt-4` |
| **Maximum rows** | Max rows shown in report tables | 100 |
| **Success threshold** | Minimum % for competency mastery (used by colour-coding and `quizaccess_failgrade_ext`) | 60 |

---

## 🛠️ Usage

1. **Map questions to competencies** using `qbank_comp_ext`.
2. **Deliver quizzes** — students attempt quizzes as normal.
3. **Access reports:**
   - **Teachers:** Course navigation → *Class Report*, *Student Analysis*, *Student Exam Analysis*, *Group Competency Analysis*.
   - **Students:** Course navigation → *My Competency Reports* → choose from report card, exam analysis, competency state, or timeline.
   - **Admins:** Site administration → Reports → *School General Report* / *School PDF Report*.
4. **Export PDF:** Click the PDF button on any report page.
5. **Process evidence:** Use the *Process Success Rates* page (admin only) to queue a background task that writes competency evidence for all enrolled students.

---

## 📋 Changelog

### v3.5.1 — 2026-07-25
- **Automated Verification:** Added CLI Test Lab Test 11 to execute task and verify zero duplicate evidence entries remain in `{competency_userevidencecomp}`.

### v3.5.0 — 2026-07-25
- **Fix & Real-time Execution:** Updated `add_success_to_evidence.php` to execute evidence processing and deduplication task synchronously (`$task->execute()`) so clicking "Process Success Rates Now" updates student competencies and purges duplicate evidence entries instantly in real-time.

### v3.4.9 — 2026-07-25
- **Fix & Purge:** Implemented complete deduplication and automatic purging of duplicate records across `{competency_userevidence}` and `{competency_userevidencecomp}` tables in `process_competency_rates_task.php` so Moodle's native Competency Breakdown evidence modal displays exactly 1 clean, updated evidence entry per student per competency.

### v3.4.8 — 2026-07-25
- **Fix & Deduplication:** Implemented strict evidence deduplication and automatic cleanup logic in `process_competency_rates_task.php` to prevent duplicate evidence records from being generated in `{competency_evidence}` and `{competency_userevidence}` when task is executed repeatedly, and automatically purge past duplicate entries.

### v3.4.7 — 2026-07-25
- **Fix & Enhancement:** Explicitly update `proficiency = 1` in `{competency_usercomp}` and `{competency_usercompcourse}` tables during evidence processing task (`process_competency_rates_task.php`) so Moodle native Course Competencies Breakdown page (`course/competencies.php`) automatically marks student as Proficient (متقن) upon achieving success threshold.

### v3.4.6 — 2026-07-25
- **Testing & Verification:** Expanded automated CLI Test Lab suite (`tests/cli_test_lab.php`) to test all 5 ecosystem plugins (`local_comp_report_ext`, `qbank_comp_ext`, `block_comp_report_ext`, `quizaccess_failgrade_ext`, `quizaccess_attemptpassword`) achieving 10/10 PASS rate on Production server.

### v3.4.5 — 2026-07-25
- **New Feature & Testing:** Added automated CLI Test Lab runner (`tests/cli_test_lab.php`) and extended PHPUnit test suite (`tests/competency_calculator_test.php`) covering context details builder, AI prompt generation, header logo resolution, competency calculator thresholds, DB tables, and form redirect URLs.

### v3.4.4 — 2026-07-25
- **Fix:** Added missing `require_once(__DIR__ . '/lib.php');` in AJAX endpoints (`ajax_ai.php` and `ajax_studyplan.php`) and `add_success_to_evidence.php` to resolve `Call to undefined function local_comp_report_ext_build_context_details()` fatal exception during AI report generation.

### v3.4.3 — 2026-07-25
- **Fix:** Resolved `File not found` error after submitting Assessment Setup form (`add`, `update`, `delete` actions) by fixing hardcoded redirect URLs in `assessment_setup.php` from `/local/competency_report/assessment_setup.php` to `/local/comp_report_ext/assessment_setup.php`.

### v3.3.2 — 2026-07-24
- **New Feature:** Added support for OpenRouter, DeepSeek, Groq, and custom OpenAI-compatible API providers in AI settings, enabling users to seamlessly connect to any cloud or local LLM company.

### v3.3.1 — 2026-07-24
- **Fix:** Added missing admin settings language strings (`enable_ai`, `logo_left`, `logo_right`, `success_threshold_desc`, etc.) in `lang/en/local_comp_report_ext.php` and `lang/ar/local_comp_report_ext.php` to resolve double bracket `[[...]]` display on Site Administration settings page.

### v3.4.2 — 2026-07-25
- **Fix:** Ensured all PDF export generators (`parent_pdf.php`, `group_competency_pdf.php`, `group_quiz_competency_pdf.php`, `course_master_report_pdf.php`, `school_pdf.php`) pass full curriculum context details (`local_comp_report_ext_build_context_details`) to prevent LLM meta-disclaimers/preambles.
- **Fix:** Added strict Rule 6 (`NO META-DISCLAIMERS`) in system prompt.

### v3.4.1 — 2026-07-25
- **Fix:** Added missing `require_once(__DIR__ . '/lib.php');` in `course_master_report_pdf.php` to resolve `Call to undefined function local_comp_report_ext_render_pdf_header_logos()` fatal exception.

### v3.4.0 — 2026-07-25
- **New Feature:** Domain-Specific & Curriculum-Aware AI Pedagogical Analysis — AI reports now inspect the course full name, exam topic context, and specific mastered vs. missed questions.
- **Improvement:** Added automatic Arabic language detection and domain-specific terminology generation (RTL) for Arabic academy courses.

### v3.3.3 — 2026-07-25
- **Fix:** Resolved `File not found` error on PDF exports by updating all Mustache templates, forms, AJAX requests, and PHP scripts to use the correct plugin path `/local/comp_report_ext/`.
- **Fix:** Fixed `local/competency_report:viewreports` capability error on `group_competency.php` by mapping all capability checks to `local/comp_report_ext:` and registering legacy capability aliases in `db/access.php`.

### v3.3.0 — 2026-07-24
- **New Feature:** Header logo settings in Site Administration (`logo_left`, `logo_right`, and URL fallbacks) to render custom academy logos on the top header of all exported PDF reports.
- **Fix:** Restructured the "Exams & General Grades Summary" table in Unified Course Master Report (`course_master_report.php` and PDF) into 5 logical columns: Exam Name, Number of Questions (from quiz slots), Participating Students, Average Score, and Success Rate (%).
- **Fix:** Resolved column header misalignment in TCPDF export by adding explicit percentage width attributes to both `<th>` and `<td>` tags.
- **Fix:** Fixed Class Average calculation in Student Performance Dashboard (`class_report.php`) to query student group membership in `{groups_members}`, avoiding `%0` display.
- **Fix:** Fixed HTML syntax error in `student_competency_detail_page.mustache` (missing `">"` on line 65) that caused column shifts on Teacher Dashboard.
- **Fix:** Resolved 100% of Moodle CodeSniffer (`moodle-plugin-ci codechecker`) rules and guidelines.

### v3.2.2 — 2026-07-05
- **Fix:** Corrected all Moodle PHP CodeSniffer violations across the codebase (including PSR12 class opening brace spaces, comma argument spaces, trailing spaces, array commas, and variable casing in tests).
- **Fix:** Synchronized ecosystem dependency version requirements to require `qbank_comp_ext` >= `2026070500` to prevent PHP 8.x Fatal Errors.
- **Fix:** Updated GitHub Actions workflow (`ci.yml`) to correctly load the `qbank_comp_ext` dependency using `moodle-plugin-ci add-plugin`.

### v3.2.1 — 2026-07-03
- **Fix:** Added missing capability definitions for `local/competency_report:manageassessments` and `local/competency_report:enterpractical` in `db/access.php` to resolve runtime exceptions during fresh installs.
- **Fix:** Refined observer and background task logic to skip writing duplicate Moodle competency evidence when success rates have not changed.
- **Cleanup:** Removed local development-only files (`update_ip.sh`).

### v3.2.0 — 2026-05-31
- **New:** Complete Arabic (`ar`) language pack — all 50+ UI strings, AI commentary widget, radar chart, study plan, and at-risk alert strings are now fully translated into Arabic (RTL support).
- **Improvement:** Study plan language selector — the AI Personalized Study Plan section now includes a language dropdown (🇬🇧 English / 🇸🇦 العربية) so students can request their study plan in their preferred language instead of always defaulting to English.

### v3.1.0 — 2026-05-25
- **New:** Local LLM API integration support — choose between official OpenAI cloud services or locally hosted, privacy-compliant LLMs (e.g. Ollama or vLLM running Llama 3.1 / Qwen 2.5) via standard OpenAI-compatible API endpoints.
- **New:** High-performance AI Commentary Caching — utilizes Moodle's native Cache API (`db/caches.php`) to store generated pedagogical comments. Avoids redundant LLM queries for the same competency grades, saving 98%+ in API cost and reducing load time from seconds to 0.1s.
- **New:** Custom LLM API Endpoint URL and Provider selection exposed in the admin settings page.

### v3.0.9 — 2026-05-20
- **Refactor:** Updated Mustache templates to utilize modern standard ES6 named module imports from `visualizer.js` instead of the obsolete RequireJS module system.
- **Fix:** Resolved a critical session key mismatch in `add_success_to_evidence.php` that caused background evidence process launching to crash with an "Invalid session key" error.
- **Fix:** Converted `$a` variable inside `local_comp_report_ext_structured_comment` to a `stdClass` object to resolve property dereferencing notices/warnings.
- **Fix:** Escaped XML special characters using Moodle's `s()` helper inside `pdf_report.php` and `school_pdf.php` to prevent TCPDF HTML parser corruption.

### v3.0.8 — 2026-05-19
- **Fix:** Added `success_threshold` to `settings.php` — this setting was referenced by `quizaccess_failgrade_ext` and the background evidence task but was not exposed in the admin UI, making it impossible to change from the default.
- **Fix:** Background task (`process_competency_rates_task`) now fetches only enrolled students with `mod/quiz:attempt` capability in the target course, instead of all non-deleted users site-wide. This significantly reduces execution time on large installations.
- **Fix:** Competency SQL query in the background task now correctly filters by `m.courseid = :courseid` without an erroneous JOIN on the quiz table.

### v3.0.7 and earlier
- AI feedback integration (OpenAI).
- PDF report generation.
- Group competency and group quiz competency analysis views.
- Timeline view for competency progress over time.
- School-wide report and PDF export.

---

## 💻 Directory Structure

```
competency-report/
├── amd/                    # AMD JavaScript modules (Chart.js integrations)
│   ├── src/                # ES6 source files
│   └── build/              # Compiled AMD modules (built via Grunt)
├── classes/
│   ├── output/             # Renderable output classes
│   ├── privacy/            # GDPR Privacy provider
│   └── task/               # Adhoc background task
├── db/                     # Moodle DB files (services.php, install.xml, access.php, tasks.php)
├── forms/                  # Moodle form definitions
├── lang/
│   └── en/                 # English language strings
├── templates/              # Mustache HTML templates
├── *.php                   # Report pages (class_report, student_report, group_competency, etc.)
├── ai.php                  # AI and rule-based comment generation functions
├── settings.php            # Admin settings page
├── version.php             # Plugin version and metadata
└── README.md
```

---

## 🔗 The Competency Ecosystem

```mermaid
graph TD
    A[qbank_comp_ext] -->|Maps questions to competencies| B[local_comp_report_ext]
    B -->|Analyses attempts & generates reports| C[block_comp_report_ext]
    C -->|Shows progress on dashboard| D[Moodle Dashboard / Course]
    B -->|Provides threshold config| E[quizaccess_failgrade_ext]
    A -->|Provides question-competency data| E
```

---

## 🧑‍💻 Developer Guide

If you modify JavaScript files in `amd/src/`, rebuild the production assets using Grunt:

```bash
cd /path/to/moodle
npm install
npx grunt amd --files=local/competency_report
```

---

## 🔒 Security & Code Compliance

- **SQL Injection Prevention:** All queries use Moodle's `$DB` API with named parameter bindings.
- **Input Sanitization:** All input retrieved via `required_param()` / `optional_param()` with strict type filters.
- **Capability Controls:** All report pages enforce `require_login()` and `require_capability()`.
- **Privacy Compliance:** `privacy/provider.php` declares the plugin's data footprint including OpenAI data transmission.
- **Coding Standards:** Compliant with Moodle's `PHP_CodeSniffer` (PHPCS) ruleset.

---

## 📋 Changelog

### v3.3.2 (2026072502) — 2026-07-25
- **Fix:** Fixed course menu navigation callback name (`local_comp_report_ext_extend_navigation_course` & `local_comp_report_ext_extend_navigation_reports`) so competency reports appear under Course Reports & secondary navigation tabs.
- **Fix:** Fixed folder read permissions (755) on server and cleaned up duplicate language string declarations causing `[[string_name]]` double bracket placeholders.

## 📄 License & Credits

- **Copyright:** © 2026 Mahmoud Salem
- **Based on work by:** 2026 Hakan Çiğci
- **License:** [GNU GPL v3](http://www.gnu.org/copyleft/gpl.html) or later.
