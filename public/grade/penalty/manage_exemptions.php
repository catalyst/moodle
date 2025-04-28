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
 * Site configuration settings for the core_grades plugin
 *
 * @package   core_grades
 * @copyright 2024 Catalyst IT Australia Pty Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\notification;
use core\url;
use core_grades\penalty_exemption;
use core_reportbuilder\system_report_factory;
use core_grades\reportbuilder\local\systemreports\context_exemption_report;
use core_grades\reportbuilder\local\systemreports\group_exemption_report;
use core_grades\reportbuilder\local\systemreports\user_exemption_report;
use core_grades\output\form\exemption_form;

require_once(__DIR__ . '/../../config.php');

// Page parameters.
$contextid = optional_param('contextid', context_system::instance()->id, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);
$sesskey = optional_param('sesskey', '', PARAM_RAW);
$currenttab = optional_param('tab', penalty_exemption::TYPE_USER, PARAM_ALPHA);

// Check login and permissions.
[$context, $course, $cm] = get_context_info_array($contextid);
if ($context->contextlevel == CONTEXT_SYSTEM) {
    require_admin();
    $courseid = SITEID;
} else {
    require_login($course, false, $cm);
    require_capability('moodle/grade:viewpenaltyexemptions', $context);
    $courseid = $course->id;
}

$PAGE->set_context($context);
$url = new url('/grade/penalty/manage_exemptions.php', ['contextid' => $contextid, 'tab' => $currenttab]);
$PAGE->set_url($url);

// Display page according to context.
switch ($context->contextlevel) {
    case CONTEXT_SYSTEM:
        require_once("$CFG->libdir/adminlib.php");
        admin_externalpage_setup('managepenaltyexemptions');
        $PAGE->navbar->add(get_string('exemptions:manage', 'core_grades'), $url);
        break;
    case CONTEXT_COURSE:
    case CONTEXT_MODULE:
        $PAGE->set_heading($course->fullname);

        // Remove the existing exemption node if it exists.
        $node = $PAGE->settingsnav->find('manageexemptions', navigation_node::TYPE_SETTING);
        if (empty($node)) {
            break;
        }
        $node->remove();

        // Add the exemption node back to generate the dropdown navigation element.
        $node = $PAGE->settingsnav->find('gradepenalty', navigation_node::TYPE_CONTAINER);
        if (empty($node)) {
            break;
        }
        $node->add(
            get_string('exemptions:manage', 'core_grades'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'manageexemptions'
        );
        break;
    default:
        break;
}

// Print the header and tabs.
$PAGE->set_cacheable(false);
$title = get_string('exemptions:manage', 'core_grades');
$PAGE->set_title($title);
$PAGE->set_pagelayout('admin');
$PAGE->activityheader->disable();

// Check capabilities for the action.
if ($action === 'add' || $action === 'edit' || $action === 'delete') {
    $exemption = penalty_exemption::get($id);

    require_capability('moodle/grade:managepenaltyexemptions', $context);
    if (!empty($exemption) && $exemption->get_contextid() !== $contextid) {
        require_capability('moodle/grade:managepenaltyexemptions', context::instance_by_id($exemption->get_contextid()));
    }
}

$mform = new exemption_form(null, ['contextid' => $contextid, 'courseid' => $courseid, 'id' => $id, 'tab' => $currenttab]);
if ($mform->is_cancelled()) {
    redirect($url);
}

// Process deletion.
if ($id && $action === 'delete' && confirm_sesskey()) {
    // Clicking the delete button multiple times can attempt to delete an exemption more than once.
    if (!empty($exemption)) {
        $exemption->delete();
    }

    redirect(
        new url($url, ['tab' => $exemption->get_itemtype()]),
        get_string('exemptions:form:successdelete', 'core_grades'),
        0,
        notification::NOTIFY_SUCCESS
    );
}

if ($data = $mform->get_data()) {
    $mform->process($data);

    redirect(
        new url($url, ['tab' => $data->type]),
        $data->create ? get_string('exemptions:form:success', 'core_grades') : '',
        0,
        notification::NOTIFY_SUCCESS
    );
}

// Start output.
echo $OUTPUT->header();

// Add heading with help text.
echo $OUTPUT->heading_with_help($title, 'exemptions:manage', 'core_grades');

if ($action === 'add' || ($mform->is_submitted() && empty($data))) {
    $mform->display();
} else if ($action === 'edit') {
    $mform->self_populate();
    $mform->display();
} else {
    $addurl = new url('/grade/penalty/manage_exemptions.php', ['contextid' => $contextid]);

    // Define tabs.
    $tabs = [];
    $tabs[] = new tabobject('user',
        new url($url, ['tab' => 'user']),
        get_string('exemptions:manage:usertable', 'core_grades')
    );
    $tabs[] = new tabobject('group',
        new url($url, ['tab' => 'group']),
        get_string('exemptions:manage:grouptable', 'core_grades')
    );
    $tabs[] = new tabobject('exemptusers',
        new url($url, ['tab' => 'exemptusers']),
        get_string('exemptions:manage:contexttable', 'core_grades')
    );

    echo $OUTPUT->tabtree($tabs, $currenttab);

    if ($currenttab === penalty_exemption::TYPE_USER) {
        // Display the user exemption table.
        echo $OUTPUT->heading_with_help(get_string('exemptions:manage:usertable', 'core_grades'),
            'exemptions:manage:usertable', 'core_grades',
            '', '', 4);
        echo $OUTPUT->box_start();

        if (has_capability('moodle/grade:managepenaltyexemptions', $context)) {
            echo $OUTPUT->single_button(new url($addurl, ['tab' => penalty_exemption::TYPE_USER, 'action' => 'add']),
                get_string('exemptions:manage:new', 'core_grades'), 'get', ['type' => 'primary']);
        }

        $report = system_report_factory::create(
            user_exemption_report::class,
            $context,
            'core_grades',
            '',
            0,
            ['contextids' => (string) $contextid]
        );
        echo $report->output();
        echo $OUTPUT->box_end();

    } else if ($currenttab === penalty_exemption::TYPE_GROUP) {
        // Display the group exemption table.
        echo $OUTPUT->heading_with_help(get_string('exemptions:manage:grouptable', 'core_grades'),
            'exemptions:manage:grouptable', 'core_grades',
            '', '', 4);
        echo $OUTPUT->box_start();

        if (has_capability('moodle/grade:managepenaltyexemptions', $context)) {
            echo $OUTPUT->single_button(new url($addurl, ['tab' => penalty_exemption::TYPE_GROUP, 'action' => 'add']),
                get_string('exemptions:manage:new', 'core_grades'), 'get', ['type' => 'primary']);
        }

        $report = system_report_factory::create(
            group_exemption_report::class,
            $context,
            'core_grades',
            '',
            0,
            ['contextids' => (string) $contextid]
        );
        echo $report->output();
        echo $OUTPUT->box_end();
    } else if ($currenttab === 'exemptusers') {
        // Display the context exemption table.
        echo $OUTPUT->heading_with_help(get_string('exemptions:manage:contexttable', 'core_grades'),
            'exemptions:manage:contexttable', 'core_grades',
            '', '', 4);

        $report = system_report_factory::create(
            context_exemption_report::class,
            $context,
            'core_grades',
            '',
            0,
            ['contextids' => implode(',', $context->get_parent_context_ids(true))]
        );
        echo $report->output();
    }
}

// Footer.
echo $OUTPUT->footer();
