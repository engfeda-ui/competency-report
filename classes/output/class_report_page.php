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
 * Class Report for Competency Matching output class.
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
 * Renderable page class for the competency class report.
 *
 * @package    local_yetkinlik
 * @copyright  2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class class_report_page implements renderable, templatable {
    /** @var stdClass Data to be rendered */
    protected $data;

    /** @var \moodleform The filter form */
    protected $mform;

    /**
     * Constructor
     *
     * @param stdClass $data
     * @param \moodleform $mform
     */
    public function __construct($data, $mform) {
        $this->data = $data;
        $this->mform = $mform;
    }

    /**
     * Export data for the Mustache template.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        $export = new stdClass();

        // Render and pass the form HTML.
        $export->form_html = $this->mform->render();

        // PDF report URL.
        $pdfurl = new \moodle_url('/local/yetkinlik/pdf_report.php', ['courseid' => $this->data->courseid]);
        $export->pdf_url = $pdfurl->out(false);

        // Table data.
        $export->has_data = !empty($this->data->rows);
        $export->rows = $this->data->rows;

        // JS Chart data (sending as JSON string is safer for AMD modules).
        $export->chart_config = json_encode($this->data->chart_params);

        return $export;
    }
}
