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
 * Renderable class for teacher's student-exam analysis view.
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
 * Class teacher_student_exam_page.
 *
 * This class handles the data exportation for the teacher student exam Mustache template.
 *
 * @package    local_yetkinlik
 * @copyright  2026 Hakan Çiğci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teacher_student_exam_page implements renderable, templatable {
    /** @var stdClass Data object containing calculation results. */
    protected $data;

    /** @var \moodleform The filter form instance. */
    protected $mform;

    /**
     * Constructor.
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

        // Render the Moodle filter form into HTML.
        $export->form_html = $this->mform->render();

        // Selection and row status flags.
        $export->has_selection = ($this->data->userid > 0 && $this->data->quizid > 0);
        $export->has_rows = !empty($this->data->rows);
        $export->rows = $this->data->rows;

        // Prepare chart configuration.
        $labels = [];
        $values = [];
        foreach ($this->data->rows as $row) {
            $labels[] = $row->shortname;
            $values[] = $row->raw_rate;
        }

        $export->chart_config = json_encode([
            'labels' => $labels,
            'values' => $values,
            'label'  => get_string('successrate', 'local_yetkinlik') . ' %',
        ]);

        return $export;
    }
}
