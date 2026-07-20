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
 * Unified Course Master Report Page Renderable.
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
 * Renderable class for the Unified Course Master Report page.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_master_report_page implements renderable, templatable {
    /** @var stdClass The data to render. */
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
     * Export data for Mustache.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        $export = new stdClass();
        $export->courseid = $this->data->courseid;
        $export->coursename = $this->data->coursename;

        // Statistics.
        $export->stats = $this->data->stats;

        // Exams & Grades.
        $export->quizzes = $this->data->quizzes;

        // Course competencies.
        $export->competencies = $this->data->competencies;

        // Group Comparison Matrix.
        $export->matrix_headers = $this->data->matrix_headers;
        $export->matrix_rows = $this->data->matrix_rows;

        // Context details for the standard AI commentary widget.
        $export->context_type = 'course_master';

        return $export;
    }
}
