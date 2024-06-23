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

namespace gradepenalty_duedate\output\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once(__DIR__ . '/../../../lib.php');

use gradepenalty_duedate\helper;
use core_reportbuilder\local\helpers\format;
use gradepenalty_duedate\penalty_rule;
use moodleform;

/**
 * Form to set up the penalty rules for the gradepenalty_duedate plugin.
 *
 * @package    gradepenalty_duedate
 * @copyright 2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class penalty_rule_form extends moodleform {
    /** @var int Min latefor value */
    protected $minlatefor = GRADEPENALTY_DUEDATE_MIN_LATEFOR;

    /** @var int Max latefor value */
    protected $maxlatefor = GRADEPENALTY_DUEDATE_MAX_LATEFOR;

    /** @var int Min penalty value */
    protected $minpenalty = GRADEPENALTY_DUEDATE_MIN_PENALTY;

    /** @var int Max penalty value */
    protected $maxpenalty = GRADEPENALTY_DUEDATE_MAX_PENALTY;

    /** @var int ruleid */
    protected $ruleid = 0;

    /** @var int contextid */
    protected $contextid = 0;

    /** @var int action */
    protected $action = GRADEPENALTY_DUEDATE_ACTION_CREATE;

    /**
     * Define the form.
     *
     * @return void
     */
    public function definition() {
        $mform = $this->_form;

        // Set up min/max for latefor and penalty.
        $this->ruleid = $this->_customdata['ruleid'] ?? 0;
        $this->contextid = $this->_customdata['contextid'] ?? 0;
        $this->action = $this->_customdata['action'] ?? GRADEPENALTY_DUEDATE_ACTION_CREATE;

        // Calculate min/max value for latefor.
        list($this->minlatefor, $this->maxlatefor) = helper::calculate_min_max_values($this->ruleid, $this->action, 'latefor',
            GRADEPENALTY_DUEDATE_MIN_LATEFOR, GRADEPENALTY_DUEDATE_MAX_LATEFOR);
        // And for penalty.
        list($this->minpenalty, $this->maxpenalty) = helper::calculate_min_max_values($this->ruleid, $this->action, 'penalty',
            GRADEPENALTY_DUEDATE_MIN_PENALTY, GRADEPENALTY_DUEDATE_MAX_PENALTY);

        // Hidden context id, value is stored in $mform.
        $mform->addElement('hidden', 'contextid');
        $mform->setType('contextid', PARAM_INT);
        $mform->setDefault('contextid', $this->contextid);

        // Hidden rule id, value is stored in $mform.
        $mform->addElement('hidden', 'ruleid');
        $mform->setType('ruleid', PARAM_INT);
        $mform->setDefault('ruleid', $this->ruleid);

        // Hidden action, value is stored in $mform.
        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_INT);
        $mform->setDefault('action', $this->action);

        // If ruleid is not 0, then we are editing an existing rule.
        $rule = new penalty_rule($this->ruleid);

        // Latefor field.
        $mform->addElement('duration', 'latefor', get_string('latefor', 'gradepenalty_duedate'), ['defaultunit' => DAYSECS]);
        $mform->setType('latefor', PARAM_INT);
        // Default value. If we are updating a rule, use the current value.
        $mform->setDefault('latefor', $this->action === GRADEPENALTY_DUEDATE_ACTION_UPDATE ?
            $rule->get('latefor') : $this->minlatefor);
        // Required rule.
        $mform->addRule('latefor', get_string('required'), 'required');
        // Help button.
        $mform->addHelpButton('latefor', 'latefor', 'gradepenalty_duedate');

        // Penalty field.
        $mform->addElement('text', 'penalty', get_string('penalty', 'gradepenalty_duedate'));
        $mform->setType('penalty', PARAM_INT);
        // Default value. If we are updating a rule, use the current value.
        $mform->setDefault('penalty', $this->action === GRADEPENALTY_DUEDATE_ACTION_UPDATE ?
            $rule->get('penalty') : $this->minpenalty);
        // Required rule.
        $mform->addRule('penalty', get_string('required'), 'required');
        // Help button.
        $mform->addHelpButton('penalty', 'penalty', 'gradepenalty_duedate');

        // Add buttons.
        $this->add_action_buttons();
    }

    /**
     * Make sure the latefor and penalty values are within the min/max values.
     *
     * @param array $data array of data from the form.
     * @param array $files array of files from the form.
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Validate latefor.
        // Min value.
        if ($data['latefor'] < $this->minlatefor) {
            $errors['latefor'] = get_string('error_latefor_minvalue', 'gradepenalty_duedate', format_time($this->minlatefor));
        }
        // Max value.
        if ($data['latefor'] > $this->maxlatefor) {
            $errors['latefor'] = get_string('error_latefor_maxvalue', 'gradepenalty_duedate', format_time($this->maxlatefor));
        }

        // Validate penalty.
        // Min value.
        if ($data['penalty'] < $this->minpenalty) {
            $errors['penalty'] = get_string('error_penalty_minvalue', 'gradepenalty_duedate', format::percent($this->minpenalty));
        }
        // Max value.
        if ($data['penalty'] > $this->maxpenalty) {
            $errors['penalty'] = get_string('error_penalty_maxvalue', 'gradepenalty_duedate', format::percent($this->maxpenalty));
        }

        return $errors;
    }
}
