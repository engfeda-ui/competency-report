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
 * Output renderable for the modern institutional competency dashboard.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_comp_report_ext\output;

use renderable;
use templatable;
use renderer_base;
use stdClass;

/**
 * Renderable class for the school-wide institutional competency report.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class school_report_page implements renderable, templatable {
    /** @var stdClass Data to be rendered */
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
        $export->has_data                  = $this->data->has_data;
        $export->categoryid                = $this->data->categoryid;
        $export->categories                = $this->data->categories;
        $export->total_courses             = $this->data->total_courses;
        $export->total_students            = $this->data->total_students;
        $export->total_competencies        = $this->data->total_competencies;
        $export->overall_mastery           = $this->data->overall_mastery;
        $export->courses                   = $this->data->courses;
        $export->top_competencies          = $this->data->top_competencies;
        $export->lowest_competencies       = $this->data->lowest_competencies;
        $export->has_top_competencies      = !empty($this->data->top_competencies);
        $export->has_lowest_competencies   = !empty($this->data->lowest_competencies);
        $export->chart_courses_labels_json = $this->data->chart_courses_labels_json;
        $export->chart_courses_data_json   = $this->data->chart_courses_data_json;
        $export->chart_dist_data_json      = $this->data->chart_dist_data_json;
        $export->pdf_url                   = $this->data->pdf_url;

        return $export;
    }
}
