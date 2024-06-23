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
/**
 * Test penalty rule persistent methods.
 *
 * @package    gradepenalty_duedate
 * @copyright 2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class penalty_rule_persistent_test extends penalty_test_base {

    /**
     * Test get next rule.
     *
     * @covers \gradepenalty_duedate\penalty_rule::get_next_rule
     */
    public function test_get_next_rule(): void {
        $this->resetAfterTest();
        $this->create_sample_rules();
        $rule = penalty_rule::get_next_rule(2, 1);
        $this->assertEquals(3, $rule->get('sortorder'));
    }

    /**
     * Test get previous rule.
     *
     * @covers \gradepenalty_duedate\penalty_rule::get_previous_rule
     */
    public function test_get_previous_rule(): void {
        $this->resetAfterTest();
        $this->create_sample_rules();
        $rule = penalty_rule::get_previous_rule(3, 1);
        $this->assertEquals(2, $rule->get('sortorder'));
    }

    /**
     * Test create first rule.
     *
     * @covers \gradepenalty_duedate\penalty_rule::create_first_rule
     */
    public function test_create_first_rule(): void {
        $this->resetAfterTest();

        $ruledata = (object) ['contextid' => 1, 'latefor' => DAYSECS, 'penalty' => 10];
        penalty_rule::create_first_rule($ruledata);

        // Check data of the new rule.
        $rule = penalty_rule::get_record(['contextid' => 1, 'sortorder' => 1]);
        $this->assertEquals(DAYSECS, $rule->get('latefor'));
        $this->assertEquals(10, $rule->get('penalty'));

        // Exception if trying to create first rule again.
        $ruledata = (object) ['contextid' => 1, 'latefor' => WEEKSECS, 'penalty' => 20];
        $this->expectException(\coding_exception::class);
        penalty_rule::create_first_rule($ruledata);
    }

    /**
     * Data provider for test_insert_rule.
     *
     * return array
     */
    public static function insert_rule_provider(): array {
        return [
            // Insert before the third rule.
            // Invalid. Latefor period is not greater than the one from second rule.
            [(object)['contextid' => 1, 'latefor' => DAYSECS * 2, 'penalty' => 25], true, 0, 0, 0],
            // Invalid. Penalty is not greater than the one from second rule.
            [(object)['contextid' => 1, 'latefor' => DAYSECS * 2 + 1, 'penalty' => 20], true, 0, 0, 0],
            // Valid. Insert before the third rule.
            [(object)['contextid' => 1, 'latefor' => DAYSECS * 2 + 1, 'penalty' => 25], false, 3, DAYSECS * 2 + 1, 25],

            // Insert after the third rule.
            // Invalid. Latefor period is not less than the one from fourth rule.
            [(object)['contextid' => 1, 'latefor' => DAYSECS * 4, 'penalty' => 35], true, 0, 0, 0],
            // Invalid. Penalty is not less than the one from fourth rule.
            [(object)['contextid' => 1, 'latefor' => DAYSECS * 4 - 1, 'penalty' => 40], true, 0, 0, 0],
            // Valid. Insert after the third rule.
            [(object)['contextid' => 1, 'latefor' => DAYSECS * 4 - 1, 'penalty' => 35], false, 4, DAYSECS * 4 - 1, 35],
        ];
    }

    /**
     * Test insert rule.
     *
     * @covers \gradepenalty_duedate\penalty_rule::insert_rule
     *
     * @dataProvider insert_rule_provider
     *
     * @param object $data The data of the new rule
     * @param bool $expectederror If an exception is expected
     * @param int $expectedsortorder The expected sortorder of the new rule
     * @param int $expectedlatefor The expected latefor period of the new rule
     * @param int $expectedpenalty The expected penalty of the new rule
     */
    public function test_insert_rule($data, $expectederror, $expectedsortorder, $expectedlatefor, $expectedpenalty): void {
        $this->resetAfterTest();

        $this->create_sample_rules();

        if ($expectederror) {
            $this->expectException(\coding_exception::class);
        }
        // Insert after or before the third rule.
        $previousrule = penalty_rule::get_record(['contextid' => 1, 'sortorder' => 3]);
        penalty_rule::insert_rule($previousrule->get('id'), $data);

        if (!$expectederror) {
            // Check data of the new rule.
            $rule = penalty_rule::get_record(['contextid' => 1, 'sortorder' => $expectedsortorder]);
            $this->assertEquals($expectedlatefor, $rule->get('latefor'));
            $this->assertEquals($expectedpenalty, $rule->get('penalty'));
        }
    }

    /**
     * Data provider for test_update_rule.
     *
     * return array
     */
    public static function update_rule_provider(): array {
        return [
            // Update the third rule.
            // Invalid. Latefor period is not greater than the one from second rule.
            [(object)['latefor' => DAYSECS * 2], true, 0, 0, 0],
            // Invalid. Penalty is not greater than the one from second rule.
            [(object)['penalty' => 20], true, 0, 0, 0],
            // Valid. Update the third rule.
            [(object)['latefor' => DAYSECS * 2 + 1, 'penalty' => 21], false, 3, DAYSECS * 2 + 1, 21],
            // Invalid. Latefor period is not less than the one from fourth rule.
            [(object)['latefor' => DAYSECS * 4], true, 0, 0, 0],
            // Invalid. Penalty is not less than the one from fourth rule.
            [(object)['penalty' => 40], true, 0, 0, 0],
            // Valid. Update the third rule.
            [(object)['latefor' => DAYSECS * 4 - 1, 'penalty' => 39], false, 3, DAYSECS * 4 - 1, 39],
        ];
    }

    /**
     * Test update rule.
     *
     * @covers \gradepenalty_duedate\penalty_rule::update_rule
     *
     * @dataProvider update_rule_provider
     *
     * @param object $data The data of the rule to update
     * @param bool $expectederror If an exception is expected
     * @param int $expectedsortorder The expected sortorder of the rule
     * @param int $expectedlatefor The expected latefor period of the rule
     * @param int $expectedpenalty The expected penalty of the rule
     */
    public function test_update_rule($data, $expectederror, $expectedsortorder, $expectedlatefor, $expectedpenalty): void {
        $this->resetAfterTest();

        $this->create_sample_rules();

        if ($expectederror) {
            $this->expectException(\coding_exception::class);
        }
        $rule = penalty_rule::get_record(['contextid' => 1, 'sortorder' => 3]);
        penalty_rule::update_rule($rule->get('id'), $data);

        if (!$expectederror) {
            // Check data of the updated rule.
            $rule = penalty_rule::get_record(['contextid' => 1, 'sortorder' => $expectedsortorder]);
            $this->assertEquals($expectedlatefor, $rule->get('latefor'));
            $this->assertEquals($expectedpenalty, $rule->get('penalty'));
        }
    }

    /**
     * Test delete rule.
     *
     * @covers \gradepenalty_duedate\penalty_rule::delete_rule
     */
    public function test_delete_rule(): void {
        $this->resetAfterTest();

        $this->create_sample_rules();

        // Delete third rule.
        $rule = penalty_rule::get_record(['contextid' => 1, 'sortorder' => 3]);
        penalty_rule::delete_rule($rule->get('id'));

        // Check new sort order of the rules.
        $rules = penalty_rule::get_records(['contextid' => 1], 'sortorder');
        $this->assertEquals(4, count($rules));
        // Rule 1.
        $this->assertEquals(1, $rules[0]->get('sortorder'));
        $this->assertEquals(DAYSECS, $rules[0]->get('latefor'));
        $this->assertEquals(10, $rules[0]->get('penalty'));
        // Rule 2.
        $this->assertEquals(2, $rules[1]->get('sortorder'));
        $this->assertEquals(DAYSECS * 2, $rules[1]->get('latefor'));
        $this->assertEquals(20, $rules[1]->get('penalty'));
        // Rule 3.
        $this->assertEquals(3, $rules[2]->get('sortorder'));
        $this->assertEquals(DAYSECS * 4, $rules[2]->get('latefor'));
        $this->assertEquals(40, $rules[2]->get('penalty'));
        // Rule 4.
        $this->assertEquals(4, $rules[3]->get('sortorder'));
        $this->assertEquals(DAYSECS * 5, $rules[3]->get('latefor'));
        $this->assertEquals(50, $rules[3]->get('penalty'));
    }
}
