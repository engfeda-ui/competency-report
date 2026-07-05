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
 * @package    local_competency_report
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
function local_competency_report_generate_comment(
    array $stats,
    $context = 'student',
    $customprompt = '',
    $focustype = 'competency'
) {
    if (!get_config('local_competency_report', 'enable_ai')) {
        return local_competency_report_rule_based_comment($stats);
    }

    // Generate unique cache key for the student grades, custom prompts and focus type.
    $statskeys = $stats;
    ksort($statskeys);
    // Updated cache key version suffix to bypass old cached failures.
    $cachekey = md5(json_encode($statskeys) . '_' . $context . '_' . md5($customprompt) . '_' . $focustype . '_v7');

    try {
        $cache = \cache::make('local_competency_report', 'ai_feedback');
        $cachedcomment = $cache->get($cachekey);
        if ($cachedcomment !== false) {
            return $cachedcomment;
        }
    } catch (\Exception $e) {
        $cache = null; // Fallback silently if cache is not initialized.
    }

    // Call AI comment function.
    $comment = local_competency_report_ai_comment($stats, $context, $customprompt, $focustype);

    // Save in cache if successful (not a failure and not unconfigured).
    $aifailedstr = get_string('ai_failed', 'local_competency_report');
    $ainotconfigstr = get_string('ai_not_configured', 'local_competency_report');
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
function local_competency_report_rule_based_comment(array $stats) {
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

    $text = html_writer::tag('b', get_string('generalcomment', 'local_competency_report') . ":") . html_writer::empty_tag('br');

    if ($red) {
        $text .= html_writer::tag('span', get_string('comment_red', 'local_competency_report', implode(', ', $red)), [
            'style' => 'color: red;',
        ]) . html_writer::empty_tag('br');
    }
    if ($orange) {
        $text .= html_writer::tag('span', get_string('comment_orange', 'local_competency_report', implode(', ', $orange)), [
            'style' => 'color: orange;',
        ]) . html_writer::empty_tag('br');
    }
    if ($blue) {
        $text .= html_writer::tag('span', get_string('comment_blue', 'local_competency_report', implode(', ', $blue)), [
            'style' => 'color: blue;',
        ]) . html_writer::empty_tag('br');
    }
    if ($green) {
        $text .= html_writer::tag('span', get_string('comment_green', 'local_competency_report', implode(', ', $green)), [
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
function local_competency_report_ai_comment(array $stats, $context = 'student', $customprompt = '', $focustype = 'competency') {
    global $CFG;
    require_once($CFG->libdir . '/filelib.php');

    $provider = get_config('local_competency_report', 'ai_provider');
    if (!$provider) {
        $provider = 'openai';
    }

    $apikey = get_config('local_competency_report', 'apikey');
    $model  = get_config('local_competency_report', 'model');

    if ($provider === 'openai' && (empty($apikey) || empty($model))) {
        return get_string('ai_not_configured', 'local_competency_report');
    }

    if ($provider === 'local' && empty($model)) {
        return get_string('ai_not_configured', 'local_competency_report');
    }

    // Configure prompt depending on focus (Competency vs. General Grades).
    if ($focustype === 'grades') {
        $systemprompt = "You are a professional pedagogical advisor.\n"
            . "Your task is to analyze the general quiz grades and exam scores of the student, group, "
            . "or course and write a highly structured, concise, and actionable pedagogical feedback report.\n"
            . "Follow these rules strictly:\n"
            . "1. Output format: Write directly in HTML. Use clean paragraphs, strong bold headers, "
            . "and bulleted lists.\n"
            . "   - For each subject/quiz analyzed, you MUST append a progress bar placeholder using this format:\n"
            . "     '[PROGRESSBAR: Subject Name | Score%]' (e.g. '[PROGRESSBAR: Quiz 1 | 85%]').\n"
            . "2. Tone: Extremely professional, encouraging, and direct.\n"
            . "3. Length: Keep it short, concise, and focused. Maximum 200 words.\n"
            . "4. Language: Write in English unless the custom instruction explicitly requests another language.\n"
            . "5. Structure:\n"
            . "   - <h4><strong>Exam Performance Summary</strong></h4> followed by a very brief summary "
            . "and the progress bar placeholders.\n"
            . "   - <h4><strong>Strengths & Progress</strong></h4> followed by bullet points.\n"
            . "   - <h4><strong>Recommendations & Next Steps</strong></h4> followed by bullet points.";

        $prompt = "Write a pedagogical analysis of the following general grade results for context: {$context}\n";
    } else {
        $systemprompt = "You are a professional pedagogical advisor.\n"
            . "Your task is to analyze the student or class competency success percentages and write "
            . "a highly structured, concise, and actionable pedagogical feedback report.\n"
            . "Follow these rules strictly:\n"
            . "1. Output format: Write directly in HTML. Use clean paragraphs, strong bold headers, "
            . "and bulleted lists.\n"
            . "   - For each competency analyzed, you MUST append a progress bar placeholder using this format:\n"
            . "     '[PROGRESSBAR: Competency Name | Score%]' (e.g. '[PROGRESSBAR: Communication | 85%]').\n"
            . "2. Tone: Extremely professional, encouraging, and direct.\n"
            . "3. Length: Keep it short, concise, and focused. Maximum 200 words.\n"
            . "4. Language: Write in English unless the custom instruction explicitly requests another language.\n"
            . "5. structure:\n"
            . "   - <h4><strong>Performance Overview</strong></h4> followed by a very brief summary "
            . "and the progress bar placeholders.\n"
            . "   - <h4><strong>Key Strengths</strong></h4> followed by bullet points.\n"
            . "   - <h4><strong>Areas for Development & Next Steps</strong></h4> followed by bullet points "
            . "with actionable next steps.";

        if ($context === 'school') {
            $prompt = get_string('ai_prompt_school', 'local_competency_report') . "\n";
        } else {
            $prompt = get_string('ai_prompt_student', 'local_competency_report') . "\n";
        }
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
    $curl->setHeader($headers);

    if ($provider === 'local') {
        $endpoint = get_config('local_competency_report', 'local_endpoint');
        if (empty($endpoint)) {
            $endpoint = 'http://localhost:11434/v1';
        }
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
        $endpoint = "https://api.openai.com/v1/chat/completions";
    }

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
        debugging("[local_competency_report] AI cURL error: {$detail} (endpoint: {$endpoint})", DEBUG_DEVELOPER);
        return get_string('ai_failed', 'local_competency_report')
            . ' <small class="text-muted">(Connection error: ' . s($detail) . ' | Endpoint: ' . s($endpoint) . ')</small>';
    }

    $data = json_decode($response, true);

    if (json_last_error() === JSON_ERROR_NONE && !empty($data['choices'][0]['message']['content'])) {
        $content = $data['choices'][0]['message']['content'];
        @file_put_contents(__DIR__ . '/ai_raw_response.txt', $content);
        return local_competency_report_parse_progress_bars($content);
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

    debugging("[local_competency_report] AI HTTP {$httpcode}: {$errormsg} (endpoint: {$endpoint})", DEBUG_DEVELOPER);

    return get_string('ai_failed', 'local_competency_report')
        . ' <small class="text-muted">(HTTP ' . $httpcode . ': ' . s($errormsg) . ' | Endpoint: ' . s($endpoint) . ')</small>';
}

/**
 * Generates a personalized AI remedial study plan using an enriched prompt.
 *
 * @param string $fullprompt The complete, pre-built prompt string.
 * @return string HTML study plan or error string.
 */
function local_competency_report_generate_study_plan($fullprompt) {
    global $CFG;
    require_once($CFG->libdir . '/filelib.php');

    $provider = get_config('local_competency_report', 'ai_provider') ?: 'openai';
    $apikey   = get_config('local_competency_report', 'apikey');
    $model    = get_config('local_competency_report', 'model');

    if ($provider === 'openai' && (empty($apikey) || empty($model))) {
        return get_string('ai_not_configured', 'local_competency_report');
    }
    if ($provider === 'local' && empty($model)) {
        return get_string('ai_not_configured', 'local_competency_report');
    }

    $curloptions = ($provider === 'local') ? ['ignoresecurity' => true] : [];
    $curl = new \curl($curloptions);

    $headers = ['Content-Type: application/json'];
    if (!empty($apikey)) {
        $headers[] = "Authorization: Bearer {$apikey}";
    }
    $curl->setHeader($headers);

    if ($provider === 'local') {
        $endpoint = get_config('local_competency_report', 'local_endpoint') ?: 'http://localhost:11434/v1';
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
        debugging("[local_competency_report] Study plan cURL error: {$detail}", DEBUG_DEVELOPER);
        return get_string('ai_failed', 'local_competency_report')
            . ' <small class="text-muted">(Connection error: ' . s($detail) . ')</small>';
    }

    $data = json_decode($response, true);

    if (json_last_error() === JSON_ERROR_NONE && !empty($data['choices'][0]['message']['content'])) {
        $content = $data['choices'][0]['message']['content'];
        @file_put_contents(__DIR__ . '/studyplan_raw_response.txt', $content);
        return local_competency_report_parse_progress_bars($content);
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

    debugging("[local_competency_report] Study plan HTTP {$httpcode}: {$errormsg}", DEBUG_DEVELOPER);

    return get_string('ai_failed', 'local_competency_report')
        . ' <small class="text-muted">(HTTP ' . $httpcode . ': ' . s($errormsg) . ')</small>';
}


/**
 * Generates a structured rule-based comment list.
 *
 * @param array $stats
 * @return string
 */
function local_competency_report_structured_comment(array $stats) {
    $text = html_writer::tag('b', get_string('generalcomment', 'local_competency_report') . ":") . html_writer::empty_tag('br');

    foreach ($stats as $shortname => $rate) {
        $a = new \stdClass();
        $a->shortname = $shortname;
        $a->rate = $rate;
        if ($rate <= 39) {
            $text .= html_writer::tag('span', get_string('structured_red', 'local_competency_report', $a), [
                'style' => 'color: red;',
            ]) . html_writer::empty_tag('br');
        } else if ($rate >= 40 && $rate <= 59) {
            $text .= html_writer::tag('span', get_string('structured_orange', 'local_competency_report', $a), [
                'style' => 'color: orange;',
            ]) . html_writer::empty_tag('br');
        } else if ($rate >= 60 && $rate <= 79) {
            $text .= html_writer::tag('span', get_string('structured_blue', 'local_competency_report', $a), [
                'style' => 'color: blue;',
            ]) . html_writer::empty_tag('br');
        } else if ($rate >= 80) {
            $text .= html_writer::tag('span', get_string('structured_green', 'local_competency_report', $a), [
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
function local_competency_report_markdown_to_html_table($html) {
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
function local_competency_report_parse_progress_bars($html) {
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
