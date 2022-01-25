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

/**
 * A scheduled task for LDAP user sync.
 *
 * @package    auth_ldap
 * @copyright  2015 Vadim Dvorovenko <Vadimon@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_ldap\task;

/**
 * A scheduled task class for LDAP user sync.
 *
 * @copyright  2015 Vadim Dvorovenko <Vadimon@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_task extends \core\task\scheduled_task {

    const MTRACE_MSG = 'Queued async sync ldap users';

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('synctask', 'auth_ldap');
    }

    /**
     * Run users sync.
     */
    public function execute() {
        global $CFG;
        if (is_enabled_auth('ldap')) {
            $maxcount = 100;

            $auth = get_auth_plugin('ldap');
            $auth->_cnt = 0;
            $auth->sync_users_update_callback(function ($users, $updatekeys) use ($auth) {
                $asynctask = new asynchronous_sync_task();
                $asynctask->set_custom_data([
                    'users' => $users,
                    'updatekeys' => $updatekeys,
                ]);
                \core\task\manager::queue_adhoc_task($asynctask);

                $auth->_cnt++;
                mtrace(sprintf(" %s (%d)", self::MTRACE_MSG, $auth->_cnt));
                sleep(1);
            });
        }
    }
}
