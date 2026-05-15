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
 * Report for competency analysis based on school-wide or course-specific data.
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
use moodle_url;

/**
 * Renderable class for the school-wide competency report.
 *
 * @package    local_yetkinlik
 * @copyright  2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class school_report_page implements renderable, templatable {
    /** @var stdClass Data to be rendered */
    protected $data;

    /**
     * Constructor
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
        $export->has_data = !empty($this->data->rows);
        $export->courseid = $this->data->courseid;
        $export->pdf_url = (new moodle_url('/local/yetkinlik/school_pdf.php', ['courseid' => $this->data->courseid]))->out(false);

        if ($export->has_data) {
            $export->rows = [];
            foreach ($this->data->rows as $r) {
                // 1. Calculate success rate and format (e.g., 75.4).
                $rawrate = $r->attempts ? ($r->correct / $r->attempts) * 100 : 0;
                $formattedrate = number_format($rawrate, 1);

                // 2. Format question and correct counts (e.g., 1,250).
                // Using 0 decimals for attempts; formatting follows localization.
                $formattedattempts = number_format($r->attempts, 0);
                $formattedcorrect  = number_format($r->correct, 1);

                // Determine CSS color class for the table row based on the success rate.
                $rowclass = 'table-danger';
                if ($rawrate >= 70) {
                    $rowclass = 'table-success';
                } else if ($rawrate >= 50) {
                    $rowclass = 'table-warning';
                }

                $export->rows[] = [
                    'shortname'   => $r->shortname,
                    'description' => format_text($r->description, FORMAT_HTML),
                    'attempts'    => $formattedattempts,
                    'correct'     => $formattedcorrect,
                    'rate'        => $formattedrate,
                    'rowclass'    => $rowclass,
                ];
            }

            // AI Commentary Section.
            if (!empty($this->data->comment)) {
                $export->ai_comment = [
                    'title'   => get_string('generalcomment', 'local_yetkinlik'),
                    'content' => format_text($this->data->comment, FORMAT_HTML),
                ];
            }
        }

        return $export;
    }
}
