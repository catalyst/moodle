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

namespace core_grades\local\penalty;

defined('MOODLE_INTERNAL') || die();

use grade_item;
use stdClass;

require_once($CFG->libdir.'/gradelib.php');

/**
 * Grade penalty abstract class.
 * The penalty plugin must extend this class to perform the penalty calculation.
 *
 * @package    core_grades
 * @copyright 2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class penalty_handler {
    /** @var stdClass The course moudule object */
    public readonly stdClass $cm;

    /** @var float the grade before penalty is applied */
    public readonly float $gradebefore;

    /** @var float the penalty applied */
    public readonly float $penalty;

    /**
     * Constructor for the hook.
     *
     * @param int $userid the user id
     * @param int $submissiondate the submission date
     * @param int $duedate the due date
     * @param int $courseid The course id.
     * @param string $itemtype The type of the grade item.
     * @param string $itemmodule The module of the grade item.
     * @param int $iteminstance The instance of the grade item.
     * @param int|null $itemnumber The number of the grade item.
     * @throws \coding_exception
     */
    public function __construct(
        /** @var int The course id */
        public readonly int $userid,
        /** @var int The submission date */
        public readonly int $submissiondate,
        /** @var int The due date */
        public readonly int $duedate,
        /** @var int The course id */
        public readonly int $courseid,
        /** @var string The type of the grade item */
        public readonly string $itemtype,
        /** @var string The module of the grade item */
        public readonly string $itemmodule,
        /** @var int The instance of the grade item */
        public readonly int $iteminstance,
        /** @var int The number of the grade item */
        public readonly ?int $itemnumber,
    ) {
        // Course module required to get the penalty rules.
        $this->cm = get_coursemodule_from_instance($itemmodule, $iteminstance, $courseid);
    }

    /**
     * Mark will be deducted from student grade.
     *
     * @param float $finalgrade The final grade of the student.
     * @return float
     */
    abstract public function calculate_penalty($finalgrade): float;

    /**
     * Apply penalty to the grade.
     *
     * @return bool return true if penalty is applied successfully.
     */
    final public function apply_penalty(): bool {
        // Fetch the grade item.
        $gradeitem = grade_item::fetch(
            [
                'courseid' => $this->courseid,
                'itemtype' => $this->itemtype,
                'itemmodule' => $this->itemmodule,
                'iteminstance' => $this->iteminstance,
                'itemnumber' => $this->itemnumber,
            ]
        );

        // Get the final grade. It returns a single grade object as we specify the user id.
        $usergrade = $gradeitem->get_final($this->userid);
        $this->gradebefore = $usergrade->finalgrade;

        // Calculate the penalty.
        $this->penalty = $this->calculate_penalty($this->gradebefore);
        $finalgrade = $this->gradebefore - $this->penalty;

        // Update the final grade.
        return $gradeitem->update_final_grade($this->userid, $finalgrade);
    }
}
