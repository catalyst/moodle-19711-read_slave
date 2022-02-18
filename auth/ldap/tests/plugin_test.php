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
 * LDAP authentication plugin tests.
 *
 * NOTE: in order to execute this test you need to set up
 *       OpenLDAP server with core, cosine, nis and internet schemas
 *       and add configuration to config.php.
 *       See connect_ldap\test\ldap_testcase.
 *
 * @package    auth_ldap
 * @copyright  2013 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_ldap;

use connect_ldap\test\ldap_testcase;
use connect_ldap\users;
use connect_ldap\excerption\user_not_found_error;

/**
 * LDAP authentication plugin tests.
 *
 * @package    auth_ldap
 * @copyright  2013 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plugin_test extends ldap_testcase {

    /**
     * Data provider for auth_ldap tests
     *
     * Used to ensure that all the paged stuff works properly, irrespectively
     * of the pagesize configured (that implies all the chunking and paging
     * built in the plugis is doing its work consistently). Both searching and
     * not searching within subcontexts.
     *
     * @return array[]
     */
    public function auth_ldap_provider() {
        // My OpenLDAP server would not take less than 5.
        $pagesizes = [5, 1000];
        $subcontexts = [0, 1];
        $combinations = [];
        foreach ($pagesizes as $pagesize) {
            foreach ($subcontexts as $subcontext) {
                $combinations["pagesize {$pagesize}, subcontexts {$subcontext}"] = [$pagesize, $subcontext];
            }
        }
        return $combinations;
    }

    /**
     * General auth_ldap testcase
     *
     * @dataProvider auth_ldap_provider
     * @param int $pagesize Value to be configured in settings controlling page size.
     * @param int $subcontext Value to be configured in settings controlling searching in subcontexts.
     */
    public function test_auth_ldap(int $pagesize, int $subcontext) {
        global $CFG, $DB;

        // Make sure we can connect the server.
        $ldap = $this->client([
            'pagesize' => $pagesize,
            'user_search_sub' => $subcontext,
            'member_attribute' => 'memberUid',
        ]);

        require_once($CFG->dirroot.'/auth/ldap/auth.php');

        $debuginfo = '';

        $this->enable_plugin();

        $creatorrole = $DB->get_record('role', array('shortname'=>'coursecreator'));
        $this->assertNotEmpty($creatorrole);

        // Configure the plugin a bit.
        set_config('coursecreatorcontext', 'cn=creators,'.self::$containerdn, 'auth_ldap');
        set_config('removeuser', AUTH_REMOVEUSER_KEEP, 'auth_ldap');

        set_config('field_map_email', 'mail', 'auth_ldap');
        set_config('field_updatelocal_email', 'oncreate', 'auth_ldap');
        set_config('field_updateremote_email', '0', 'auth_ldap');
        set_config('field_lock_email', 'unlocked', 'auth_ldap');

        set_config('field_map_firstname', 'givenName', 'auth_ldap');
        set_config('field_updatelocal_firstname', 'oncreate', 'auth_ldap');
        set_config('field_updateremote_firstname', '0', 'auth_ldap');
        set_config('field_lock_firstname', 'unlocked', 'auth_ldap');

        set_config('field_map_lastname', 'sn', 'auth_ldap');
        set_config('field_updatelocal_lastname', 'oncreate', 'auth_ldap');
        set_config('field_updateremote_lastname', '0', 'auth_ldap');
        set_config('field_lock_lastname', 'unlocked', 'auth_ldap');

        // Set up creators group.
        $creatorrolecn = 'creators';
        $creatorroledn = "cn=$creatorrolecn,".self::$containerdn;
        $assignedroles = array('username1', 'username2');
        $o = array();
        $o['objectClass'] = array('posixGroup');
        $o['cn']          = $creatorrolecn;
        $o['gidNumber']   = 1;
        $o['memberUid']   = $assignedroles;
        $ldap->add('cn='.$o['cn'].','.self::$containerdn, $o);

        $createdusers = array();
        for ($i=1; $i<=5; $i++) {
            $this->create_ldap_user($ldap, $i);
            $createdusers[] = 'username' . $i;
        }

        try {
            $this->assertEquals(2, $DB->count_records('user'));
            $this->assertEquals(0, $DB->count_records('role_assignments'));

            /** @var \auth_plugin_ldap $auth */
            $auth = get_auth_plugin('ldap');

            ob_start();
            $sink = $this->redirectEvents();
            $auth->sync_users(true);
            $events = $sink->get_events();
            $sink->close();
            ob_end_clean();

            // Check events, 5 users created with 2 users having roles.
            $this->assertCount(7, $events);
            foreach ($events as $index => $event) {
                $username = $DB->get_field('user', 'username', array('id' => $event->relateduserid)); // Get username.

                if ($event->eventname === '\core\event\user_created') {
                    $this->assertContains($username, $createdusers);
                    unset($events[$index]); // Remove matching event.

                } else if ($event->eventname === '\core\event\role_assigned') {
                    $this->assertContains($username, $assignedroles);
                    unset($events[$index]); // Remove matching event.

                } else {
                    $this->fail('Unexpected event found: ' . $event->eventname);
                }
            }
            // If all the user_created and role_assigned events have matched
            // then the $events array should be now empty.
            $this->assertCount(0, $events);

            $this->assertEquals(5, $DB->count_records('user', array('auth'=>'ldap')));
            $this->assertEquals(2, $DB->count_records('role_assignments'));
            $this->assertEquals(2, $DB->count_records('role_assignments', array('roleid'=>$creatorrole->id)));

            for ($i=1; $i<=5; $i++) {
                $this->assertTrue($DB->record_exists('user', array('username'=>'username'.$i, 'email'=>'user'.$i.'@example.com', 'firstname'=>'Firstname'.$i, 'lastname'=>'Lastname'.$i)));
            }

            $this->delete_ldap_user($ldap, 1);

            ob_start();
            $sink = $this->redirectEvents();
            $auth->sync_users(true);
            $events = $sink->get_events();
            $sink->close();
            ob_end_clean();

            // Check events, no new event.
            $this->assertCount(0, $events);

            $this->assertEquals(5, $DB->count_records('user', array('auth'=>'ldap')));
            $this->assertEquals(0, $DB->count_records('user', array('suspended'=>1)));
            $this->assertEquals(0, $DB->count_records('user', array('deleted'=>1)));
            $this->assertEquals(2, $DB->count_records('role_assignments'));
            $this->assertEquals(2, $DB->count_records('role_assignments', array('roleid'=>$creatorrole->id)));


            set_config('removeuser', AUTH_REMOVEUSER_SUSPEND, 'auth_ldap');

            /** @var \auth_plugin_ldap $auth */
            $auth = get_auth_plugin('ldap');

            ob_start();
            $sink = $this->redirectEvents();
            $auth->sync_users(true);
            $events = $sink->get_events();
            $sink->close();
            ob_end_clean();

            // Check events, 1 user got updated.
            $this->assertCount(1, $events);
            $event = reset($events);
            $this->assertInstanceOf('\core\event\user_updated', $event);

            $this->assertEquals(5, $DB->count_records('user', array('auth'=>'ldap')));
            $this->assertEquals(0, $DB->count_records('user', array('auth'=>'nologin', 'username'=>'username1')));
            $this->assertEquals(1, $DB->count_records('user', array('auth'=>'ldap', 'suspended'=>'1', 'username'=>'username1')));
            $this->assertEquals(0, $DB->count_records('user', array('deleted'=>1)));
            $this->assertEquals(2, $DB->count_records('role_assignments'));
            $this->assertEquals(2, $DB->count_records('role_assignments', array('roleid'=>$creatorrole->id)));

            $this->create_ldap_user($ldap, 1);

            ob_start();
            $sink = $this->redirectEvents();
            $auth->sync_users(true);
            $events = $sink->get_events();
            $sink->close();
            ob_end_clean();

            // Check events, 1 user got updated.
            $this->assertCount(1, $events);
            $event = reset($events);
            $this->assertInstanceOf('\core\event\user_updated', $event);

            $this->assertEquals(5, $DB->count_records('user', array('auth'=>'ldap')));
            $this->assertEquals(0, $DB->count_records('user', array('suspended'=>1)));
            $this->assertEquals(0, $DB->count_records('user', array('deleted'=>1)));
            $this->assertEquals(2, $DB->count_records('role_assignments'));
            $this->assertEquals(2, $DB->count_records('role_assignments', array('roleid'=>$creatorrole->id)));

            $DB->set_field('user', 'auth', 'nologin', array('username'=>'username1'));

            ob_start();
            $sink = $this->redirectEvents();
            $auth->sync_users(true);
            $events = $sink->get_events();
            $sink->close();
            ob_end_clean();

            // Check events, 1 user got updated.
            $this->assertCount(1, $events);
            $event = reset($events);
            $this->assertInstanceOf('\core\event\user_updated', $event);

            $this->assertEquals(5, $DB->count_records('user', array('auth'=>'ldap')));
            $this->assertEquals(0, $DB->count_records('user', array('suspended'=>1)));
            $this->assertEquals(0, $DB->count_records('user', array('deleted'=>1)));
            $this->assertEquals(2, $DB->count_records('role_assignments'));
            $this->assertEquals(2, $DB->count_records('role_assignments', array('roleid'=>$creatorrole->id)));

            set_config('removeuser', AUTH_REMOVEUSER_FULLDELETE, 'auth_ldap');

            /** @var \auth_plugin_ldap $auth */
            $auth = get_auth_plugin('ldap');

            $this->delete_ldap_user($ldap, 1);

            ob_start();
            $sink = $this->redirectEvents();
            $auth->sync_users(true);
            $events = $sink->get_events();
            $sink->close();
            ob_end_clean();

            // Check events, 2 events role_unassigned and user_deleted.
            $this->assertCount(2, $events);
            $event = array_pop($events);
            $this->assertInstanceOf('\core\event\user_deleted', $event);
            $event = array_pop($events);
            $this->assertInstanceOf('\core\event\role_unassigned', $event);

            $this->assertEquals(5, $DB->count_records('user', array('auth'=>'ldap')));
            $this->assertEquals(0, $DB->count_records('user', array('username'=>'username1')));
            $this->assertEquals(0, $DB->count_records('user', array('suspended'=>1)));
            $this->assertEquals(1, $DB->count_records('user', array('deleted'=>1)));
            $this->assertEquals(1, $DB->count_records('role_assignments'));
            $this->assertEquals(1, $DB->count_records('role_assignments', array('roleid'=>$creatorrole->id)));

            $this->create_ldap_user($ldap, 1);

            ob_start();
            $sink = $this->redirectEvents();
            $auth->sync_users(true);
            $events = $sink->get_events();
            $sink->close();
            ob_end_clean();

            // Check events, 2 events role_assigned and user_created.
            $this->assertCount(2, $events);
            $event = array_pop($events);
            $this->assertInstanceOf('\core\event\role_assigned', $event);
            $event = array_pop($events);
            $this->assertInstanceOf('\core\event\user_created', $event);

            $this->assertEquals(6, $DB->count_records('user', array('auth'=>'ldap')));
            $this->assertEquals(1, $DB->count_records('user', array('username'=>'username1')));
            $this->assertEquals(0, $DB->count_records('user', array('suspended'=>1)));
            $this->assertEquals(1, $DB->count_records('user', array('deleted'=>1)));
            $this->assertEquals(2, $DB->count_records('role_assignments'));
            $this->assertEquals(2, $DB->count_records('role_assignments', array('roleid'=>$creatorrole->id)));
        } finally {
            for ($i=1; $i<=5; $i++) {
                $this->delete_ldap_user($ldap, $i);
            }
            $ldap->delete($creatorroledn);
        }
    }

    /**
     * Test logging in via LDAP calls a user_loggedin event.
     */
    public function test_ldap_user_loggedin_event() {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/auth/ldap/auth.php');

        $this->resetAfterTest();

        $this->assertFalse(isloggedin());
        $user = $DB->get_record('user', array('username'=>'admin'));

        // Note: we are just going to trigger the function that calls the event,
        // not actually perform a LDAP login, for the sake of sanity.
        $ldap = new \auth_plugin_ldap();

        // Set the key for the cache flag we want to set which is used by LDAP.
        set_cache_flag($ldap->pluginconfig . '/ntlmsess', sesskey(), $user->username, AUTH_NTLMTIMEOUT);

        // We are going to need to set the sesskey as the user's password in order for the LDAP log in to work.
        update_internal_user_password($user, sesskey());

        // The function ntlmsso_finish is responsible for triggering the event, so call it directly and catch the event.
        $sink = $this->redirectEvents();
        // We need to supress this function call, or else we will get the message "session_regenerate_id(): Cannot
        // regenerate session id - headers already sent" as the ntlmsso_finish function calls complete_user_login
        @$ldap->ntlmsso_finish();
        $events = $sink->get_events();
        $sink->close();

        // Check that the event is valid.
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf('\core\event\user_loggedin', $event);
        $this->assertEquals('user', $event->objecttable);
        $this->assertEquals('2', $event->objectid);
        $this->assertEquals(\context_system::instance()->id, $event->contextid);
        $expectedlog = array(SITEID, 'user', 'login', 'view.php?id=' . $USER->id . '&course=' . SITEID, $user->id,
            0, $user->id);
        $this->assertEventLegacyLogData($expectedlog, $event);
    }

    /**
     * Test logging in via LDAP calls a user_loggedin event.
     */
    public function test_ldap_user_signup() {
        global $CFG, $DB;

        $ldap = $this->client([
            'pagesize' => 2,
            'user_search_sub' => 0,
            'member_attribute' => 'memberUid',
            'passtype' => 'md5',
        ]);

        // User to create.
        $user = array(
            'username' => 'usersignuptest1',
            'password' => 'Moodle2014!',
            'idnumber' => 'idsignuptest1',
            'firstname' => 'First Name User Test 1',
            'lastname' => 'Last Name User Test 1',
            'middlename' => 'Middle Name User Test 1',
            'lastnamephonetic' => '最後のお名前のテスト一号',
            'firstnamephonetic' => 'お名前のテスト一号',
            'alternatename' => 'Alternate Name User Test 1',
            'email' => 'usersignuptest1@example.com',
            'description' => 'This is a description for user 1',
            'city' => 'Perth',
            'country' => 'AU',
            'mnethostid' => $CFG->mnet_localhost_id,
            'auth' => 'ldap'
            );

        require_once($CFG->dirroot.'/auth/ldap/auth.php');

        // Make sure we can connect the server.
        $debuginfo = '';

        $this->enable_plugin();

        // Configure the plugin a bit.
        set_config('creators', 'cn=creators,'.self::$containerdn, 'auth_ldap');
        set_config('removeuser', AUTH_REMOVEUSER_KEEP, 'auth_ldap');

        set_config('field_map_email', 'mail', 'auth_ldap');
        set_config('field_updatelocal_email', 'oncreate', 'auth_ldap');
        set_config('field_updateremote_email', '0', 'auth_ldap');
        set_config('field_lock_email', 'unlocked', 'auth_ldap');

        set_config('field_map_firstname', 'givenName', 'auth_ldap');
        set_config('field_updatelocal_firstname', 'oncreate', 'auth_ldap');
        set_config('field_updateremote_firstname', '0', 'auth_ldap');
        set_config('field_lock_firstname', 'unlocked', 'auth_ldap');

        set_config('field_map_lastname', 'sn', 'auth_ldap');
        set_config('field_updatelocal_lastname', 'oncreate', 'auth_ldap');
        set_config('field_updateremote_lastname', '0', 'auth_ldap');
        set_config('field_lock_lastname', 'unlocked', 'auth_ldap');

        $this->assertEquals(2, $DB->count_records('user'));
        $this->assertEquals(0, $DB->count_records('role_assignments'));

        /** @var \auth_plugin_ldap $auth */
        $auth = get_auth_plugin('ldap');

        $sink = $this->redirectEvents();
        $mailsink = $this->redirectEmails();
        $auth->user_signup((object)$user, false);
        $this->assertEquals(1, $mailsink->count());
        $events = $sink->get_events();
        $sink->close();

        // Verify 2 events get generated.
        $this->assertCount(2, $events);

        // Get record from db.
        $dbuser = $DB->get_record('user', array('username' => $user['username']));
        $user['id'] = $dbuser->id;

        // Last event is user_created.
        $event = array_pop($events);
        $this->assertInstanceOf('\core\event\user_created', $event);
        $this->assertEquals($user['id'], $event->objectid);
        $this->assertEquals('user_created', $event->get_legacy_eventname());
        $this->assertEquals(\context_user::instance($user['id']), $event->get_context());
        $expectedlogdata = array(SITEID, 'user', 'add', '/view.php?id='.$event->objectid, fullname($dbuser));
        $this->assertEventLegacyLogData($expectedlogdata, $event);

        // First event is user_password_updated.
        $event = array_pop($events);
        $this->assertInstanceOf('\core\event\user_password_updated', $event);
        $this->assertEventContextNotUsed($event);

        // Delete user which we just created.
        //ldap_delete($connection, 'cn='.$user['username'].',ou=users,'.$topdn);
    }

    protected function create_ldap_user($ldap, $i) {
        $username = 'username'.$i;
        $o = array();
        $o['sn']            = 'Lastname'.$i;
        $o['givenName']     = 'Firstname'.$i;
        $o['mail']          = 'user'.$i.'@example.com';

        $users = new users($ldap);
        $user = $users->user($username);
        $user->create($o, 'pass'.$i);
    }

    protected function delete_ldap_user($ldap, $i) {
        $username = 'username'.$i;
        $users = new users($ldap);
        $user = $users->user($username);
        if ($user->exists()) {
            $ldap->delete($user->dn());
        }
    }

    protected function enable_plugin() {
        $auths = get_enabled_auth_plugins();
        if (!in_array('ldap', $auths)) {
            $auths[] = 'ldap';

        }
        set_config('auth', implode(',', $auths));
    }
}
