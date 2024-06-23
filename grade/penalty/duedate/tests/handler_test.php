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
use context_system;
use grade_item;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/grade/penalty/duedate/tests/penalty_test_base.php');
require_once($CFG->dirroot . '/grade/penalty/duedate/lib.php');

/**
 * Test handler methods.
 *
 * @package    gradepenalty_duedate
 * @copyright 2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class handler_test extends \gradepenalty_duedate\penalty_test_base {
    /**
     * Data provider for test_calculate_penalty.
     */
    public static function calculate_penalty_provider(): array {
        return [
            // No penalty.
            [0, 0, 0],
            // One day late.
            [1, 0, 10],
            [DAYSECS, 0, 10],
            // Two day late.
            [DAYSECS + 1, 0, 20],
            [DAYSECS * 2, 0, 20],
            // Three day late.
            [DAYSECS * 2 + 1, 0, 30],
            [DAYSECS * 3, 0, 30],
            // Four day late.
            [DAYSECS * 3 + 1, 0, 40],
            [DAYSECS * 4, 0, 40],
            // Five day late.
            [DAYSECS * 4 + 1, 0, 50],
            [DAYSECS * 5, 0, 50],
            // Six day late. Same penalty as five day late.
            [DAYSECS * 5 + 1, 0, 50],
            [DAYSECS * 6, 0, 50],
        ];
    }

    /**
     * Test calculate penalty.
     * @dataProvider calculate_penalty_provider
     * @covers \gradepenalty_duedate\handler::calculate_penalty
     * @covers \gradepenalty_duedate\handler::apply_penalty
     *
     * @param int $submissiondate The submission date.
     * @param int $duedate The due date.
     * @param int $expectedpenalty The expected penalty.
     */
    public function test_calculate_penalty($submissiondate, $duedate, $expectedpenalty): void {
        $this->resetAfterTest();

        // Create a course and an assignment.
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $assignment = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);

        // Create sample rules.
        $this->create_sample_rules();

        // Grade item.
        $gradeitem = grade_item::fetch(
            [
                'courseid' => $course->id,
                'itemtype' => 'mod',
                'itemmodule' => 'assign',
                'iteminstance' => $assignment->id,
                'itemnumber' => 0,
            ]
        );

        // Calculate the penalty.
        $handler = new \gradepenalty_duedate\handler($user->id, $gradeitem, $submissiondate, $duedate);

        // Check calculation.
        $penalty = $handler->calculate_penalty(100);
        $this->assertEquals($expectedpenalty, $penalty);

        // Grade before applying penalty.
        $gradeitem->update_final_grade($user->id, 100);
        $this->assertEquals(100, $gradeitem->get_final($user->id)->finalgrade);

        // Apply penalty to final grade.
        $handler->apply_penalty();
        $this->assertEquals(100 - $expectedpenalty, $gradeitem->get_final($user->id)->finalgrade);
    }

    /**
     * Rules set at different contexts.
     * @covers \gradepenalty_duedate\handler::find_effective_penalty_rules
     * @covers \gradepenalty_duedate\handler::calculate_penalty
     */
    public function test_effective_rules(): void {
        global $DB;
        $this->resetAfterTest();

        // Create a course and an assignment.
        $course = $this->getDataGenerator()->create_course();
        $assignment = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);

        // Grade item.
        $gradeitem = grade_item::fetch(
            [
                'courseid' => $course->id,
                'itemtype' => 'mod',
                'itemmodule' => 'assign',
                'iteminstance' => $assignment->id,
                'itemnumber' => 0,
            ]
        );

        // Penalty for 1 second late.
        $handler = new \gradepenalty_duedate\handler(1, $gradeitem, 1, 0);

        // Create a penalty rule at the system context.
        $systemcontext = context_system::instance();
        $systemrule = [
            'contextid' => $systemcontext->id,
            'latefor' => 1,
            'penalty' => 10,
            'sortorder' => 1,
        ];
        $DB->insert_record('gradepenalty_duedate_rule', (object)$systemrule);
        // The penalty should be 10.
        $this->assertEquals(10, $handler->calculate_penalty(100));

        // Create a penalty rule at the course context.
        $coursecontext = context_course::instance($course->id);
        $courserule = [
            'contextid' => $coursecontext->id,
            'latefor' => 1,
            'penalty' => 20,
            'sortorder' => 1,
        ];
        $DB->insert_record('gradepenalty_duedate_rule', (object)$courserule);
        // The penalty should be 20.
        $this->assertEquals(20, $handler->calculate_penalty(100));

        // Create a penalty rule at the module context.
        $cm = get_coursemodule_from_instance('assign', $assignment->id, $course->id);
        $modulecontext = context_module::instance($cm->id);
        $modulerule = [
            'contextid' => $modulecontext->id,
            'latefor' => 1,
            'penalty' => 30,
            'sortorder' => 1,
        ];
        $DB->insert_record('gradepenalty_duedate_rule', (object)$modulerule);
        // The penalty should be 30.
        $this->assertEquals(30, $handler->calculate_penalty(100));
    }
}
