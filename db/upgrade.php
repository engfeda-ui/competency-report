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
 * Upgrade script for local_comp_report_ext.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the plugin from one version to another.
 *
 * @param int $oldversion The old version of the plugin.
 * @return bool
 */
function xmldb_local_comp_report_ext_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026070100) {
        // 1. Create local_comp_report_ext_asmt table.
        $table = new xmldb_table('local_comp_report_ext_asmt');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('quizid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('type', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'quiz');
        $table->add_field('weight', XMLDB_TYPE_NUMBER, '5, 2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('coursefk', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);

        $table->add_index('course_quiz_idx', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'quizid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // 2. Create local_comp_report_ext_prac table.
        $table2 = new xmldb_table('local_comp_report_ext_prac');

        $table2->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table2->add_field('assessmentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table2->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table2->add_field('competencyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table2->add_field('studentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table2->add_field('trainerid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table2->add_field('competency_percent', XMLDB_TYPE_NUMBER, '5, 2', null, XMLDB_NOTNULL, null, '0');
        $table2->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table2->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table2->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table2->add_key('assessmentfk', XMLDB_KEY_FOREIGN, ['assessmentid'], 'local_comp_report_ext_asmt', ['id']);
        $table2->add_key('coursefk', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table2->add_key('studentfk', XMLDB_KEY_FOREIGN, ['studentid'], 'user', ['id']);

        $table2->add_index('assessment_student_comp_idx', XMLDB_INDEX_NOTUNIQUE, ['assessmentid', 'studentid', 'competencyid']);
        $table2->add_index('course_student_idx', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'studentid']);

        if (!$dbman->table_exists($table2)) {
            $dbman->create_table($table2);
        }

        upgrade_plugin_savepoint(true, 2026070100, 'local', 'comp_report_ext');
    }

    if ($oldversion < 2026070302) {
        $table = new xmldb_table('local_comp_report_ext_asmt');
        $field = new xmldb_field('assignid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'quizid');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026070302, 'local', 'comp_report_ext');
    }

    if ($oldversion < 2026070500) {
        // Version bump: added manageassessments and enterpractical capabilities to db/access.php.
        // No database changes required, capabilities are registered automatically by Moodle.
        upgrade_plugin_savepoint(true, 2026070500, 'local', 'comp_report_ext');
    }

    // 2026082700: Enforce data integrity on practical results.
    // 1) Remove duplicate rows keeping the newest per (assessmentid, studentid, competencyid).
    // 2) Replace the non-unique index with a UNIQUE one to prevent future races.
    if ($oldversion < 2026082700) {
        $table = new xmldb_table('local_comp_report_ext_prac');

        $dupids = $DB->get_fieldset_sql("
            SELECT p.id
              FROM {local_comp_report_ext_prac} p
              JOIN {local_comp_report_ext_prac} newer
                ON newer.assessmentid  = p.assessmentid
               AND newer.studentid     = p.studentid
               AND newer.competencyid  = p.competencyid
               AND newer.id > p.id
        ");
        if (!empty($dupids)) {
            foreach (array_chunk($dupids, 1000) as $chunk) {
                $DB->delete_records_list('local_comp_report_ext_prac', 'id', $chunk);
            }
        }

        $oldidx = new xmldb_index('assessment_student_comp_idx', XMLDB_INDEX_NOTUNIQUE,
            ['assessmentid', 'studentid', 'competencyid']);
        if ($dbman->find_index_name($table, $oldidx)) {
            $dbman->drop_index($table, $oldidx);
        }

        $newidx = new xmldb_index('assessment_student_comp_idx', XMLDB_INDEX_UNIQUE,
            ['assessmentid', 'studentid', 'competencyid']);
        if (!$dbman->find_index_name($table, $newidx)) {
            $dbman->add_index($table, $newidx);
        }

        upgrade_plugin_savepoint(true, 2026082700, 'local', 'comp_report_ext');
    }

    return true;
}
