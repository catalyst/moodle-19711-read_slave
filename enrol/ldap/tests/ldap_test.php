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
 * LDAP enrolment plugin tests.
 *
 * NOTE: in order to execute this test you need to set up
 *       OpenLDAP server with core, cosine, nis and internet schemas
 *       and add configuration to config.php.
 *       See connect_ldap\test\ldap_testcase
 *
 * @package    enrol_ldap
 * @copyright  2013 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_ldap;

use null_progress_trace;
use context_course;
use connect_ldap\test\ldap_testcase;

defined('MOODLE_INTERNAL') || die();

/**
 * LDAP enrolment plugin tests.
 *
 * @package    enrol_ldap
 * @copyright  2013 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ldap_test extends ldap_testcase {
    /**
     * Data provider for enrol_ldap tests
     *
     * Used to ensure that all the paged stuff works properly, irrespectively
     * of the pagesize configured (that implies all the chunking and paging
     * built in the plugis is doing its work consistently). Both searching and
     * not searching within subcontexts.
     *
     * @return array[]
     */
    public function enrol_ldap_provider() {
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
     * General enrol_ldap testcase
     *
     * @dataProvider enrol_ldap_provider
     * @param int $pagesize Value to be configured in settings controlling page size.
     * @param int $subcontext Value to be configured in settings controlling searching in subcontexts.
     */
    public function test_enrol_ldap(int $pagesize, int $subcontext) {
        global $CFG, $DB;

        // Make sure we can connect the server.
        $ldap = $this->client([
            'pagesize' => $pagesize,
            'user_search_sub' => $subcontext,
            'member_attribute' => 'memberUid',
        ]);

        require_once($CFG->dirroot.'/enrol/ldap/lib.php');

        $debuginfo = '';

        $this->enable_plugin();

        // Configure enrol plugin.
        /** @var enrol_ldap_plugin $enrol */
        $enrol = enrol_get_plugin('ldap');
        $enrol->set_config('course_search_sub', $subcontext);
        $enrol->set_config('course_idnumber', 'cn');
        $enrol->set_config('course_shortname', 'cn');
        $enrol->set_config('course_fullname', 'cn');
        $enrol->set_config('course_summary', '');
        $enrol->set_config('ignorehiddencourses', 0);
        $enrol->set_config('nested_groups', 0);
        $enrol->set_config('autocreate', 0);
        $enrol->set_config('unenrolaction', ENROL_EXT_REMOVED_KEEP);

        $roles = get_all_roles();
        foreach ($roles as $role) {
            $enrol->set_config('contexts_role'.$role->id, '');
            $enrol->set_config('memberattribute_role'.$role->id, '');
        }

        // Create group for teacher enrolments.
        $teacherrole = $DB->get_record('role', array('shortname'=>'teacher'));
        $this->assertNotEmpty($teacherrole);
        $ou = "teachers$pagesize$subcontext";
        $o = [];
        $o['objectClass'] = ['organizationalUnit'];
        $o['ou']          = $ou;
        $teachersdn = "ou=$ou,".self::$containerdn;
        $ldap->add($teachersdn, $o);
        $enrol->set_config('contexts_role'.$teacherrole->id, $teachersdn);
        $enrol->set_config('memberattribute_role'.$teacherrole->id, 'memberuid');

        // Create group for student enrolments.
        $studentrole = $DB->get_record('role', array('shortname'=>'student'));
        $this->assertNotEmpty($studentrole);
        $ou = "students$pagesize$subcontext";
        $o = [];
        $o['objectClass'] = ['organizationalUnit'];
        $o['ou']          = $ou;
        $studentsdn = "ou=$ou,".self::$containerdn;
        $ldap->add($studentsdn, $o);
        $enrol->set_config('contexts_role'.$studentrole->id, $studentsdn);
        $enrol->set_config('memberattribute_role'.$studentrole->id, 'memberuid');

        // Create some users and courses.
        $user1 = $this->getDataGenerator()->create_user(array('idnumber'=>'user1', 'username'=>'user1'));
        $user2 = $this->getDataGenerator()->create_user(array('idnumber'=>'user2', 'username'=>'user2'));
        $user3 = $this->getDataGenerator()->create_user(array('idnumber'=>'user3', 'username'=>'user3'));
        $user4 = $this->getDataGenerator()->create_user(array('idnumber'=>'user4', 'username'=>'user4'));
        $user5 = $this->getDataGenerator()->create_user(array('idnumber'=>'user5', 'username'=>'user5'));
        $user6 = $this->getDataGenerator()->create_user(array('idnumber'=>'user6', 'username'=>'user6'));

        $course1 = $this->getDataGenerator()->create_course(array('idnumber'=>'course1', 'shortname'=>'course1'));
        $course2 = $this->getDataGenerator()->create_course(array('idnumber'=>'course2', 'shortname'=>'course2'));
        $course3 = $this->getDataGenerator()->create_course(array('idnumber'=>'course3', 'shortname'=>'course3'));

        // Set up some ldap data.
        $o = array();
        $o['objectClass'] = array('posixGroup');
        $o['cn']          = 'course1';
        $o['gidNumber']   = '1';
        $o['memberUid']   = array('user1', 'user2', 'user3', 'userx');
        $ldap->add('cn='.$o['cn'].','.$studentsdn, $o);
        $o = array();
        $o['objectClass'] = array('posixGroup');
        $o['cn']          = 'course1';
        $o['gidNumber']   = '2';
        $o['memberUid']   = array('user5');
        $ldap->add('cn='.$o['cn'].','.$teachersdn, $o);

        $o = array();
        $o['objectClass'] = array('posixGroup');
        $o['cn']          = 'course2';
        $o['gidNumber']   = '3';
        $o['memberUid']   = array('user1', 'user2', 'user3', 'user4');
        $ldap->add('cn='.$o['cn'].','.$studentsdn, $o);

        $o = array();
        $o['objectClass'] = array('posixGroup');
        $o['cn']          = 'course4';
        $o['gidNumber']   = '4';
        $o['memberUid']   = array('user1', 'user2');
        $ldap->add('cn='.$o['cn'].','.$studentsdn, $o);
        $o = array();
        $o['objectClass'] = array('posixGroup');
        $o['cn']          = 'course4';
        $o['gidNumber']   = '5';
        $o['memberUid']   = array('user5', 'user6');
        $ldap->add('cn='.$o['cn'].','.$teachersdn, $o);


        // Test simple test without creation.

        $this->assertEquals(0, $DB->count_records('user_enrolments'));
        $this->assertEquals(0, $DB->count_records('role_assignments'));
        $this->assertEquals(4, $DB->count_records('course'));

        $enrol->sync_enrolments(new \null_progress_trace());

        $this->assertEquals(8, $DB->count_records('user_enrolments'));
        $this->assertEquals(8, $DB->count_records('role_assignments'));
        $this->assertEquals(4, $DB->count_records('course'));

        $this->assertIsEnrolled($course1->id, $user1->id, $studentrole->id);
        $this->assertIsEnrolled($course1->id, $user2->id, $studentrole->id);
        $this->assertIsEnrolled($course1->id, $user3->id, $studentrole->id);
        $this->assertIsEnrolled($course1->id, $user5->id, $teacherrole->id);

        $this->assertIsEnrolled($course2->id, $user1->id, $studentrole->id);
        $this->assertIsEnrolled($course2->id, $user2->id, $studentrole->id);
        $this->assertIsEnrolled($course2->id, $user3->id, $studentrole->id);
        $this->assertIsEnrolled($course2->id, $user4->id, $studentrole->id);


        try {
            // Test course creation.
            $enrol->set_config('autocreate', 1);

            $enrol->sync_enrolments(new null_progress_trace());

            $this->assertEquals(12, $DB->count_records('user_enrolments'));
            $this->assertEquals(12, $DB->count_records('role_assignments'));
            $this->assertEquals(5, $DB->count_records('course'));

            $course4 = $DB->get_record('course', array('idnumber'=>'course4'), '*', MUST_EXIST);

            $this->assertIsEnrolled($course4->id, $user1->id, $studentrole->id);
            $this->assertIsEnrolled($course4->id, $user2->id, $studentrole->id);
            $this->assertIsEnrolled($course4->id, $user5->id, $teacherrole->id);
            $this->assertIsEnrolled($course4->id, $user6->id, $teacherrole->id);


            // Test unenrolment.
            $ldap->delete('cn=course1,'.$studentsdn, $o);
            $o = array();
            $o['objectClass'] = array('posixGroup');
            $o['cn']          = 'course1';
            $o['gidNumber']   = '1';
            $ldap->add('cn='.$o['cn'].','.$studentsdn, $o);

            $enrol->set_config('unenrolaction', ENROL_EXT_REMOVED_KEEP);
            $enrol->sync_enrolments(new null_progress_trace());
            $this->assertEquals(12, $DB->count_records('user_enrolments'));
            $this->assertEquals(12, $DB->count_records('role_assignments'));
            $this->assertEquals(5, $DB->count_records('course'));

            $enrol->set_config('unenrolaction', ENROL_EXT_REMOVED_SUSPEND);
            $enrol->sync_enrolments(new null_progress_trace());
            $this->assertEquals(12, $DB->count_records('user_enrolments'));
            $this->assertEquals(12, $DB->count_records('role_assignments'));
            $this->assertEquals(5, $DB->count_records('course'));
            $this->assertIsEnrolled($course1->id, $user1->id, $studentrole->id, ENROL_USER_SUSPENDED);
            $this->assertIsEnrolled($course1->id, $user2->id, $studentrole->id, ENROL_USER_SUSPENDED);
            $this->assertIsEnrolled($course1->id, $user3->id, $studentrole->id, ENROL_USER_SUSPENDED);

            $ldap->delete('cn=course1,'.$studentsdn, $o);
            $o = array();
            $o['objectClass'] = array('posixGroup');
            $o['cn']          = 'course1';
            $o['gidNumber']   = '1';
            $o['memberUid']   = array('user1', 'user2', 'user3');
            $ldap->add('cn='.$o['cn'].','.$studentsdn, $o);

            $enrol->sync_enrolments(new null_progress_trace());
            $this->assertEquals(12, $DB->count_records('user_enrolments'));
            $this->assertEquals(12, $DB->count_records('role_assignments'));
            $this->assertEquals(5, $DB->count_records('course'));
            $this->assertIsEnrolled($course1->id, $user1->id, $studentrole->id, ENROL_USER_ACTIVE);
            $this->assertIsEnrolled($course1->id, $user2->id, $studentrole->id, ENROL_USER_ACTIVE);
            $this->assertIsEnrolled($course1->id, $user3->id, $studentrole->id, ENROL_USER_ACTIVE);

            $ldap->delete('cn=course1,'.$studentsdn, $o);
            $o = array();
            $o['objectClass'] = array('posixGroup');
            $o['cn']          = 'course1';
            $o['gidNumber']   = '1';
            $ldap->add('cn='.$o['cn'].','.$studentsdn, $o);

            $enrol->set_config('unenrolaction', ENROL_EXT_REMOVED_SUSPENDNOROLES);
            $enrol->sync_enrolments(new null_progress_trace());
            $this->assertEquals(12, $DB->count_records('user_enrolments'));
            $this->assertEquals(9, $DB->count_records('role_assignments'));
            $this->assertEquals(5, $DB->count_records('course'));
            $this->assertIsEnrolled($course1->id, $user1->id, 0, ENROL_USER_SUSPENDED);
            $this->assertIsEnrolled($course1->id, $user2->id, 0, ENROL_USER_SUSPENDED);
            $this->assertIsEnrolled($course1->id, $user3->id, 0, ENROL_USER_SUSPENDED);

            $ldap->delete('cn=course1,'.$studentsdn, $o);
            $o = array();
            $o['objectClass'] = array('posixGroup');
            $o['cn']          = 'course1';
            $o['gidNumber']   = '1';
            $o['memberUid']   = array('user1', 'user2', 'user3');
            $ldap->add('cn='.$o['cn'].','.$studentsdn, $o);

            $enrol->sync_enrolments(new null_progress_trace());
            $this->assertEquals(12, $DB->count_records('user_enrolments'));
            $this->assertEquals(12, $DB->count_records('role_assignments'));
            $this->assertEquals(5, $DB->count_records('course'));
            $this->assertIsEnrolled($course1->id, $user1->id, $studentrole->id, ENROL_USER_ACTIVE);
            $this->assertIsEnrolled($course1->id, $user2->id, $studentrole->id, ENROL_USER_ACTIVE);
            $this->assertIsEnrolled($course1->id, $user3->id, $studentrole->id, ENROL_USER_ACTIVE);

            $ldap->delete('cn=course1,'.$studentsdn, $o);
            $o = array();
            $o['objectClass'] = array('posixGroup');
            $o['cn']          = 'course1';
            $o['gidNumber']   = '1';
            $ldap->add('cn='.$o['cn'].','.$studentsdn, $o);

            $enrol->set_config('unenrolaction', ENROL_EXT_REMOVED_UNENROL);
            $enrol->sync_enrolments(new null_progress_trace());
            $this->assertEquals(9, $DB->count_records('user_enrolments'));
            $this->assertEquals(9, $DB->count_records('role_assignments'));
            $this->assertEquals(5, $DB->count_records('course'));
            $this->assertIsNotEnrolled($course1->id, $user1->id);
            $this->assertIsNotEnrolled($course1->id, $user2->id);
            $this->assertIsNotEnrolled($course1->id, $user3->id);


            // Individual user enrolments-

            $ldap->delete('cn=course1,'.$studentsdn, $o);
            $o = array();
            $o['objectClass'] = array('posixGroup');
            $o['cn']          = 'course1';
            $o['gidNumber']   = '1';
            $o['memberUid']   = array('user1', 'user2', 'user3');
            $ldap->add('cn='.$o['cn'].','.$studentsdn, $o);

            $enrol->sync_user_enrolments($user1);
            $this->assertEquals(10, $DB->count_records('user_enrolments'));
            $this->assertEquals(10, $DB->count_records('role_assignments'));
            $this->assertEquals(5, $DB->count_records('course'));
            $this->assertIsEnrolled($course1->id, $user1->id, $studentrole->id, ENROL_USER_ACTIVE);

            $ldap->delete('cn=course1,'.$studentsdn, $o);
            $o = array();
            $o['objectClass'] = array('posixGroup');
            $o['cn']          = 'course1';
            $o['gidNumber']   = '1';
            $o['memberUid']   = array('user2', 'user3');
            $ldap->add('cn='.$o['cn'].','.$studentsdn, $o);

            $enrol->set_config('unenrolaction', ENROL_EXT_REMOVED_KEEP);
            $enrol->sync_user_enrolments($user1);
            $this->assertEquals(10, $DB->count_records('user_enrolments'));
            $this->assertEquals(10, $DB->count_records('role_assignments'));
            $this->assertEquals(5, $DB->count_records('course'));
            $this->assertIsEnrolled($course1->id, $user1->id, $studentrole->id, ENROL_USER_ACTIVE);

            $enrol->set_config('unenrolaction', ENROL_EXT_REMOVED_SUSPEND);
            $enrol->sync_user_enrolments($user1);
            $this->assertEquals(10, $DB->count_records('user_enrolments'));
            $this->assertEquals(10, $DB->count_records('role_assignments'));
            $this->assertEquals(5, $DB->count_records('course'));
            $this->assertIsEnrolled($course1->id, $user1->id, $studentrole->id, ENROL_USER_SUSPENDED);

            $ldap->delete('cn=course1,'.$studentsdn, $o);
            $o = array();
            $o['objectClass'] = array('posixGroup');
            $o['cn']          = 'course1';
            $o['gidNumber']   = '1';
            $o['memberUid']   = array('user1', 'user2', 'user3');
            $ldap->add('cn='.$o['cn'].','.$studentsdn, $o);

            $enrol->sync_user_enrolments($user1);
            $this->assertEquals(10, $DB->count_records('user_enrolments'));
            $this->assertEquals(10, $DB->count_records('role_assignments'));
            $this->assertEquals(5, $DB->count_records('course'));
            $this->assertIsEnrolled($course1->id, $user1->id, $studentrole->id, ENROL_USER_ACTIVE);

            $ldap->delete('cn=course1,'.$studentsdn, $o);
            $o = array();
            $o['objectClass'] = array('posixGroup');
            $o['cn']          = 'course1';
            $o['gidNumber']   = '1';
            $o['memberUid']   = array('user2', 'user3');
            $ldap->add('cn='.$o['cn'].','.$studentsdn, $o);

            $enrol->set_config('unenrolaction', ENROL_EXT_REMOVED_SUSPENDNOROLES);
            $enrol->sync_user_enrolments($user1);
            $this->assertEquals(10, $DB->count_records('user_enrolments'));
            $this->assertEquals(9, $DB->count_records('role_assignments'));
            $this->assertEquals(5, $DB->count_records('course'));
            $this->assertIsEnrolled($course1->id, $user1->id, 0, ENROL_USER_SUSPENDED);

            $ldap->delete('cn=course1,'.$studentsdn, $o);
            $o = array();
            $o['objectClass'] = array('posixGroup');
            $o['cn']          = 'course1';
            $o['gidNumber']   = '1';
            $o['memberUid']   = array('user1', 'user2', 'user3');
            $ldap->add('cn='.$o['cn'].','.$studentsdn, $o);

            $enrol->sync_user_enrolments($user1);
            $this->assertEquals(10, $DB->count_records('user_enrolments'));
            $this->assertEquals(10, $DB->count_records('role_assignments'));
            $this->assertEquals(5, $DB->count_records('course'));
            $this->assertIsEnrolled($course1->id, $user1->id, $studentrole->id, ENROL_USER_ACTIVE);

            $ldap->delete('cn=course1,'.$studentsdn, $o);
            $o = array();
            $o['objectClass'] = array('posixGroup');
            $o['cn']          = 'course1';
            $o['gidNumber']   = '1';
            $o['memberUid']   = array('user2', 'user3');
            $ldap->add('cn='.$o['cn'].','.$studentsdn, $o);

            $enrol->set_config('unenrolaction', ENROL_EXT_REMOVED_UNENROL);
            $enrol->sync_user_enrolments($user1);
            $this->assertEquals(9, $DB->count_records('user_enrolments'));
            $this->assertEquals(9, $DB->count_records('role_assignments'));
            $this->assertEquals(5, $DB->count_records('course'));
            $this->assertIsNotEnrolled($course1->id, $user1->id);
        } finally {
        }

        // NOTE: multiple roles in one course is not supported, sorry
    }

    public function assertIsEnrolled($courseid, $userid, $roleid, $status=null) {
        global $DB;

        $context = context_course::instance($courseid);
        $instance = $DB->get_record('enrol', array('courseid'=>$courseid, 'enrol'=>'ldap'));
        $this->assertNotEmpty($instance);
        $ue = $DB->get_record('user_enrolments', array('enrolid'=>$instance->id, 'userid'=>$userid));
        $this->assertNotEmpty($ue);
        if (isset($status)) {
            $this->assertEquals($status, $ue->status);
        }
        if ($roleid) {
            $this->assertTrue($DB->record_exists('role_assignments', array('contextid'=>$context->id, 'userid'=>$userid, 'roleid'=>$roleid, 'component'=>'enrol_ldap')));
        } else {
            $this->assertFalse($DB->record_exists('role_assignments', array('contextid'=>$context->id, 'userid'=>$userid, 'component'=>'enrol_ldap')));
        }
    }

    public function assertIsNotEnrolled($courseid, $userid) {
        $context = context_course::instance($courseid);
        $this->assertFalse(is_enrolled($context, $userid));
    }

    protected function enable_plugin() {
        $enabled = enrol_get_plugins(true);
        $enabled['ldap'] = true;
        $enabled = array_keys($enabled);
        set_config('enrol_plugins_enabled', implode(',', $enabled));
    }

    protected function disable_plugin() {
        $enabled = enrol_get_plugins(true);
        unset($enabled['ldap']);
        $enabled = array_keys($enabled);
        set_config('enrol_plugins_enabled', implode(',', $enabled));
    }
}
