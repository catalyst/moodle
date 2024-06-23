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

namespace gradepenalty_duedate;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../../lib.php');

/**
 * Helper for grade penalty
 *
 * @package    gradepenalty_duedate
 * @copyright 2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
    /**
     * Determine min and max value for latefor and penalty for a rule when updating or inserting.
     * If updating, the ruleid belongs to the rule we are updating.
     * If inserting, the ruleid belong to the rule which we will insert new rule before or after it
     *
     * @param int $ruleid the rule id we are updating or inserting
     * @param int $action we are updating or inserting
     * @param string $field  which is 'latefor' or 'penalty'
     * @param int $defaultmin default min value
     * @param int $defaultmax default max value
     * @return array
     */
    public static function calculate_min_max_values($ruleid, $action, $field, $defaultmin, $defaultmax) {
        global $DB;

        $minlatefor = $defaultmin;
        $maxlatefor = $defaultmax;

        if ($ruleid !== 0) {
            // Current rule.
            $currentrule = $DB->get_record('gradepenalty_duedate_rule', ['id' => $ruleid]);

            // Get the previous rule.
            $previousrule = $DB->get_record('gradepenalty_duedate_rule', [
                'sortorder' => $currentrule->sortorder - 1,
                'contextid' => $currentrule->contextid,
            ]);

            // Get the next rule.
            $nextrule = $DB->get_record('gradepenalty_duedate_rule', [
                'sortorder' => $currentrule->sortorder + 1,
                'contextid' => $currentrule->contextid,
            ]);

            if ($action === GRADEPENALTY_DUEDATE_ACTION_INSERT_ABOVE) {
                // We will insert new rule above the current rule.
                $minlatefor = $previousrule ? $previousrule->$field + 1 : $defaultmin;
                $maxlatefor = $currentrule->$field - 1;
            } else if ($action === GRADEPENALTY_DUEDATE_ACTION_INSERT_BELOW) {
                // We will insert new rule below the current rule.
                $minlatefor = $currentrule->$field + 1;
                $maxlatefor = $nextrule ? $nextrule->$field - 1 : $defaultmax;
            } else if ($action === GRADEPENALTY_DUEDATE_ACTION_UPDATE) {
                // We are updating the rule, so we need to check the min and max value for the rule.
                $minlatefor = $previousrule ? $previousrule->$field + 1 : $defaultmin;
                $maxlatefor = $nextrule ? $nextrule->$field - 1 : $defaultmax;
            }
        }

        return [$minlatefor, $maxlatefor];
    }

    /**
     * Whether we can insert rule above or below.
     *
     * @param int $ruleid the rule id which we want to insert above or below.
     * @param int $action insert above or below.
     *
     * @return bool
     */
    public static function can_insert_rule(int $ruleid, int $action): bool {
        global $DB;

        if ($ruleid === 0) {
            return false;
        }

        $currentrule = $DB->get_record('gradepenalty_duedate_rule', ['id' => $ruleid]);

        if ($action === GRADEPENALTY_DUEDATE_ACTION_INSERT_ABOVE) {
            // Get the previous rule.
            $previousrule = $DB->get_record('gradepenalty_duedate_rule', [
                'sortorder' => $currentrule->sortorder - 1,
                'contextid' => $currentrule->contextid,
            ]);
            $previouslatefor = $previousrule ? $previousrule->latefor : GRADEPENALTY_DUEDATE_MIN_LATEFOR;
            $previouspenalty = $previousrule ? $previousrule->penalty : GRADEPENALTY_DUEDATE_MIN_PENALTY;
            // Check if we still have space for insertion (at least 1 second and 1 percent).
            return $currentrule->latefor > ($previouslatefor + 1) && $currentrule->penalty > ($previouspenalty + 1);
        } else if ($action === GRADEPENALTY_DUEDATE_ACTION_INSERT_BELOW) {
            // Get the next rule.
            $nextrule = $DB->get_record('gradepenalty_duedate_rule', [
                'sortorder' => $currentrule->sortorder + 1,
                'contextid' => $currentrule->contextid,
            ]);
            $nextlatefor = $nextrule ? $nextrule->latefor : GRADEPENALTY_DUEDATE_MAX_LATEFOR;
            $nextpenalty = $nextrule ? $nextrule->penalty : GRADEPENALTY_DUEDATE_MAX_PENALTY;
            // Check if we still have space for insertion (at least 1 second and 1 percent).
            return $currentrule->latefor < ($nextlatefor - 1) && $currentrule->penalty < ($nextpenalty - 1);
        }

        return false;
    }
}
