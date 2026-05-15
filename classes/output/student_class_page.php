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
 * Renderable class for student performance analysis compared with class/course data.
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
 * Output class for student class comparison page.
 *
 * @package    local_yetkinlik
 * @copyright  2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_class_page implements renderable, templatable {
    /** @var stdClass Data object containing performance statistics. */
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
        $export->has_data = !empty($this->data->coursedata);

        if ($export->has_data) {
            $export->rows = [];
            $labels = [];
            $courserates = [];
            $classrates = [];
            $myrates = [];

            foreach ($this->data->coursedata as $cid => $c) {
                // Calculate success rates for course, class, and individual student.
                $courserate = $c->attempts ? round(($c->correct / $c->attempts) * 100, 1) : 0;

                $classrate = (isset($this->data->classdata[$cid]) && $this->data->classdata[$cid]->attempts)
                    ? round(($this->data->classdata[$cid]->correct / $this->data->classdata[$cid]->attempts) * 100, 1) : 0;

                $myrate = (isset($this->data->studentdata[$cid]) && $this->data->studentdata[$cid]->attempts)
                    ? round(($this->data->studentdata[$cid]->correct / $this->data->studentdata[$cid]->attempts) * 100, 1) : 0;

                // Color coding based on whether the student's rate is above or below the course average.
                $colorclass = ($myrate >= $courserate) ? 'text-success' : 'text-danger';

                $export->rows[] = [
                    'shortname'  => $c->shortname,
                    'courserate' => $courserate,
                    'classrate'  => $classrate,
                    'myrate'     => $myrate,
                    'colorclass' => $colorclass,
                ];

                // Prepare arrays for visual chart data.
                $labels[] = $c->shortname;
                $courserates[] = $courserate;
                $classrates[] = $classrate;
                $myrates[] = $myrate;
            }

            // Encode chart configuration into JSON to be passed via data-attribute to AMD module.
            $export->chart_config = json_encode([
                'labels'     => $labels,
                'courseData' => $courserates,
                'classData'  => $classrates,
                'myData'     => $myrates,
                'labelNames' => [
                    'course' => get_string('courseavg', 'local_yetkinlik'),
                    'class'  => get_string('classavg', 'local_yetkinlik'),
                    'my'     => get_string('myavg', 'local_yetkinlik'),
                ],
            ]);
        }

        return $export;
    }
}
