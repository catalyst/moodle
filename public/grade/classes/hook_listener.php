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

namespace core_grades;

use core_group\hook\after_group_deleted;

/**
 * Hook listener for core_grades.
 *
 * @package    core_grades
 * @copyright  2025 Catalyst IT Australia Pty Ltd
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_listener {
    /**
     * Deletes group exemptions when a group is deleted.
     *
     * @param after_group_deleted $hook The delete group hook.
     */
    public static function delete_group_exemptions(after_group_deleted $hook): void {
        $exemptions = penalty_exemption::find_by([
            'itemtype' => penalty_exemption::TYPE_GROUP,
            'itemid' => $hook->groupinstance->id,
        ]);

        foreach ($exemptions as $exemption) {
            $exemption->delete();
        }
    }
}
