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

/**
 * Test class covering the component_exemption_service within the service layer of exemptions.
 *
 * @package    core_exemptions
 * @category   test
 * @copyright  2019 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class component_exemption_service_test extends \advanced_testcase {

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    // Basic setup stuff to be reused in most tests.
    protected function setup_users_and_courses() {
        $user1 = self::getDataGenerator()->create_user();
        $user1context = \context_user::instance($user1->id);
        $user2 = self::getDataGenerator()->create_user();
        $user2context = \context_user::instance($user2->id);
        $course1 = self::getDataGenerator()->create_course();
        $course2 = self::getDataGenerator()->create_course();
        $course1context = \context_course::instance($course1->id);
        $course2context = \context_course::instance($course2->id);
        return [$user1context, $user2context, $course1context, $course2context];
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
            ->will($this->returnCallback(function(exemption $exemption) use (&$mockstore) {
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
                $index = count($mockstore);     // Integer index.
                $exemption->uniquekey = $key;   // Simulate the unique key constraint.
                $exemption->id = $index;
                $mockstore[$index] = $exemption;
                return $mockstore[$index];
            })
        );
        $mockrepo->expects($this->any())
            ->method('find_by')
            ->will($this->returnCallback(function(array $criteria, int $limitfrom = 0, int $limitnum = 0) use (&$mockstore) {
                // Check the mockstore for all objects with properties matching the key => val pairs in $criteria.
                foreach ($mockstore as $index => $mockrow) {
                    $mockrowarr = (array)$mockrow;
                    if (array_diff_assoc($criteria, $mockrowarr) == []) {
                        $returns[$index] = $mockrow;
                    }
                }
                // Return a subset of the records, according to the paging options, if set.
                if ($limitnum != 0) {
                    return array_slice($returns, $limitfrom, $limitnum);
                }
                // Otherwise, just return the full set.
                return $returns;
            })
        );
        $mockrepo->expects($this->any())
            ->method('find_exemption')
            ->will($this->returnCallback(function (string $comp, string $type, int $id, int $ctxid) use (&$mockstore) {
                // Check the mockstore for all objects with properties matching the key => val pairs in $criteria.
                $crit = ['component' => $comp, 'itemtype' => $type, 'itemid' => $id, 'contextid' => $ctxid];
                foreach ($mockstore as $fakerow) {
                    $fakerowarr = (array)$fakerow;
                    if (array_diff_assoc($crit, $fakerowarr) == []) {
                        return $fakerow;
                    }
                }
                throw new \dml_missing_record_exception("Item not found");
            })
        );
        $mockrepo->expects($this->any())
            ->method('find')
            ->will($this->returnCallback(function(int $id) use (&$mockstore) {
                return $mockstore[$id];
            })
        );
        $mockrepo->expects($this->any())
            ->method('exists')
            ->will($this->returnCallback(function(int $id) use (&$mockstore) {
                return array_key_exists($id, $mockstore);
            })
        );
        $mockrepo->expects($this->any())
            ->method('count_by')
            ->will($this->returnCallback(function(array $criteria) use (&$mockstore) {
                $count = 0;
                // Check the mockstore for all objects with properties matching the key => val pairs in $criteria.
                foreach ($mockstore as $index => $mockrow) {
                    $mockrowarr = (array)$mockrow;
                    if (array_diff_assoc($criteria, $mockrowarr) == []) {
                        $count++;
                    }
                }
                return $count;
            })
        );
        $mockrepo->expects($this->any())
            ->method('delete')
            ->will($this->returnCallback(function(int $id) use (&$mockstore) {
                foreach ($mockstore as $mockrow) {
                    if ($mockrow->id == $id) {
                        unset($mockstore[$id]);
                    }
                }
            })
        );
        $mockrepo->expects($this->any())
            ->method('delete_by')
            ->will($this->returnCallback(function(array $criteria) use (&$mockstore) {
                // Check the mockstore for all objects with properties matching the key => val pairs in $criteria.
                foreach ($mockstore as $index => $mockrow) {
                    $mockrowarr = (array)$mockrow;
                    if (array_diff_assoc($criteria, $mockrowarr) == []) {
                        unset($mockstore[$index]);
                    }
                }
            })
        );
        $mockrepo->expects($this->any())
            ->method('exists_by')
            ->will($this->returnCallback(function(array $criteria) use (&$mockstore) {
                // Check the mockstore for all objects with properties matching the key => val pairs in $criteria.
                foreach ($mockstore as $index => $mockrow) {
                    $mockrowarr = (array)$mockrow;
                    echo "Here";
                    if (array_diff_assoc($criteria, $mockrowarr) == []) {
                        return true;
                    }
                }
                return false;
            })
        );
        return $mockrepo;
    }

    /**
     * Test confirming the deletion of exemptions by type and item, but with no optional context filter provided.
     */
    public function test_delete_exemptions_by_type_and_item(): void {
        list($user1context, $user2context, $course1context, $course2context) = $this->setup_users_and_courses();

        // Get a user_exemption_service for each user.
        $repo = $this->get_mock_repository([]); // Mock repository, using the array as a mock DB.
        $user1service = new \core_exemptions\local\service\user_exemption_service($user1context, $repo);
        $user2service = new \core_exemptions\local\service\user_exemption_service($user2context, $repo);

        // exemption both courses for both users.
        $exem1 = $user1service->create_exemption('core_course', 'course', $course1context->instanceid, $course1context);
        $exem2 = $user2service->create_exemption('core_course', 'course', $course1context->instanceid, $course1context);
        $exem3 = $user1service->create_exemption('core_course', 'course', $course2context->instanceid, $course2context);
        $exem4 = $user2service->create_exemption('core_course', 'course', $course2context->instanceid, $course2context);
        $this->assertTrue($repo->exists($exem1->id));
        $this->assertTrue($repo->exists($exem2->id));
        $this->assertTrue($repo->exists($exem3->id));
        $this->assertTrue($repo->exists($exem4->id));

        // exemption something else arbitrarily.
        $exem5 = $user2service->create_exemption('core_user', 'course', $course2context->instanceid, $course2context);
        $exem6 = $user2service->create_exemption('core_course', 'whatnow', $course2context->instanceid, $course2context);

        // Get a component_exemption_service to perform the type based deletion.
        $service = new \core_exemptions\local\service\component_exemption_service('core_course', $repo);

        // Delete all 'course' type exemptions (for all users who have exemptiond course1).
        $service->delete_exemptions_by_type_and_item('course', $course1context->instanceid);

        // Delete all 'course' type exemptions (for all users who have exemptiond course2).
        $service->delete_exemptions_by_type_and_item('course', $course2context->instanceid);

        // Verify the exemptions don't exist.
        $this->assertFalse($repo->exists($exem1->id));
        $this->assertFalse($repo->exists($exem2->id));
        $this->assertFalse($repo->exists($exem3->id));
        $this->assertFalse($repo->exists($exem4->id));

        // Verify exemptions of other types or for other components are not affected.
        $this->assertTrue($repo->exists($exem5->id));
        $this->assertTrue($repo->exists($exem6->id));

        // Try to delete exemptions for a type which we know doesn't exist. Verify no exception.
        $this->assertNull($service->delete_exemptions_by_type_and_item('course', $course1context->instanceid));
    }

    /**
     * Test confirming the deletion of exemptions by type and item and with the optional context filter provided.
     */
    public function test_delete_exemptions_by_type_and_item_with_context(): void {
        list($user1context, $user2context, $course1context, $course2context) = $this->setup_users_and_courses();

        // Get a user_exemption_service for each user.
        $repo = $this->get_mock_repository([]); // Mock repository, using the array as a mock DB.
        $user1service = new \core_exemptions\local\service\user_exemption_service($user1context, $repo);
        $user2service = new \core_exemptions\local\service\user_exemption_service($user2context, $repo);

        // exemption both courses for both users.
        $exem1 = $user1service->create_exemption('core_course', 'course', $course1context->instanceid, $course1context);
        $exem2 = $user2service->create_exemption('core_course', 'course', $course1context->instanceid, $course1context);
        $exem3 = $user1service->create_exemption('core_course', 'course', $course2context->instanceid, $course2context);
        $exem4 = $user2service->create_exemption('core_course', 'course', $course2context->instanceid, $course2context);
        $this->assertTrue($repo->exists($exem1->id));
        $this->assertTrue($repo->exists($exem2->id));
        $this->assertTrue($repo->exists($exem3->id));
        $this->assertTrue($repo->exists($exem4->id));

        // exemption something else arbitrarily.
        $exem5 = $user2service->create_exemption('core_user', 'course', $course1context->instanceid, $course1context);
        $exem6 = $user2service->create_exemption('core_course', 'whatnow', $course1context->instanceid, $course1context);

        // exemption the courses again, but this time in another context.
        $exem7 = $user1service->create_exemption('core_course', 'course', $course1context->instanceid, \context_system::instance());
        $exem8 = $user2service->create_exemption('core_course', 'course', $course1context->instanceid, \context_system::instance());
        $exem9 = $user1service->create_exemption('core_course', 'course', $course2context->instanceid, \context_system::instance());
        $exem10 = $user2service->create_exemption('core_course', 'course', $course2context->instanceid, \context_system::instance());

        // Get a component_exemption_service to perform the type based deletion.
        $service = new \core_exemptions\local\service\component_exemption_service('core_course', $repo);

        // Delete all 'course' type exemptions (for all users at ONLY the course 1 context).
        $service->delete_exemptions_by_type_and_item('course', $course1context->instanceid, $course1context);

        // Verify the exemptions for course 1 context don't exist.
        $this->assertFalse($repo->exists($exem1->id));
        $this->assertFalse($repo->exists($exem2->id));

        // Verify the exemptions for the same component and type, but NOT for the same contextid and unaffected.
        $this->assertTrue($repo->exists($exem3->id));
        $this->assertTrue($repo->exists($exem4->id));

        // Verify exemptions of other types or for other components are not affected.
        $this->assertTrue($repo->exists($exem5->id));
        $this->assertTrue($repo->exists($exem6->id));

        // Verify the course exemption at the system context are unaffected.
        $this->assertTrue($repo->exists($exem7->id));
        $this->assertTrue($repo->exists($exem8->id));
        $this->assertTrue($repo->exists($exem9->id));
        $this->assertTrue($repo->exists($exem10->id));

        // Try to delete exemptions for a type which we know doesn't exist. Verify no exception.
        $this->assertNull($service->delete_exemptions_by_type_and_item('course', $course1context->instanceid, $course1context));
    }
}
