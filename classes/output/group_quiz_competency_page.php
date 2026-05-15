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
 * Report for competency analysis based on group and quiz selection.
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
 * Output class for group and quiz competency report page.
 *
 * @package    local_yetkinlik
 * @copyright  2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class group_quiz_competency_page implements renderable, templatable {
    /** @var int The course ID. */
    protected $courseid;

    /** @var int The group ID. */
    protected $groupid;

    /** @var int The quiz ID. */
    protected $quizid;

    /** @var stdClass The data to be rendered. */
    protected $data;

    /**
     * Constructor.
     *
     * @param int $courseid
     * @param int $groupid
     * @param int $quizid
     * @param stdClass $data
     */
    public function __construct($courseid, $groupid, $quizid, $data) {
        $this->courseid = $courseid;
        $this->groupid = $groupid;
        $this->quizid = $quizid;
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
        $export->courseid = $this->courseid;
        $export->groupid = $this->groupid;
        $export->quizid = $this->quizid;
        $export->has_data = ($this->groupid > 0 && $this->quizid > 0);

        $export->groups = !empty($this->data->groups) ? array_values((array)$this->data->groups) : [];
        $export->quizzes = !empty($this->data->quizzes) ? array_values((array)$this->data->quizzes) : [];
        $export->competencies = !empty($this->data->competencies) ? array_values((array)$this->data->competencies) : [];
        $export->students = !empty($this->data->students) ? array_values((array)$this->data->students) : [];
        $export->totals = !empty($this->data->totals) ? array_values((array)$this->data->totals) : [];

        return $export;
    }
}
