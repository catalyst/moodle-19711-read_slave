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
use context_course;
use context_module;
use core\plugininfo\gradepenalty;
use core_exemptions\service_factory;
use grade_item;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/grade/penalty/duedate/tests/penalty_test_base.php');

/**
 * Exemption helper test.
 *
 * @package   gradepenalty_duedate
 * @copyright 2024 Catalyst IT Australia Pty Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \gradepenalty_duedate\exemption_helper
 */
final class exemption_helper_test extends penalty_test_base {
    /**
     * Test exempt user.
     *
     * @covers ::exempt_user
     * @covers ::is_exempt
     * @covers ::update_exemption
     * @covers ::delete_exemption
     */
    public function test_exempt_user(): void {
        $course = $this->getDataGenerator()->create_course();
        $coursectx = context_course::instance($course->id);
        $assign1 = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $assign2 = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $user1 = $this->getDataGenerator()->create_and_enrol($course);
        $user2 = $this->getDataGenerator()->create_and_enrol($course);

        // Create sample rules.
        $this->create_sample_rules();

        // Enable grade penalty.
        set_config('gradepenalty_enabled', 1);
        set_config('gradepenalty_supportedplugins', 'assign');
        gradepenalty::enable_plugin('duedate', true);

        // Exempt users.
        exemption_helper::exempt_user($user1->id, $coursectx->id, 'Reason 1');
        exemption_helper::exempt_user($user2->id, context_module::instance($assign2->cmid)->id, 'Reason 2');

        // Grade users.
        grade_update('mod/assign', $course->id, 'mod', 'assign', $assign1->id, 0,
            ['userid' => $user1->id, 'rawgrade' => 100]);
        grade_update('mod/assign', $course->id, 'mod', 'assign', $assign2->id, 0,
            ['userid' => $user1->id, 'rawgrade' => 100]);

        grade_update('mod/assign', $course->id, 'mod', 'assign', $assign1->id, 0,
            ['userid' => $user2->id, 'rawgrade' => 100]);
        grade_update('mod/assign', $course->id, 'mod', 'assign', $assign2->id, 0,
            ['userid' => $user2->id, 'rawgrade' => 100]);

        // Fetch grade items.
        $gradeitem1 = grade_item::fetch([
            'courseid' => $course->id,
            'itemmodule' => 'assign',
            'iteminstance' => $assign1->id,
        ]);

        $gradeitem2 = grade_item::fetch([
            'courseid' => $course->id,
            'itemmodule' => 'assign',
            'iteminstance' => $assign2->id,
        ]);

        // Apply penalties.
        $time = time();
        apply_grade_penalty_to_user($user1->id, $gradeitem1, $time, $time - HOURSECS);
        apply_grade_penalty_to_user($user2->id, $gradeitem1, $time, $time - HOURSECS);

        apply_grade_penalty_to_user($user1->id, $gradeitem2, $time, $time - HOURSECS);
        apply_grade_penalty_to_user($user2->id, $gradeitem2, $time, $time - HOURSECS);

        // Check the grades.
        $this->assertEquals(100, $gradeitem1->get_final($user1->id)->finalgrade);
        $this->assertEquals(90, $gradeitem1->get_final($user2->id)->finalgrade);

        $this->assertEquals(100, $gradeitem2->get_final($user1->id)->finalgrade);
        $this->assertEquals(100, $gradeitem2->get_final($user2->id)->finalgrade);

        // Get the course context exemption.
        $service = service_factory::get_service_for_component('gradepenalty_duedate');
        $exemption = $service->find('user', $user1->id, $coursectx->id);
        $this->assertEquals('Reason 1', $exemption->reason);

        // Update the exemption.
        $exemption->reason = 'Reason 3';
        exemption_helper::update_exemption($exemption);
        $exemption = $service->find('user', $user1->id, $coursectx->id);
        $this->assertEquals('Reason 3', $exemption->reason);

        // Delete the exemption.
        exemption_helper::delete_exemption($exemption->id);
        apply_grade_penalty_to_user($user1->id, $gradeitem2, $time, $time - HOURSECS);
        apply_grade_penalty_to_user($user2->id, $gradeitem2, $time, $time - HOURSECS);

        // Check the grades.
        $this->assertEquals(90, $gradeitem2->get_final($user1->id)->finalgrade);
        $this->assertEquals(100, $gradeitem2->get_final($user2->id)->finalgrade);
    }

    /**
     * Test exempt group.
     *
     * @covers ::exempt_group
     * @covers ::is_exempt
     * @covers ::update_exemption
     * @covers ::delete_exemption
     */
    public function test_exempt_group(): void {
        $course = $this->getDataGenerator()->create_course();
        $coursectx = context_course::instance($course->id);
        $assign1 = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $assign2 = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $user1 = $this->getDataGenerator()->create_and_enrol($course);
        $user2 = $this->getDataGenerator()->create_and_enrol($course);
        $group1 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $group2 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        // Add users to groups.
        groups_add_member($group1->id, $user1->id);
        groups_add_member($group2->id, $user2->id);

        // Create sample rules.
        $this->create_sample_rules();

        // Enable grade penalty.
        set_config('gradepenalty_enabled', 1);
        set_config('gradepenalty_supportedplugins', 'assign');
        gradepenalty::enable_plugin('duedate', true);

        // Exempt groups.
        exemption_helper::exempt_group($group1->id, $coursectx->id, 'Reason 1');
        exemption_helper::exempt_group($group2->id, context_module::instance($assign2->cmid)->id, 'Reason 2');

        // Grade users.
        grade_update('mod/assign', $course->id, 'mod', 'assign', $assign1->id, 0,
            ['userid' => $user1->id, 'rawgrade' => 100]);
        grade_update('mod/assign', $course->id, 'mod', 'assign', $assign2->id, 0,
            ['userid' => $user1->id, 'rawgrade' => 100]);

        grade_update('mod/assign', $course->id, 'mod', 'assign', $assign1->id, 0,
            ['userid' => $user2->id, 'rawgrade' => 100]);
        grade_update('mod/assign', $course->id, 'mod', 'assign', $assign2->id, 0,
            ['userid' => $user2->id, 'rawgrade' => 100]);

        // Fetch grade items.
        $gradeitem1 = grade_item::fetch([
            'courseid' => $course->id,
            'itemmodule' => 'assign',
            'iteminstance' => $assign1->id,
        ]);

        $gradeitem2 = grade_item::fetch([
            'courseid' => $course->id,
            'itemmodule' => 'assign',
            'iteminstance' => $assign2->id,
        ]);

        // Apply penalties.
        $time = time();
        apply_grade_penalty_to_user($user1->id, $gradeitem1, $time, $time - HOURSECS);
        apply_grade_penalty_to_user($user2->id, $gradeitem1, $time, $time - HOURSECS);

        apply_grade_penalty_to_user($user1->id, $gradeitem2, $time, $time - HOURSECS);
        apply_grade_penalty_to_user($user2->id, $gradeitem2, $time, $time - HOURSECS);

        // Check the grades.
        $this->assertEquals(100, $gradeitem1->get_final($user1->id)->finalgrade);
        $this->assertEquals(90, $gradeitem1->get_final($user2->id)->finalgrade);

        $this->assertEquals(100, $gradeitem2->get_final($user1->id)->finalgrade);
        $this->assertEquals(100, $gradeitem2->get_final($user2->id)->finalgrade);

        // Get the course context exemption.
        $service = service_factory::get_service_for_component('gradepenalty_duedate');
        $exemption = $service->find('group', $group1->id, $coursectx->id);
        $this->assertEquals('Reason 1', $exemption->reason);

        // Update the exemption.
        $exemption->reason = 'Reason 3';
        exemption_helper::update_exemption($exemption);
        $exemption = $service->find('group', $group1->id, $coursectx->id);
        $this->assertEquals('Reason 3', $exemption->reason);

        // Delete the exemption.
        exemption_helper::delete_exemption($exemption->id);
        apply_grade_penalty_to_user($user1->id, $gradeitem2, $time, $time - HOURSECS);
        apply_grade_penalty_to_user($user2->id, $gradeitem2, $time, $time - HOURSECS);

        // Check the grades.
        $this->assertEquals(90, $gradeitem2->get_final($user1->id)->finalgrade);
        $this->assertEquals(100, $gradeitem2->get_final($user2->id)->finalgrade);
    }
}
