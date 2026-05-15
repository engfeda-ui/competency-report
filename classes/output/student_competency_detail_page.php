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
 * Renderable class for detailed competency report of a specific student.
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
 * Output class for student competency detail page.
 *
 * @package    local_yetkinlik
 * @copyright  2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_competency_detail_page implements renderable, templatable {
    /** @var stdClass Raw data to be processed for the template. */
    protected $data;

    /**
     * Constructor.
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
            // Calculate success rate as a percentage.
            $rate = $r->questions ? number_format(($r->correct / $r->questions) * 100, 1) : 0;

            // Define visual indicators (colors) based on performance thresholds.
            if ($rate >= 80) {
                $color = 'green';
            } else if ($rate >= 60) {
                $color = 'blue';
            } else if ($rate >= 40) {
                $color = 'orange';
            } else {
                $color = 'red';
            }

            $row = new stdClass();
            $row->shortname = $r->shortname;

            // Clean up description: decode HTML entities and strip tags for clean text rendering.
            $row->description = strip_tags(html_entity_decode($r->description, ENT_QUOTES, 'UTF-8'));

            $row->questions = (float)$r->questions;
            $row->correct = (float)$r->correct;
            $row->rate = $rate;
            $row->color = $color;

            $export->rows[] = $row;
        }

        // Pass supplementary data to the export object.
        $export->pdf_url = $this->data->pdf_url;
        $export->ai_comment = $this->data->ai_comment;
        $export->has_data = !empty($export->rows);

        return $export;
    }
}
