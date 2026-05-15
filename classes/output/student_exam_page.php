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
 * Renderable class for student exam-based competency analysis page.
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
 * Output class for student exam competency page.
 *
 * @package    local_yetkinlik
 * @copyright  2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_exam_page implements renderable, templatable {
    /** @var stdClass Data object containing raw statistics from the database. */
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

        // Data for the quiz selection form.
        $export->courseid = $this->data->courseid;
        $export->quizid = $this->data->quizid;
        $export->quizzes = $this->data->quizzes;

        // Report and visualization data.
        $export->has_data = !empty($this->data->rows);
        $export->rows = [];

        if ($export->has_data) {
            $labels = [];
            $chartdata = [];
            $bgcolors = [];

            foreach ($this->data->rows as $r) {
                // Calculate competency achievement rate.
                $rate = $r->attempts ? number_format(($r->correct / $r->attempts) * 100, 1) : 0;

                // Determine hex colors based on performance thresholds.
                if ($rate >= 80) {
                    $color = '#28a745'; // Success Green.
                } else if ($rate >= 60) {
                    $color = '#007bff'; // Primary Blue.
                } else if ($rate >= 40) {
                    $color = '#fd7e14'; // Warning Orange.
                } else {
                    $color = '#dc3545'; // Danger Red.
                }

                $export->rows[] = [
                    'shortname' => $r->shortname,
                    'description' => format_text($r->description, FORMAT_HTML),
                    'rate' => $rate,
                    'color' => $color,
                ];

                // Prepare arrays for Chart.js configuration.
                $labels[] = $r->shortname;
                $chartdata[] = $rate;
                $bgcolors[] = $color;
            }

            // Encode chart configuration to be passed to the AMD module.
            $export->chart_config = json_encode([
                'labels'     => $labels,
                'chartData'  => $chartdata,
                'bgColors'   => $bgcolors,
                'chartLabel' => get_string('successpercent', 'local_yetkinlik') . ' (%)',
            ]);
        }

        return $export;
    }
}
