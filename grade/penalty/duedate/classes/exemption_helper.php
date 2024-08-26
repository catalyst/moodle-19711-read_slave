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

namespace gradepenalty_duedate;
use context;
use core_exemptions\local\entity\exemption;
use core_exemptions\local\repository\exemption_repository;
use core_exemptions\service_factory;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lib.php');

/**
 * CRUD operations for penalty exemptions for users and groups.
 *
 * @package   gradepenalty_duedate
 * @copyright 2024 Catalyst IT Australia Pty Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exemption_helper {

    /**
     * Check if the user is exempt from the grade penalty.
     *
     * @param int $userid
     * @param int $contextid
     *
     * @return bool
     */
    public static function is_exempt(int $userid, int $contextid): bool {

        $context = context::instance_by_id($contextid);
        $contextids = array_filter(explode('/', $context->path ?? ''), 'is_numeric');

        if (empty($contextids)) {
            return false;
        }

        // Check if the user is exempt.
        if (self::is_user_exempt($userid, $contextids)) {
            return true;
        }

        // Check if the user is a member of any groups that are exempt.
        $courseid = $context->get_course_context()->instanceid ?? null;
        if ($courseid) {
            $groupings = groups_get_user_groups($courseid, $userid, true);
            foreach ($groupings[0] as $groupid) {
                if (self::is_group_exempt($groupid, $contextids)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if the user is exempt from the grade penalty.
     *
     * @param int $userid The user id to check.
     * @param array $contextids The context ids to check.
     *
     * @return bool
     */
    private static function is_user_exempt(int $userid, array $contextids): bool {
        $service = service_factory::get_service_for_component('gradepenalty_duedate');
        $records = $service->find_by(['itemtype' => 'user', 'itemid' => $userid]);

        foreach ($records as $record) {
            if (in_array($record->contextid, $contextids)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the group is exempt from the grade penalty.
     *
     * @param int $groupid The group id to check.
     * @param array $contextids The context ids to check.
     *
     * @return bool
     */
    private static function is_group_exempt(int $groupid, array $contextids): bool {
        $service = service_factory::get_service_for_component('gradepenalty_duedate');
        $records = $service->find_by(['itemtype' => 'group', 'itemid' => $groupid]);

        foreach ($records as $record) {
            if (in_array($record->contextid, $contextids)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Exempt a user from the grade penalty. This method will update the exemption if it already exists.
     *
     * @param int $userid The user id to exempt.
     * @param int $contextid The context id to exempt the user from.
     * @param string|null $reason The reason for the exemption.
     * @param int|null $reasonformat The format of the reason.
     */
    public static function exempt_user(int $userid, int $contextid, ?string $reason = null, ?int $reasonformat = null) {
        $service = service_factory::get_service_for_component('gradepenalty_duedate');
        $exem = $service->find('user', $userid, $contextid);
        if (empty($exem)) {
            $exem = $service->create('user', $userid, $contextid, $reason, $reasonformat);
        } else {
            $exem->reason = $reason;
            $exem->reasonformat = $reasonformat;
            $service->update($exem);
        }
    }

    /**
     * Exempt a group from the grade penalty. This method will update the exemption if it already exists.
     *
     * @param int $groupid The group id to exempt.
     * @param int $contextid The context id to exempt the group from.
     * @param string|null $reason The reason for the exemption.
     * @param int|null $reasonformat The format of the reason.
     */
    public static function exempt_group(int $groupid, int $contextid, ?string $reason = null, ?int $reasonformat = null) {
        $service = service_factory::get_service_for_component('gradepenalty_duedate');
        $exem = $service->find('group', $groupid, $contextid);
        if (empty($exem)) {
            $exem = $service->create('group', $groupid, $contextid, $reason, $reasonformat);
        } else {
            $exem->reason = $reason;
            $exem->reasonformat = $reasonformat;
            $service->update($exem);
        }
    }

    /**
     * Delete a user exemption.
     *
     * @param int $userid The user id to delete the exemption for.
     * @param int $contextid The context id to delete the exemption for.
     */
    public static function delete_user_exemption(int $userid, int $contextid) {
        $service = service_factory::get_service_for_component('gradepenalty_duedate');
        $service->delete('user', $userid, $contextid);
    }

    /**
     * Delete a group exemption.
     *
     * @param int $groupid The group id to delete the exemption for.
     * @param int $contextid The context id to delete the exemption for.
     */
    public static function delete_group_exemption(int $groupid, int $contextid) {
        $service = service_factory::get_service_for_component('gradepenalty_duedate');
        $service->delete('group', $groupid, $contextid);
    }

    /**
     * Delete an exemption by id.
     *
     * @param int $id The id of the exemption to delete.
     */
    public static function delete_exemption(int $id) {
        $service = service_factory::get_service_for_component('gradepenalty_duedate');
        $service->delete_by(['id' => $id]);
    }

    /**
     * Find an exemption by id.
     *
     * @param int $id The id of the exemption to find.
     * @return exemption|false
     */
    public static function get_exemption(int $id): ?exemption {
        $service = service_factory::get_service_for_component('gradepenalty_duedate');
        $found = $service->find_by(['id' => $id]);
        return reset($found);
    }

    /**
     * Update an exemption.
     *
     * @param exemption $exemption The exemption to update.
     */
    public static function update_exemption(exemption $exemption) {
        $service = service_factory::get_service_for_component('gradepenalty_duedate');
        $service->update($exemption);
    }
}
