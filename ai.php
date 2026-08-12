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
 * AI and Rule-based commentary logic for competencies.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @copyright  based on work by 2026 Hakan Ã‡iÄŸci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Main function to generate comments based on competency stats.
 *
 * @param array  $stats   The competency shortname and success rates.
 * @param string $context The context of the comment (student or school).
 * @return string
 */
function local_comp_report_ext_generate_comment(
    array $stats,
    $context = 'student',
    $customprompt = '',
    $focustype = 'competency',
    array $contextdetails = []
) {
    if (!get_config('local_comp_report_ext', 'enable_ai')) {
        return local_comp_report_ext_rule_based_comment($stats);
    }

    // Generate unique cache key for the student grades, custom prompts, focus type, and rich context.
    $statskeys = $stats;
    $keydata = json_encode($statskeys) . '_' . $context . '_' . md5($customprompt) . '_' . $focustype . '_'
        . md5(json_encode($contextdetails)) . '_v8';
    $cachekey = md5($keydata);

    try {
        $cache = \cache::make('local_comp_report_ext', 'ai_feedback');
        $cachedcomment = $cache->get($cachekey);
        if ($cachedcomment !== false) {
            return $cachedcomment;
        }
    } catch (\Exception $e) {
        $cache = null; // Fallback silently if cache is not initialized.
    }

    // Call AI comment function with rich context details.
    $comment = local_comp_report_ext_ai_comment($stats, $context, $customprompt, $focustype, $contextdetails);

    // Save in cache if successful (not a failure and not unconfigured).
    $aifailedstr = get_string('ai_failed', 'local_comp_report_ext');
    $ainotconfigstr = get_string('ai_not_configured', 'local_comp_report_ext');
    if (
        strpos($comment, $aifailedstr) === false &&
        strpos($comment, $ainotconfigstr) === false
    ) {
        try {
            if (isset($cache)) {
                $cache->set($cachekey, $comment);
            }
        } catch (\Exception $e) {
            $unused = $e; // Ignore cache save errors.
        }
    }

    return $comment;
}

/**
 * Generates rule-based comments when AI is disabled.
 *
 * @param array $stats
 * @return string
 */
function local_comp_report_ext_rule_based_comment(array $stats) {
    $red = [];
    $orange = [];
    $blue = [];
    $green = [];

    foreach ($stats as $k => $rate) {
        if ($rate <= 39) {
            $red[] = $k;
        } else if ($rate >= 40 && $rate <= 59) {
            $orange[] = $k;
        } else if ($rate >= 60 && $rate <= 79) {
            $blue[] = $k;
        } else if ($rate >= 80) {
            $green[] = $k;
        }
    }

    $text = html_writer::tag('b', get_string('generalcomment', 'local_comp_report_ext') . ":") . html_writer::empty_tag('br');

    if ($red) {
        $text .= html_writer::tag('span', get_string('comment_red', 'local_comp_report_ext', implode(', ', $red)), [
            'style' => 'color: red;',
        ]) . html_writer::empty_tag('br');
    }
    if ($orange) {
        $text .= html_writer::tag('span', get_string('comment_orange', 'local_comp_report_ext', implode(', ', $orange)), [
            'style' => 'color: orange;',
        ]) . html_writer::empty_tag('br');
    }
    if ($blue) {
        $text .= html_writer::tag('span', get_string('comment_blue', 'local_comp_report_ext', implode(', ', $blue)), [
            'style' => 'color: blue;',
        ]) . html_writer::empty_tag('br');
    }
    if ($green) {
        $text .= html_writer::tag('span', get_string('comment_green', 'local_comp_report_ext', implode(', ', $green)), [
            'style' => 'color: green;',
        ]) . html_writer::empty_tag('br');
    }

    return $text;
}

/**
 * AI-based comment generation using OpenAI API.
 *
 * @param array  $stats
 * @param string $context
 * @return string
 */
function local_comp_report_ext_ai_comment(
    array $stats,
    $context = 'student',
    $customprompt = '',
    $focustype = 'competency',
    array $contextdetails = []
) {
    global $CFG;
    require_once($CFG->libdir . '/filelib.php');

    $provider = get_config('local_comp_report_ext', 'ai_provider');
    if (!$provider) {
        $provider = 'openai';
    }

    $apikey = get_config('local_comp_report_ext', 'apikey');
    $model  = get_config('local_comp_report_ext', 'model');

    if ($provider === 'openai' && (empty($apikey) || empty($model))) {
        return get_string('ai_not_configured', 'local_comp_report_ext');
    }

    if ($provider === 'local' && empty($model)) {
        return get_string('ai_not_configured', 'local_comp_report_ext');
    }

    $coursename = $contextdetails['coursename'] ?? '';
    $quizname   = $contextdetails['quizname'] ?? '';
    $missed     = $contextdetails['missed_questions'] ?? [];
    $mastered   = $contextdetails['mastered_questions'] ?? [];
    $lang = $contextdetails['lang'] ?? current_language();
    $explicitlang = $contextdetails['language'] ?? '';

    $isarabic = (
        $explicitlang === 'Arabic'
        || strpos($lang, 'ar') === 0
        || (preg_match('/[\x{0600}-\x{06FF}]/u', $coursename) > 0)
        || (preg_match('/[\x{0600}-\x{06FF}]/u', $customprompt) > 0)
        || stripos($customprompt, 'arabic') !== false
        || stripos($customprompt, 'arabi') !== false
        || stripos($customprompt, 'عربي') !== false
        || stripos($customprompt, 'بالعربي') !== false
    );

    if ($explicitlang === 'English') {
        $isarabic = false;
    }

    if ($isarabic) {
        $langdirective = "CRITICAL LANGUAGE REQUIREMENT: Output the report ENTIRELY in natural, highly professional Arabic (العربية). "
            . "Use exact technical, industrial, and academic terminology appropriate for the course subject. Do NOT output in English.";
    } else {
        $langdirective = "CRITICAL LANGUAGE REQUIREMENT: Output the report in English, "
            . "using exact domain-specific technical terms suitable for the course subject.";
    }

    $courseinfo = !empty($coursename) ? "Course Title / Subject Domain: {$coursename}\n" : "";
    if (!empty($quizname)) {
        $courseinfo .= "Quiz / Exam Name: {$quizname}\n";
    }

    $questioninfo = "";
    if (!empty($mastered)) {
        $questioninfo .= "Topics / Questions Mastered Successfully:\n- " . implode("\n- ", $mastered) . "\n";
    }
    if (!empty($missed)) {
        $questioninfo .= "Topics / Questions Needing Review / Missed:\n- " . implode("\n- ", $missed) . "\n";
    }

    // Configure prompt depending on focus (Competency vs. General Grades).
    if ($focustype === 'grades') {
        if ($isarabic) {
            $struct = "   - <h4><strong>ملخص الأداء في التقييمات والدرجات</strong></h4> مع شريط التقدم لكل تقييم/اختبار.\n"
                . "   - <h4><strong>نقاط القوة والإتقان الفني</strong></h4> قائمة نقاط ترتبط بموضوعات المقرر.\n"
                . "   - <h4><strong>التوصيات وخطوات التطوير</strong></h4> قائمة نقاط إجراءات عملية لمراجعتها.";
        } else {
            $struct = "   - <h4><strong>Exam Performance Summary</strong></h4> with progress bar.\n"
                . "   - <h4><strong>Strengths & Progress</strong></h4> with domain-specific bullet points.\n"
                . "   - <h4><strong>Recommendations & Next Steps</strong></h4> with actionable technical review steps.";
        }

        $systemprompt = "You are an expert pedagogical and technical domain advisor.\n"
            . "Your task is to analyze exam grades and student performance within the specific subject domain ({$coursename}).\n"
            . "Follow these rules strictly:\n"
            . "1. {$langdirective}\n"
            . "2. Tone: Extremely professional, domain-specific, direct, and encouraging.\n"
            . "3. Content: Ground your advice in the actual technical subject matter of the course ({$coursename}). "
            . "Explicitly discuss the specific technical topics mastered and missed.\n"
            . "4. Length: Short and focused (150-250 words).\n"
            . "5. Format: Write directly in HTML. Use clean paragraphs, strong bold headers, and bulleted lists.\n"
            . "   - For each subject/quiz analyzed, you MUST append a progress bar placeholder using this format:\n"
            . "     '[PROGRESSBAR: Subject Name | Score%]' (e.g. '[PROGRESSBAR: Quiz 1 | 85%]').\n"
            . "6. NO META-DISCLAIMERS: Do NOT output any notes or intros like 'Please note...'. "
            . "Start directly with the first <h4> heading.\n"
            . "7. Structure:\n{$struct}";

        $prompt = "Write a domain-specific pedagogical feedback report for context: {$context}\n"
            . "{$courseinfo}{$questioninfo}\nGrade Summary:\n";
    } else {
        if ($isarabic) {
            $struct = "   - <h4><strong>نظرة عامة على الإتقان</strong></h4> مع شريط التقدم لكل كفاية.\n"
                . "   - <h4><strong>أبرز نقاط القوة</strong></h4> قائمة نقاط ترتبط بالمادة والموضوعات.\n"
                . "   - <h4><strong>مجالات التطوير والتوصيات</strong></h4> قائمة خطوات محددة لمراجعة المفاهيم.";
        } else {
            $struct = "   - <h4><strong>Performance Overview</strong></h4> with progress bar.\n"
                . "   - <h4><strong>Key Strengths</strong></h4> with domain-specific bullet points.\n"
                . "   - <h4><strong>Areas for Development & Next Steps</strong></h4> with actionable technical review steps.";
        }

        $systemprompt = "You are an expert pedagogical and technical domain advisor.\n"
            . "Your task is to analyze student competency mastery percentages within the specific course domain ({$coursename}).\n"
            . "Follow these rules strictly:\n"
            . "1. {$langdirective}\n"
            . "2. Tone: Extremely professional, domain-specific, direct, and encouraging.\n"
            . "3. Content: Ground your analysis in the actual technical subject matter of the course ({$coursename}). "
            . "Discuss the specific competencies and technical question topics mastered or missed.\n"
            . "4. Length: Short and focused (150-250 words).\n"
            . "5. Format: Write directly in HTML. Use clean paragraphs, strong bold headers, and bulleted lists.\n"
            . "   - For each competency analyzed, you MUST append a progress bar placeholder using this format:\n"
            . "     '[PROGRESSBAR: Competency Name | Score%]' (e.g. '[PROGRESSBAR: Communication | 85%]').\n"
            . "6. NO META-DISCLAIMERS: Do NOT output any notes or intros like 'Please note...'. "
            . "Start directly with the first <h4> heading.\n"
            . "7. Structure:\n{$struct}";

        $prompt = "Write a domain-specific competency feedback report for context: {$context}\n"
            . "{$courseinfo}{$questioninfo}\nCompetency Achievements:\n";
    }

    foreach ($stats as $k => $v) {
        $prompt .= "{$k}: %{$v}\n";
    }

    if (!empty($customprompt)) {
        $prompt .= "\nCRITICAL SPECIAL USER INSTRUCTIONS (adhere to this strictly, "
            . "e.g. language/conciseness/focus): " . $customprompt;
    }

    // Bypassing cURL security check for local LLMs (e.g. Ollama) on custom ports/subnets.
    $curloptions = [];
    if ($provider === 'local') {
        $curloptions['ignoresecurity'] = true;
    }
    $curl = new \curl($curloptions);

    $headers = ["Content-Type: application/json"];
    if (!empty($apikey)) {
        $headers[] = "Authorization: Bearer {$apikey}";
    }

    if ($provider === 'openrouter') {
        $headers[] = "HTTP-Referer: https://sanad.ws";
        $headers[] = "X-Title: Sanad Moodle Competency Report";
        $endpoint = 'https://openrouter.ai/api/v1/chat/completions';
    } else if ($provider === 'deepseek') {
        $endpoint = 'https://api.deepseek.com/v1/chat/completions';
    } else if ($provider === 'groq') {
        $endpoint = 'https://api.groq.com/openai/v1/chat/completions';
    } else if ($provider === 'local') {
        $endpoint = get_config('local_comp_report_ext', 'local_endpoint');
        if (empty($endpoint)) {
            $endpoint = 'http://localhost:11434/v1';
        }
        $endpoint = rtrim($endpoint, '/');
        if (strpos($endpoint, '/chat/completions') === false) {
            $endpoint .= '/chat/completions';
        }
    } else {
        $endpoint = "https://api.openai.com/v1/chat/completions";
    }

    $curl->setHeader($headers);

    $postdata = json_encode([
        "model" => $model,
        "messages" => [
            [
                "role" => "system",
                "content" => $systemprompt,
            ],
            [
                "role" => "user",
                "content" => $prompt,
            ],
        ],
    ]);

    $options = [
        'timeout'    => 120,
        'ipresolve'  => 1, // Force IPv4 to bypass Docker Desktop IPv6 404 proxy issues on Windows.
    ];

    \core_php_time_limit::raise(120);
    $response = $curl->post($endpoint, $postdata, $options);

    // Diagnose connection-level failures first.
    $curlinfo = $curl->get_info();
    $httpcode = isset($curlinfo['http_code']) ? (int)$curlinfo['http_code'] : 0;
    $curlerror = $curl->get_errno() ? $curl->error : '';

    if ($httpcode === 0 || !empty($curlerror)) {
        $detail = !empty($curlerror) ? $curlerror : 'No response from server';
        debugging("[local_comp_report_ext] AI cURL error: {$detail} (endpoint: {$endpoint})", DEBUG_DEVELOPER);
        return get_string('ai_failed', 'local_comp_report_ext')
            . ' <small class="text-muted">(Connection error: ' . s($detail) . ' | Endpoint: ' . s($endpoint) . ')</small>';
    }

    $data = json_decode($response, true);

    if (json_last_error() === JSON_ERROR_NONE && !empty($data['choices'][0]['message']['content'])) {
        $content = $data['choices'][0]['message']['content'];
        return local_comp_report_ext_parse_progress_bars($content);
    }

    // Build a human-readable diagnostic from the API error response.
    $errormsg = '';
    if (is_array($data) && !empty($data['error']['message'])) {
        $errormsg = $data['error']['message'];
    } else if (is_array($data) && !empty($data['error'])) {
        $errormsg = is_string($data['error']) ? $data['error'] : json_encode($data['error']);
    } else if (!is_array($data)) {
        $errormsg = 'Invalid JSON response from AI provider';
    } else {
        $errormsg = 'Empty or unexpected response structure';
    }

    debugging("[local_comp_report_ext] AI HTTP {$httpcode}: {$errormsg} (endpoint: {$endpoint})", DEBUG_DEVELOPER);

    return get_string('ai_failed', 'local_comp_report_ext')
        . ' <small class="text-muted">(HTTP ' . $httpcode . ': ' . s($errormsg) . ' | Endpoint: ' . s($endpoint) . ')</small>';
}

/**
 * Generates a personalized AI remedial study plan using an enriched prompt.
 *
 * @param string $fullprompt The complete, pre-built prompt string.
 * @return string HTML study plan or error string.
 */
function local_comp_report_ext_generate_study_plan($fullprompt) {
    global $CFG;
    require_once($CFG->libdir . '/filelib.php');

    $provider = get_config('local_comp_report_ext', 'ai_provider') ?: 'openai';
    $apikey   = get_config('local_comp_report_ext', 'apikey');
    $model    = get_config('local_comp_report_ext', 'model');

    if ($provider === 'openai' && (empty($apikey) || empty($model))) {
        return get_string('ai_not_configured', 'local_comp_report_ext');
    }
    if ($provider === 'local' && empty($model)) {
        return get_string('ai_not_configured', 'local_comp_report_ext');
    }
    // Cloud providers other than openai require an API key.
    if (in_array($provider, ['openrouter', 'deepseek', 'groq']) && empty($apikey)) {
        return get_string('ai_not_configured', 'local_comp_report_ext');
    }

    $curloptions = ($provider === 'local') ? ['ignoresecurity' => true] : [];
    $curl = new \curl($curloptions);

    $headers = ['Content-Type: application/json'];
    if (!empty($apikey)) {
        $headers[] = "Authorization: Bearer {$apikey}";
    }
    if ($provider === 'openrouter') {
        $headers[] = 'HTTP-Referer: https://sanad.ws';
        $headers[] = 'X-Title: Sanad Moodle Competency Report';
    }
    $curl->setHeader($headers);

    if ($provider === 'openrouter') {
        $endpoint = 'https://openrouter.ai/api/v1/chat/completions';
    } else if ($provider === 'deepseek') {
        $endpoint = 'https://api.deepseek.com/v1/chat/completions';
    } else if ($provider === 'groq') {
        $endpoint = 'https://api.groq.com/openai/v1/chat/completions';
    } else if ($provider === 'local') {
        $endpoint = get_config('local_comp_report_ext', 'local_endpoint') ?: 'http://localhost:11434/v1';
        if (strpos($endpoint, 'localhost') !== false || strpos($endpoint, 'host.docker.internal') !== false) {
            $ipfile = __DIR__ . '/host_ip.txt';
            if (file_exists($ipfile) && is_readable($ipfile)) {
                $dynamicip = trim(file_get_contents($ipfile));
                if (!empty($dynamicip) && filter_var($dynamicip, FILTER_VALIDATE_IP)) {
                    $endpoint = str_replace(['localhost', 'host.docker.internal'], $dynamicip, $endpoint);
                }
            }
        }
        $endpoint = rtrim($endpoint, '/');
        if (strpos($endpoint, '/chat/completions') === false) {
            $endpoint .= '/chat/completions';
        }
    } else {
        $endpoint = 'https://api.openai.com/v1/chat/completions';
    }

    $postdata = json_encode([
        'model'    => $model,
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are an expert educational psychologist and personal study coach. '
                    . 'Output only clean HTML — no markdown, no preamble.',
            ],
            ['role' => 'user', 'content' => $fullprompt],
        ],
    ]);

    \core_php_time_limit::raise(180);
    $response = $curl->post($endpoint, $postdata, [
        'timeout'   => 180,
        'ipresolve' => 1, // Force IPv4 to bypass Docker Desktop IPv6 404 proxy issues on Windows.
    ]);

    // Diagnose connection-level failures first.
    $curlinfo = $curl->get_info();
    $httpcode = isset($curlinfo['http_code']) ? (int)$curlinfo['http_code'] : 0;
    $curlerror = $curl->get_errno() ? $curl->error : '';

    if ($httpcode === 0 || !empty($curlerror)) {
        $detail = !empty($curlerror) ? $curlerror : 'No response from server';
        debugging("[local_comp_report_ext] Study plan cURL error: {$detail}", DEBUG_DEVELOPER);
        return get_string('ai_failed', 'local_comp_report_ext')
            . ' <small class="text-muted">(Connection error: ' . s($detail) . ')</small>';
    }

    $data = json_decode($response, true);

    if (json_last_error() === JSON_ERROR_NONE && !empty($data['choices'][0]['message']['content'])) {
        $content = $data['choices'][0]['message']['content'];
        return local_comp_report_ext_parse_progress_bars($content);
    }

    // Build a human-readable diagnostic from the API error response.
    $errormsg = '';
    if (is_array($data) && !empty($data['error']['message'])) {
        $errormsg = $data['error']['message'];
    } else if (is_array($data) && !empty($data['error'])) {
        $errormsg = is_string($data['error']) ? $data['error'] : json_encode($data['error']);
    } else if (!is_array($data)) {
        $errormsg = 'Invalid JSON response from AI provider';
    } else {
        $errormsg = 'Empty or unexpected response structure';
    }

    debugging("[local_comp_report_ext] Study plan HTTP {$httpcode}: {$errormsg}", DEBUG_DEVELOPER);

    return get_string('ai_failed', 'local_comp_report_ext')
        . ' <small class="text-muted">(HTTP ' . $httpcode . ': ' . s($errormsg) . ')</small>';
}


/**
 * Generates a structured rule-based comment list.
 *
 * @param array $stats
 * @return string
 */
function local_comp_report_ext_structured_comment(array $stats) {
    $text = html_writer::tag('b', get_string('generalcomment', 'local_comp_report_ext') . ":") . html_writer::empty_tag('br');

    foreach ($stats as $shortname => $rate) {
        $a = new \stdClass();
        $a->shortname = $shortname;
        $a->rate = $rate;
        if ($rate <= 39) {
            $text .= html_writer::tag('span', get_string('structured_red', 'local_comp_report_ext', $a), [
                'style' => 'color: red;',
            ]) . html_writer::empty_tag('br');
        } else if ($rate >= 40 && $rate <= 59) {
            $text .= html_writer::tag('span', get_string('structured_orange', 'local_comp_report_ext', $a), [
                'style' => 'color: orange;',
            ]) . html_writer::empty_tag('br');
        } else if ($rate >= 60 && $rate <= 79) {
            $text .= html_writer::tag('span', get_string('structured_blue', 'local_comp_report_ext', $a), [
                'style' => 'color: blue;',
            ]) . html_writer::empty_tag('br');
        } else if ($rate >= 80) {
            $text .= html_writer::tag('span', get_string('structured_green', 'local_comp_report_ext', $a), [
                'style' => 'color: green;',
            ]) . html_writer::empty_tag('br');
        }
    }

    return $text;
}

/**
 * Converts raw Markdown tables in LLM responses to beautiful, styled HTML tables.
 * Falls back safely if the text does not contain any markdown tables.
 *
 * @return string The parsed HTML with styled tables.
 */
function local_comp_report_ext_markdown_to_html_table($html) {
    if (strpos($html, '|') === false) {
        return $html;
    }

    $lines = explode("\n", $html);
    $intable = false;
    $tablehtml = "";
    $newlines = [];

    foreach ($lines as $line) {
        $trimmed = trim($line);
        // Check if this line is part of a markdown table.
        if (preg_match('/^\|(.*)\|$/', $trimmed, $matches)) {
            $cells = array_map('trim', explode('|', trim($matches[1])));

            // Check if this is a separator line (e.g. |---|---| or |:---:|).
            $isseparator = true;
            foreach ($cells as $cell) {
                if ($cell !== '' && !preg_match('/^:?-+:?$/', $cell)) {
                    $isseparator = false;
                    break;
                }
            }

            if ($isseparator) {
                continue; // Skip separator line.
            }

            if (!$intable) {
                $intable = true;
                $tablehtml = '<div class="table-responsive">'
                    . '<table class="table table-bordered table-striped table-hover mt-3 mb-3 bg-white" '
                    . 'style="border-radius: 8px; overflow: hidden; border-collapse: separate; '
                    . 'border-spacing: 0; border: 1px solid #dee2e6; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">';
                $tablehtml .= '<thead class="thead-light"><tr>';
                foreach ($cells as $cell) {
                    $tablehtml .= '<th class="font-weight-bold text-center align-middle" '
                        . 'style="padding: 12px; background-color: #e9ecef; border-bottom: 2px solid #dee2e6; '
                        . 'color: #495057;">' . $cell . '</th>';
                }
                $tablehtml .= '</tr></thead><tbody>';
            } else {
                $tablehtml .= '<tr>';
                $colidx = 0;
                foreach ($cells as $cell) {
                    // Center align session numbers and times, left align goals/activities.
                    $align = ($colidx == 0 || $colidx == 4) ? 'text-center' : 'text-left';
                    $bold = ($colidx == 0) ? 'font-weight-bold text-success' : '';
                    $tablehtml .= '<td class="' . $align . ' ' . $bold . ' align-middle" '
                        . 'style="padding: 11px; border-top: 1px solid #dee2e6;">' . $cell . '</td>';
                    $colidx++;
                }
                $tablehtml .= '</tr>';
            }
        } else {
            if ($intable) {
                $intable = false;
                $tablehtml .= '</tbody></table></div>';
                $newlines[] = $tablehtml;
                $tablehtml = "";
            }
            $newlines[] = $line;
        }
    }

    if ($intable) {
        $tablehtml .= '</tbody></table></div>';
        $newlines[] = $tablehtml;
    }

    return implode("\n", $newlines);
}

/**
 * Replaces [PROGRESSBAR: Name | Percent] placeholders in the AI response
 * with beautifully styled HTML progress tables.
 *
 * @param string $html
 * @return string
 */
function local_comp_report_ext_parse_progress_bars($html) {
    // Regex matches [PROGRESSBAR: Name | Percent%] or [PROGRESSBAR: Name | Percent] including floats.
    $pattern = '/\[PROGRESSBAR:\s*([^|\]]+)\s*\|\s*(\d+(?:\.\d+)?)%?\s*\]/i';

    return preg_replace_callback($pattern, function ($matches) {
        $name = trim($matches[1]);
        $percent = (float)$matches[2];
        if ($percent < 0) {
            $percent = 0.0;
        }
        if ($percent > 100) {
            $percent = 100.0;
        }
        $width = (int)round($percent);
        $remaining = 100 - $width;

        // Color coding.
        if ($percent >= 80.0) {
            $color = '#28a745'; // Green.
        } else if ($percent >= 60.0) {
            $color = '#007bff'; // Blue.
        } else if ($percent >= 40.0) {
            $color = '#ffc107'; // Yellow/Orange.
        } else {
            $color = '#dc3545'; // Red.
        }

        $output = '<div class="ai-progress-item" style="margin-top: 5px; margin-bottom: 8px;">';
        $output .= '<strong>' . s($name) . ' (' . $percent . '%)</strong>';
        $output .= '<table border="0" cellspacing="0" cellpadding="0" ' .
            'width="150" height="8" style="border: 1px solid #dee2e6; margin-top: 2px;">';
        $output .= '<tr>';
        if ($width > 0) {
            $output .= '<td bgcolor="' . $color . '" width="' . $width . '%">&nbsp;</td>';
        }
        if ($remaining > 0) {
            $output .= '<td bgcolor="#e9ecef" width="' . $remaining . '%">&nbsp;</td>';
        }
        $output .= '</tr>';
        $output .= '</table>';
        $output .= '</div>';

        return $output;
    }, $html);
}
