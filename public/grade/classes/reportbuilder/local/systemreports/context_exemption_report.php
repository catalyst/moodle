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

use context;
use core\url;
use core_reportbuilder\local\helpers\database;
use html_writer;
use lang_string;
use pix_icon;
use core_reportbuilder\local\report\action;
use core_reportbuilder\system_report;
use core_grades\reportbuilder\local\entities\penalty_exemption;
use core_reportbuilder\local\entities\user;
use core_group\reportbuilder\local\entities\group;

/**
 * Report for exemptions within a context.
 *
 * @package     core_grades
 * @copyright   2025 Catalyst IT Australia Pty Ltd
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class context_exemption_report extends system_report {

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
        $this->add_base_fields('pe.id, pe.itemid, pe.itemtype, pe.contextid, pe.usermodified, u.id AS userid');

        $usertype = \core_grades\penalty_exemption::TYPE_USER;
        $grouptype = \core_grades\penalty_exemption::TYPE_GROUP;

        $user = new user();
        $user->set_table_alias('user', 'u');
        $user->add_joins([
            "LEFT JOIN {groups_members} gm ON gm.groupid = pe.itemid AND pe.itemtype = '{$grouptype}'",
            "LEFT JOIN {user} u ON (u.id = gm.userid AND pe.itemtype = '{$grouptype}')
                       OR (u.id = pe.itemid AND pe.itemtype = '{$usertype}')",
        ]);
        $this->add_entity($user);

        $creator = (new user())
            ->set_entity_name('creator')
            ->set_entity_title(new lang_string('usermodified', 'core_reportbuilder'));
        $creator->set_table_alias('user', 'c');
        $this->add_entity($creator->add_join("JOIN {user} c ON c.id = pe.usermodified"));

        $penalty = new penalty_exemption();
        $penalty->set_table_alias('grade_penalty_exemptions', 'pe');
        $this->add_entity($penalty);

        $group = new group();
        $group->set_entity_name('group');
        $group->set_table_alias('groups', 'g');
        $group->set_table_alias('context', 'ctx');
        $group->add_joins([
            "LEFT JOIN {groups} g ON g.id = pe.itemid AND pe.itemtype = '{$grouptype}'",
            "LEFT JOIN {context} ctx ON ctx.id = pe.contextid",
        ]);
        $this->add_entity($group);

        if (!empty($this->contextids)) {
            $prefix = database::generate_param_name('contextids');
            [$insql, $params] = $DB->get_in_or_equal($this->contextids, SQL_PARAMS_NAMED, $prefix);
            $this->add_base_condition_sql("pe.contextid $insql", $params);
        }

        $this->add_columns();
        $this->add_actions();
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
            'penalty_exemption:itemtype',
        ]);

        $this->add_column_from_entity('group:name')
            ->set_title(new lang_string('groupname', 'core_group'))
            ->add_callback(fn($value, $group): string => empty($value) ? new lang_string('statusna')
                : html_writer::link(new url('/group/members.php', ['group' => $group->id]), $value));

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
            'penalty_exemption:scope',
            'penalty_exemption:itemtype',
        ];
        $this->add_filters_from_entities($filters);
    }

    /**
     * Adds actions.
     *
     * @return void
     */
    protected function add_actions(): void {
        // Navigate to the exemption management page for the selected context/scope.
        $this->add_action((new action(
            new url('/grade/penalty/manage_exemptions.php', ['contextid' => ':contextid', 'tab' => ':itemtype']),
            new pix_icon('url', '', 'tool_lp'),
            [],
            false,
            new lang_string('report:scope:navigate', 'core_grades')
        ))->add_callback(function ($row): bool {
            // Only show the action if the user has permission to view the exemption reports in the context.
            $context = context::instance_by_id($row->contextid);
            return has_capability('moodle/grade:viewpenaltyexemptions', $context);
        }));
    }
}
