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

namespace core_grades\reportbuilder\local\systemreports;

use core_grades\reportbuilder\local\entities\penalty_exemption;
use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\report\action;
use core_reportbuilder\system_report;
use lang_string;
use moodle_url;
use pix_icon;

/**
 * Report for user exemptions.
 *
 * @package     core_grades
 * @copyright   2025 Catalyst IT Australia Pty Ltd
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_exemption_report extends system_report {

    /** @var array $contextids */
    protected array $contextids = [];

    /**
     * Initialises the report.
     *
     * @return void
     */
    protected function initialise(): void {
        global $DB;

        $this->contextids = array_map('intval', explode(',', $this->get_parameter('contextids', '', PARAM_SEQUENCE)));

        $this->set_main_table('grade_penalty_exemptions', 'pe');
        $this->add_base_fields('pe.id, pe.itemid, pe.itemtype, pe.contextid, pe.usermodified, u');

        $usertype = \core_grades\penalty_exemption::TYPE_USER;

        $creator = (new user())
            ->set_entity_name('creator')
            ->set_entity_title(new lang_string('usermodified', 'core_reportbuilder'));
        $creator->set_table_alias('user', 'c');
        $this->add_entity($creator->add_join("JOIN {user} c ON c.id = pe.usermodified"));

        $penalty = new penalty_exemption();
        $penalty->set_table_alias('grade_penalty_exemptions', 'pe');
        $this->add_entity($penalty);

        $user = new user();
        $user->set_entity_name('user');
        $user->set_table_alias('user', 'u');
        $user->set_table_alias('context', 'ctx');
        $user->add_joins([
            "JOIN {user} u ON (u.id = pe.itemid AND pe.itemtype = '{$usertype}')",
            "JOIN {context} ctx ON ctx.id = pe.contextid",
        ]);
        $this->add_entity($user);

        if (!empty($this->contextids)) {
            $prefix = database::generate_param_name('contextids');
            [$insql, $params] = $DB->get_in_or_equal($this->contextids, SQL_PARAMS_NAMED, $prefix);
            $this->add_base_condition_sql("pe.contextid $insql", $params);
        }

        $this->add_columns();
        if (has_capability('moodle/grade:managepenaltyexemptions', $this->get_context())) {
            $this->add_actions();
        }
        $this->add_filters();
    }

    /**
     * Adds columns to the report.
     *
     * @return void
     */
    protected function add_columns(): void {
        $this->add_columns_from_entities([
            'user:fullnamewithlink',
            'user:email',
            'penalty_exemption:reason',
            'penalty_exemption:scope',
        ]);

        $this->add_column_from_entity('creator:fullnamewithlink')
            ->set_title(new lang_string('usermodified', 'core_reportbuilder'));
    }

    /**
     * Check if the user can view the report.
     *
     * @return bool
     */
    protected function can_view(): bool {
        return has_capability('moodle/grade:viewpenaltyexemptions', $this->get_context());
    }

    /**
     * Adds filters.
     *
     * @return void
     */
    protected function add_filters(): void {
        $filters = [
            'user:fullname',
            'user:email',
            'penalty_exemption:reason',
        ];
        $this->add_filters_from_entities($filters);
    }

    /**
     * Adds actions.
     *
     * @return void
     */
    protected function add_actions(): void {
        // Edit action.
        $this->add_action(new action(
            new moodle_url('/grade/penalty/manage_exemptions.php', [
                'contextid' => ':contextid',
                'tab' => ':itemtype',
                'action' => 'edit',
                'id' => ':id',
            ]),
            new pix_icon('i/edit', ''),
            [],
            false,
            new lang_string('edit', 'core')
        ));

        // Delete action with a proper modal.
        $this->add_action(new action(
            new moodle_url('/grade/penalty/manage_exemptions.php', [
                'contextid' => ':contextid',
                'action' => 'delete',
                'id' => ':id',
                'sesskey' => sesskey(),
            ]),
            new pix_icon('t/delete', ''),
            [
                'class' => 'text-danger',
                'data-modal' => 'confirmation',
                'data-modal-title-str' => json_encode(['delete', 'core']),
                'data-modal-content-str' => json_encode(['exemptions:manage:deleteconfirm', 'core_grades']),
                'data-modal-yes-button-str' => json_encode(['delete', 'core']),
            ],
            false,
            new lang_string('delete', 'core')
        ));
    }
}
