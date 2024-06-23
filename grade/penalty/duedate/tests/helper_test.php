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

global $CFG;

require_once($CFG->dirroot . '/grade/penalty/duedate/tests/penalty_test_base.php');
require_once($CFG->dirroot . '/grade/penalty/duedate/lib.php');

/**
 * Test helper methods.
 *
 * @package    gradepenalty_duedate
 * @copyright 2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class helper_test extends penalty_test_base {
    /**
     * Data provider for test_calculate_min_max_values.
     */
    public static function calculate_min_max_values_provider(): array {
        return [
            // Updating.
            [1, GRADEPENALTY_DUEDATE_ACTION_UPDATE, 1, DAYSECS * 2 - 1, 1, 19],
            [2, GRADEPENALTY_DUEDATE_ACTION_UPDATE, DAYSECS + 1, DAYSECS * 3 - 1, 11, 29],
            [3, GRADEPENALTY_DUEDATE_ACTION_UPDATE, DAYSECS * 2 + 1, DAYSECS * 4 - 1, 21, 39],
            [4, GRADEPENALTY_DUEDATE_ACTION_UPDATE, DAYSECS * 3 + 1, DAYSECS * 5 - 1, 31, 49],
            [5, GRADEPENALTY_DUEDATE_ACTION_UPDATE, DAYSECS * 4 + 1, YEARSECS, 41, 100],
            // Insert above.
            [1, GRADEPENALTY_DUEDATE_ACTION_INSERT_ABOVE, 1, DAYSECS - 1, 1, 9],
            [2, GRADEPENALTY_DUEDATE_ACTION_INSERT_ABOVE, DAYSECS + 1, DAYSECS * 2 - 1, 11, 19],
            [3, GRADEPENALTY_DUEDATE_ACTION_INSERT_ABOVE, DAYSECS * 2 + 1, DAYSECS * 3 - 1, 21, 29],
            [4, GRADEPENALTY_DUEDATE_ACTION_INSERT_ABOVE, DAYSECS * 3 + 1, DAYSECS * 4 - 1, 31, 39],
            [5, GRADEPENALTY_DUEDATE_ACTION_INSERT_ABOVE, DAYSECS * 4 + 1, DAYSECS * 5 - 1, 41, 49],
            // Insert below.
            [1, GRADEPENALTY_DUEDATE_ACTION_INSERT_BELOW, DAYSECS + 1, DAYSECS * 2 - 1, 11, 19],
            [2, GRADEPENALTY_DUEDATE_ACTION_INSERT_BELOW, DAYSECS * 2 + 1, DAYSECS * 3 - 1, 21, 29],
            [3, GRADEPENALTY_DUEDATE_ACTION_INSERT_BELOW, DAYSECS * 3 + 1, DAYSECS * 4 - 1, 31, 39],
            [4, GRADEPENALTY_DUEDATE_ACTION_INSERT_BELOW, DAYSECS * 4 + 1, DAYSECS * 5 - 1, 41, 49],
            [5, GRADEPENALTY_DUEDATE_ACTION_INSERT_BELOW, DAYSECS * 5 + 1, YEARSECS, 51, 100],
        ];
    }

    /**
     * Test calculate min max values.
     *
     * @dataProvider calculate_min_max_values_provider
     *
     * @covers \gradepenalty_duedate\helper::calculate_min_max_values
     *
     * @param int $sortorder The sortorder of the rule
     * @param string $action The action
     * @param int $expectedminlatefor The expected minlatefor value
     * @param int $expectedmaxlatefor The expected maxlatefor value
     * @param int $expectedminpenalty The expected minpenalty value
     * @param int $expectedmaxpenalty The expected maxpenalty value
     */
    public function test_calculate_min_max_values($sortorder, $action,
                                                  $expectedminlatefor, $expectedmaxlatefor,
                                                  $expectedminpenalty, $expectedmaxpenalty): void {
        $this->resetAfterTest();
        $this->create_sample_rules();

        // Get third rule.
        $rule = penalty_rule::get_record(['contextid' => 1, 'sortorder' => $sortorder]);

        list ($minlatefor, $maxlatefor) = helper::calculate_min_max_values(
            $rule->get('id'),
            $action,
            'latefor',
            GRADEPENALTY_DUEDATE_MIN_LATEFOR,
            GRADEPENALTY_DUEDATE_MAX_LATEFOR
        );

        // Check the expected values.
        $this->assertEquals($expectedminlatefor, $minlatefor);
        $this->assertEquals($expectedmaxlatefor, $maxlatefor);

        list ($minpenalty, $maxpenalty) = helper::calculate_min_max_values(
            $rule->get('id'),
            $action,
            'penalty',
            GRADEPENALTY_DUEDATE_MIN_PENALTY,
            GRADEPENALTY_DUEDATE_MAX_PENALTY
        );

        // Check the expected values.
        $this->assertEquals($expectedminpenalty, $minpenalty);
        $this->assertEquals($expectedmaxpenalty, $maxpenalty);
    }
}
