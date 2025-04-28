<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace gradepenalty_duedate;

use context_course;
use context_module;
use core\plugininfo\gradepenalty;
use core_grades\penalty_exemption;
use core_grades\penalty_manager;
use grade_item;
use gradepenalty_duedate\tests\penalty_testcase;

/**
 * Exemption API test.
 *
 * @package     gradepenalty_duedate
 * @copyright   2025 Catalyst IT Australia Pty Ltd
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \gradepenalty_duedate\penalty_exemption
 */
final class exemption_test extends penalty_testcase {
    /**
     * Test exempt user.
     *
     * @covers ::exempt_user
     * @covers ::is_user_exempt
     * @covers ::update_exemption
     * @covers ::delete_exemption
     */
    public function test_exempt_user(): void {
        $course = $this->getDataGenerator()->create_course();
        $coursectx = context_course::instance($course->id);
        $assign1 = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $assign2 = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $assign1ctx = context_module::instance($assign1->cmid);
        $assign2ctx = context_module::instance($assign2->cmid);
        $user1 = $this->getDataGenerator()->create_and_enrol($course);
        $user2 = $this->getDataGenerator()->create_and_enrol($course);
        $time = time();

        // Create sample rules.
        $this->create_sample_rules();

        // Enable grade penalty.
        gradepenalty::enable_plugin('duedate', true);
        penalty_manager::enable_module('assign');

        // Check user exemption status.
        $this->assertFalse(penalty_exemption::is_user_exempt($user1->id, $assign1ctx->id));
        $this->assertFalse(penalty_exemption::is_user_exempt($user1->id, $assign2ctx->id));
        $this->assertFalse(penalty_exemption::is_user_exempt($user2->id, $assign1ctx->id));
        $this->assertFalse(penalty_exemption::is_user_exempt($user2->id, $assign2ctx->id));

        // Exempt users.
        $exemption = penalty_exemption::exempt_user($user1->id, $coursectx->id, 'Course exemption');
        penalty_exemption::exempt_user($user2->id, $assign2ctx->id, 'Activity exemption');

        // Check user exemption status.
        $this->assertTrue(penalty_exemption::is_user_exempt($user1->id, $assign1ctx->id));
        $this->assertTrue(penalty_exemption::is_user_exempt($user1->id, $assign2ctx->id));
        $this->assertFalse(penalty_exemption::is_user_exempt($user2->id, $assign1ctx->id));
        $this->assertTrue(penalty_exemption::is_user_exempt($user2->id, $assign2ctx->id));

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

        // Grade users.
        grade_update('mod/assign', $course->id, 'mod', 'assign', $assign1->id, 0, ['userid' => $user1->id, 'rawgrade' => 100]);
        grade_update('mod/assign', $course->id, 'mod', 'assign', $assign2->id, 0, ['userid' => $user1->id, 'rawgrade' => 100]);
        grade_update('mod/assign', $course->id, 'mod', 'assign', $assign1->id, 0, ['userid' => $user2->id, 'rawgrade' => 100]);
        grade_update('mod/assign', $course->id, 'mod', 'assign', $assign2->id, 0, ['userid' => $user2->id, 'rawgrade' => 100]);

        // Apply penalties.
        penalty_manager::apply_grade_penalty_to_user($user1->id, $gradeitem1, $time, $time - HOURSECS);
        penalty_manager::apply_grade_penalty_to_user($user1->id, $gradeitem2, $time, $time - HOURSECS);
        penalty_manager::apply_grade_penalty_to_user($user2->id, $gradeitem1, $time, $time - HOURSECS);
        penalty_manager::apply_grade_penalty_to_user($user2->id, $gradeitem2, $time, $time - HOURSECS);

        // Check the grades.
        $this->assertEquals(100, $gradeitem1->get_final($user1->id)->finalgrade);
        $this->assertEquals(100, $gradeitem2->get_final($user1->id)->finalgrade);
        $this->assertEquals(90, $gradeitem1->get_final($user2->id)->finalgrade);
        $this->assertEquals(100, $gradeitem2->get_final($user2->id)->finalgrade);

        // Delete the first exemption.
        $exemption->delete();

        // Grade users.
        grade_update('mod/assign', $course->id, 'mod', 'assign', $assign1->id, 0, ['userid' => $user1->id, 'rawgrade' => 100]);
        grade_update('mod/assign', $course->id, 'mod', 'assign', $assign2->id, 0, ['userid' => $user1->id, 'rawgrade' => 100]);
        grade_update('mod/assign', $course->id, 'mod', 'assign', $assign1->id, 0, ['userid' => $user2->id, 'rawgrade' => 100]);
        grade_update('mod/assign', $course->id, 'mod', 'assign', $assign2->id, 0, ['userid' => $user2->id, 'rawgrade' => 100]);

        // Apply penalties.
        penalty_manager::apply_grade_penalty_to_user($user1->id, $gradeitem1, $time, $time - HOURSECS);
        penalty_manager::apply_grade_penalty_to_user($user1->id, $gradeitem2, $time, $time - HOURSECS);
        penalty_manager::apply_grade_penalty_to_user($user2->id, $gradeitem1, $time, $time - HOURSECS);
        penalty_manager::apply_grade_penalty_to_user($user2->id, $gradeitem2, $time, $time - HOURSECS);

        // Check the grades.
        $this->assertEquals(90, $gradeitem1->get_final($user1->id)->finalgrade);
        $this->assertEquals(90, $gradeitem2->get_final($user1->id)->finalgrade);
        $this->assertEquals(90, $gradeitem1->get_final($user2->id)->finalgrade);
        $this->assertEquals(100, $gradeitem2->get_final($user2->id)->finalgrade);

        // Check user exemption status.
        $this->assertFalse(penalty_exemption::is_user_exempt($user1->id, $assign1ctx->id));
        $this->assertFalse(penalty_exemption::is_user_exempt($user1->id, $assign2ctx->id));
        $this->assertFalse(penalty_exemption::is_user_exempt($user2->id, $assign1ctx->id));
        $this->assertTrue(penalty_exemption::is_user_exempt($user2->id, $assign2ctx->id));
    }

    /**
     * Test exempt group.
     *
     * @covers ::exempt_group
     * @covers ::is_user_exempt
     * @covers ::update_exemption
     * @covers ::delete_exemption
     */
    public function test_exempt_group(): void {
        $course = $this->getDataGenerator()->create_course();
        $coursectx = context_course::instance($course->id);
        $assign1 = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $assign2 = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $assign1ctx = context_module::instance($assign1->cmid);
        $assign2ctx = context_module::instance($assign2->cmid);
        $user1 = $this->getDataGenerator()->create_and_enrol($course);
        $user2 = $this->getDataGenerator()->create_and_enrol($course);
        $group1 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $group2 = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $time = time();

        // Add users to groups.
        groups_add_member($group1->id, $user1->id);
        groups_add_member($group2->id, $user2->id);

        // Create sample rules.
        $this->create_sample_rules();

        // Enable grade penalty.
        gradepenalty::enable_plugin('duedate', true);
        penalty_manager::enable_module('assign');

        // Check user exemption status.
        $this->assertFalse(penalty_exemption::is_user_exempt($user1->id, $assign1ctx->id));
        $this->assertFalse(penalty_exemption::is_user_exempt($user1->id, $assign2ctx->id));
        $this->assertFalse(penalty_exemption::is_user_exempt($user2->id, $assign1ctx->id));
        $this->assertFalse(penalty_exemption::is_user_exempt($user2->id, $assign2ctx->id));

        // Exempt groups.
        $exemption = penalty_exemption::exempt_group($group1->id, $coursectx->id, 'Course exemption');
        penalty_exemption::exempt_group($group2->id, $assign2ctx->id, 'Activity exemption');

        // Check user exemption status.
        $this->assertTrue(penalty_exemption::is_user_exempt($user1->id, $assign1ctx->id));
        $this->assertTrue(penalty_exemption::is_user_exempt($user1->id, $assign2ctx->id));
        $this->assertFalse(penalty_exemption::is_user_exempt($user2->id, $assign1ctx->id));
        $this->assertTrue(penalty_exemption::is_user_exempt($user2->id, $assign2ctx->id));

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

        // Grade users.
        grade_update('mod/assign', $course->id, 'mod', 'assign', $assign1->id, 0, ['userid' => $user1->id, 'rawgrade' => 100]);
        grade_update('mod/assign', $course->id, 'mod', 'assign', $assign2->id, 0, ['userid' => $user1->id, 'rawgrade' => 100]);
        grade_update('mod/assign', $course->id, 'mod', 'assign', $assign1->id, 0, ['userid' => $user2->id, 'rawgrade' => 100]);
        grade_update('mod/assign', $course->id, 'mod', 'assign', $assign2->id, 0, ['userid' => $user2->id, 'rawgrade' => 100]);

        // Apply penalties.
        penalty_manager::apply_grade_penalty_to_user($user1->id, $gradeitem1, $time, $time - HOURSECS);
        penalty_manager::apply_grade_penalty_to_user($user1->id, $gradeitem2, $time, $time - HOURSECS);
        penalty_manager::apply_grade_penalty_to_user($user2->id, $gradeitem1, $time, $time - HOURSECS);
        penalty_manager::apply_grade_penalty_to_user($user2->id, $gradeitem2, $time, $time - HOURSECS);

        // Check the grades.
        $this->assertEquals(100, $gradeitem1->get_final($user1->id)->finalgrade);
        $this->assertEquals(100, $gradeitem2->get_final($user1->id)->finalgrade);
        $this->assertEquals(90, $gradeitem1->get_final($user2->id)->finalgrade);
        $this->assertEquals(100, $gradeitem2->get_final($user2->id)->finalgrade);

        // Delete the exemption.
        $exemption->delete();

        // Grade users.
        grade_update('mod/assign', $course->id, 'mod', 'assign', $assign1->id, 0, ['userid' => $user1->id, 'rawgrade' => 100]);
        grade_update('mod/assign', $course->id, 'mod', 'assign', $assign2->id, 0, ['userid' => $user1->id, 'rawgrade' => 100]);
        grade_update('mod/assign', $course->id, 'mod', 'assign', $assign1->id, 0, ['userid' => $user2->id, 'rawgrade' => 100]);
        grade_update('mod/assign', $course->id, 'mod', 'assign', $assign2->id, 0, ['userid' => $user2->id, 'rawgrade' => 100]);

        // Apply penalties.
        penalty_manager::apply_grade_penalty_to_user($user1->id, $gradeitem1, $time, $time - HOURSECS);
        penalty_manager::apply_grade_penalty_to_user($user1->id, $gradeitem2, $time, $time - HOURSECS);
        penalty_manager::apply_grade_penalty_to_user($user2->id, $gradeitem1, $time, $time - HOURSECS);
        penalty_manager::apply_grade_penalty_to_user($user2->id, $gradeitem2, $time, $time - HOURSECS);

        // Check the grades.
        $this->assertEquals(90, $gradeitem1->get_final($user1->id)->finalgrade);
        $this->assertEquals(90, $gradeitem2->get_final($user1->id)->finalgrade);
        $this->assertEquals(90, $gradeitem1->get_final($user2->id)->finalgrade);
        $this->assertEquals(100, $gradeitem2->get_final($user2->id)->finalgrade);

        // Check user exemption status.
        $this->assertFalse(penalty_exemption::is_user_exempt($user1->id, $assign1ctx->id));
        $this->assertFalse(penalty_exemption::is_user_exempt($user1->id, $assign2ctx->id));
        $this->assertFalse(penalty_exemption::is_user_exempt($user2->id, $assign1ctx->id));
        $this->assertTrue(penalty_exemption::is_user_exempt($user2->id, $assign2ctx->id));
    }
}
