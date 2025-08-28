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

namespace mod_quiz\local;

use advanced_testcase;
use context_module;

/**
 * Tests for the new quiz_overrides cache and manager.
 *
 * @package     mod_quiz
 * @copyright   2025 Catalyst IT Australia Pty Ltd
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \mod_quiz\local\quiz_overrides_cache
 * @covers      \mod_quiz\local\quiz_overrides_cache_manager
 */
final class quiz_overrides_cache_test extends advanced_testcase {
    /**
     * Tests the quiz overrides cache response and invalidation flow.
     */
    public function test_quiz_overrides_cache_flow(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup environment.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $quiz = $generator->create_module('quiz', ['course' => $course->id]);
        $user1 = $generator->create_and_enrol($course);
        $user2 = $generator->create_and_enrol($course);
        $group = $generator->create_group(['courseid' => $course->id]);
        groups_add_member($group->id, $user2->id);

        $manager = new override_manager($quiz, context_module::instance($quiz->cmid));

        // Initially no overrides.
        $this->assertSame([], quiz_overrides_cache_manager::get_overrides($quiz->id, $user1->id));
        $this->assertSame([], quiz_overrides_cache_manager::get_overrides($quiz->id, $user2->id));

        // Create a user override and a group override.
        $useroverrideid = $manager->save_override([
            'userid' => $user1->id,
            'timelimit' => HOURSECS,
        ]);
        $groupoverrideid = $manager->save_override([
            'groupid' => $group->id,
            'timelimit' => HOURSECS * 2,
        ]);

        // User1 sees their user override.
        $overrides1 = quiz_overrides_cache_manager::get_overrides($quiz->id, $user1->id);
        $this->assertIsArray($overrides1);
        $this->assertCount(1, $overrides1);
        $this->assertEquals($useroverrideid, reset($overrides1)->id);

        // User2 sees the group override.
        $overrides2 = quiz_overrides_cache_manager::get_overrides($quiz->id, $user2->id);
        $this->assertIsArray($overrides2);
        $this->assertCount(1, $overrides2);
        $this->assertEquals($groupoverrideid, reset($overrides2)->id);

        // Remove user override; user1 should have none.
        $manager->delete_overrides_by_id([$useroverrideid], false);
        $this->assertSame([], quiz_overrides_cache_manager::get_overrides($quiz->id, $user1->id));

        // Delete group override; user2 should have none.
        $groupoverride = $DB->get_record('quiz_overrides', ['id' => $groupoverrideid], '*', MUST_EXIST);
        $manager->delete_overrides([$groupoverride], false);
        $this->assertSame([], quiz_overrides_cache_manager::get_overrides($quiz->id, $user2->id));
    }

    /**
     * Tests the core functionality of the quiz overrides cache.
     */
    public function test_cache_operations(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup environment.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $quiz = $generator->create_module('quiz', ['course' => $course->id]);
        $user1 = $generator->create_and_enrol($course);
        $user2 = $generator->create_and_enrol($course);
        $group = $generator->create_group(['courseid' => $course->id]);
        groups_add_member($group->id, $user2->id);

        $manager = new override_manager($quiz, context_module::instance($quiz->cmid));

        // Populate the cache and check it is empty initially.
        $this->assertEmpty(quiz_overrides_cache_manager::get_overrides($quiz->id, $user1->id));
        $this->assertEmpty(quiz_overrides_cache_manager::get_overrides($quiz->id, $user2->id));

        $useroverrideconfig = [
            'quizid' => $quiz->id,
            'userid' => $user1->id,
            'timelimit' => HOURSECS,
        ];

        $groupoverrideconfig = [
            'quizid' => $quiz->id,
            'groupid' => $group->id,
            'timelimit' => HOURSECS * 2,
        ];

        // Create a user override and a group override.
        $useroverrideid = $manager->save_override($useroverrideconfig);
        $groupoverrideid = $manager->save_override($groupoverrideconfig);

        // Check the overrides were created.
        $overrides = quiz_overrides_cache_manager::get_overrides($quiz->id, $user1->id);
        $this->assertIsArray($overrides);
        $this->assertCount(1, $overrides);
        $this->assertEquals($useroverrideid, reset($overrides)->id);

        $overrides = quiz_overrides_cache_manager::get_overrides($quiz->id, $user2->id);
        $this->assertIsArray($overrides);
        $this->assertCount(1, $overrides);
        $this->assertEquals($groupoverrideid, reset($overrides)->id);

        // Test deleting override by id.
        $manager->delete_overrides_by_id([$useroverrideid], false);
        $this->assertEmpty(quiz_overrides_cache_manager::get_overrides($quiz->id, $user1->id));
        $this->assertCount(1, quiz_overrides_cache_manager::get_overrides($quiz->id, $user2->id)); // User2 cache should remain.

        // Test deleting override by object.
        $groupoverride = $DB->get_record('quiz_overrides', ['id' => $groupoverrideid], '*', MUST_EXIST);
        $manager->delete_overrides([$groupoverride], false);
        $this->assertEmpty(quiz_overrides_cache_manager::get_overrides($quiz->id, $user1->id));
        $this->assertEmpty(quiz_overrides_cache_manager::get_overrides($quiz->id, $user2->id));

        // Test deleting all overrides.
        $manager->save_override($useroverrideconfig);
        $manager->save_override($groupoverrideconfig);

        $this->assertCount(1, quiz_overrides_cache_manager::get_overrides($quiz->id, $user1->id));
        $this->assertCount(1, quiz_overrides_cache_manager::get_overrides($quiz->id, $user2->id));

        $manager->delete_all_overrides(false);
        $this->assertEmpty(quiz_overrides_cache_manager::get_overrides($quiz->id, $user1->id));
        $this->assertEmpty(quiz_overrides_cache_manager::get_overrides($quiz->id, $user2->id));

        // Test group change events and check cache update.
        $manager->save_override($useroverrideconfig);
        $manager->save_override($groupoverrideconfig);

        $this->assertCount(1, quiz_overrides_cache_manager::get_overrides($quiz->id, $user1->id));
        $this->assertCount(1, quiz_overrides_cache_manager::get_overrides($quiz->id, $user2->id));

        // Add member and check cache update.
        groups_add_member($group->id, $user1->id);
        $this->assertCount(2, quiz_overrides_cache_manager::get_overrides($quiz->id, $user1->id));
        $this->assertCount(1, quiz_overrides_cache_manager::get_overrides($quiz->id, $user2->id));

        // Remove member and check cache update.
        groups_remove_member($group->id, $user1->id);
        $this->assertCount(1, quiz_overrides_cache_manager::get_overrides($quiz->id, $user1->id));
        $this->assertCount(1, quiz_overrides_cache_manager::get_overrides($quiz->id, $user2->id));

        // Delete group and check cache update.
        groups_delete_group($group->id);
        $this->assertCount(1, quiz_overrides_cache_manager::get_overrides($quiz->id, $user1->id));
        $this->assertEmpty(quiz_overrides_cache_manager::get_overrides($quiz->id, $user2->id));
    }
}
