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
 * @package    local_yetkinlik
 * @copyright  2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Main function to generate comments based on competency stats.
 *
 * @param array  $stats   The competency shortname and success rates.
 * @param string $context The context of the comment (student or school).
 * @return string
 */
function local_yetkinlik_generate_comment(array $stats, $context = 'student') {
    if (!get_config('local_yetkinlik', 'enable_ai')) {
        return local_yetkinlik_rule_based_comment($stats);
    }
    // Call AI comment function.
    return local_yetkinlik_ai_comment($stats, $context);
}

/**
 * Generates rule-based comments when AI is disabled.
 *
 * @param array $stats
 * @return string
 */
function local_yetkinlik_rule_based_comment(array $stats) {
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

    $text = html_writer::tag('b', get_string('generalcomment', 'local_yetkinlik') . ":") . html_writer::empty_tag('br');

    if ($red) {
        $text .= html_writer::tag('span', get_string('comment_red', 'local_yetkinlik', implode(', ', $red)), [
            'style' => 'color: red;',
        ]) . html_writer::empty_tag('br');
    }
    if ($orange) {
        $text .= html_writer::tag('span', get_string('comment_orange', 'local_yetkinlik', implode(', ', $orange)), [
            'style' => 'color: orange;',
        ]) . html_writer::empty_tag('br');
    }
    if ($blue) {
        $text .= html_writer::tag('span', get_string('comment_blue', 'local_yetkinlik', implode(', ', $blue)), [
            'style' => 'color: blue;',
        ]) . html_writer::empty_tag('br');
    }
    if ($green) {
        $text .= html_writer::tag('span', get_string('comment_green', 'local_yetkinlik', implode(', ', $green)), [
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
function local_yetkinlik_ai_comment(array $stats, $context = 'student') {
    global $CFG;
    require_once($CFG->libdir . '/filelib.php');

    $apikey = get_config('local_yetkinlik', 'apikey');
    $model  = get_config('local_yetkinlik', 'model');

    if (empty($apikey) || empty($model)) {
        return get_string('ai_not_configured', 'local_yetkinlik');
    }

    // Prompt selection.
    if ($context === 'school') {
        $prompt = get_string('ai_prompt_school', 'local_yetkinlik') . "\n";
    } else {
        $prompt = get_string('ai_prompt_student', 'local_yetkinlik') . "\n";
    }

    foreach ($stats as $k => $v) {
        $prompt .= "{$k}: %{$v}\n";
    }

    $curl = new \curl();
    $headers = [
        "Authorization: Bearer {$apikey}",
        "Content-Type: application/json",
    ];

    $postdata = json_encode([
        "model" => $model,
        "messages" => [
            [
                "role" => "system",
                "content" => get_string('ai_system_prompt', 'local_yetkinlik'),
            ],
            [
                "role" => "user",
                "content" => $prompt,
            ],
        ],
    ]);

    $options = [
        'httpheader' => $headers,
        'timeout'    => 30,
    ];

    $response = $curl->post("https://api.openai.com/v1/chat/completions", $postdata, $options);
    $data = json_decode($response, true);

    if (json_last_error() === JSON_ERROR_NONE && !empty($data['choices'][0]['message']['content'])) {
        return $data['choices'][0]['message']['content'];
    }

    return get_string('ai_failed', 'local_yetkinlik');
}

/**
 * Generates a structured rule-based comment list.
 *
 * @param array $stats
 * @return string
 */
function local_yetkinlik_structured_comment(array $stats) {
    $text = html_writer::tag('b', get_string('generalcomment', 'local_yetkinlik') . ":") . html_writer::empty_tag('br');

    foreach ($stats as $shortname => $rate) {
        $a = ['shortname' => $shortname, 'rate' => $rate];
        if ($rate <= 39) {
            $text .= html_writer::tag('span', get_string('structured_red', 'local_yetkinlik', $a), [
                'style' => 'color: red;',
            ]) . html_writer::empty_tag('br');
        } else if ($rate >= 40 && $rate <= 59) {
            $text .= html_writer::tag('span', get_string('structured_orange', 'local_yetkinlik', $a), [
                'style' => 'color: orange;',
            ]) . html_writer::empty_tag('br');
        } else if ($rate >= 60 && $rate <= 79) {
            $text .= html_writer::tag('span', get_string('structured_blue', 'local_yetkinlik', $a), [
                'style' => 'color: blue;',
            ]) . html_writer::empty_tag('br');
        } else if ($rate >= 80) {
            $text .= html_writer::tag('span', get_string('structured_green', 'local_yetkinlik', $a), [
                'style' => 'color: green;',
            ]) . html_writer::empty_tag('br');
        }
    }

    return $text;
}
