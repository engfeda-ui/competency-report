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
 * Renderable class for the student competency progress timeline.
 * Prepares time-series data for chart visualization.
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
 * Output class for student competency timeline page.
 *
 * @package    local_yetkinlik
 * @copyright  2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class timeline_page implements renderable, templatable {
    /** @var stdClass Data object containing timeline periods and competency datasets. */
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
        $export->courseid = $this->data->courseid;
        $export->selected_days = $this->data->days;

        // Define filter timeframes for the dropdown selection.
        $export->filter_options = [
            [
                'value' => 30,
                'label' => get_string('last30days', 'local_yetkinlik'),
                'selected' => ($this->data->days == 30),
            ],
            [
                'value' => 90,
                'label' => get_string('last90days', 'local_yetkinlik'),
                'selected' => ($this->data->days == 90),
            ],
            [
                'value' => 0,
                'label' => get_string('alltime', 'local_yetkinlik'),
                'selected' => ($this->data->days == 0),
            ],
        ];

        // Chart configuration to be passed as JSON to the JavaScript AMD module.
        $export->chart_config = json_encode([
            'labels'       => $this->data->periods,
            'datasets'     => $this->data->datasets,
            'successLabel' => get_string('successrate', 'local_yetkinlik'),
        ]);

        // Determine if there is sufficient data to render the visualization.
        $export->has_data = !empty($this->data->datasets);

        return $export;
    }
}
