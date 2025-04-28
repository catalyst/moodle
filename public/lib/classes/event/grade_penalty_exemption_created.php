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

namespace core\event;

/**
 * Grade penalty exemption created event class.
 *
 * @package    core
 * @since      Moodle 5.1
 * @copyright  2025 Catalyst IT Australia Pty Ltd
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_penalty_exemption_created extends base {

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['objecttable'] = 'grade_penalty_exemptions';
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventgradeexemptioncreated', 'core_grades');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->userid}'" .
            " created the grade penalty exemption with id '{$this->objectid}'" .
            " and itemtype '{$this->other['itemtype']}'" .
            " and itemid '{$this->other['itemid']}'" .
            " and contextid '{$this->contextid}'" .
            " in the course with id '{$this->courseid}'.";
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception when validation does not pass.
     */
    protected function validate_data() {
        parent::validate_data();

        if (!array_key_exists('itemtype', $this->other)) {
            throw new \coding_exception("The grade penalty exemption 'itemtype' value must be set in other.");
        }

        if (!array_key_exists('itemid', $this->other)) {
            throw new \coding_exception("The grade penalty exemption 'itemid' value must be set in other.");
        }
    }
}
