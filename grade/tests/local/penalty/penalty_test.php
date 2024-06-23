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

namespace core_grades\local\penalty;

use grade_item;

/**
 * Test for manager class.
 *
 * @package   core_grades
 * @copyright 2024 Catalyst IT Australia Pty Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class penalty_test extends \advanced_testcase {
    /**
     * Test get_active_grade_penalty_plugin.
     *
     * @covers \core_grades\local\penalty\manager::get_active_grade_penalty_plugin
     */
    public function test_get_active_grade_penalty_plugin(): void {
        $this->resetAfterTest();

        // No active plugin by default.
        $this->assertEquals('', manager::get_active_grade_penalty_plugin());

        // Enable fake plugin.
        \core\plugininfo\gradepenalty::enable_plugin('fake', true);
        $this->assertEquals('fake', manager::get_active_grade_penalty_plugin());

        // Disable fake plugin.
        \core\plugininfo\gradepenalty::enable_plugin('fake', false);
        $this->assertEquals('', manager::get_active_grade_penalty_plugin());
    }

    /**
     * Test get_required_handler_class.
     *
     * @covers \core_grades\local\penalty\manager::get_required_handler_class
     */
    public function test_get_required_handler_class(): void {
        $this->resetAfterTest();

        // Fixture classes are not autoloaded.
        require_once('grade/tests/fixtures/gradepenalty_fake/classes/handler.php');

        // Get required handler class.
        $this->assertEquals('\gradepenalty_fake\handler', manager::get_required_handler_class('fake'));
    }

    /**
     * Test apply_penalty.
     *
     * @covers \core_grades\local\penalty\manager::apply_penalty
     * @covers \core_grades\local\penalty\penalty_handler::calculate_penalty
     * @covers \core_grades\local\penalty\penalty_handler::apply_penalty
     * @covers \core_grades\hook\before_penalty_applied
     * @covers \core_grades\hook\after_penalty_applied
     */
    public function test_apply_penalty(): void {
        $this->resetAfterTest();

        // Fixture classes are not autoloaded.
        require_once('grade/tests/fixtures/gradepenalty_fake/classes/handler.php');

        // Create user, course and assignment.
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);

        // Hook mock up.
        \core\di::set(
            \core\hook\manager::class,
            \core\hook\manager::phpunit_get_instance([
                'test_plugin1' => 'grade/tests/fixtures/hook_fixtures.php',
            ]),
        );

        // Get grade item.
        $gradeitem = grade_item::fetch(
            [
                'courseid' => $course->id,
                'itemtype' => 'mod',
                'itemmodule' => 'assign',
                'iteminstance' => $assign->id,
                'itemnumber' => 0,
            ]
        );

        // Grade for the user.
        $grade = [];
        $grade['userid'] = $user->id;
        $grade['rawgrade'] = 50;
        $grade['datesubmitted'] = time();

        // Penalty is not enabled.
        grade_update('mod/assign', $course->id, 'mod', 'assign', $assign->id, 0, $grade);
        $this->assertEquals(50, $gradeitem->get_final($user->id)->finalgrade);

        // Enable penalty. But the assign module is not supported/enabled.
        set_config('gradepenalty_enabled', 1);
        grade_update('mod/assign', $course->id, 'mod', 'assign', $assign->id, 0, $grade);
        $this->assertEquals(50, $gradeitem->get_final($user->id)->finalgrade);

        // Enable assign module. But there is no active penalty plugin.
        set_config('gradepenalty_supportedplugins', 'quiz,assign');
        grade_update('mod/assign', $course->id, 'mod', 'assign', $assign->id, 0, $grade);
        $this->assertEquals(50, $gradeitem->get_final($user->id)->finalgrade);

        // Enable fake gradepenalty plugin.
        \core\plugininfo\gradepenalty::enable_plugin('fake', true);

        // Update the grade again.
        grade_update('mod/assign', $course->id, 'mod', 'assign', $assign->id, 0, $grade);

        // Expect debugging messages from hooks.
        $debugmessages = [
            'before_penalty_applied callback',
            'Submission date: 86400',
            'Due date: 172800',
            'after_penalty_applied callback',
            'Grade before penalty: 50',
            'Penalty: 20',
        ];
        $this->assertdebuggingcalledcount(6, $debugmessages);

        // This will be deducted by 20.
        $this->assertEquals(30, $gradeitem->get_final($user->id)->finalgrade);
    }
}
