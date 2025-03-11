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

use advanced_testcase;
use grade_item;

/**
 * Unit tests for penalty_manager class.
 *
 * @package   core_grades
 * @copyright 2024 Catalyst IT Australia Pty Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class penalty_manager_test extends advanced_testcase {
    /**
     * Test is_penalty_enabled_for_module method.
     *
     * @covers penalty_manager::is_penalty_enabled_for_module
     */
    public function test_is_penalty_enabled_for_module(): void {
        $this->resetAfterTest();
        $this->resetAllData();
        $this->setAdminUser();

        set_config('gradepenalty_supportedplugins', 'mod_assign');

        $this->assertFalse(penalty_manager::is_penalty_enabled_for_module('mod_quiz'));
        $this->assertTrue(penalty_manager::is_penalty_enabled_for_module('mod_assign'));
    }

    /**
     * Test apply_grade_penalty_to_user method.
     *
     * @covers penalty_manager::apply_grade_penalty_to_user
     */
    public function test_apply_grade_penalty_to_user(): void {
        $this->resetAfterTest();
        $this->resetAllData();
        $this->setAdminUser();
        // Create user, course and assignment.
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);

        // Get grade item.
        $gradeitemparams = [
            'courseid' => $course->id,
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
            'iteminstance' => $assign->id,
            'itemnumber' => 0,
        ];

        $gradeitem = grade_item::fetch($gradeitemparams);

        grade_update('mod/assign',
            $course->id,
            'mod',
            'assign',
            $assign->id,
            0,
           ['userid' => $user->id, 'rawgrade' => 90]);

        $submissiondate = time();
        $duedate = time();
        $container = penalty_manager::apply_grade_penalty_to_user($user->id, $gradeitem, $submissiondate, $duedate);

        // No penalty by default.
        $this->assertEquals(90, $container->get_grade_after_penalties());
    }
}
