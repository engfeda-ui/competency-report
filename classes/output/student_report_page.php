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
 * @package    local_yetkinlik
 * @copyright  2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_yetkinlik\output;

use renderable;
use templatable;
use renderer_base;
use stdClass;

/**
 * Output class for student report page.
 *
 * @package    local_yetkinlik
 * @copyright  2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_report_page implements renderable, templatable {
    /** @var stdClass Raw report data including database rows and context information. */
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

        foreach ($this->data->rows as $r) {
            // Calculate achievement rate as a percentage.
            $rate = $r->questions ? number_format(($r->correct / $r->questions) * 100, 1) : 0;

            // Define color codes for visual representation (Bootstrap compatible HEX codes).
            if ($rate >= 80) {
                $color = '#28a745'; // Green.
            } else if ($rate >= 60) {
                $color = '#007bff'; // Blue.
            } else if ($rate >= 40) {
                $color = '#fd7e14'; // Orange.
            } else {
                $color = '#dc3545'; // Red.
            }

            $row = new stdClass();
            $row->shortname = s($r->shortname);

            // Render description maintaining HTML formatting and context-aware filtering.
            $row->description = format_text($r->description, $r->descriptionformat, ['context' => $this->data->context]);

            $row->questions = (float)$r->questions;
            $row->correct = (float)$r->correct;
            $row->rate = $rate;
            $row->color = $color;

            $export->rows[] = $row;
        }

        // Assign additional report meta-data.
        $export->pdf_url = $this->data->pdf_url;
        $export->ai_comment = $this->data->ai_comment;
        $export->has_data = !empty($export->rows);

        return $export;
    }
}
