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

use core\output\renderer_base;
use core\output\templatable;
use core\output\renderable;
use core_grades\penalty_exemption;
use grade_grade;

/**
 * Class used to render the penalty status for a user grade.
 *
 * @package    core_grades
 * @copyright  2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class penalty_status implements renderable, templatable {
    /**
     * The class constructor.
     *
     * @param grade_grade $grade The user grade.
     * @param int $decimals The number of decimal places to show in the grade.
     * @param bool $showexemptions Whether to show exemptions in the status.
     */
    public function __construct(
        /** @var grade_grade $grade The user grade. */
        protected grade_grade $grade,

        /** @var int $decimals The number of decimal places to show in the grade. */
        protected int $decimals = 2,

        /** @var bool $showexemptions Whether to show exemptions in the status. */
        protected bool $showexemptions = true,
    ) {
    }

    /**
     * Returns the template for rendering the penalty status.
     *
     * @return string
     */
    public function get_template(): string {
        return 'core_grades/penalty_status';
    }

    #[\Override]
    public function export_for_template(renderer_base $output): array {
        if ($this->showexemptions && penalty_exemption::is_user_exempt($this->grade->userid, $this->grade->get_context()->id)) {
            return [
                'isexempt' => true,
                'penaltyapplied' => false,
                'exemptionicon' => ['name' => 'i/shield', 'component' => 'core'],
                'exemptioninfo' => get_string('gradepenalty_exemption_info', 'core_grades'),
            ];
        }

        if ($this->grade->is_penalty_applied_to_final_grade()) {
            $penalty = format_float($this->grade->deductedmark, $this->decimals);
            return [
                'isexempt' => false,
                'penaltyapplied' => true,
                'penaltyicon' => ['name' => 'i/risk_xss', 'component' => 'core'],
                'penaltyinfo' => get_string('gradepenalty_indicator_info', 'core_grades', $penalty),
            ];
        }

        return [
            'isexempt' => false,
            'penaltyapplied' => false,
        ];
    }
}
