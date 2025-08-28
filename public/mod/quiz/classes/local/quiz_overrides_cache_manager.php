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

use cache;

/**
 * Manager/Facade for the new quiz_overrides cache operations.
 *
 * This centralises interactions with the 'mod_quiz:quiz_overrides' cache to
 * avoid duplication and keep concerns separate from the datasource.
 *
 * @package     mod_quiz
 * @copyright   2025 Catalyst IT Australia Pty Ltd
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_overrides_cache_manager {
    /**
     * Get all overrides for a given quiz and user from cache (or underlying data source).
     *
     * @param int $quizid The quiz id.
     * @param int $userid The user id.
     * @return array Array of overrides (empty when none).
     */
    public static function get_overrides(int $quizid, int $userid): array {
        $cache = self::get_cache();
        $key = self::make_key($quizid, $userid);
        $value = $cache->get($key);
        return is_array($value) ? $value : [];
    }

    /**
     * Purge all overrides from the overrides cache.
     */
    public static function purge_all(): void {
        self::get_cache()->purge();
    }

    /**
     * Purge overrides for a specific user in a specific quiz.
     *
     * @param int $quizid The quiz id.
     * @param int $userid The user id.
     */
    public static function purge_for_user(int $quizid, int $userid): void {
        self::purge_for_users($quizid, [$userid]);
    }

    /**
     * Purge overrides for specific users in a specific quiz.
     *
     * @param int $quizid The quiz id.
     * @param int[] $userids The user ids.
     */
    public static function purge_for_users(int $quizid, array $userids): void {
        if (empty($userids)) {
            return;
        }
        $keys = array_map(static fn(int $userid): string => self::make_key($quizid, $userid), $userids);
        self::get_cache()->delete_many($keys);
    }

    /**
     * Purge overrides for all members of a given group in a specific quiz.
     *
     * @param int $quizid The quiz id.
     * @param int $groupid The group id.
     */
    public static function purge_for_group(int $quizid, int $groupid): void {
        global $DB;
        $userids = $DB->get_records('groups_members', ['groupid' => $groupid], '', 'userid');
        if (!empty($userids)) {
            self::purge_for_users($quizid, array_keys($userids));
        }
    }

    /**
     * Purge overrides for all members of a given group across all quizzes.
     *
     * @param int $groupid The group id.
     * @param int[] $userids The user ids.
     */
    public static function purge_for_group_members(int $groupid, array $userids): void {
        global $DB;

        $records = $DB->get_records('quiz_overrides', ['groupid' => $groupid], '', 'id,quiz');
        foreach (array_unique(array_column($records, 'quiz')) as $quizid) {
            self::purge_for_users($quizid, $userids);
        }
    }

    /**
     * Build the cache key.
     *
     * @param int $quizid The quiz id.
     * @param int $userid The user id.
     * @return string The cache key.
     */
    private static function make_key(int $quizid, int $userid): string {
        return "{$quizid}_{$userid}";
    }

    /**
     * Get the overrides cache instance.
     */
    private static function get_cache(): cache {
        return cache::make('mod_quiz', 'quiz_overrides');
    }
}
