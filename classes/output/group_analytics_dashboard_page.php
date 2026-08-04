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
 * Renderable for the Group Analytics Dashboard page.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_comp_report_ext\output;

use renderable;
use renderer_base;
use templatable;
use stdClass;

/**
 * Renderable for the group analytics dashboard page.
 *
 * @package local_comp_report_ext
 */
class group_analytics_dashboard_page implements renderable, templatable {
    /** @var stdClass */
    private $data;

    /**
     * Constructor.
     *
     * @param stdClass $data Page data.
     */
    public function __construct(stdClass $data) {
        $this->data = $data;
    }

    /**
     * Export data for the Mustache template.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        $d = new stdClass();
        $d->courseid           = $this->data->courseid;
        $d->groupid            = $this->data->groupid;
        $d->groups             = $this->data->groups;
        $d->has_data           = $this->data->has_data;
        $d->avg_mastery        = $this->data->avg_mastery;
        $d->remediation_rate   = $this->data->remediation_rate;
        $d->top_strength       = $this->data->top_strength;
        $d->critical_gap       = $this->data->critical_gap;

        $d->radar_labels_json     = $this->data->radar_labels_json;
        $d->radar_data_json       = $this->data->radar_data_json;
        $d->dist_data_json        = $this->data->dist_data_json;
        $d->histogram_labels_json = $this->data->histogram_labels_json;
        $d->histogram_data_json   = $this->data->histogram_data_json;
        $d->gap_labels_json       = $this->data->gap_labels_json;
        $d->gap_theory_json       = $this->data->gap_theory_json;
        $d->gap_practice_json     = $this->data->gap_practice_json;

        $d->student_list          = $this->data->student_list;
        $d->student_list_json     = $this->data->student_list_json;

        // Common strings.
        $d->strselectgroup     = get_string('selectgroup', 'local_comp_report_ext');
        $d->strshowstudents    = get_string('showstudents', 'local_comp_report_ext');
        $d->strnodata          = get_string('no_data_dashboard', 'local_comp_report_ext');
        $d->strexportpdf       = get_string('exportpdf', 'local_comp_report_ext');

        // Fields needed by the ai_commentary_widget partial.
        $d->userid        = 0;
        $d->context_type  = 'group';

        return $d;
    }
}
