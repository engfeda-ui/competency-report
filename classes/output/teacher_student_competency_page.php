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
 * Renderable class for teacher's view of student-specific competency performance.
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
 * Output class for teacher's student competency performance report.
 *
 * @package    local_yetkinlik
 * @copyright  2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teacher_student_competency_page implements renderable, templatable {
    /** @var stdClass Data object passed from the script. */
    protected $data;

    /** @var \moodleform The filter form instance. */
    protected $mform;

    /**
     * Constructor.
     *
     * @param stdClass $data The data to be rendered.
     * @param \moodleform $mform The filter form.
     */
    public function __construct($data, $mform) {
        $this->data = $data;
        $this->mform = $mform;
    }

    /**
     * Export data for the Mustache template.
     *
     * @param renderer_base $output The renderer base.
     * @return stdClass The data ready for the template.
     */
    public function export_for_template(renderer_base $output) {
        $export = new stdClass();
        $export->form_html = $this->mform->render();
        $export->competencies = $this->data->competencies;
        $export->has_selection = ($this->data->userid > 0 && $this->data->competencyid > 0);
        if ($export->has_selection) {
            $export->description = $this->data->description;
        }
        $export->has_rows = !empty($this->data->rows);
        $export->has_questions = !empty($this->data->questiondetails);
        $export->rows = $this->data->rows;
        $export->questiondetails = $this->data->questiondetails;
        $export->total = $this->data->total ?? null;

        return $export;
    }
}
