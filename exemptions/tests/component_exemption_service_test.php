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

use core_exemptions\local\entity\exemption;
use html_writer;

/**
 * Test class covering the component_exemption_service within the service layer of exemptions.
 *
 * @package     core_exemptions
 * @category    test
 * @author      Alexander Van der Bellen <alexandervanderbellen@catalyst-au.net>
 * @copyright   2018 Jake Dallimore <jrhdallimore@gmail.com>
 * @copyright   2024 Catalyst IT Australia
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \core_exemptions\local\service\component_exemption_service
 */
final class component_exemption_service_test extends \advanced_testcase {

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Helper function to setup some users and courses for testing.
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
     * Generates an in-memory repository for testing, using an array store for CRUD stuff.
     *
     * @param array $mockstore
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    protected function get_mock_repository(array $mockstore) {
        // This mock will just store data in an array.
        $mockrepo = $this->getMockBuilder(\core_exemptions\local\repository\exemption_repository_interface::class)
            ->onlyMethods([])
            ->getMock();
        $mockrepo->expects($this->any())
            ->method('add')
            ->will($this->returnCallback(function (exemption $exemption) use (&$mockstore) {
                // Mock implementation of repository->add(), where an array is used instead of the DB.
                // Duplicates are confirmed via the unique key, and exceptions thrown just like a real repo.
                $key = $exemption->component . $exemption->itemtype . $exemption->itemid
                    . $exemption->contextid;

                // Check the objects for the unique key.
                foreach ($mockstore as $item) {
                    if ($item->uniquekey == $key) {
                        throw new \moodle_exception('exemption already exists');
                    }
                }
                $index = count($mockstore) + 1;     // Integer index.
                $exemption->uniquekey = $key;   // Simulate the unique key constraint.
                $exemption->id = $index;
                $mockstore[$index] = $exemption;
                return $mockstore[$index];
            }));
        $mockrepo->expects($this->any())
            ->method('update')
            ->will($this->returnCallback(function (exemption $exemption) use (&$mockstore) {
                $key = $exemption->component . $exemption->itemtype . $exemption->itemid . $exemption->contextid;
                foreach ($mockstore as $index => $item) {
                    if ($item->uniquekey == $key) {
                        $mockstore[$index] = $exemption;
                        return $exemption;
                    }
                }
                throw new \moodle_exception('exemption not found');
            }));
        $mockrepo->expects($this->any())
            ->method('find_by')
            ->will($this->returnCallback(function (array $criteria, int $limitfrom = 0, int $limitnum = 0) use (&$mockstore) {
                // Check for single value key pair vs multiple.
                $multipleconditions = [];
                foreach ($criteria as $key => $value) {
                    if (is_array($value)) {
                        $multipleconditions[$key] = $value;
                        unset($criteria[$key]);
                    }
                }

                // Initialise the return array.
                $returns = [];

                // Check the mockstore for all objects with properties matching the key => val pairs in $criteria.
                foreach ($mockstore as $index => $mockrow) {
                    $mockrowarr = (array) $mockrow;
                    if (array_diff_assoc($criteria, $mockrowarr) == []) {
                        $found = true;
                        foreach ($multipleconditions as $key => $value) {
                            if (!in_array($mockrowarr[$key], $value)) {
                                $found = false;
                                break;
                            }
                        }
                        if ($found) {
                            $returns[$index] = $mockrow;
                        }
                    }
                }
                // Return a subset of the records, according to the paging options, if set.
                if ($limitnum != 0) {
                    return array_slice($returns, $limitfrom, $limitnum);
                }
                // Otherwise, just return the full set.
                return $returns;
            }));
        $mockrepo->expects($this->any())
            ->method('find_exemption')
            ->will($this->returnCallback(function (string $comp, string $type, int $id, int $ctxid) use (&$mockstore) {
                // Check the mockstore for all objects with properties matching the key => val pairs in $criteria.
                $crit = ['component' => $comp, 'itemtype' => $type, 'itemid' => $id, 'contextid' => $ctxid];
                foreach ($mockstore as $fakerow) {
                    $fakerowarr = (array) $fakerow;
                    if (array_diff_assoc($crit, $fakerowarr) == []) {
                        return $fakerow;
                    }
                }
                throw new \dml_missing_record_exception("Item not found");
            }));
        $mockrepo->expects($this->any())
            ->method('find')
            ->will($this->returnCallback(function (int $id) use (&$mockstore) {
                return $mockstore[$id];
            }));
        $mockrepo->expects($this->any())
            ->method('exists')
            ->will($this->returnCallback(function (int $id) use (&$mockstore) {
                return array_key_exists($id, $mockstore);
            }));
        $mockrepo->expects($this->any())
            ->method('count_by')
            ->will($this->returnCallback(function (array $criteria) use (&$mockstore) {
                $count = 0;
                // Check the mockstore for all objects with properties matching the key => val pairs in $criteria.
                foreach ($mockstore as $index => $mockrow) {
                    $mockrowarr = (array) $mockrow;
                    if (array_diff_assoc($criteria, $mockrowarr) == []) {
                        $count++;
                    }
                }
                return $count;
            }));
        $mockrepo->expects($this->any())
            ->method('delete')
            ->will($this->returnCallback(function (int $id) use (&$mockstore) {
                foreach ($mockstore as $mockrow) {
                    if ($mockrow->id == $id) {
                        unset($mockstore[$id]);
                    }
                }
            }));
        $mockrepo->expects($this->any())
            ->method('delete_by')
            ->will($this->returnCallback(function (array $criteria) use (&$mockstore) {
                // Check the mockstore for all objects with properties matching the key => val pairs in $criteria.
                foreach ($mockstore as $index => $mockrow) {
                    $mockrowarr = (array) $mockrow;
                    if (array_diff_assoc($criteria, $mockrowarr) == []) {
                        unset($mockstore[$index]);
                    }
                }
            }));
        $mockrepo->expects($this->any())
            ->method('exists_by')
            ->will($this->returnCallback(function (array $criteria) use (&$mockstore) {
                // Check the mockstore for all objects with properties matching the key => val pairs in $criteria.
                foreach ($mockstore as $index => $mockrow) {
                    $mockrowarr = (array) $mockrow;
                    if (array_diff_assoc($criteria, $mockrowarr) == []) {
                        return true;
                    }
                }
                return false;
            }));
        return $mockrepo;
    }

    /**
     * Test the constructor.
     *
     * @covers ::__construct
     */
    public function test_constructor(): void {
        $repo = $this->get_mock_repository([]);

        // Create a service for a non-existent component.
        $this->expectException('moodle_exception');
        $service = new \core_exemptions\local\service\component_exemption_service('core_cccourse', $repo);
    }

    /**
     * Test the create method.
     *
     * @covers ::create
     */
    public function test_create(): void {
        [$user1ctx, $user2ctx, $course1ctx, $course2ctx] = $this->setup_users_and_courses();
        $repo = $this->get_mock_repository([]);
        $service = new \core_exemptions\local\service\component_exemption_service('core_course', $repo);

        // Create an exemption for a course.
        $exem = $service->create('course', $course1ctx->instanceid, $course1ctx->id);
        $this->assertObjectHasProperty('id', $exem);

        // Try to create an exemption for the same course again.
        $this->expectException('moodle_exception');
        $service->create('course', $course1ctx->instanceid, $course1ctx->id);
    }

    /**
     * Test the find method.
     *
     * @covers ::find
     */
    public function test_find(): void {
        [$user1ctx, $user2ctx, $course1ctx, $course2ctx] = $this->setup_users_and_courses();
        $repo = $this->get_mock_repository([]);
        $service = new \core_exemptions\local\service\component_exemption_service('core_course', $repo);

        // Create a couple of exemptions.
        $exem1 = $service->create('course', $course1ctx->instanceid, $course1ctx->id);
        $exem2 = $service->create('course', $course2ctx->instanceid, $course2ctx->id);

        // Find the exemption.
        $found = $service->find('course', $course1ctx->instanceid, $course1ctx->id);
        $this->assertEquals($exem1->id, $found->id);
    }

    /**
     * Test the find_by method.
     *
     * @covers ::find_by
     */
    public function test_findby(): void {
        [$user1ctx, $user2ctx, $course1ctx, $course2ctx] = $this->setup_users_and_courses();
        $repo = $this->get_mock_repository([]);
        $service = new \core_exemptions\local\service\component_exemption_service('core_course', $repo);

        // Create a couple of exemptions.
        $exem1 = $service->create('course', $course1ctx->instanceid, $course1ctx->id);
        $exem2 = $service->create('course', $course2ctx->instanceid, $course2ctx->id);

        // Find the exemption.
        $found = $service->find_by(['id' => $exem1->id]);
        $this->assertCount(1, $found);
        $this->assertEquals($exem1->id, reset($found)->id);

        $found = $service->find_by(['itemtype' => 'course']);
        $this->assertCount(2, $found);

        $found = $service->find_by(['contextid' => [$course1ctx->id, $course2ctx->id]]);
        $this->assertCount(2, $found);

        $found = $service->find_by(['itemtype' => 'module']);
        $this->assertCount(0, $found);
    }

    /**
     * Test the update method.
     *
     * @covers ::update
     */
    public function test_update(): void {
        [$user1ctx, $user2ctx, $course1ctx, $course2ctx] = $this->setup_users_and_courses();
        $repo = $this->get_mock_repository([]);
        $service = new \core_exemptions\local\service\component_exemption_service('core_course', $repo);
        $reason = 'Exemption granted by royal decree';
        $format = FORMAT_PLAIN;

        // Create an exemption.
        $exem = $service->create('course', $course1ctx->instanceid, $course1ctx->id, $reason, $format);
        $this->assertNotEmpty($exem->id);
        $this->assertEquals($reason, $exem->reason);
        $this->assertEquals($format, $exem->reasonformat);

        // Update the exemption.
        $newreason = html_writer::tag('p', 'Exemption granted by the gods');
        $newformat = FORMAT_HTML;
        $exem->reason = $newreason;
        $exem->reasonformat = $newformat;
        $updated = $service->update($exem);
        $this->assertEquals($updated->id, $exem->id);
        $this->assertEquals($newreason, $updated->reason);
        $this->assertEquals($newformat, $updated->reasonformat);
    }

    /**
     * Test the delete method.
     *
     * @covers ::delete
     */
    public function test_delete(): void {
        [$user1ctx, $user2ctx, $course1ctx, $course2ctx] = $this->setup_users_and_courses();
        $repo = $this->get_mock_repository([]);
        $service = new \core_exemptions\local\service\component_exemption_service('core_course', $repo);

        $itemtype = 'course';
        $itemid = $course1ctx->instanceid;
        $contextid = $course1ctx->id;

        // Create an exemption.
        $service->create($itemtype, $itemid, $contextid);
        $this->assertTrue($service->exists($itemtype, $itemid, $contextid));

        // Delete the exemption.
        $service->delete($itemtype, $itemid, $contextid);
        $this->assertFalse($service->exists($itemtype, $itemid, $contextid));
    }

    /**
     * Test the delete_by method.
     *
     * @covers ::delete_by
     */
    public function test_delete_by(): void {
        [$user1ctx, $user2ctx, $course1ctx, $course2ctx] = $this->setup_users_and_courses();
        $service = service_factory::get_service_for_component('core_course');

        $exem1 = $service->create('user', $user1ctx->instanceid, $course1ctx->id);
        $exem2 = $service->create('user', $user1ctx->instanceid, $course2ctx->id);
        $exem3 = $service->create('user', $user2ctx->instanceid, $course1ctx->id);
        $exem4 = $service->create('user', $user2ctx->instanceid, $course2ctx->id);

        // Delete exemption by id.
        $service->delete_by(['id' => $exem1->id]);
        $this->assertFalse($service->exists('user', $user1ctx->instanceid, $course1ctx->id));
        $this->assertEquals(3, $service->count_by(['itemtype' => 'user']));

        // Delete exemptions by contextid.
        $service->delete_by(['contextid' => $course1ctx->id]);
        $this->assertFalse($service->exists('user', $user2ctx->instanceid, $course1ctx->id));
        $this->assertEquals(2, $service->count_by(['itemtype' => 'user']));

        // Delete exemptions by itemtype and an array of itemids.
        $service->delete_by(['itemtype' => 'user', 'itemid' => [$user1ctx->instanceid, $user2ctx->instanceid]]);
        $this->assertFalse($service->exists('user', $user1ctx->instanceid, $course2ctx->id));
        $this->assertFalse($service->exists('user', $user1ctx->instanceid, $course2ctx->id));
        $this->assertEquals(0, $service->count_by(['itemtype' => 'user']));

        // Attempt to delete all exemptions.
        $this->expectException('moodle_exception');
        $service->delete_by([]);
    }

    /**
     * Test the exists method.
     *
     * @covers ::exists
     */
    public function test_exists(): void {
        [$user1ctx, $user2ctx, $course1ctx, $course2ctx] = $this->setup_users_and_courses();
        $repo = $this->get_mock_repository([]);
        $service = new \core_exemptions\local\service\component_exemption_service('core_course', $repo);

        $itemtype = 'course';
        $itemid = $course1ctx->instanceid;
        $contextid = $course1ctx->id;

        $this->assertFalse($service->exists($itemtype, $itemid, $contextid));
        $service->create($itemtype, $itemid, $contextid);
        $this->assertTrue($service->exists($itemtype, $itemid, $contextid));
    }

    /**
     * Test the count_by method.
     *
     * @covers ::count_by
     */
    public function test_count_by(): void {
        [$user1ctx, $user2ctx, $course1ctx, $course2ctx] = $this->setup_users_and_courses();
        $repo = $this->get_mock_repository([]);
        $service = new \core_exemptions\local\service\component_exemption_service('core_course', $repo);

        $itemtype = 'course';
        $itemid = $course1ctx->instanceid;
        $contextid = $course1ctx->id;

        $this->assertEquals(0, $service->count_by(['itemtype' => $itemtype, 'itemid' => $itemid, 'contextid' => $contextid]));
        $service->create($itemtype, $itemid, $contextid);
        $this->assertEquals(1, $service->count_by(['itemtype' => $itemtype, 'itemid' => $itemid, 'contextid' => $contextid]));
    }

    /**
     * Verify that the join sql generated by get_join_sql_by_type is valid and can be used to include exemption information.
     *
     * @covers ::get_join_sql_by_type
     */
    public function test_get_join_sql_by_type(): void {
        global $DB;
        [$user1ctx, $user2ctx, $course1ctx, $course2ctx] = $this->setup_users_and_courses();

        // Get a component_exemption_service for the user.
        // We need to use a real (DB) repository, as we want to run the SQL.
        $repo = new \core_exemptions\local\repository\exemption_repository();
        $service = new \core_exemptions\local\service\component_exemption_service('core_course', $repo);

        // Exempt the first course only.
        $service->create('course', $course1ctx->instanceid, $course1ctx->id);

        // Generate the join snippet.
        [$exemsql, $exemparams] = $service->get_join_sql_by_type('course', 'exemalias', 'c.id');

        // Join against a simple select, including the 2 courses only.
        $params = ['courseid1' => $course1ctx->instanceid, 'courseid2' => $course2ctx->instanceid];
        $params = $params + $exemparams;
        $records = $DB->get_records_sql("SELECT c.id, exemalias.component
                                           FROM {course} c $exemsql
                                          WHERE c.id = :courseid1 OR c.id = :courseid2", $params);

        // Verify the exemption information is returned, but only for the exempt course.
        $this->assertCount(2, $records);
        $this->assertEquals('core_course', $records[$course1ctx->instanceid]->component);
        $this->assertEmpty($records[$course2ctx->instanceid]->component);
    }
}
