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
 * Renderable class for the Group Exam & Grade Analytics page.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class group_exam_analytics_page implements renderable, templatable {
    /** @var stdClass Dashboard data object */
    protected $data;

    /**
     * Constructor.
     *
     * @param stdClass $data Dashboard parameters and precomputed data.
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
        $d->has_data              = $this->data->has_data;
        $d->quiz_name             = $this->data->quiz_name;
        $d->exam_avg              = $this->data->exam_avg;
        $d->pass_rate             = $this->data->pass_rate;
        $d->highest_score         = $this->data->highest_score;
        $d->lowest_score          = $this->data->lowest_score;

        $d->histogram_labels_json = $this->data->histogram_labels_json;
        $d->histogram_data_json   = $this->data->histogram_data_json;
        $d->tier_data_json        = $this->data->tier_data_json;
        $d->item_labels_json      = $this->data->item_labels_json;
        $d->item_difficulty_json  = $this->data->item_difficulty_json;
        $d->item_discrim_json     = $this->data->item_discrim_json;

        $d->student_list          = $this->data->student_list;
        $d->student_list_json     = $this->data->student_list_json;

        // Common strings.
        $d->strselectgroup        = get_string('selectgroup', 'local_comp_report_ext');
        $d->strshowstudents       = get_string('showstudents', 'local_comp_report_ext');
        $d->strexportpdf          = get_string('group_analytics_dashboard_pdf', 'local_comp_report_ext');
        $d->strnodata             = get_string('no_data_dashboard', 'local_comp_report_ext');

        return $d;
    }
}
