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

use stdClass;

/**
 * Forum user preference helpers.
 *
 * @package     mod_forum
 * @copyright   2026 Catalyst IT Australia Pty Ltd
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class preferences {
    /**
     * Get the effective value of the alternative nested discussion UI preference.
     *
     * @param stdClass|int|null $user The user record or id, or null for the current user.
     * @return bool Whether the alternative experimental UI should be used.
     */
    public static function get_useexperimentalui(stdClass|int|null $user = null): bool {
        return (bool) (get_user_preferences('forum_useexperimentalui', null, $user)
            ?? get_config('core', 'defaultpreference_useexperimentalui'));
    }
}
