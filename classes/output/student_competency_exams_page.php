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
 * Renderable class for student competency exam analysis page.
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
 * Output class for student competency exams page.
 *
 * @package    local_yetkinlik
 * @copyright  2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_competency_exams_page implements renderable, templatable {
    /** @var stdClass Data object passed from the script. */
    protected $data;

    /**
     * Constructor.
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
        $export->courseid = $this->data->courseid;
        $export->competencyid = $this->data->competencyid;
        $export->competencies = $this->data->competencies;
        $export->has_selection = ($this->data->competencyid > 0);

        if ($export->has_selection) {
            $export->description = $this->data->description;
            $export->rows = [];
            $totalq = 0;
            $totalc = 0;

            foreach ($this->data->rows as $r) {
                // Calculate success rate for each quiz attempt.
                $rate = $r->questions ? number_format(($r->correct / $r->questions) * 100, 1) : 0;

                // Define Bootstrap text color classes based on success thresholds.
                $colorclass = 'text-danger';
                if ($rate >= 80) {
                    $colorclass = 'text-success';
                } else if ($rate >= 60) {
                    $colorclass = 'text-primary';
                } else if ($rate >= 40) {
                    $colorclass = 'text-warning';
                }

                $export->rows[] = [
                    'quizname' => $r->quizname,
                    'quizurl' => $r->quizurl,
                    'questions' => (float) $r->questions,
                    'correct' => number_format($r->correct, 1),
                    'rate' => $rate,
                    'colorclass' => $colorclass,
                ];

                $totalq += $r->questions;
                $totalc += (float) $r->correct;
            }

            // Generate aggregated total data for the table footer.
            if (!empty($export->rows)) {
                $totalrate = $totalq ? number_format(($totalc / $totalq) * 100, 1) : 0;
                $export->total = [
                    'questions' => $totalq,
                    'correct' => number_format($totalc, 1),
                    'rate' => $totalrate,
                    'colorclass' => ($totalrate >= 80) ? 'text-success' : (($totalrate >= 40) ? 'text-warning' : 'text-danger'),
                ];
            }

            // Export detailed question links for the modal viewer.
            $export->has_questions = !empty($this->data->questiondetails);
            $export->questiondetails = $this->data->questiondetails;
        }

        return $export;
    }
}
