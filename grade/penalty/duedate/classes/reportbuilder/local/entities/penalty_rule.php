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

namespace gradepenalty_duedate\reportbuilder\local\entities;

use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\column;
use lang_string;

/**
 * Penalty rule entity.
 *
 * @package    gradepenalty_duedate
 * @copyright  2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class penalty_rule extends base {

    /**
     * Set the default tables for the penalty rule entity.
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return [
            'gradepenalty_duedate_rule',
        ];
    }

    /**
     * Get the default entity title.
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('penaltyrule', 'gradepenalty_duedate');
    }

    /**
     * Initialise the penalty rule entity.
     *
     * @return base
     */
    public function initialise(): base {
        $columns = $this->get_all_columns();
        foreach ($columns as $column) {
            $this->add_column($column);
        }
        return $this;
    }

    /**
     * Get the columns for the penalty rule report.
     */
    protected function get_all_columns(): array {
        $penaltyrulealias = $this->get_table_alias('gradepenalty_duedate_rule');

        // Late for column.
        $columns[] = (new column(
            'latefor',
            new lang_string('latefor', 'gradepenalty_duedate'),
            $this->get_entity_name()
        ))
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field($penaltyrulealias . '.latefor')
            ->set_is_sortable(false)
            ->add_callback(function ($value) {
                return format_time($value);
            });

        // Penalty column.
        $columns[] = (new column(
            'penalty',
            new lang_string('penalty', 'gradepenalty_duedate'),
            $this->get_entity_name()
        ))
            ->set_type(column::TYPE_INTEGER)
            ->add_field($penaltyrulealias . '.penalty')
            ->set_is_sortable(false)
            ->add_callback([format::class, 'percent']);

        return $columns;
    }
}
