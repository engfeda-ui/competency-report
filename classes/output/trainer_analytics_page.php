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
 * Renderable class for the Trainer Performance Analytics page.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class trainer_analytics_page implements renderable, templatable {
    /** @var stdClass Dashboard data object */
    protected $data;

    /**
     * Constructor.
     *
     * @param stdClass $data Trainer analytics parameters and precomputed data.
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
        $d->course_btn_label      = $this->data->course_btn_label ?? '';
        $d->selected_courses_count= $this->data->selected_courses_count ?? count($d->course_options);
        $d->is_all_courses        = $this->data->is_all_courses ?? false;
        $d->is_single_course      = $this->data->is_single_course ?? false;
        $d->has_data              = $this->data->has_data;
        $d->trainer_count         = $this->data->trainer_count;

        $d->trainer_list          = $this->data->trainer_list;
        $d->trainer_names_json    = $this->data->trainer_names_json;
        $d->trainer_mastery_json  = $this->data->trainer_mastery_json;
        $d->trainer_pass_json     = $this->data->trainer_pass_json;
        $d->trainer_list_json     = $this->data->trainer_list_json;

        // Common strings.
        $d->strselectgroup        = get_string('selectgroup', 'local_comp_report_ext');
        $d->strshowstudents       = get_string('showstudents', 'local_comp_report_ext');
        $d->strprintreport        = get_string('printreport', 'local_comp_report_ext');
        $d->strnotrainers         = get_string('no_trainers_found', 'local_comp_report_ext');

        return $d;
    }
}
