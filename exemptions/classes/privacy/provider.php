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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace core_exemptions\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\transform;

/**
 * Privacy class for requesting user data.
 *
 * @package     core_exemptions
 * @author      Alexander Van der Bellen <alexandervanderbellen@catalyst-au.net>
 * @copyright   2018 Jake Dallimore <jrhdallimore@gmail.com>
 * @copyright   2024 Catalyst IT Australia
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\subsystem\plugin_provider,
        \core_privacy\local\request\shared_userlist_provider {

    /**
     * Sanitise the exemption record, removing personal information.
     *
     * @param \stdClass $record
     * @return \stdClass
     */
    private static function sanitise_exemption_record(\stdClass $record): \stdClass {
        $record->timemodified = time();
        $record->usermodified = 0;
        $record->reason = null;
        $record->reasonformat = null;
        return $record;
    }

    /**
     * Returns metadata about this system.
     *
     * @param   collection $collection The initialised collection to add items to.
     * @return  collection     A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        return $collection->add_database_table('exemption', [
            'component' => 'privacy:metadata:exemption:component',
            'itemtype' => 'privacy:metadata:exemption:itemtype',
            'itemid' => 'privacy:metadata:exemption:itemid',
            'timecreated' => 'privacy:metadata:exemption:timecreated',
            'timemodified' => 'privacy:metadata:exemption:timemodified',
            'usermodified' => 'privacy:metadata:exemption:usermodified',
            'reason' => 'privacy:metadata:exemption:reason',
            'reasonformat' => 'privacy:metadata:exemption:reasonformat',
        ], 'privacy:metadata:exemption');
    }

    /**
     * Provide a list of contexts which have exemptions for the user, in the respective area (component/itemtype combination).
     *
     * This method is to be called by consumers of the exemptions subsystem (plugins), in their get_contexts_for_userid() method,
     * to add the contexts for items which may have been exempt, but would normally not be reported as having user data by the
     * plugin responsible for them.
     *
     * Consider an example: exempt courses.
     * Exempt courses will be handled by the core_course subsystem and courses can be exempt at site context.
     *
     * Now normally, the course provider method get_contexts_for_userid() would report the context of any courses the user is in.
     * Then, we'd export data for those contexts. This won't include courses the user has exempt, but is not a member of.
     *
     * To report the full list, the course provider needs to be made aware of the contexts of any courses the user may have marked
     * as exemptions. Course will need to ask th exemptions subsystem for this - a call to add_contexts_for_userid($userid).
     *
     * Once called, if a course has been marked as an exemption, at site context, then we'd return the site context. During export,
     * the consumer (course), just looks at all contexts and decides whether to export exemption courses for each one.
     *
     * @param contextlist $contextlist
     * @param int $userid The id of the user in scope.
     * @param string $component the frankenstyle component name.
     * @param string|null $itemtype the type of the exempt items.
     */
    public static function add_contexts_for_userid(contextlist $contextlist,
        int $userid, string $component, ?string $itemtype = null) {

        $sql = "SELECT contextid
                  FROM {exemption} f
                 WHERE usermodified = :usermodified
                   AND component = :component";

        $params = ['usermodified' => $userid, 'component' => $component];

        if (!is_null($itemtype)) {
            $sql .= " AND itemtype = :itemtype";
            $params['itemtype'] = $itemtype;
        }

        $contextlist->add_from_sql($sql, $params);
    }

    /**
     * Add users to a userlist who have exemptions within the specified context.
     *
     * @param \core_privacy\local\request\userlist $userlist The userlist to add the users to.
     * @param string|null $itemtype the type of the exempt items.
     * @return void
     */
    public static function add_userids_for_context(\core_privacy\local\request\userlist $userlist, ?string $itemtype = null) {
        if (empty($userlist)) {
            return;
        }

        $params = [
            'contextid' => $userlist->get_context()->id,
            'component' => $userlist->get_component(),
        ];

        $sql = "SELECT usermodified
                  FROM {exemption}
                 WHERE contextid = :contextid
                       AND component = :component";

        if (!is_null($itemtype)) {
            $sql .= " AND itemtype = :itemtype";
            $params['itemtype'] = $itemtype;
        }

        $userlist->add_from_sql('usermodified', $sql, $params);
    }

    /**
     * Get exemptions data for the specified user in the specified component, item type and item ID.
     *
     * @param int $userid The id of the user in scope.
     * @param \context $context The context to which data is scoped.
     * @param string $component The exemption's component name.
     * @param string $itemtype The exemption's item type.
     * @param int $itemid The exemption's item ID.
     * @return array|null
     */
    public static function get_exemptions_info_for_user(int $userid, \context $context,
                                                        string $component, string $itemtype, int $itemid) {
        global $DB;

        $params = [
            'usermodified' => $userid,
            'component' => $component,
            'itemtype' => $itemtype,
            'itemid' => $itemid,
            'contextid' => $context->id,
        ];

        if (!$exempt = $DB->get_record('exemption', $params)) {
            return;
        }

        return [
            'component' => $component,
            'itemtype' => $itemtype,
            'itemid' => $itemid,
            'contextid' => $context->id,
            'timecreated' => transform::datetime($exempt->timecreated),
            'timemodified' => transform::datetime($exempt->timemodified),
            'usermodified' => $exempt->usermodified,
            'reason' => $exempt->reason,
            'reasonformat' => $exempt->reasonformat,
        ];
    }

    /**
     * Delete all exemptions for all users in the specified contexts, and component area.
     *
     * @param \context $context The context to which deletion is scoped.
     * @param string $component The exemption's component name.
     * @param string $itemtype The exemption's itemtype.
     * @param int $itemid Optional itemid associated with component.
     * @throws \dml_exception if any errors are encountered during deletion.
     */
    public static function delete_exemptions_for_all_users(\context $context, string $component, string $itemtype,
                                                           int $itemid = 0) {
        global $DB;

        $params = [
            'component' => $component,
            'itemtype' => $itemtype,
            'contextid' => $context->id,
        ];

        $select = "component = :component AND itemtype = :itemtype AND contextid = :contextid";

        if (!empty($itemid)) {
            $select .= " AND itemid = :itemid";
            $params['itemid'] = $itemid;
        }
        $records = $DB->get_records_select('exemption', $select, $params);
        foreach ($records as $record) {
            $DB->update_record('exemption', self::sanitise_exemption_record($record));
        }
    }

    /**
     * Delete all exemptions for the specified users in the specified context, component area and item type.
     *
     * @param \core_privacy\local\request\approved_userlist $userlist The approved contexts and user information
     * to delete information for.
     * @param string $itemtype The exemption's itemtype.
     * @param int $itemid Optional itemid associated with component.
     * @throws \dml_exception if any errors are encountered during deletion.
     */
    public static function delete_exemptions_for_userlist(\core_privacy\local\request\approved_userlist $userlist,
                                                          string $itemtype, int $itemid = 0) {
        global $DB;

        $userids = $userlist->get_userids();

        if (empty($userids)) {
            return;
        }

        $context = $userlist->get_context();
        [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        $params = [
            'component' => $userlist->get_component(),
            'itemtype' => $itemtype,
            'contextid' => $context->id,
        ];

        $params += $userparams;
        $select = "component = :component AND itemtype = :itemtype AND contextid = :contextid AND usermodified $usersql";

        if (!empty($itemid)) {
            $select .= " AND itemid = :itemid";
            $params['itemid'] = $itemid;
        }

        $records = $DB->get_records_select('exemption', $select, $params);
        foreach ($records as $record) {
            $DB->update_record('exemption', self::sanitise_exemption_record($record));
        }
    }

    /**
     * Delete all exemptions for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     * @param string $component The exemption's component name.
     * @param string $itemtype The exemption's itemtype.
     * @param int $itemid Optional itemid associated with component.
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function delete_exemptions_for_user(approved_contextlist $contextlist, string $component, string $itemtype,
        int $itemid = 0) {
        global $DB;

        $usermodified = $contextlist->get_user()->id;

        [$insql, $inparams] = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        $params = [
            'usermodified' => $usermodified,
            'component' => $component,
            'itemtype' => $itemtype,
        ];
        $params += $inparams;

        $select = "usermodified = :usermodified AND component = :component AND itemtype = :itemtype AND contextid $insql";

        if (!empty($itemid)) {
            $select .= " AND itemid = :itemid";
            $params['itemid'] = $itemid;
        }

        $records = $DB->get_records_select('exemption', $select, $params);
        foreach ($records as $record) {
            $DB->update_record('exemption', self::sanitise_exemption_record($record));
        }
    }
}
