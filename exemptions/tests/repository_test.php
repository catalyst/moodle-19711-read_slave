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

namespace core_exemptions;

use core_exemptions\local\repository\exemption_repository;
use core_exemptions\local\entity\exemption;

/**
 * Test class covering the exemption_repository.
 *
 * @package     core_exemptions
 * @category    test
 * @author      Alexander Van der Bellen <alexandervanderbellen@catalyst-au.net>
 * @copyright   2018 Jake Dallimore <jrhdallimore@gmail.com>
 * @copyright   2024 Catalyst IT Australia
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \core_exemptions\local\repository\exemption_repository
 */
final class repository_test extends \advanced_testcase {

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Setup some users and courses for use in the tests.
     *
     * @return array
     */
    protected function setup_users_and_courses() {
        $user1 = self::getDataGenerator()->create_user();
        $user1ctx = \context_user::instance($user1->id);
        $user2 = self::getDataGenerator()->create_user();
        $user2ctx = \context_user::instance($user2->id);
        $course1 = self::getDataGenerator()->create_course();
        $course2 = self::getDataGenerator()->create_course();
        $course1ctx = \context_course::instance($course1->id);
        $course2ctx = \context_course::instance($course2->id);
        return [$user1ctx, $user2ctx, $course1ctx, $course2ctx];
    }

    /**
     * Verify the basic create operation can create records, and is validated.
     *
     * @covers ::add
     */
    public function test_add(): void {
        [$user1ctx, $user2ctx, $course1ctx, $course2ctx] = $this->setup_users_and_courses();

        // Create an exemption repository and exempt a course.
        $exemptionsrepo = new exemption_repository();

        $exemcourse = new exemption(
            'core_course',
            'course',
            $course1ctx->instanceid,
            $course1ctx->id,
            'Hello, world!',
            FORMAT_PLAIN
        );
        $timenow = time(); // Reference only, to check that the created item has a time equal to or greater than this.
        $exemption = $exemptionsrepo->add($exemcourse);

        // Verify we get the record back.
        $this->assertInstanceOf(exemption::class, $exemption);
        $this->assertObjectHasProperty('id', $exemption);
        $this->assertEquals('core_course', $exemption->component);
        $this->assertEquals('course', $exemption->itemtype);
        $this->assertEquals('Hello, world!', $exemption->reason);
        $this->assertEquals(FORMAT_PLAIN, $exemption->reasonformat);

        // Verify the returned object has additional properties, created as part of the add.
        $this->assertObjectHasProperty('timecreated', $exemption);
        $this->assertGreaterThanOrEqual($timenow, $exemption->timecreated);
        $this->assertObjectHasProperty('timemodified', $exemption);
        $this->assertGreaterThanOrEqual($exemption->timemodified, $exemption->timecreated);
        $this->assertObjectHasProperty('usermodified', $exemption);

        // Try to save the same record again and confirm the store throws an exception.
        $this->expectException('dml_write_exception');
        $exemptionsrepo->add($exemcourse);
    }

    /**
     * Tests that incomplete exemptions cannot be saved.
     *
     * @covers ::add
     */
    public function test_add_incomplete_exemption(): void {
        [$user1ctx, $user2ctx, $course1ctx, $course2ctx] = $this->setup_users_and_courses();

        // Create an exemption repository and try to exempt a course.
        $exemptionsrepo = new exemption_repository();

        $exemcourse = new exemption(
            'core_course',
            'course',
            $course1ctx->instanceid,
            $course1ctx->id,
        );
        unset($exemcourse->component);

        $this->expectException('moodle_exception');
        $exemptionsrepo->add($exemcourse);
    }

    /**
     * Tests that exemptions can be saved in bulk.
     *
     * @covers ::add_all
     */
    public function test_add_all_basic(): void {
        [$user1ctx, $user2ctx, $course1ctx, $course2ctx] = $this->setup_users_and_courses();

        // Create an exemption repository and exempt several courses.
        $exemptionsrepo = new exemption_repository();
        $exemcourses = [];

        $exemcourses[] = new exemption(
            'core_course',
            'course',
            $course1ctx->instanceid,
            $course1ctx->id,
        );
        $exemcourses[] = new exemption(
            'core_course',
            'course',
            $course2ctx->instanceid,
            $course2ctx->id,
        );

        $timenow = time(); // Reference only, to check that the created item has a time equal to or greater than this.
        $exemptions = $exemptionsrepo->add_all($exemcourses);

        $this->assertIsArray($exemptions);
        $this->assertCount(2, $exemptions);
        foreach ($exemptions as $exemption) {
            // Verify we get the exemption back.
            $this->assertInstanceOf(exemption::class, $exemption);
            $this->assertEquals('core_course', $exemption->component);
            $this->assertEquals('course', $exemption->itemtype);

            // Verify the returned object has additional properties, created as part of the add.
            $this->assertObjectHasProperty('timecreated', $exemption);
            $this->assertGreaterThanOrEqual($timenow, $exemption->timecreated);
            $this->assertObjectHasProperty('timemodified', $exemption);
            $this->assertGreaterThanOrEqual($exemption->timemodified, $exemption->timecreated);
            $this->assertObjectHasProperty('usermodified', $exemption);
            $this->assertObjectHasProperty('reason', $exemption);
            $this->assertObjectHasProperty('reasonformat', $exemption);
        }

        // Try to save the same record again and confirm the store throws an exception.
        $this->expectException('dml_write_exception');
        $exemptionsrepo->add_all($exemcourses);
    }

    /**
     * Tests reading from the repository by instance id.
     *
     * @covers ::find
     */
    public function test_find(): void {
        [$user1ctx, $user2ctx, $course1ctx, $course2ctx] = $this->setup_users_and_courses();

        // Create an exemption repository and exempt a course.
        $exemptionsrepo = new exemption_repository();
        $exemption = new exemption(
            'core_course',
            'course',
            $course1ctx->instanceid,
            $course1ctx->id,
        );
        $exemption = $exemptionsrepo->add($exemption);

        // Now, from the repo, get the single exemption we just created, by id.
        $userexemption = $exemptionsrepo->find($exemption->id);
        $this->assertInstanceOf(exemption::class, $userexemption);
        $this->assertObjectHasProperty('timecreated', $userexemption);

        // Try to get an exemption we know doesn't exist.
        // We expect an exception in this case.
        $this->expectException(\dml_exception::class);
        $exemptionsrepo->find(0);
    }

    /**
     * Test verifying that find_all() returns all exemptions, or an empty array.
     *
     * @covers ::find_all
     */
    public function test_find_all(): void {
        [$user1ctx, $user2ctx, $course1ctx, $course2ctx] = $this->setup_users_and_courses();

        $exemptionsrepo = new exemption_repository();

        // Verify that the repository is empty.
        $this->assertCount(0, $exemptionsrepo->find_all());

        // Save an exemption for 2 courses, in different areas.
        $exemption = new exemption(
            'core_course',
            'course',
            $course1ctx->instanceid,
            $course1ctx->id,
        );
        $exemption2 = new exemption(
            'core_course',
            'course',
            $course2ctx->instanceid,
            $course2ctx->id,
        );
        $exemptionsrepo->add($exemption);
        $exemptionsrepo->add($exemption2);

        // Verify that find_all returns both of our exemptions.
        $exemptions = $exemptionsrepo->find_all();
        $this->assertCount(2, $exemptions);
        foreach ($exemptions as $exem) {
            $this->assertInstanceOf(exemption::class, $exem);
            $this->assertObjectHasProperty('id', $exem);
            $this->assertObjectHasProperty('timecreated', $exem);
        }
    }

    /**
     * Testing the pagination of the find_all method.
     *
     * @covers ::find_all
     */
    public function test_find_all_pagination(): void {
        [$user1ctx, $user2ctx, $course1ctx, $course2ctx] = $this->setup_users_and_courses();

        $exemptionsrepo = new exemption_repository();

        // Verify that the repository is empty.
        $this->assertCount(0, $exemptionsrepo->find_all(0, 10));

        // Save 10 arbitrary exemptions to the repo.
        foreach (range(1, 10) as $i) {
            $exemption = new exemption(
                'core_course',
                'course',
                $i,
                $course1ctx->id,
            );
            $exemptionsrepo->add($exemption);
        }

        // Verify we have 10 exemptions.
        $this->assertEquals(10, $exemptionsrepo->count());

        // Verify we can fetch the first page of 5 records.
        $exemptions = $exemptionsrepo->find_all(0, 5);
        $this->assertCount(5, $exemptions);

        // Verify we can fetch the second page.
        $exemptions = $exemptionsrepo->find_all(5, 10);
        $this->assertCount(5, $exemptions);

        // Verify the third page request ends with an empty array.
        $exemptions = $exemptionsrepo->find_all(10, 15);
        $this->assertCount(0, $exemptions);
    }

    /**
     * Test retrieval of a user's exemptions for a given criteria, in this case, area.
     *
     * @covers ::find_by
     */
    public function test_find_by(): void {
        [$user1ctx, $user2ctx, $course1ctx, $course2ctx] = $this->setup_users_and_courses();

        // Create an exemption repository and exempt a course.
        $exemptionsrepo = new exemption_repository();
        $exemption = new exemption(
            'core_course',
            'course',
            $course1ctx->instanceid,
            $course1ctx->id,
        );
        $exemptionsrepo->add($exemption);

        // Add another exemption.
        $exemption = new exemption(
            'core_course',
            'course_item',
            $course1ctx->instanceid,
            $course1ctx->id,
        );
        $exemptionsrepo->add($exemption);

        // From the repo, get the list of exemptions for the 'core_course/course' area.
        $userexemptions = $exemptionsrepo->find_by(['component' => 'core_course', 'itemtype' => 'course']);
        $this->assertIsArray($userexemptions);
        $this->assertCount(1, $userexemptions);

        // Try to get a list of exemptions for a non-existent area.
        $userexemptions = $exemptionsrepo->find_by(['component' => 'core_racecar', 'itemtype' => 'course']);
        $this->assertIsArray($userexemptions);
        $this->assertCount(0, $userexemptions);

        // From the repo, get the list of exemptions for the 'core_course/course' area when passed as an array.
        $userexemptions = $exemptionsrepo->find_by(['component' => 'core_course', 'itemtype' => ['course']]);
        $this->assertIsArray($userexemptions);
        $this->assertCount(1, $userexemptions);

        // From the repo, get the list of exemptions for the 'core_course' area given multiple item_types.
        $userexemptions = $exemptionsrepo->find_by(['component' => 'core_course', 'itemtype' => ['course', 'course_item']]);
        $this->assertIsArray($userexemptions);
        $this->assertCount(2, $userexemptions);
    }

    /**
     * Testing the pagination of the find_by method.
     *
     * @covers ::find_by
     */
    public function test_find_by_pagination(): void {
        [$user1ctx, $user2ctx, $course1ctx, $course2ctx] = $this->setup_users_and_courses();

        $exemptionsrepo = new exemption_repository();

        // Verify that by default, the repository is empty.
        $this->assertCount(0, $exemptionsrepo->find_by([], 0, 10));

        // Save 10 arbitrary exemptions to the repo.
        foreach (range(1, 10) as $i) {
            $exemption = new exemption(
                'core_course',
                'course',
                $i,
                $course1ctx->id,
            );
            $exemptionsrepo->add($exemption);
        }

        // Verify we have 10 exemptions.
        $this->assertEquals(10, $exemptionsrepo->count());

        // Verify a request for a page, when no criteria match, results in 0.
        $exemptions = $exemptionsrepo->find_by(['component' => 'core_racecar'], 0, 5);
        $this->assertCount(0, $exemptions);

        // Verify we can fetch a the first page of 5 records.
        $exemptions = $exemptionsrepo->find_by(['component' => 'core_course'], 0, 5);
        $this->assertCount(5, $exemptions);

        // Verify we can fetch the second page.
        $exemptions = $exemptionsrepo->find_by(['component' => 'core_course'], 5, 5);
        $this->assertCount(5, $exemptions);

        // Verify the third page request ends with an empty array.
        $exemptions = $exemptionsrepo->find_by(['component' => 'core_course'], 10, 5);
        $this->assertCount(0, $exemptions);
    }

    /**
     * Test the count_by() method.
     *
     * @covers ::count_by
     */
    public function test_count_by(): void {
        [$user1ctx, $user2ctx, $course1ctx, $course2ctx] = $this->setup_users_and_courses();

        // Create an exemption repository and add 2 exemptions in different areas.
        $exemptionsrepo = new exemption_repository();
        $exemption = new exemption(
            'core_course',
            'course',
            $course1ctx->instanceid,
            $course1ctx->id,
        );
        $exemption2 = new exemption(
            'core_course',
            'anothertype',
            $course2ctx->instanceid,
            $course2ctx->id,
        );
        $exemptionsrepo->add($exemption);
        $exemptionsrepo->add($exemption2);

        // Verify counts can be restricted by criteria.
        $this->assertEquals(1, $exemptionsrepo->count_by(['component' => 'core_course', 'itemtype' => 'course']));
        $this->assertEquals(1, $exemptionsrepo->count_by(['component' => 'core_course', 'itemtype' => 'anothertype']));
        $this->assertEquals(0, $exemptionsrepo->count_by(['component' => 'core_course', 'itemtype' => 'nonexistenttype']));
    }

    /**
     * Test the exists() function.
     *
     * @covers ::exists
     */
    public function test_exists(): void {
        [$user1ctx, $user2ctx, $course1ctx, $course2ctx] = $this->setup_users_and_courses();

        // Create an exemption repository and exempt a course.
        $exemptionsrepo = new exemption_repository();
        $exemption = new exemption(
            'core_course',
            'course',
            $course1ctx->instanceid,
            $course1ctx->id,
        );
        $createdexemption = $exemptionsrepo->add($exemption);

        // Verify the existence of the exemption in the repo.
        $this->assertTrue($exemptionsrepo->exists($createdexemption->id));

        // Verify exists returns false for non-existent exemption.
        $this->assertFalse($exemptionsrepo->exists(0));
    }

    /**
     * Test the exists_by() method.
     *
     * @covers ::exists_by
     */
    public function test_exists_by(): void {
        [$user1ctx, $user2ctx, $course1ctx, $course2ctx] = $this->setup_users_and_courses();

        // Create an exemption repository and exempt two courses, in different areas.
        $exemptionsrepo = new exemption_repository();
        $exemption = new exemption(
            'core_course',
            'course',
            $course1ctx->instanceid,
            $course1ctx->id,
        );
        $exemption2 = new exemption(
            'core_course',
            'anothertype',
            $course2ctx->instanceid,
            $course2ctx->id,
        );
        $exemption1 = $exemptionsrepo->add($exemption);
        $exemption2 = $exemptionsrepo->add($exemption2);

        // Verify the existence of the exemptions.
        $this->assertTrue($exemptionsrepo->exists_by(
            [
                'component' => 'core_course',
                'itemtype' => 'course',
                'itemid' => $exemption1->itemid,
                'contextid' => $exemption1->contextid,
            ]
        ));
        $this->assertTrue($exemptionsrepo->exists_by(
            [
                'component' => 'core_course',
                'itemtype' => 'anothertype',
                'itemid' => $exemption2->itemid,
                'contextid' => $exemption2->contextid,
            ]
        ));

        // Verify that we can't find an exemption from one area, in another.
        $this->assertFalse($exemptionsrepo->exists_by(
            [
                'component' => 'core_course',
                'itemtype' => 'anothertype',
                'itemid' => $exemption1->itemid,
                'contextid' => $exemption1->contextid,
            ]
        ));
    }

    /**
     * Test the update() method, by simulating changing the reason of an exemption.
     *
     * @covers ::update
     */
    public function test_update(): void {
        [$user1ctx, $user2ctx, $course1ctx, $course2ctx] = $this->setup_users_and_courses();

        // Create an exemption repository and exempt a course.
        $exemptionsrepo = new exemption_repository();
        $exemption = new exemption(
            'core_course',
            'course',
            $course1ctx->instanceid,
            $course1ctx->id,
        );
        $exemption1 = $exemptionsrepo->add($exemption);
        $this->assertNull($exemption1->reason);

        // Verify we can update the reason.
        $exemption1->reason = 'Hello, world!';
        $exemption1 = $exemptionsrepo->update($exemption1);
        $this->assertInstanceOf(exemption::class, $exemption1);
        $this->assertEquals('Hello, world!', $exemption1->reason);
    }

    /**
     * Test the delete() method.
     *
     * @covers ::delete
     */
    public function test_delete(): void {
        [$user1ctx, $user2ctx, $course1ctx, $course2ctx] = $this->setup_users_and_courses();

        // Create an exemption repository and exempt a course.
        $exemptionsrepo = new exemption_repository();
        $exemption = new exemption(
            'core_course',
            'course',
            $course1ctx->instanceid,
            $course1ctx->id,
        );
        $exemption = $exemptionsrepo->add($exemption);

        // Verify the existence of the exemption in the repo.
        $this->assertTrue($exemptionsrepo->exists($exemption->id));

        // Now, delete the exemption and confirm it's not retrievable.
        $exemptionsrepo->delete($exemption->id);
        $this->assertFalse($exemptionsrepo->exists($exemption->id));
    }

    /**
     * Test the delete_by() method.
     *
     * @covers ::delete_by
     */
    public function test_delete_by(): void {
        [$user1ctx, $user2ctx, $course1ctx, $course2ctx] = $this->setup_users_and_courses();

        // Create an exemption repository and exempt two courses, in different areas.
        $exemptionsrepo = new exemption_repository();
        $exemption = new exemption(
            'core_course',
            'course',
            $course1ctx->instanceid,
            $course1ctx->id,
        );
        $exemption2 = new exemption(
            'core_course',
            'anothertype',
            $course1ctx->instanceid,
            $course1ctx->id,
        );
        $exemption1 = $exemptionsrepo->add($exemption);
        $exemption2 = $exemptionsrepo->add($exemption2);

        // Verify we have 2 items in the repo.
        $this->assertEquals(2, $exemptionsrepo->count());

        // Try to delete by a non-existent area, and confirm it doesn't remove anything.
        $exemptionsrepo->delete_by(
            [
                'component' => 'core_course',
                'itemtype' => 'donaldduck',
            ]
        );
        $this->assertEquals(2, $exemptionsrepo->count());

        // Try to delete by a non-existent area, and confirm it doesn't remove anything.
        $exemptionsrepo->delete_by(
            [
                'component' => 'core_course',
                'itemtype' => 'cat',
            ]
        );
        $this->assertEquals(2, $exemptionsrepo->count());

        // Delete by area, and confirm we have one record left, from the 'core_course/anothertype' area.
        $exemptionsrepo->delete_by(
            [
                'component' => 'core_course',
                'itemtype' => 'course',
            ]
        );
        $this->assertEquals(1, $exemptionsrepo->count());
        $this->assertFalse($exemptionsrepo->exists($exemption1->id));
        $this->assertTrue($exemptionsrepo->exists($exemption2->id));
    }

    /**
     * Test the find_exemption() method for an existing exemption.
     *
     * @covers ::find_exemption
     */
    public function test_find_exemption_basic(): void {
        [$user1ctx, $user2ctx, $course1ctx, $course2ctx] = $this->setup_users_and_courses();

        // Create an exemption repository and exempt two courses, in different areas.
        $exemptionsrepo = new exemption_repository();
        $exemption = new exemption(
            'core_course',
            'course',
            $course1ctx->instanceid,
            $course1ctx->id,
        );
        $exemption2 = new exemption(
            'core_course',
            'anothertype',
            $course1ctx->instanceid,
            $course1ctx->id,
        );
        $exemption1 = $exemptionsrepo->add($exemption);
        $exemption2 = $exemptionsrepo->add($exemption2);

        $exem = $exemptionsrepo->find_exemption('core_course', 'course', $course1ctx->instanceid,
            $course1ctx->id);
        $this->assertInstanceOf(\core_exemptions\local\entity\exemption::class, $exem);
    }

    /**
     * Test confirming the repository throws an exception in find_exemption if the exemption can't be found.
     *
     * @covers ::find_exemption
     */
    public function test_find_exemption_nonexistent_exemption(): void {
        [$user1ctx, $user2ctx, $course1ctx, $course2ctx] = $this->setup_users_and_courses();

        // Confirm we get an exception.
        $exemptionsrepo = new exemption_repository();
        $this->expectException(\dml_exception::class);
        $exemptionsrepo->find_exemption('core_course', 'course', 0, $course1ctx->id);
    }
}
