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
 * Renderable class for the student's general competency overview report.
 *
 * Updated to support the new Score Card section (exam results + competency breakdown).
 *
 * @package    local_competency_report
 * @copyright  2026 Mahmoud Salem
 * @copyright  based on work by 2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_competency_report\output;

use renderable;
use templatable;
use renderer_base;
use stdClass;

/**
 * Output class for student report page.
 *
 * @package    local_competency_report
 * @copyright  2026 Mahmoud Salem
 * @copyright  based on work by 2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_report_page implements renderable, templatable {
    /** @var stdClass Raw report data. */
    protected $data;

    /**
     * Constructor
     *
     * @param stdClass $data
     */
    public function __construct($data) {
        $this->data = $data;
    }

    /**
     * Export data for the Mustache template.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        $export = new stdClass();
        $export->rows = [];

        // ── Legacy competency rows (radar chart + old table) ───────────────────
        foreach ($this->data->rows as $r) {
            $rate = isset($r->percent)
                ? (float)$r->percent
                : ($r->questions ? (float)(($r->correct / $r->questions) * 100) : 0);

            if ($rate >= 80) {
                $color = '#28a745';
            } else if ($rate >= 60) {
                $color = '#007bff';
            } else if ($rate >= 40) {
                $color = '#fd7e14';
            } else {
                $color = '#dc3545';
            }

            $row              = new stdClass();
            $row->shortname   = s($r->shortname);
            $row->description = format_text($r->description, $r->descriptionformat, ['context' => $this->data->context]);
            $row->questions   = isset($r->questions) ? (float)$r->questions : 0;
            $row->correct     = isset($r->correct)   ? (float)$r->correct   : 0;
            $row->rate        = number_format($rate, 1);
            $row->color       = $color;

            $export->rows[] = $row;
        }

        // ── Score Card: exam results rows ──────────────────────────────────────
        $export->examrows      = $this->data->examrows ?? [];
        $export->has_examdata  = !empty($export->examrows);

        // ── Score Card: competency breakdown rows ──────────────────────────────
        $export->comprows      = $this->data->comprows ?? [];
        $export->hasweights    = !empty($this->data->hasweights) && $this->data->hasweights;
        $export->has_compdata  = !empty($export->comprows);

        // ── Meta ───────────────────────────────────────────────────────────────
        $export->pdf_url        = $this->data->pdf_url;
        $export->ai_comment     = $this->data->ai_comment;
        $export->has_data       = !empty($export->rows) || !empty($export->comprows);
        $export->courseid       = $this->data->courseid;
        $export->userid         = $this->data->userid;
        $export->context_type   = 'student';
        $export->active_reportcard = true;
        $export->chart_data     = $this->data->chart_data ?? '{}';
        $export->has_radar      = !empty($this->data->has_radar) && $this->data->has_radar;

        return $export;
    }
}
