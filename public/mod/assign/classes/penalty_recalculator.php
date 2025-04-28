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

namespace mod_assign;

use core\context;
use mod_assign\task\recalculate_penalties;

/**
 * Recalculate penalties for the assignment.
 *
 * @package   mod_assign
 * @copyright 2025 Catalyst IT Australia Pty Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class penalty_recalculator extends \core_grades\penalty_recalculator {
    #[\Override]
    public static function recalculate_penalty(context $context, ?array $userids, int $usermodified): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        switch ($context->contextlevel) {
            case CONTEXT_MODULE:
                // Queue a task for the assignment.
                $cmid = $context->instanceid;
                $cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
                recalculate_penalties::queue([$cm->instance], $userids, $usermodified);
                break;

            case CONTEXT_COURSE:
                // Queue a task for the course.
                $courseid = $context->instanceid;
                $assignids = $DB->get_fieldset('assign', 'id', ['course' => $courseid]);
                recalculate_penalties::queue($assignids, $userids, $usermodified);
                break;

            case CONTEXT_SYSTEM:
                // Queue a task for each course with at least one assignment.
                $records = $DB->get_records('assign', null, '', 'id, course');
                $courses = [];
                foreach ($records as $id => $assign) {
                    $courses[$assign->course][] = $id;
                }

                foreach ($courses as $assignids) {
                    recalculate_penalties::queue($assignids, $userids, $usermodified);
                }
                break;

            default:
                throw new \coding_exception("Unsupported context level for assign penalty recalculation: {$context->contextlevel}");
        }
    }
}
