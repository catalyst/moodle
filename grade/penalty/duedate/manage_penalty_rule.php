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

/**
 * Site configuration settings for the gradepenalty_duedate plugin
 *
 * @package    gradepenalty_duedate
 * @copyright 2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_reportbuilder\system_report_factory;
use gradepenalty_duedate\reportbuilder\local\systemreports\penalty_rules;

require_once(__DIR__ . '/../../../config.php');
require_once("$CFG->libdir/adminlib.php");

// Page parameters.
$contextid = required_param('contextid', PARAM_INT);

list($context, $course, $cm) = get_context_info_array($contextid);

// Check login and permissions.
require_login($course, false, $cm);
require_capability('gradepenalty/duedate:manage', $context);
$PAGE->set_context($context);
$url = new moodle_url('/grade/penalty/duedate/manage_penalty_rule.php', ['contextid' => $contextid]);
$PAGE->set_url($url);

// Display page according to context.
if ($context->contextlevel == CONTEXT_COURSE) {
    $course = get_course($context->instanceid);
    $PAGE->set_heading($course->fullname);
} else if ($context->contextlevel == CONTEXT_MODULE) {
    $PAGE->set_heading($PAGE->activityrecord->name);
} else {
    $PAGE->set_heading(get_string('administrationsite'));
}

// Print the header and tabs.
$PAGE->set_cacheable(false);
$title = get_string('duedaterule', 'gradepenalty_duedate');
$PAGE->set_title($title);
$PAGE->set_pagelayout('admin');
$PAGE->activityheader->disable();

// Start output.
echo $OUTPUT->header();

// Add heading with help text.
echo $OUTPUT->heading_with_help($title, 'penaltyrule', 'gradepenalty_duedate');

// Link to create a new rule if there is none.
$newruleurl = new moodle_url('/grade/penalty/duedate/edit_penalty_rule.php', ['contextid' => $contextid]);
$report = system_report_factory::create(penalty_rules::class, $context);
$report->set_default_no_results_notice(new lang_string('nopenaltyrule', 'gradepenalty_duedate',
    $OUTPUT->action_link($newruleurl, get_string('addnewrule', 'gradepenalty_duedate'))));

// Show report.
echo $report->output();

echo $OUTPUT->footer();
