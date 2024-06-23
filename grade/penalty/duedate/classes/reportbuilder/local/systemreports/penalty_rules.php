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

namespace gradepenalty_duedate\reportbuilder\local\systemreports;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../../../lib.php');

use context_system;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\report\action;
use core_reportbuilder\system_report;
use gradepenalty_duedate\helper;
use gradepenalty_duedate\reportbuilder\local\entities\penalty_rule;
use lang_string;
use moodle_url;
use pix_icon;
use stdClass;

/**
 * System report for listing all penalty rules.
 *
 * @package     gradepenalty_duedate
 * @copyright  2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class penalty_rules extends system_report {
    /**
     * Initialise the report.
     */
    protected function initialise(): void {
        // Penalty rule entity.
        $entitymain = new penalty_rule();
        $entitymainalias = $entitymain->get_table_alias('gradepenalty_duedate_rule');

        // Main table.
        $this->set_main_table('gradepenalty_duedate_rule', $entitymainalias);
        $this->add_entity($entitymain);

        // Base field required for actions.
        $this->add_base_fields("{$entitymainalias}.id");

        // SQL query.
        $paramcontextid = database::generate_param_name();
        $context = $this->get_context();
        $this->add_base_condition_sql("{$entitymainalias}.contextid = :$paramcontextid", [$paramcontextid => $context->id]);

        // Fields.
        $this->add_base_fields("
            {$entitymainalias}.contextid,
            {$entitymainalias}.latefor,
            {$entitymainalias}.penalty,
            {$entitymainalias}.sortorder"
        );

        // Add content to the report.
        $this->add_columns();
        $this->set_default_sort_order();
        $this->add_actions();
    }

    /**
     * Add columns to the report.
     */
    protected function add_columns(): void {
        $columns = [
            'penalty_rule:latefor',
            'penalty_rule:penalty',
        ];

        $this->add_columns_from_entities($columns);
    }

    /**
     * Set default sort order.
     */
    protected function set_default_sort_order(): void {
        // Ascending order, we show rules with higher priority below the rules with lower priority.
        $this->set_initial_sort_column('penalty_rule:latefor', SORT_ASC);
    }

    /**
     * Add actions
     */
    protected function add_actions(): void {
        // Insert rule above.
        $this->add_action((new action(
            new moodle_url('/grade/penalty/duedate/edit_penalty_rule.php', [
                'contextid' => $this->get_context()->id,
                'ruleid' => ':id',
                'action' => GRADEPENALTY_DUEDATE_ACTION_INSERT_ABOVE,
            ]),
            new pix_icon('t/add', ''),
            [],
            false,
            new lang_string('insert_above', 'gradepenalty_duedate')
        ))
            ->add_callback(function(stdClass $row): bool {
                // Do not show if there is no space to insert above.
                return helper::can_insert_rule($row->id, GRADEPENALTY_DUEDATE_ACTION_INSERT_ABOVE);
            })
        );

        // Insert rule below.
        $this->add_action((new action(
            new moodle_url('/grade/penalty/duedate/edit_penalty_rule.php', [
                'contextid' => $this->get_context()->id,
                'ruleid' => ':id',
                'action' => GRADEPENALTY_DUEDATE_ACTION_INSERT_BELOW,
            ]),
            new pix_icon('t/add', ''),
            [],
            false,
            new lang_string('insert_below', 'gradepenalty_duedate')
        ))
            ->add_callback(function(stdClass $row): bool {
                // Do not show if there is no space to insert below.
                return helper::can_insert_rule($row->id, GRADEPENALTY_DUEDATE_ACTION_INSERT_BELOW);
            })
        );

        // Edit action.
        $this->add_action((new action(
            new moodle_url('/grade/penalty/duedate/edit_penalty_rule.php', [
                'contextid' => $this->get_context()->id,
                'ruleid' => ':id',
                'action' => GRADEPENALTY_DUEDATE_ACTION_UPDATE,
            ]),
            new pix_icon('t/edit', ''),
            [],
            false,
            new lang_string('edit')
        )));

        // Delete action.
        $this->add_action((new action(
            new moodle_url('/grade/penalty/duedate/edit_penalty_rule.php', [
                'contextid' => $this->get_context()->id,
                'ruleid' => ':id',
                'action' => GRADEPENALTY_DUEDATE_ACTION_DELETE,
            ]),
            new pix_icon('t/delete', ''),
            [],
            false,
            new lang_string('delete')
        )));
    }

    /**
     * Permission that can view the report.
     *
     * @return bool
     */
    protected function can_view(): bool {
        return has_capability('moodle/site:config', context_system::instance());
    }
}
