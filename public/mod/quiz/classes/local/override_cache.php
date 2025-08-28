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
use core_cache\data_source_interface;
use core_cache\definition;

/**
 * Cache encapsulation for quiz overrides.
 *
 * @package     mod_quiz
 * @copyright   2025 Catalyst IT Australia Pty Ltd
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class override_cache implements data_source_interface {
    /**
     * @var string Invalidation event used to purge data when reset_userdata is called.
     * @see \cache_helper::purge_by_event()
     **/
    public const INVALIDATION_RESET_USERDATA = 'resetuserdata';

    /** @var ?override_cache The singleton instance for this class. */
    private static $instance = null;

    /**
     * Returns the singleton instance of this class.
     *
     * @param definition $definition The cache definition.
     * @return override_cache The singleton instance.
     */
    public static function get_instance_for_cache(definition $definition): override_cache {
        return self::$instance ??= new override_cache();
    }

    /**
     * {@inheritdoc}
     * @see \core_cache\data_source_interface::load_for_cache()
     *
     * @param string|int $key The key to load.
     * @return mixed An array of override records or null if none are found or false for invalidation.
     */
    public function load_for_cache($key) {
        global $DB;

        if ($key === 'lastinvalidation') {
            return false;
        }

        [$quizid, $userid] = self::split_cache_key($key);

        $subquery = "SELECT g.id
                       FROM {groups} g
                       JOIN {groups_members} gm ON gm.groupid = g.id
                       JOIN {quiz} q ON q.course = g.courseid
                      WHERE q.id = :subqueryquizid AND gm.userid = :subqueryuserid";

        $sql = "SELECT *
                  FROM {quiz_overrides}
                 WHERE quiz = :quizid AND (userid = :userid OR groupid IN ($subquery))";

        $records = $DB->get_records_sql($sql, [
            'quizid' => $quizid,
            'userid' => $userid,
            'subqueryquizid' => $quizid,
            'subqueryuserid' => $userid,
        ]);

        return empty($records) ? null : $records;
    }

    /**
     * {@inheritdoc}
     * @see \core_cache\data_source_interface::load_many_for_cache()
     *
     * @param array $keys An array of keys each of type string.
     * @return array An array of matching overrides.
     */
    public function load_many_for_cache(array $keys) {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->load_for_cache($key);
        }
        return $results;
    }

    /**
     * Get all overrides for a given quiz and user.
     *
     * @param int $quizid The quiz id.
     * @param int $userid The user id.
     * @return ?array Array of overrides or null if none found.
     */
    public static function get_overrides(int $quizid, int $userid): array|null {
        $cache = self::get_cache();
        $key = self::get_cache_key($quizid, $userid);
        return $cache->get($key);
    }

    /**
     * Purge all overrides from the cache.
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

        $keys = array_map(fn($userid): string => self::get_cache_key($quizid, $userid), $userids);
        $cache = self::get_cache();
        $cache->delete_many($keys);
    }

    /**
     * Get the cache instance.
     *
     * @return cache The cache instance.
     */
    private static function get_cache(): cache {
        return cache::make('mod_quiz', 'overrides');
    }

    /**
     * Generate a cache key for a given quiz and user.
     *
     * @param int $quizid The quiz id.
     * @param int $userid The user id.
     * @return string The cache key.
     */
    private static function get_cache_key(int $quizid, int $userid): string {
        return "{$quizid}_{$userid}";
    }

    /**
     * Split a cache key into its quizid and userid components.
     *
     * @param string $key The cache key.
     * @return array An array with quizid and userid.
     */
    private static function split_cache_key(string $key): array {
        return array_map('intval', explode('_', $key));
    }
}
