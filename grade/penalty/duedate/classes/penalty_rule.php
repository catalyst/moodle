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

use core\persistent;

/**
 * To create/load/update/delete penalty rules.
 *
 * @package    gradepenalty_duedate
 * @copyright 2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class penalty_rule extends persistent {
    /** The table name this persistent object maps to. */
    const TABLE = 'gradepenalty_duedate_rule';

    /**
     * Return the definition of the properties of this model.
     */
    protected static function define_properties() {
        return [
            'contextid' => [
                'type' => PARAM_INT,
                'null' => NULL_NOT_ALLOWED,
            ],
            'latefor' => [
                'type' => PARAM_TEXT,
                'null' => NULL_NOT_ALLOWED,
            ],
            'penalty' => [
                'type' => PARAM_INT,
                'null' => NULL_NOT_ALLOWED,
            ],
            'sortorder' => [
                'type' => PARAM_INT,
                'null' => NULL_NOT_ALLOWED,
                'default' => 0,
            ],
        ];
    }

    /**
     * Function to get next rule, which has higher priority.
     *
     * @param int $sortorder
     * @param int $contextid
     */
    public static function get_next_rule($sortorder, $contextid) {
        return self::get_record(['sortorder' => $sortorder + 1, 'contextid' => $contextid]);
    }

    /**
     * Function to get previous rule, which has lower priority.
     *
     * @param int $sortorder
     * @param int $contextid
     */
    public static function get_previous_rule($sortorder, $contextid) {
        return self::get_record(['sortorder' => $sortorder - 1, 'contextid' => $contextid]);
    }

    /**
     * Validate sort order.
     */
    protected function validate_sortorder() {
        $sortorder = $this->raw_get('sortorder');
        $contextid = $this->raw_get('contextid');
        $ruleid = $this->raw_get('id');

        // We are creating first rule.
        if (empty($ruleid) && $sortorder == 0) {
            $rules = self::get_records(['contextid' => $contextid]);
            // There should be no existing rule.
            if (!empty($rules)) {
                return get_string('validation_this_is_not_first_rule', 'gradepenalty_duedate');
            }
            return true;
        }

        // We are updating or inserting new rule.
        $newlatefor = $this->raw_get('latefor');
        $newpenalty = $this->raw_get('penalty');
        if (!empty($this->raw_get('id'))) {
            // We are updating existing rule.
            $previousrule = self::get_previous_rule($sortorder, $contextid);
            $nextrule = self::get_next_rule($sortorder, $contextid);
        } else {
            // We are inserting new rule. Find the existing rule with the same sort order.
            $existingrule = self::get_record(['sortorder' => $sortorder, 'contextid' => $contextid]);
            // We are inserting new rule above or below the existing rule.
            if ($existingrule) {
                if ($newlatefor < $existingrule->get('latefor')) {
                    // We are inserting above.
                    $previousrule = self::get_previous_rule($sortorder, $contextid);
                    $nextrule = $existingrule;
                } else {
                    // We are inserting below.
                    $previousrule = $existingrule;
                    $nextrule = self::get_next_rule($sortorder, $contextid);
                }
            } else {
                // We use sort order as a reference for insertion. So this should not happen.
                return get_string('validation_sort_order_is_not_valid', 'gradepenalty_duedate');
            }
        }
        // The order of latefor and penalty should match.
        $isvalid = true;
        if ($previousrule) {
            $isvalid = $newlatefor > $previousrule->get('latefor') && $newpenalty > $previousrule->get('penalty');
        }
        if ($nextrule) {
            $isvalid = $isvalid && $newlatefor < $nextrule->get('latefor') && $newpenalty < $nextrule->get('penalty');
        }

        if (!$isvalid) {
            return get_string('validation_cannot_insert_new_rule', 'gradepenalty_duedate');
        }

        return true;
    }

    /**
     * Update the sort order of the rules after adding new rule.
     */
    protected function after_create() {
        $this->update_sortorder();
    }

    /**
     * Update the sort order of the rules after deletion.
     *
     * @param bool $result Whether or not the delete was successful.
     */
    protected function after_delete($result) {
        if ($result) {
            $this->update_sortorder();
        }
    }

    /**
     * Update sort order
     *
     * @return void
     */
    private function update_sortorder() {
        global $DB;
        // Update the sort order of the rules.
        $rules = $DB->get_records(self::TABLE, ['contextid' => $this->raw_get('contextid')], 'latefor ASC');
        $sortorder = 1;
        foreach ($rules as $rule) {
            $DB->update_record(self::TABLE, (object) [
                'id' => $rule->id,
                'sortorder' => $sortorder,
            ]);
            $sortorder++;
        }
    }

    /**
     * Create first rule.
     *
     * @param \stdClass $data Rule data.
     * @return void
     */
    public static function create_first_rule($data) {
        // We are creating first rule.
        $newrule = new penalty_rule(0, $data);
        // Set sort order to 0. This sort order will become 1 when resorting runs after creation.
        $newrule->set('sortorder', 0);
        $newrule->save();
    }

    /**
     * Insert new rule before or after the specified rule.
     * The sort order of the new rule is not '0', to distinguish it from the first rule.
     *
     * @param int $ruleid id of the rule which we are inserting before or after.
     * @param \stdClass $data Rule data.
     */
    public static function insert_rule($ruleid, $data) {
        $newrule = new penalty_rule(0, $data);
        $currentrule = new penalty_rule($ruleid);
        // Set sort order to the same as the current rule.
        // We will use this sort order to find the position of the new rule.
        $newrule->set('sortorder', $currentrule->get('sortorder'));
        $newrule->save();
    }

    /**
     * Update an existing rule.
     *
     * @param int $ruleid id of the rule which we are updating.
     * @param \stdClass $data Rule data.
     */
    public static function update_rule($ruleid, $data) {
        $rule = new penalty_rule($ruleid, $data);
        $rule->save();
    }

    /**
     * Delete a rule.
     *
     * @param int $ruleid id of the rule which we are deleting.
     */
    public static function delete_rule($ruleid) {
        $rule = new penalty_rule($ruleid);
        $rule->delete();
    }
}
