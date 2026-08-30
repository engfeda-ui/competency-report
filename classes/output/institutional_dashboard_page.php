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

namespace local_comp_report_ext\output;

use renderable;
use templatable;
use renderer_base;
use stdClass;

/**
 * Renderable class for the Institutional & Multi-Specialization Dashboard page.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class institutional_dashboard_page implements renderable, templatable {
    /** @var stdClass Dashboard data object */
    protected $data;

    /**
     * Constructor.
     *
     * @param stdClass $data Dashboard parameters and computed aggregates.
     */
    public function __construct(stdClass $data) {
        $this->data = $data;
    }

    /**
     * Export data for Mustache template.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        $d = new stdClass();

        $d->courseid               = $this->data->courseid;
        $d->has_data               = $this->data->has_data;
        $d->savemsg                = $this->data->savemsg;
        $d->sesskey                = $this->data->sesskey;

        // Course Selector & Group-Region Mapping data.
        $d->course_options         = $this->data->course_options;
        $d->selected_courses_count = $this->data->selected_courses_count;
        $d->all_groups             = $this->data->all_groups;
        $d->groups_count           = $this->data->groups_count;

        // KPI Summary Indicators.
        $d->total_students         = $this->data->total_students;
        $d->total_specializations  = $this->data->total_specializations;
        $d->total_regions          = $this->data->total_regions;
        $d->total_groups           = $this->data->total_groups;
        $d->overall_pass_rate      = $this->data->overall_pass_rate;
        $d->overall_avg_score      = $this->data->overall_avg_score;

        // Tables & Lists.
        $d->spec_list              = $this->data->spec_list;
        $d->region_list            = $this->data->region_list;
        $d->group_list             = $this->data->group_list;
        $d->student_list           = $this->data->student_list;

        // Chart JSON payloads.
        $d->spec_labels_json       = $this->data->spec_labels_json;
        $d->spec_pass_json         = $this->data->spec_pass_json;
        $d->spec_avg_json          = $this->data->spec_avg_json;

        $d->region_labels_json     = $this->data->region_labels_json;
        $d->region_pass_json       = $this->data->region_pass_json;
        $d->region_avg_json        = $this->data->region_avg_json;

        $d->student_list_json      = $this->data->student_list_json;

        // Strings for UI.
        $d->strexportexcel         = get_string('btn_export_master_excel', 'local_comp_report_ext');
        $d->strprintreport         = get_string('printreport', 'local_comp_report_ext');
        $d->strnodata              = get_string('no_data_dashboard', 'local_comp_report_ext');

        return $d;
    }
}
