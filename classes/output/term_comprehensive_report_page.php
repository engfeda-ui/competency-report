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
 * Renderable class for the Term Comprehensive Report page.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class term_comprehensive_report_page implements renderable, templatable {
    /** @var stdClass Report data object */
    protected $data;

    /**
     * Constructor.
     *
     * @param stdClass $data Term report parameters and computed data.
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

        $d->courseid              = $this->data->courseid;
        $d->groupid               = $this->data->groupid;
        $d->groups                = $this->data->groups;
        $d->course_options        = $this->data->course_options ?? [];
        $d->is_all_courses        = $this->data->is_all_courses ?? false;
        $d->quizid                = $this->data->quizid ?? 0;
        $d->quizoptions           = $this->data->quizoptions ?? [];
        $d->quiz_name             = $this->data->quiz_name;
        $d->has_data              = $this->data->has_data;
        $d->has_custom_upload     = $this->data->has_custom_upload;
        $d->custom_upload_count   = $this->data->custom_upload_count;
        $d->sesskey               = $this->data->sesskey;

        $d->enrolled_count        = $this->data->enrolled_count;
        $d->passed_count          = $this->data->passed_count;
        $d->failed_count          = $this->data->failed_count;
        $d->pass_rate             = $this->data->pass_rate;
        $d->term_avg              = $this->data->term_avg;
        $d->theory_pass_rate      = $this->data->theory_pass_rate;
        $d->prac_pass_rate        = $this->data->prac_pass_rate;
        $d->retake1_count         = $this->data->retake1_count;
        $d->retake2_count         = $this->data->retake2_count;

        $d->student_list          = $this->data->student_list;
        $d->gpa_rows              = $this->data->gpa_rows;

        $d->gpa_labels_json       = $this->data->gpa_labels_json;
        $d->gpa_counts_json       = $this->data->gpa_counts_json;
        $d->student_list_json     = $this->data->student_list_json;

        // Language strings for template.
        $d->strselectgroup        = get_string('selectgroup', 'local_comp_report_ext');
        $d->strshowstudents       = get_string('showstudents', 'local_comp_report_ext');
        $d->strprintreport        = get_string('printreport', 'local_comp_report_ext');
        $d->strexportexcel        = get_string('btn_export_term_excel', 'local_comp_report_ext');
        $d->strnodata             = get_string('no_data_dashboard', 'local_comp_report_ext');

        return $d;
    }
}
