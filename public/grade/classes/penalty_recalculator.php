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

use core\context;

/**
 * Abstract class for defining the interface between the core penalty system and activity plugins.
 * Activity plugins must override this class and implement their own recalculate_penalty method.
 *
 * @package   core_grades
 * @copyright 2025 Catalyst IT Australia Pty Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class penalty_recalculator {
    /**
     * Recalculate penalties for the given context.
     * Activity plugins must update their grades with the latest penalty values.
     *
     * @param context $context Limit the recalculation to this context and its children.
     * @param int[]|null $userids Further limit the recalculation to these users.
     *                            If null, all users in the context will be recalculated.
     * @param int $usermodified The user who triggered the recalculation.
     *
     * @return void
     */
    abstract public static function recalculate_penalty(context $context, ?array $userids, int $usermodified): void;
}
