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

declare(strict_types=1);

namespace core_grades\reportbuilder\local\entities;

use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\filters\text;
use lang_string;
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\{column, filter};
use core_reportbuilder\local\filters\select;

/**
 * Entity for penalty exemptions.
 *
 * @package     core_grades
 * @copyright   2025 Catalyst IT Australia Pty Ltd
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class penalty_exemption extends base {

    /**
     * Database tables that this entity uses.
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return [
            'grade_penalty_exemptions',
            'context',
        ];
    }

    /**
     * The default title for this entity.
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('report:penaltyexemption', 'core_grades');
    }

    /**
     * Initialise the entity.
     *
     * @return base
     */
    public function initialise(): base {
        foreach ($this->add_columns() as $column) {
            $this->add_column($column);
        }

        // All the filters defined by the entity can also be used as conditions.
        $filters = $this->get_all_filters();
        foreach ($filters as $filter) {
            $this
                ->add_filter($filter)
                ->add_condition($filter);
        }

        return $this;
    }

    /**
     * Returns list of all available columns.
     *
     * @return column[]
     */
    protected function add_columns(): array {
        $penaltyexemptionalias = $this->get_table_alias('grade_penalty_exemptions');
        $contextalias = $this->get_table_alias('context');

        // Time created column.
        $columns[] = (new column(
            'timecreated',
            new lang_string('timecreated', 'core_reportbuilder'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_fields("{$penaltyexemptionalias}.timecreated")
            ->set_is_sortable(true)
            ->set_callback([format::class, 'userdate']);

        // Time modified column.
        $columns[] = (new column(
            'timemodified',
            new lang_string('timemodified', 'core_reportbuilder'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_fields("{$penaltyexemptionalias}.timemodified")
            ->set_is_sortable(true)
            ->set_callback([format::class, 'userdate']);

        $columns[] = (new column(
            'itemtype',
            new lang_string('report:itemtype', 'core_grades'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_fields("{$penaltyexemptionalias}.itemtype")
            ->set_is_sortable(true)
            ->set_callback(fn(string $value): string => match ($value) {
                \core_grades\penalty_exemption::TYPE_USER => get_string('report:itemtype:user', 'core_grades'),
                \core_grades\penalty_exemption::TYPE_GROUP => get_string('report:itemtype:group', 'core_grades'),
            });

        $columns[] = (new column(
            'reason',
            new lang_string('report:reason', 'core_grades'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_fields("{$penaltyexemptionalias}.reason, {$penaltyexemptionalias}.reasonformat")
            ->set_is_sortable(true)
            ->set_callback(fn($value, $row): string => format_text($value, $row->reasonformat));

        $columns[] = (new column(
            'scope',
            new lang_string('report:scope', 'core_grades'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_join("JOIN {context} {$contextalias} ON {$contextalias}.id = {$penaltyexemptionalias}.contextid")
            ->set_type(column::TYPE_TEXT)
            ->add_fields("{$contextalias}.contextlevel")
            ->set_is_sortable(true)
            ->set_callback(fn($value): string => match ((int) $value) {
                CONTEXT_SYSTEM => get_string('site'),
                CONTEXT_COURSE => get_string('course'),
                CONTEXT_MODULE => get_string('activity'),
                default => get_string('report:scope:unknown', 'core_grades'),
            });

        return $columns;
    }

    /**
     * Return list of all available filters.
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $penaltyexemptionalias = $this->get_table_alias('grade_penalty_exemptions');
        $contextalias = $this->get_table_alias('context');

        // Time created filter.
        $filters[] = (new filter(
            date::class,
            'timecreated',
            new lang_string('timecreated', 'core_reportbuilder'),
            $this->get_entity_name(),
            "{$penaltyexemptionalias}.timecreated"
        ))
            ->add_joins($this->get_joins());

        // Time modified filter.
        $filters[] = (new filter(
            date::class,
            'timemodified',
            new lang_string('timemodified', 'core_reportbuilder'),
            $this->get_entity_name(),
            "{$penaltyexemptionalias}.timemodified"
        ))
            ->add_joins($this->get_joins());

        // Dropdown filter for itemtype.
        $filters[] = (new filter(
            select::class,
            'itemtype',
            new lang_string('report:itemtype', 'core_grades'),
            $this->get_entity_name(),
            "{$penaltyexemptionalias}.itemtype"
        ))
            ->set_options([
                \core_grades\penalty_exemption::TYPE_USER => get_string('report:itemtype:user', 'core_grades'),
                \core_grades\penalty_exemption::TYPE_GROUP => get_string('report:itemtype:group', 'core_grades'),
            ])
            ->add_joins($this->get_joins());

        // Dropdown filter for scope.
        $filters[] = (new filter(
            select::class,
            'scope',
            new lang_string('report:scope', 'core_grades'),
            $this->get_entity_name(),
            "{$contextalias}.contextlevel"
        ))
            ->set_options([
                CONTEXT_SYSTEM => new lang_string('site'),
                CONTEXT_COURSE => new lang_string('course'),
                CONTEXT_MODULE => new lang_string('activity'),
            ])
            ->add_joins($this->get_joins());

        // Text filter for reason.
        $filters[] = (new filter(
            text::class,
            'reason',
            new lang_string('report:reason', 'core_grades'),
            $this->get_entity_name(),
            "{$penaltyexemptionalias}.reason"
        ))
            ->add_joins($this->get_joins());

        return $filters;
    }
}
