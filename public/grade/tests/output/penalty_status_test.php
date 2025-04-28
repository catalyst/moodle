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

namespace core_grades\output;

use context_system;
use core_grades\penalty_exemption;
use core_grades\penalty_manager;
use core\plugininfo\gradepenalty;
use grade_grade;
use grade_item;
use gradepenalty_duedate\tests\penalty_testcase;

/**
 * Test class for penalty_status
 *
 * @package   core_grades
 * @copyright 2025 Catalyst IT Australia Pty Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \core_grades\output\penalty_status
 */
final class penalty_status_test extends penalty_testcase {
    /**
     * Reset the test environment after each test.
     *
     * @return void
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Data provider for test_render_penalty_status.
     *
     * @return array
     */
    public static function render_penalty_status_provider(): array {
        return [
            [false, false, false],
            [false, false, true],
            [false, true, false],
            [false, true, true],
            [true, false, false],
            [true, false, true],
            [true, true, false],
            [true, true, true],
        ];
    }

    /**
     * Test export for template with different parameters.
     *
     * @dataProvider render_penalty_status_provider
     *
     * @param bool $applypenalty Whether to setup penalty rules.
     * @param bool $applyexemption Whether to setup an exemption for the user.
     * @param bool $showexemptions Whether to show exemptions in the status.
     */
    public function test_render_penalty_status(bool $applypenalty, bool $applyexemption, bool $showexemptions): void {
        global $PAGE;

        // Enable grade penalties.
        gradepenalty::enable_plugin('duedate', true);
        penalty_manager::enable_module('assign');

        // Setup the test environment.
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course]);
        $student = $this->getDataGenerator()->create_and_enrol($course);
        $gradeitem = grade_item::fetch(['itemtype' => 'mod', 'itemmodule' => 'assign', 'iteminstance' => $assign->id]);
        $time = time();

        if ($applyexemption) {
            penalty_exemption::exempt_user($student->id, context_system::instance()->id);
        }

        // Grade the student.
        grade_update('mod/assign', $course->id, 'mod', 'assign', $assign->id, 0, ['userid' => $student->id, 'rawgrade' => 100]);

        if ($applypenalty) {
            $this->create_sample_rules();
            penalty_manager::apply_grade_penalty_to_user($student->id, $gradeitem, $time, $time - HOURSECS);
        }

        // Fetch the grade for the student.
        $grade = grade_grade::fetch(['itemid' => $gradeitem->id, 'userid' => $student->id]);

        // Export the penalty status.
        $renderer = $PAGE->get_renderer('core_grades');
        $status = new penalty_status($grade, 2, $showexemptions);
        $data = $status->export_for_template($renderer);

        // Set expectations based on the parameters.
        $expectexemption = $applyexemption && $showexemptions;
        $expectpenalty = $applypenalty && !$applyexemption;

        // Check the exported data.
        $this->assertEquals($expectexemption, $data['isexempt']);
        $this->assertEquals($expectexemption, isset($data['exemptionicon']));
        $this->assertEquals($expectexemption, isset($data['exemptioninfo']));

        $this->assertEquals($expectpenalty, $data['penaltyapplied']);
        $this->assertEquals($expectpenalty, isset($data['penaltyicon']));
        $this->assertEquals($expectpenalty, isset($data['penaltyinfo']));
    }
}
