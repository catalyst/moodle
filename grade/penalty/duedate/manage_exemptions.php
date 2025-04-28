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
 * @package   gradepenalty_duedate
 * @copyright 2024 Catalyst IT Australia Pty Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\url;
use core_grades\penalty_exemption;
use core_reportbuilder\system_report_factory;
use gradepenalty_duedate\reportbuilder\local\systemreports\context_exemption_report;
use gradepenalty_duedate\reportbuilder\local\systemreports\group_exemption_report;
use gradepenalty_duedate\reportbuilder\local\systemreports\user_exemption_report;
use gradepenalty_duedate\output\form\exemption_form;

require_once(__DIR__ . '/../../../config.php');

// Page parameters.
$contextid = optional_param('contextid', context_system::instance()->id, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);
$sesskey = optional_param('sesskey', '', PARAM_RAW);

// Check login and permissions.
[$context, $course, $cm] = get_context_info_array($contextid);
if ($context->contextlevel == CONTEXT_SYSTEM) {
    require_admin();
    $courseid = SITEID;
} else {
    require_login($course, false, $cm);
    require_capability('gradepenalty/duedate:manage', $context);
    $courseid = $course->id;
}

$PAGE->set_context($context);
$url = new url('/grade/penalty/duedate/manage_exemptions.php', ['contextid' => $contextid]);
$PAGE->set_url($url);

// Display page according to context.
switch ($context->contextlevel) {
    case CONTEXT_COURSE:
        $PAGE->set_heading($course->fullname);
        break;
    case CONTEXT_MODULE:
        $PAGE->set_heading($PAGE->activityrecord->name);
        break;
    default:
        $PAGE->set_heading(get_string('administrationsite'));
        break;
}

// Print the header and tabs.
$PAGE->set_cacheable(false);
$title = get_string('manage_exemptions:manage', 'gradepenalty_duedate');
$PAGE->set_title($title);
$PAGE->set_pagelayout('admin');
$PAGE->activityheader->disable();

$mform = new exemption_form(null, ['contextid' => $contextid, 'courseid' => $courseid, 'id' => $id]);
if ($mform->is_cancelled()) {
    redirect($url);
}

// Process deletion.
if ($id && $action === 'deleteconfirm' && confirm_sesskey()) {
    penalty_exemption::get($id)->delete();
    redirect($url, get_string('exemption_form:successdelete', 'gradepenalty_duedate'));
}

if ($data = $mform->get_data()) {
    $mform->process($data);

    $message = '';
    if ($data->create) {
        $message = get_string('exemption_form:success', 'gradepenalty_duedate');
    }
    redirect($url, $message);
}

// Start output.
echo $OUTPUT->header();

// Add heading with help text.
echo $OUTPUT->heading_with_help($title, 'manage_exemptions:manage', 'gradepenalty_duedate');

// Display confirmation screen when deleting.
if ($id && $action === 'delete' && confirm_sesskey()) {
    $yesurl = new url('/grade/penalty/duedate/manage_exemptions.php', [
        'id' => $id,
        'action' => 'deleteconfirm',
        'sesskey' => sesskey(),
        'contextid' => $contextid,
    ]);
    $nourl = new url('/grade/penalty/duedate/manage_exemptions.php', [
        'contextid' => $contextid,
    ]);
    echo $OUTPUT->confirm(get_string('manage_exemptions:deleteconfirm', 'gradepenalty_duedate'), $yesurl, $nourl);
    echo $OUTPUT->footer();
    exit;
}

if ($action === 'add' || ($mform->is_submitted() && empty($data))) {
    $mform->display();
} else if ($action === 'edit') {
    $mform->self_populate();
    $mform->display();
} else {
    $url = new url('/grade/penalty/duedate/manage_exemptions.php', ['contextid' => $contextid, 'action' => 'add']);
    echo $OUTPUT->box_start();
    echo $OUTPUT->single_button($url, get_string('manage_exemptions:new', 'gradepenalty_duedate'), 'get', ['type' => 'primary']);
    echo $OUTPUT->box_end();

    // Display the user exemption table.
    echo $OUTPUT->box_start();
    echo $OUTPUT->heading_with_help(get_string('manage_exemptions:usertable', 'gradepenalty_duedate'),
        'manage_exemptions:usertable', 'gradepenalty_duedate',
        '', '', 4);

    $report = system_report_factory::create(
        user_exemption_report::class,
        $context,
        'gradepenalty_duedate',
        '',
        0,
        ['contextids' => (string) $contextid]
    );
    echo $report->output();

    echo $OUTPUT->box_end();

    // Display the group exemption table.
    echo $OUTPUT->box_start();
    echo $OUTPUT->heading_with_help(get_string('manage_exemptions:grouptable', 'gradepenalty_duedate'),
        'manage_exemptions:grouptable', 'gradepenalty_duedate',
        '', '', 4);

    $report = system_report_factory::create(
        group_exemption_report::class,
        $context,
        'gradepenalty_duedate',
        '',
        0,
        ['contextids' => (string) $contextid]
    );
    echo $report->output();

    echo $OUTPUT->box_end();

    // Display the context exemption table.
    echo $OUTPUT->box_start();
    echo $OUTPUT->heading_with_help(get_string('manage_exemptions:contexttable', 'gradepenalty_duedate'),
        'manage_exemptions:contexttable', 'gradepenalty_duedate',
        '', '', 4);

    $report = system_report_factory::create(
        context_exemption_report::class,
        $context,
        'gradepenalty_duedate',
        '',
        0,
        ['contextids' => implode(',', array_filter(explode('/', $context->path ?? ''), 'is_numeric'))]
    );
    echo $report->output();
    echo $OUTPUT->box_end();
}



// Footer.
echo $OUTPUT->footer();
