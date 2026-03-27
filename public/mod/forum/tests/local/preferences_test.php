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

namespace mod_forum\local;

/**
 * Tests for the forum user preferences helper.
 *
 * @package     mod_forum
 * @copyright   2026 Catalyst IT Australia Pty Ltd
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \mod_forum\local\preferences
 */
final class preferences_test extends \advanced_testcase {
    /**
     * Test get_useexperimentalui returns the correct value.
     *
     * @covers ::get_useexperimentalui
     */
    public function test_get_useexperimentalui(): void {
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        // No admin default, no user pref.
        unset_config('defaultpreference_useexperimentalui');
        $this->assertFalse(preferences::get_useexperimentalui($user1));

        // Admin default disabled, no user pref.
        set_config('defaultpreference_useexperimentalui', '0');
        $this->assertFalse(preferences::get_useexperimentalui($user1));

        // Admin default enabled, no user pref.
        set_config('defaultpreference_useexperimentalui', '1');
        $this->assertTrue(preferences::get_useexperimentalui($user1));

        // Admin default enabled, user explicitly opts out.
        set_user_preference('forum_useexperimentalui', '0', $user1);
        $this->assertFalse(preferences::get_useexperimentalui($user1));

        // Admin default disabled, user explicitly opts in.
        set_config('defaultpreference_useexperimentalui', '0');
        set_user_preference('forum_useexperimentalui', '1', $user2);
        $this->assertTrue(preferences::get_useexperimentalui($user2));

        // Verify user-level prefs are independent.
        $this->assertFalse(preferences::get_useexperimentalui($user1));
        $this->assertTrue(preferences::get_useexperimentalui($user2));

        // Works with user id as well.
        $this->assertTrue(preferences::get_useexperimentalui($user2->id));

        // Works with null (current user).
        $this->setUser($user2);
        $this->assertTrue(preferences::get_useexperimentalui());
    }
}
