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
 * Local plugin "competency_report" - Privacy provider.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_comp_report_ext\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadataprovider;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\local\request\plugin\provider as pluginprovider;

/**
 * Privacy Subsystem implementation for local_comp_report_ext.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements metadataprovider, pluginprovider {
    /**
     * Returns metadata about user data stored by this plugin and sent to external locations.
     *
     * @param collection $collection The collection to add metadata to.
     * @return collection The modified collection.
     */
    public static function get_metadata(collection $collection): collection {
        // Declare database table storing student practical competency grades.
        $collection->add_database_table(
            'local_comp_report_ext_prac',
            [
                'studentid'          => 'privacy:metadata:local_comp_report_ext_prac:studentid',
                'trainerid'          => 'privacy:metadata:local_comp_report_ext_prac:trainerid',
                'competency_percent' => 'privacy:metadata:local_comp_report_ext_prac:competency_percent',
            ],
            'privacy:metadata:local_comp_report_ext_prac'
        );

        // External location declaration for AI prompt submissions.
        $collection->add_external_location_link(
            'openai',
            [
                'questiontext' => 'privacy:metadata:openai:questiontext',
                'answertext'   => 'privacy:metadata:openai:answertext',
            ],
            'privacy:metadata:openai:externalpurpose'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist $contextlist The contextlist containing the list of contexts.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {course} cr ON cr.id = c.instanceid AND c.contextlevel = :contextlevel
                  JOIN {local_comp_report_ext_prac} p ON p.courseid = cr.id
                 WHERE p.studentid = :studentid OR p.trainerid = :trainerid";

        $params = [
            'contextlevel' => CONTEXT_COURSE,
            'studentid'    => $userid,
            'trainerid'    => $userid,
        ];

        $contextlist->add_from_sql($sql, $params);
        return $contextlist;
    }

    /**
     * Export all user data for the specified user, for the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_COURSE) {
                continue;
            }

            $records = $DB->get_records('local_comp_report_ext_prac', [
                'courseid'  => $context->instanceid,
                'studentid' => $user->id,
            ]);

            if (!empty($records)) {
                $data = [];
                foreach ($records as $record) {
                    $data[] = (object)[
                        'assessmentid'       => $record->assessmentid,
                        'competencyid'       => $record->competencyid,
                        'competency_percent' => $record->competency_percent,
                        'timecreated'        => transform::datetime($record->timecreated),
                        'timemodified'       => transform::datetime($record->timemodified),
                    ];
                }
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_comp_report_ext')],
                    (object)['practical_records' => $data]
                );
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel == CONTEXT_COURSE) {
            $DB->delete_records('local_comp_report_ext_prac', ['courseid' => $context->instanceid]);
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_COURSE) {
                $DB->delete_records('local_comp_report_ext_prac', [
                    'courseid'  => $context->instanceid,
                    'studentid' => $user->id,
                ]);
            }
        }
    }
}
