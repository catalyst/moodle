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
 * Show form to create or update a penalty rule.
 *
 * @package    gradepenalty_duedate
 * @copyright 2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use gradepenalty_duedate\output\form\penalty_rule_form;
use gradepenalty_duedate\penalty_rule;

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/lib.php');
require_once("$CFG->libdir/adminlib.php");

// Page parameters.
$contextid = required_param('contextid', PARAM_INT);
$action = optional_param('action', GRADEPENALTY_DUEDATE_ACTION_CREATE, PARAM_INT);
$ruleid = optional_param('ruleid', null, PARAM_INT);

list($context, $course, $cm) = get_context_info_array($contextid);
// Check login and permissions.
require_login($course, false, $cm);
require_capability('gradepenalty/duedate:manage', $context);
$PAGE->set_context($context);
$url = new moodle_url('/grade/penalty/duedate/edit_penalty_rule.php', [
    'contextid' => $contextid,
    'action' => $action,
    'ruleid' => $ruleid,
]);
$PAGE->set_url($url);

// Return URL for redirection.
$returnurl = new moodle_url('/grade/penalty/duedate/manage_penalty_rule.php', ['contextid' => $contextid]);

// Display page according to context.
if ($context->contextlevel == CONTEXT_COURSE) {
    $course = get_course($context->instanceid);
    $PAGE->set_heading($course->fullname);
} else if ($context->contextlevel == CONTEXT_MODULE) {
    $PAGE->set_heading($PAGE->activityrecord->name);
} else {
    $PAGE->set_heading(get_string('administrationsite'));
}

// If we are deleting a rule, do so and redirect.
if ($action === GRADEPENALTY_DUEDATE_ACTION_DELETE) {
    penalty_rule::delete_rule($ruleid);
    redirect($returnurl);
}

// Form to add or edit a penalty rule.
$mform = new penalty_rule_form('', [
    'contextid' => $contextid,
    'ruleid' => $ruleid,
    'action' => $action,
]);

if ($mform->is_cancelled()) {
    redirect($returnurl);
} else if ($mform->is_submitted() && $mform->is_validated() && ($data = $mform->get_data())) {
    // Save the data.
    switch ($action) {
        case GRADEPENALTY_DUEDATE_ACTION_CREATE:
            penalty_rule::create_first_rule($data);
            break;
        case GRADEPENALTY_DUEDATE_ACTION_INSERT_ABOVE:
        case GRADEPENALTY_DUEDATE_ACTION_INSERT_BELOW:
            penalty_rule::insert_rule($ruleid, $data);
            break;
        case GRADEPENALTY_DUEDATE_ACTION_UPDATE:
            penalty_rule::update_rule($ruleid, $data);
            break;
        default:
            throw new coding_exception('Invalid action');
    }
    redirect($returnurl);
}

// Print the header and tabs.
$PAGE->set_cacheable(false);
$title = get_string('duedaterule', 'gradepenalty_duedate');
$PAGE->set_title($title);
$PAGE->set_pagelayout('admin');
$PAGE->activityheader->disable();

// Show the page content.
echo $OUTPUT->header();

// Add heading with help text.
$title = get_string('editpenaltyrule', 'gradepenalty_duedate');
echo $OUTPUT->heading_with_help($title, 'editpenaltyrule', 'gradepenalty_duedate');

$mform->display();
echo $OUTPUT->footer();
