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

namespace mod_assign\task;

use core\task\adhoc_task;

/**
 * Ad-hoc task to recalculate penalties for users in an assignment.
 *
 * @package    mod_assign
 * @copyright  2024 David Woloszyn <david.woloszyn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recalculate_penalties extends adhoc_task {
    #[\Override]
    public function execute(): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/assign/lib.php');
        require_once($CFG->dirroot . '/course/lib.php');

        // Get the custom data for this task.
        $assignids = $this->get_custom_data()->assignids ?? [];
        $userids = $this->get_custom_data()->userids ?? null;
        $usermodified = $this->get_custom_data()->usermodified ?? 0;

        // Validate parameters.
        if (empty($assignids) || !is_array($assignids) || !is_array($userids) && !is_null($userids)) {
            mtrace('Invalid parameters, skipping penalty recalculation.');
            return;
        }

        if (is_array($userids) && empty($userids)) {
            mtrace('Empty user list provided, skipping penalty recalculation.');
            mtrace('Hint: If you want to recalculate penalties for all users, pass null instead of an empty array.');
            return;
        }

        $assigncount = count($assignids);
        $usercount = is_null($userids) ? 'all' : count($userids);
        mtrace("Penalty recalculation was initiated by user {$usermodified} " .
            "for {$assigncount} assignments and {$usercount} users.");

        // Fetch assignment records with an additional cmidnumber field.
        [$insql, $inparams] = $DB->get_in_or_equal($assignids);
        $sql = "SELECT a.*, cm.id AS cmidnumber
                  FROM {assign} a
                  JOIN {course_modules} cm ON a.id = cm.instance
                  JOIN {modules} m ON cm.module = m.id
                 WHERE m.name = 'assign' AND a.id $insql";
        $assigns = $DB->get_records_sql($sql, $inparams);

        if (is_null($userids)) {
            // For each assignment, recalculate penalties for all users.
            foreach ($assigns as $assign) {
                mtrace("Recalculating penalties for all users in assignment {$assign->id}.");
                assign_update_grades($assign);
            }
            return;
        }

        foreach ($assigns as $assign) {
            // For each assignment, recalculate penalties for the specified users.
            $users = $DB->get_fieldset('assign_grades', 'userid', ['assignment' => $assign->id]);
            $users = array_intersect($users, $userids);
            foreach ($users as $userid) {
                mtrace("Recalculating penalties for user {$userid} in assignment {$assign->id}.");
                assign_update_grades($assign, $userid);
            }
        }

        mtrace('Penalty recalculation completed.');
    }

    /**
     * Queue the task.
     *
     * @param int[] $assignids List of assignment IDs to recalculate penalties for.
     * @param int[]|null $userids List of user IDs to recalculate penalties for. If null, all users will be processed.
     * @param int $usermodified The user ID of the user who triggered the recalculation.
     */
    public static function queue(array $assignids, ?array $userids, int $usermodified): void {
        $task = new self();
        $task->set_custom_data((object) [
            'assignids' => $assignids,
            'userids' => $userids,
            'usermodified' => $usermodified,
        ]);
        \core\task\manager::queue_adhoc_task($task);
    }
}
