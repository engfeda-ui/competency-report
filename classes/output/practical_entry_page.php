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
 * Renderable for the Practical Entry page.
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
use moodle_url;

/**
 * Renderable for the practical entry page.
 *
 * @package local_comp_report_ext
 */
class practical_entry_page implements renderable, templatable {
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
        $d->courseid     = $this->data->courseid;
        $d->assessmentid = $this->data->assessmentid;
        $d->competencyid = $this->data->competencyid;
        $d->sesskey      = $this->data->sesskey;
        $d->hasselection = ($this->data->assessmentid > 0 && $this->data->competencyid > 0);

        $d->formaction = (new moodle_url('/local/competency_report/practical_entry.php', [
            'courseid' => $this->data->courseid,
        ]))->out(false);

        $d->filteraction = (new moodle_url('/local/competency_report/practical_entry.php', [
            'courseid' => $this->data->courseid,
        ]))->out(false);

        $d->setupurl = (new moodle_url('/local/competency_report/assessment_setup.php', [
            'courseid' => $this->data->courseid,
        ]))->out(false);

        // Practical assessment selector.
        $d->assessments = [];
        foreach ($this->data->assessments as $a) {
            $item           = new stdClass();
            $item->id       = $a->id;
            $item->name     = $a->name;
            $item->weight   = $a->weight;
            $item->selected = ($a->id == $this->data->assessmentid);
            $d->assessments[] = $item;
        }
        $d->hasassessments = !empty($d->assessments);

        // Competency selector.
        $d->competencies = [];
        foreach ($this->data->competencies as $c) {
            $item           = new stdClass();
            $item->id       = $c->id;
            $item->shortname = $c->shortname;
            $item->selected = ($c->id == $this->data->competencyid);
            $d->competencies[] = $item;
        }
        $d->hascompetencies = !empty($d->competencies);

        // Students with their existing results (if selection made).
        $d->students = [];
        if ($d->hasselection) {
            foreach ($this->data->students as $s) {
                $row           = new stdClass();
                $row->id       = $s->id;
                $row->fullname = fullname($s);
                $row->idnumber = $s->idnumber ?? '';
                $row->percent  = $this->data->existingresults[$s->id] ?? '';
                $d->students[] = $row;
            }
        }
        $d->hasstudents = !empty($d->students);

        // String labels.
        $d->strselectassessment = get_string('selectpracticalassessment', 'local_comp_report_ext');
        $d->strselectcompetency = get_string('selectcompetency', 'local_comp_report_ext');
        $d->strstudent          = get_string('student', 'local_comp_report_ext');
        $d->strpercent          = get_string('competencypercent', 'local_comp_report_ext');
        $d->strsave             = get_string('savechanges');
        $d->strshowstudents     = get_string('showstudents', 'local_comp_report_ext');
        $d->strnostudenst       = get_string('nostudentsenrolled', 'local_comp_report_ext');
        $d->strnopracticals     = get_string('nopracticalassessments', 'local_comp_report_ext');

        return $d;
    }
}
