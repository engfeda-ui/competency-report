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
 * Renderable for the Assessment Setup page.
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
 * Renderable for the assessment setup page.
 *
 * @package local_comp_report_ext
 */
class assessment_setup_page implements renderable, templatable {
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
        $d->courseid    = $this->data->courseid;
        $d->sesskey     = $this->data->sesskey;
        $d->totalweight = $this->data->totalweight;
        $d->weightok    = abs($this->data->totalweight - 100) <= 0.01;

        // Assessments list (for the saved rows table).
        $d->assessments = [];
        foreach ($this->data->assessments as $a) {
            $row              = new stdClass();
            $row->id          = $a->id;
            $row->name        = $a->name;
            $row->type        = $a->type;
            $row->typelabel   = ($a->type === 'practical')
                ? get_string('typepractical', 'local_comp_report_ext')
                : get_string('typequiz', 'local_comp_report_ext');
            $row->weight      = $a->weight;
            $row->quizid      = $a->quizid;
            $row->assignid    = $a->assignid;

            global $DB;
            if ($a->type === 'quiz' && $a->quizid) {
                $quiz = $DB->get_record('quiz', ['id' => $a->quizid], 'name');
                $row->associatedactivity = $quiz ? $quiz->name : 'Unknown Quiz';
            } else if ($a->type === 'practical' && $a->assignid) {
                $assign = $DB->get_record('assign', ['id' => $a->assignid], 'name');
                $row->associatedactivity = $assign ? $assign->name : 'Unknown Assignment';
            } else {
                $row->associatedactivity = '—';
            }

            $row->deleteurl   = (new \moodle_url('/local/comp_report_ext/assessment_setup.php', [
                'courseid' => $this->data->courseid,
                'action'   => 'delete',
                'deleteid' => $a->id,
                'sesskey'  => $this->data->sesskey,
            ]))->out(false);
            $d->assessments[] = $row;
        }
        $d->hasassessments = !empty($d->assessments);

        // Quiz list for the "add quiz assessment" dropdown.
        $d->quizzes = [];
        foreach ($this->data->quizzes as $q) {
            $item        = new stdClass();
            $item->id    = $q->id;
            $item->name  = $q->name;
            $d->quizzes[] = $item;
        }
        $d->hasquizzes = !empty($d->quizzes);

        // Assignment list.
        $d->assignments = [];
        if (!empty($this->data->assignments)) {
            foreach ($this->data->assignments as $as) {
                $item        = new stdClass();
                $item->id    = $as->id;
                $item->name  = $as->name;
                $d->assignments[] = $item;
            }
        }
        $d->hasassignments = !empty($d->assignments);

        // Setup form action URL.
        $d->formaction = (new \moodle_url('/local/comp_report_ext/assessment_setup.php', [
            'courseid' => $this->data->courseid,
        ]))->out(false);

        $d->straddquiz       = get_string('addquizassessment', 'local_comp_report_ext');
        $d->straddpractical  = get_string('addpracticalassessment', 'local_comp_report_ext');
        $d->strsave          = get_string('savechanges');
        $d->strweight        = get_string('weight', 'local_comp_report_ext');
        $d->strname          = get_string('assessmentname', 'local_comp_report_ext');
        $d->strtype          = get_string('assessmenttype', 'local_comp_report_ext');
        $d->strquiz          = get_string('quiz', 'local_comp_report_ext');
        $d->strtotalweight   = get_string('totalweight', 'local_comp_report_ext');
        $d->strdelete        = get_string('delete');
        $d->strweightwarning = !$d->weightok
            ? get_string('weightwarning', 'local_comp_report_ext', $d->totalweight)
            : '';

        return $d;
    }
}
