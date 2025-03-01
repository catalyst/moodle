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

use core\di;
use core\hook;
use core_grades\hook\after_penalty_applied;
use core_grades\hook\before_penalty_applied;
use grade_grade;
use grade_item;

/**
 * Manager class for grade penalty.
 *
 * @package   core_grades
 * @copyright 2024 Catalyst IT Australia Pty Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class penalty_manager {
    /**
     * Lists of modules which support grade penalty feature.
     *
     * @return array list of supported modules.
     */
    public static function get_supported_modules(): array {
        $plugintype = 'mod';
        $mods = \core_component::get_plugin_list($plugintype);
        $supported = [];
        foreach ($mods as $mod => $plugindir) {
            if (plugin_supports($plugintype, $mod, FEATURE_GRADE_HAS_PENALTY)) {
                $supported[] = $mod;
            }
        }
        return $supported;
    }

    /**
     * Whether penalty is enabled for a module.
     *
     * @param string $module the module name.
     * @return bool if penalty is enabled for the module.
     */
    public static function is_penalty_enabled_for_module(string $module): bool {
        // Check if the module is in the enable list.
        $supportedmodules = get_config('core', 'gradepenalty_supportedplugins');
        if (!in_array($module, explode(',', $supportedmodules))) {
            return false;
        }
        return true;
    }

    /**
     * Apply grade penalties to a user.
     *
     * Grade penalties are determined by the enabled penalty plugin.
     * This function should be called each time a module creates or updates a grade item for a user.
     *
     * @param int $userid The user ID
     * @param grade_item $gradeitem grade item
     * @param int $submissiondate submission date
     * @param int $duedate due date
     * @param bool $previewonly do not update the grade if true, only return the penalty
     * @return float deducted penalty percentage
     */
    public static function apply_grade_penalty_to_user(
        int $userid,
        grade_item $gradeitem,
        int $submissiondate,
        int $duedate,
        bool $previewonly = false
    ): float {

        try {
            $deductedpercentage = self::apply_penalty($userid, $gradeitem, $submissiondate, $duedate, $previewonly);
        } catch (\core\exception\moodle_exception $e) {
            debugging($e->getMessage(), DEBUG_DEVELOPER);
            return 0;
        }
        return $deductedpercentage;
    }

    /**
     * Fetch the penalty for a user based on the submission date and due date and deduct marks from the grade item accordingly.
     *
     * @param int $userid ID of user
     * @param grade_item $gradeitem the grade item object
     * @param int $submissiondate submission date
     * @param int $duedate due date
     * @param bool $previewonly do not update the grade if true
     * @return float returns the deducted percentage.
     */
    private static function apply_penalty(
        int $userid,
        grade_item $gradeitem,
        int $submissiondate,
        int $duedate,
        bool $previewonly = false
    ): float {
        // If the grade item belong to a supported module.
        if (!self::is_penalty_enabled_for_module($gradeitem->itemmodule)) {
            return 0;
        }

        // Check if there is any existing grade.
        $grade = $gradeitem->get_final($userid);
        if (!$grade || !$grade->rawgrade) {
            debugging('No raw grade found for user ' . $userid . ' and grade item ' . $gradeitem->id, DEBUG_DEVELOPER);
            return 0;
        } else if ($grade->rawgrade <= 0 || $grade->finalgrade <= 0) {
            // There is no penalty for zero or negative grades.
            return 0;
        } else if ($grade->overridden > 0 || $grade->locked > 0) {
            // Do not apply penalty if the grade is overridden or locked.
            // We may need a separate setting to allow penalty for overridden grades.
            return 0;
        }

        // Hook for plugins to calculate the penalty.
        $beforepenaltyhook = new before_penalty_applied($userid, $gradeitem, $submissiondate, $duedate, $grade->finalgrade);
        di::get(hook\manager::class)->dispatch($beforepenaltyhook);

        // Ensure the deducted percentage is within the valid range.
        $deductedpercentage = $beforepenaltyhook->get_deducted_percentage();
        if ($deductedpercentage < 0) {
            throw new \coding_exception('The deducted percentage cannot be less than 0%.');
        }

        if ($deductedpercentage > 100) {
            throw new \coding_exception('The deducted percentage cannot be greater than 100%.');
        }

        // Apply the penalty to the grade.
        if (!$previewonly) {
            // Update the final grade after the penalty is applied.
            $gradeitem->update_raw_grade($userid, $beforepenaltyhook->get_grade_after_penalty(), 'gradepenalty');
            $gradeitem->update_deducted_mark($userid, $beforepenaltyhook->get_deducted_grade());

            // Hook for plugins to process further after the penalty is applied to the grade.
            $afterpenaltyhook = new after_penalty_applied(
                $userid,
                $gradeitem,
                $submissiondate,
                $duedate,
                $beforepenaltyhook->get_grade_before_penalty(),
                $beforepenaltyhook->get_deducted_percentage(),
                $beforepenaltyhook->get_deducted_grade(),
                $beforepenaltyhook->get_grade_after_penalty()
            );
            di::get(hook\manager::class)->dispatch($afterpenaltyhook);
        }

        return $deductedpercentage;
    }

    /**
     * Returns the penalty indicator HTML code if a penalty is applied to the grade.
     * Otherwise, returns an empty string.
     *
     * @param grade_grade $grade Grade object
     * @return string HTML code for penalty indicator
     */
    public static function show_penalty_indicator(grade_grade $grade): string {
        global $PAGE;

        // Show penalty indicator if penalty is greater than 0.
        if ($grade->is_penalty_applied_to_final_grade()) {
            $indicator = new \core_grades\output\penalty_indicator(2, $grade);
            $renderer = $PAGE->get_renderer('core_grades');
            return $renderer->render_penalty_indicator($indicator);
        }

        return '';
    }
}
