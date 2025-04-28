<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace core_grades\output\form;

use context;
use context_system;
use core_grades\penalty_exemption;
use core_user;
use moodle_exception;
use moodleform;
use context_course;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");

/**
 * Form for adding a new AI statement.
 *
 * @package     core_grades
 * @copyright   2025 Catalyst IT Australia Pty Ltd
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exemption_form extends moodleform {
    /**
     * Form definition.
     *
     * @return void
     */
    public function definition() {
        $mform = $this->_form;

        // Add hidden fields to prevent the parent page from complaining and improve navigation flow.
        $context = context::instance_by_id($this->_customdata['contextid']);
        $mform->addElement('hidden', 'contextid', $this->_customdata['contextid']);
        $mform->setType('contextid', PARAM_INT);

        $mform->addElement('hidden', 'tab', $this->_customdata['tab']);
        $mform->setType('tab', PARAM_ALPHA);

        // Add hidden id field.
        $id = $this->_customdata['id'] ?? 0;
        $mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);

        // Add exemption context static element.
        $mform->addElement('static', 'context', get_string('exemptions:form:context', 'core_grades'),
            match ($context->contextlevel) {
                CONTEXT_SYSTEM => get_string('site'),
                CONTEXT_COURSE => get_string('course'),
                CONTEXT_MODULE => get_string('activity'),
                default => get_string('report:scope:unknown', 'core_grades'),
            },
        );
        $mform->addHelpButton('context', 'exemptions:form:context', 'core_grades');

        // Add exemption type select element, with options for user and group exemptions.
        $mform->addElement('select', 'type', get_string('exemptions:form:type', 'core_grades'), [
            'user' => get_string('exemptions:form:type:user', 'core_grades'),
            'group' => get_string('exemptions:form:type:group', 'core_grades'),
        ]);
        $mform->addHelpButton('type', 'exemptions:form:type', 'core_grades');
        $mform->setType('type', PARAM_ALPHA);
        $mform->disabledIf('type', 'id', 'neq', 0);
        if (!empty($this->_customdata['tab'])) {
            $mform->setDefault('type', $this->_customdata['tab']);
        }

        if (empty($id)) {
            $users = [];
            $options = ['multiple' => true];

            if ($context->contextlevel === CONTEXT_SYSTEM) {
                $options['ajax'] = 'core_user/form_user_selector';
            } else {
                $users = $this->get_participants();
            }

            // Add an autocomplete element for selecting students.
            $mform->addElement('autocomplete', 'users', get_string('exemptions:form:users', 'core_grades'),
                $users, $options);
            $mform->addHelpButton('users', 'exemptions:form:users', 'core_grades');
            $mform->setType('users', PARAM_INT);
            $mform->hideIf('users', 'type', 'neq', 'user');

            // Add an autocomplete element for selecting groups.
            $mform->addElement('autocomplete', 'groups', get_string('exemptions:form:groups', 'core_grades'),
                $this->get_groups(), ['multiple' => true]);
            $mform->addHelpButton('groups', 'exemptions:form:groups', 'core_grades');
            $mform->setType('groups', PARAM_INT);
            $mform->hideIf('groups', 'type', 'neq', 'group');
        } else {
            $mform->addElement('static', 'users', get_string('exemptions:form:users', 'core_grades'));
            $mform->disabledIf('users', 'id', 'neq', 0);
            $mform->hideIf('users', 'type', 'neq', 'user');

            $mform->addElement('static', 'groups', get_string('exemptions:form:groups', 'core_grades'));
            $mform->disabledIf('groups', 'id', 'neq', 0);
            $mform->hideIf('groups', 'type', 'neq', 'group');
        }

        // Add an editor for the reason.
        $mform->addElement('editor', 'reason', get_string('exemptions:form:reason', 'core_grades'), ['rows' => 10], [
            'maxfiles' => 0,
            'noclean' => true,
            'context' => context_course::instance($this->_customdata['courseid']),
        ]);
        $mform->addHelpButton('reason', 'exemptions:form:reason', 'core_grades');
        $mform->setType('reason', PARAM_RAW);

        // Submit and cancel buttons.
        if (empty($id)) {
            $buttons = [
                $mform->createElement('submit', 'create', get_string('create')),
                $mform->createElement('cancel'),
            ];
        } else {
            $buttons = [
                $mform->createElement('submit', 'create', get_string('update')),
                $mform->createElement('cancel'),
            ];
        }

        $mform->addGroup($buttons, 'buttons', '', null, false);
    }

    /**
     * Validate form data.
     *
     * @param array $data Form data.
     * @param array $files Form files.
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (empty($data['id'])) {
            if ($data['type'] === 'user' && empty($data['users'])) {
                $errors['users'] = get_string('required');
            }

            if ($data['type'] === 'group' && empty($data['groups'])) {
                $errors['groups'] = get_string('required');
            }
        }

        return $errors;
    }

    /**
     * Process form submission.
     *
     * @param \stdClass $data Form data.
     * @return void
     */
    public function process(\stdClass $data): void {
        if (empty($data->id)) {
            foreach ($data->users as $userid) {
                penalty_exemption::exempt_user($userid, $this->_customdata['contextid'],
                    $data->reason['text'], $data->reason['format']);
            }

            foreach ($data->groups as $groupid) {
                penalty_exemption::exempt_group($groupid, $this->_customdata['contextid'],
                    $data->reason['text'], $data->reason['format']);
            }
        } else {
            $exemption = penalty_exemption::get($data->id);
            $exemption->set_reason($data->reason['text'], $data->reason['format']);
            $exemption->save();
        }
    }

    /**
     * Get course participants.
     *
     * @return array
     */
    private function get_participants(): array {
        global $CFG, $OUTPUT;
        $users = get_enrolled_users(context_course::instance($this->_customdata['courseid']));
        // Remove the guest user from the list of participants.
        unset($users[$CFG->siteguest]);
        $options = [];
        foreach ($users as $user) {
            $user->fullname = fullname($user);
            $user->extrafields[] = (object) [
                'name' => 'email',
                'value' => $user->email,
            ];
            $options[$user->id] = $OUTPUT->render_from_template('core_user/form_user_selector_suggestion', $user);
        }

        return $options;
    }

    /**
     * Get course groups.
     *
     * @return array
     */
    private function get_groups(): array {
        $groups = groups_get_all_groups($this->_customdata['courseid']);
        $options = [];
        foreach ($groups as $group) {
            $options[$group->id] = $group->name;
        }
        return $options;
    }

    /**
     * Populate the form with data.
     *
     * @return void
     */
    public function self_populate(): void {
        global $DB, $OUTPUT;

        if (empty($this->_customdata['id'])) {
            return;
        }

        $exemption = penalty_exemption::get($this->_customdata['id']);

        if (empty($exemption)) {
            throw new moodle_exception('exemptions:invalid', 'core_grades', '', $this->_customdata['id']);
        }

        $data = (object) [
            'type' => $exemption->get_itemtype(),
            'reason' => ['text' => $exemption->get_reason(), 'format' => $exemption->get_reasonformat()],
        ];

        switch ($exemption->get_itemtype()) {
            case penalty_exemption::TYPE_USER:
                $user = core_user::get_user($exemption->get_itemid());
                $user->fullname = fullname($user);
                $user->extrafields[] = (object) [
                    'name' => 'email',
                    'value' => $user->email,
                ];
                $data->users = $OUTPUT->render_from_template('core_user/form_user_selector_suggestion', $user);
                break;

            case penalty_exemption::TYPE_GROUP:
                $group = groups_get_group($exemption->get_itemid());
                $data->groups = $group->name;
                break;
        }

        $this->set_data($data);
    }
}
