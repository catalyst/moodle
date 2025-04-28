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

namespace core_grades;

use context_course;

/**
 * Test hook listener for core_grades.
 *
 * @package    core_grades
 * @copyright  2025 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \core_grades\hook_listener
 */
final class hook_listener_test extends \advanced_testcase {

    /**
     * Reset after test.
     *
     * @return void
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test that group exemptions are deleted when a group is deleted.
     *
     * @covers \core_grades\hook_listener::delete_group_exemptions
     */
    public function test_delete_group_exemptions(): void {

        // Create some group exemptions.
        $course = $this->getDataGenerator()->create_course();
        $group1 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $group2 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $exemption1 = penalty_exemption::exempt_group($group1->id, context_course::instance($course->id)->id);
        $exemption2 = penalty_exemption::exempt_group($group1->id, SITEID);
        $exemption3 = penalty_exemption::exempt_group($group2->id, context_course::instance($course->id)->id);

        // Check the exemptions exist.
        $this->assertEquals(1, penalty_exemption::count_by(['id' => $exemption1->get_id()]));
        $this->assertEquals(1, penalty_exemption::count_by(['id' => $exemption2->get_id()]));
        $this->assertEquals(1, penalty_exemption::count_by(['id' => $exemption3->get_id()]));

        // Trigger the after_group_deleted hook.
        groups_delete_group($group1->id);

        // Check exemptions for group1 are deleted.
        $this->assertEquals(0, penalty_exemption::count_by(['id' => $exemption1->get_id()]));
        $this->assertEquals(0, penalty_exemption::count_by(['id' => $exemption2->get_id()]));
        $this->assertEquals(1, penalty_exemption::count_by(['id' => $exemption3->get_id()]));
    }
}

