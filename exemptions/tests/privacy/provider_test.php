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

use core_privacy\tests\provider_testcase;
use core_exemptions\privacy\provider;
use core_privacy\local\request\transform;

/**
 * Privacy tests for core_exemptions.
 *
 * @package     core_exemptions
 * @category    test
 * @author      Alexander Van der Bellen <alexandervanderbellen@catalyst-au.net>
 * @copyright   2018 Jake Dallimore <jrhdallimore@gmail.com>
 * @copyright   2024 Catalyst IT Australia
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \core_exemptions\privacy\provider
 */
final class provider_test extends provider_testcase {

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Helper to set up some sample users and courses.
     */
    protected function set_up_courses_and_users() {
        $user1 = self::getDataGenerator()->create_user();
        $user1ctx = \context_user::instance($user1->id);
        $user2 = self::getDataGenerator()->create_user();
        $user2ctx = \context_user::instance($user2->id);
        $course1 = self::getDataGenerator()->create_course();
        $course2 = self::getDataGenerator()->create_course();
        $course1ctx = \context_course::instance($course1->id);
        $course2ctx = \context_course::instance($course2->id);
        return [$user1, $user2, $user1ctx, $user2ctx, $course1ctx, $course2ctx];
    }

    /**
     * Test confirming that contexts of exempt items can be added to the contextlist.
     *
     * @covers ::add_contexts_for_userid
     */
    public function test_add_contexts_for_userid(): void {
        [$user1, $user2, $user1ctx, $user2ctx, $course1ctx, $course2ctx] = $this->set_up_courses_and_users();
        $course3 = self::getDataGenerator()->create_course();

        // Exempt 2 courses for user1 and 1 course for user2.
        $service = \core_exemptions\service_factory::get_service_for_component('core_course');

        $reason = 'Exemption granted by royal decree.';
        $format = FORMAT_PLAIN;

        $systemcontext = \context_system::instance();
        $this->setUser($user1);
        $service->create('courses', $course1ctx->instanceid, $systemcontext->id, $reason, $format);
        $service->create('courses', $course2ctx->instanceid, $systemcontext->id, $reason, $format);

        // Now, just for variety, let's assume you can exempt a course at user context, and do so for user1.
        $service->create('courses', $course1ctx->instanceid, $user1ctx->id, $reason, $format);

        $this->setUser($user2);
        $service->create('courses', $course3->id, $systemcontext->id);

        $this->assertCount(4, $service->find_by(['itemtype' => 'courses']));

        // Now, ask the exemptions privacy api to export contexts for exemptions of the type we just created, for user1.
        $contextlist = new \core_privacy\local\request\contextlist();
        provider::add_contexts_for_userid($contextlist, $user1->id, 'core_course', 'courses');

        // Verify we have two contexts in the list for user1.
        $this->assertCount(2, $contextlist->get_contextids());

        // And verify we only have the system context returned for user2.
        $contextlist = new \core_privacy\local\request\contextlist();
        provider::add_contexts_for_userid($contextlist, $user2->id, 'core_course', 'courses');
        $this->assertCount(1, $contextlist->get_contextids());
    }

    /**
     * Test deletion of user exemptions based on an approved_contextlist and component area.
     *
     * @covers ::delete_exemptions_for_user
     */
    public function test_delete_exemptions_for_user(): void {
        [$user1, $user2, $user1ctx, $user2ctx, $course1ctx, $course2ctx] = $this->set_up_courses_and_users();

        // Exempt 2 courses for user1 and 1 course for user2.
        $service = \core_exemptions\service_factory::get_service_for_component('core_course');

        $reason = 'Exemption granted by royal decree.';
        $format = FORMAT_PLAIN;

        $this->setUser($user1);
        $service->create('user', $user1->id, $course1ctx->id, $reason, $format);
        $service->create('user', $user1->id, $course2ctx->id, $reason, $format);

        $this->setUser($user2);
        $service->create('user', $user2->id, $course2ctx->id, $reason, $format);

        $this->assertCount(3, $service->find_by(['itemtype' => 'user']));

        // Now, delete/sanitise the exemptions for user1 only.
        $contextids = [$course1ctx->id, $course2ctx->id];
        $approvedcontextlist = new \core_privacy\local\request\approved_contextlist($user1, 'core_course', $contextids);
        provider::delete_exemptions_for_user($approvedcontextlist, 'core_course', 'user');

        // Get 'user' exemptions and filter out the exemptions for user2.
        $exems = array_filter($service->find_by(['itemtype' => 'user']), function ($exem) use ($user2) {
            return $exem->itemid != $user2->id;
        });

        $this->assertCount(2, $exems);

        foreach ($exems as $exem) {
            $this->assertNotEquals($user1->id, $exem->usermodified);
            $this->assertNotEquals($reason, $exem->reason);
            $this->assertNotEquals($format, $exem->reasonformat);
        }
    }

    /**
     * Test deletion of all exemptions for a specified context, component area and item type.
     *
     * @covers ::delete_exemptions_for_all_users
     */
    public function test_delete_exemptions_for_all_users(): void {
        [$user1, $user2, $user1ctx, $user2ctx, $course1ctx, $course2ctx] = $this->set_up_courses_and_users();

        $service = \core_exemptions\service_factory::get_service_for_component('core_course');

        $reason = 'Exemption granted by royal decree.';
        $format = FORMAT_PLAIN;

        $service->create('modules', 1, $course1ctx->id, $reason, $format);
        $service->create('modules', 2, $course1ctx->id, $reason, $format);
        $service->create('modules', 3, $course2ctx->id, $reason, $format);

        $this->assertCount(3, $service->find_by(['itemtype' => 'modules']));

        // Now, delete/sanitise all course module exemptions in the 'course1' context only.
        provider::delete_exemptions_for_all_users($course1ctx, 'core_course', 'modules');

        // Verify that only a single exemption for user1 in course1 remains.
        $exems = array_filter($service->find_by(['itemtype' => 'modules']), function ($exem) use ($course2ctx) {
            return $exem->contextid != $course2ctx->id;
        });
        $this->assertCount(2, $exems);
        foreach ($exems as $exem) {
            $this->assertNotEquals($user1->id, $exem->usermodified);
            $this->assertNotEquals($reason, $exem->reason);
            $this->assertNotEquals($format, $exem->reasonformat);
        }
    }

    /**
     * Test confirming that user ID's from exempt items can be added to the userlist.
     *
     * @covers ::add_userids_for_context
     */
    public function test_add_userids_for_context(): void {
        [$user1, $user2, $user1ctx, $user2ctx, $course1ctx, $course2ctx] = $this->set_up_courses_and_users();

        $service = \core_exemptions\service_factory::get_service_for_component('core_course');

        $reason = 'Exemption granted by royal decree.';
        $format = FORMAT_PLAIN;

        $options1 = [
            'reason' => 'Exemption granted for personal reasons.',
            'reasonformat' => FORMAT_PLAIN,
            'usermodified' => $user1->id,
        ];
        $options2 = [
            'usermodified' => $user2->id,
        ];
        $systemcontext = \context_system::instance();
        $this->setUser($user1);
        $service->create('courses', $course1ctx->instanceid, $systemcontext->id, $reason, $format);
        // Now, for variety, let's assume you can exemption a course at user context, and do so for user1.
        $service->create('courses', $course1ctx->instanceid, $user1ctx->id, $reason, $format);

        $this->setUser($user2);
        $service->create('courses', $course2ctx->instanceid, $systemcontext->id, $reason, $format);

        $this->assertCount(3, $service->find_by(['itemtype' => 'courses']));

        // Now, ask the exemptions privacy api to export userids for exemptions of the type we just created, in the system context.
        $userlist = new \core_privacy\local\request\userlist($systemcontext, 'core_course');
        provider::add_userids_for_context($userlist, 'courses');

        // Verify we have two userids in the list for system context.
        $this->assertCount(2, $userlist->get_userids());
        $expected = [
            $user1->id,
            $user2->id,
        ];
        $this->assertEqualsCanonicalizing($expected, $userlist->get_userids());

        // Ask the exemptions privacy api to export userids for exemptions of the type we just created, in the user1 context.
        $userlist = new \core_privacy\local\request\userlist($user1ctx, 'core_course');
        provider::add_userids_for_context($userlist, 'courses');

        // Verify we have one userid in the list for user1 context.
        $this->assertCount(1, $userlist->get_userids());
        $this->assertEquals([$user1->id], $userlist->get_userids());

        // Ask the exemptions privacy api to export userids for exemptions of the type we just created, in the user2 context.
        $userlist = new \core_privacy\local\request\userlist($user2ctx, 'core_exemptions');
        provider::add_userids_for_context($userlist, 'courses');

        // Verify we do not have any userids in the list for user2 context.
        $this->assertCount(0, $userlist->get_userids());
    }

    /**
     * Test deletion of user exemptions based on an approved_userlist, component area and item type.
     *
     * @covers ::delete_exemptions_for_userlist
     */
    public function test_delete_exemptions_for_userlist(): void {
        [$user1, $user2, $user1ctx, $user2ctx, $course1ctx, $course2ctx] = $this->set_up_courses_and_users();

        $reason = 'Exemption granted by royal decree.';
        $format = FORMAT_PLAIN;

        $service = \core_exemptions\service_factory::get_service_for_component('core_course');
        $this->setUser($user1);
        $service->create('user', $user1->id, $course1ctx->id, $reason, $format);
        $service->create('user', $user1->id, $course2ctx->id, $reason, $format);
        $this->setUser($user2);
        $service->create('user', $user2->id, $course2ctx->id, $reason, $format);

        $this->assertCount(3, $service->find_by(['itemtype' => 'user']));

        $userlist = new \core_privacy\local\request\userlist($course1ctx, 'core_course');
        provider::add_userids_for_context($userlist, 'user');
        $this->assertCount(1, $userlist->get_userids());

        // Now, delete/sanitise the exemptions for user1 in the course1 context only.
        $approveduserlist = new \core_privacy\local\request\approved_userlist($course1ctx, 'core_course', [$user1->id]);
        provider::delete_exemptions_for_userlist($approveduserlist, 'user');
        $this->assertEquals([$user1->id], $approveduserlist->get_userids());
        $this->assertEmpty($service->find('user', $user1->id, $course1ctx->id)->usermodified);
        $this->assertEquals($user1->id, $service->find('user', $user1->id, $course2ctx->id)->usermodified);
        $this->assertEquals($user2->id, $service->find('user', $user2->id, $course2ctx->id)->usermodified);
    }

    /**
     * Test fetching the exemptions data for a specified user in a specified component, item type and item ID.
     *
     * @covers ::get_exemptions_info_for_user
     */
    public function test_get_exemptions_info_for_user(): void {
        [$user1, $user2, $user1ctx, $user2ctx, $course1ctx, $course2ctx] = $this->set_up_courses_and_users();

        $reason = 'Exemption granted by royal decree.';
        $format = FORMAT_PLAIN;

        $service = \core_exemptions\service_factory::get_service_for_component('core_course');
        $this->setUser($user1);
        $service->create('user', $user1->id, $course1ctx->id, $reason, $format);
        $service->create('user', $user1->id, $course2ctx->id, $reason, $format);
        $this->setUser($user2);
        $service->create('user', $user2->id, $course2ctx->id, $reason, $format);

        $this->assertCount(3, $service->find_by(['itemtype' => 'user']));

        // Get the exemptions info for user1 in the course1 context.
        $exeminfo = (object) provider::get_exemptions_info_for_user($user1->id, $course1ctx, 'core_course', 'user', $user1->id);
        $exem = $service->find('user', $user1->id, $course1ctx->id);
        $this->assertEquals($exem->component, $exeminfo->component);
        $this->assertEquals($exem->itemtype, $exeminfo->itemtype);
        $this->assertEquals($exem->itemid, $exeminfo->itemid);
        $this->assertEquals($exem->contextid, $exeminfo->contextid);
        $this->assertEquals(transform::datetime($exem->timecreated), $exeminfo->timecreated);
        $this->assertEquals(transform::datetime($exem->timemodified), $exeminfo->timemodified);
        $this->assertEquals($exem->usermodified, $exeminfo->usermodified);
        $this->assertEquals($exem->reason, $exeminfo->reason);
        $this->assertEquals($exem->reasonformat, $exeminfo->reasonformat);
    }
}
