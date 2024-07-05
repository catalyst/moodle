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
 * Contains the component_exemption_service class, part of the service layer for the exemptions subsystem.
 *
 * @package   core_exemptions
 * @copyright 2019 Jake Dallimore <jrhdallimore@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core_exemptions\local\service;
use \core_exemptions\local\repository\exemption_repository_interface;

defined('MOODLE_INTERNAL') || die();

/**
 * Class service, providing an single API for interacting with the exemptions subsystem, for all exemptions of a specific component.
 *
 * This class provides operations which can be applied to exemptions within a component, based on type and context identifiers.
 *
 * All object persistence is delegated to the exemption_repository_interface object.
 *
 * @copyright 2019 Jake Dallimore <jrhdallimore@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class component_exemption_service {

    /** @var exemption_repository_interface $repo the exemption repository object. */
    protected $repo;

    /** @var int $component the frankenstyle component name to which this exemptions service is scoped. */
    protected $component;

    /**
     * The component_exemption_service constructor.
     *
     * @param string $component The frankenstyle name of the component to which this service operations are scoped.
     * @param \core_exemptions\local\repository\exemption_repository_interface $repository a exemptions repository.
     * @throws \moodle_exception if the component name is invalid.
     */
    public function __construct(string $component, exemption_repository_interface $repository) {
        if (!in_array($component, \core_component::get_component_names())) {
            throw new \moodle_exception("Invalid component name '$component'");
        }
        $this->repo = $repository;
        $this->component = $component;
    }


    /**
     * Delete a collection of exemptions by type and item, and optionally for a given context.
     *
     * E.g. delete all exemptions of type 'message_conversations' for the conversation '11' and in the CONTEXT_COURSE context.
     *
     * @param string $itemtype the type of the exemptiond items.
     * @param int $itemid the id of the item to which the exemptions relate
     * @param \context $context the context of the items which were exemptiond.
     */
    public function delete_exemptions_by_type_and_item(string $itemtype, int $itemid, \context $context = null) {
        $criteria = ['component' => $this->component, 'itemtype' => $itemtype, 'itemid' => $itemid] +
            ($context ? ['contextid' => $context->id] : []);
        $this->repo->delete_by($criteria);
    }
}
