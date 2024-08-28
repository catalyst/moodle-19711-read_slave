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
 * @package     core_exemptions
 * @author      Alexander Van der Bellen <alexandervanderbellen@catalyst-au.net>
 * @copyright   2019 Jake Dallimore <jrhdallimore@gmail.com>
 * @copyright   2024 Catalyst IT Australia
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core_exemptions\local\service;

use core_exemptions\local\entity\exemption;
use core_exemptions\local\repository\exemption_repository_interface;

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
     * @param \core_exemptions\local\repository\exemption_repository_interface $repository an exemptions repository.
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
     * @param string $itemtype the type of the exempt items.
     * @param int $itemid the id of the item to which the exemptions relate
     * @param \context|null $context the context of the items which were exempt.
     */
    public function delete_exemptions_by_type_and_item(string $itemtype, int $itemid, ?\context $context = null) {
        $criteria = ['component' => $this->component, 'itemtype' => $itemtype, 'itemid' => $itemid] +
            ($context ? ['contextid' => $context->id] : []);
        $this->repo->delete_by($criteria);
    }

    /**
     * Exempt an item defined by itemid/context, in the area defined by component/itemtype.
     *
     * @param string $itemtype the type of the item being exempt.
     * @param int $itemid the id of the item which is to be exempt.
     * @param \context $context the context in which the item is to be exempt.
     * @param array $options optional parameters including 'reason', 'reasonformat', 'ordering', and 'usermodified'.
     * @return exemption the exemption, once created.
     * @throws \moodle_exception if the component name is invalid, or if the repository encounters any errors.
     */
    public function create_exemption(string $itemtype, int $itemid, \context $context, array $options = []): exemption {
        // Access: Any component can ask to exempt something, we can't verify access to that 'something' here though.

        // Validate the component name.
        if (!in_array($this->component, \core_component::get_component_names())) {
            throw new \moodle_exception("Invalid component name '$this->component'");
        }

        // Extract optional parameters with defaults.
        $reason = $options['reason'] ?? null;
        $reasonformat = $options['reasonformat'] ?? null;
        $ordering = $options['ordering'] ?? null;
        $usermodified = $options['usermodified'] ?? null;

        $exemption = new exemption($this->component, $itemtype, $itemid, $context->id, $reason, $reasonformat, $usermodified);
        $exemption->ordering = $ordering > 0 ? $ordering : null;
        return $this->repo->add($exemption);
    }

    /**
     * Find a list of exemptions, by type, where type is the component/itemtype pair.
     *
     * E.g. "Find all exemption courses" might result in:
     * $exemcourses = find_exemptions_by_type('core_course', 'course');
     *
     * @param string $itemtype the type of the exempt item.
     * @param int $limitfrom optional pagination control for returning a subset of records, starting at this point.
     * @param int $limitnum optional pagination control for returning a subset comprising this many records.
     * @return array the list of exemptions found.
     * @throws \moodle_exception if the component name is invalid, or if the repository encounters any errors.
     */
    public function find_exemptions_by_type(string $itemtype, int $limitfrom = 0, int $limitnum = 0): array {
        if (!in_array($this->component, \core_component::get_component_names())) {
            throw new \moodle_exception("Invalid component name '$this->component'");
        }
        return $this->repo->find_by(
            [
                'component' => $this->component,
                'itemtype' => $itemtype,
            ],
            $limitfrom,
            $limitnum
        );
    }

    /**
     * Find a list of exemptions, by multiple types within a component.
     *
     * E.g. "Find all exemptions in the activity chooser" might result in:
     * $exemcourses = find_all_exemptions('core_course', ['contentitem_mod_assign');
     *
     * @param array $itemtypes optional the type of the exempt item.
     * @param int $limitfrom optional pagination control for returning a subset of records, starting at this point.
     * @param int $limitnum optional pagination control for returning a subset comprising this many records.
     * @return array the list of exemptions found.
     * @throws \moodle_exception if the component name is invalid, or if the repository encounters any errors.
     */
    public function find_all_exemptions(array $itemtypes = [], int $limitfrom = 0, int $limitnum = 0): array {
        if (!in_array($this->component, \core_component::get_component_names())) {
            throw new \moodle_exception("Invalid component name '$this->component'");
        }
        $params = [
            'component' => $this->component,
        ];
        if ($itemtypes) {
            $params['itemtype'] = $itemtypes;
        }

        return $this->repo->find_by(
            $params,
            $limitfrom,
            $limitnum
        );
    }

    /**
     * Returns the SQL required to include exemption information for a given component/itemtype combination.
     *
     * Generally, find_exemptions_by_type() is the recommended way to fetch exemptions.
     *
     * This method is used to include exemption information in external queries, for items identified by their
     * component and itemtype, matching itemid to the $joinitemid, and for the user to which this service is scoped.
     *
     * It uses a LEFT JOIN to preserve the original records. If you wish to restrict your records, please consider using a
     * "WHERE {$tablealias}.id IS NOT NULL" in your query.
     *
     * Example usage:
     *
     * list($sql, $params) = $service->get_join_sql_by_type('core_message', 'message_conversations', 'myexemptiontablealias',
     *                                                      'conv.id');
     * Results in $sql:
     *     "LEFT JOIN {exemption} exem
     *             ON exem.component = :exemptioncomponent
     *            AND exem.itemtype = :exemptionitemtype
     *            AND exem.itemid = conv.id"
     * and $params:
     *     ['exemptioncomponent' => 'core_message', 'exemptionitemtype' => 'message_conversations']
     *
     * @param string $itemtype the type of the exempt item.
     * @param string $tablealias the desired alias for the exemptions table.
     * @param string $joinitemid the table and column identifier which the itemid is joined to. E.g. conversation.id.
     * @return array the list of sql and params, in the format [$sql, $params].
     */
    public function get_join_sql_by_type(string $itemtype, string $tablealias, string $joinitemid): array {
        $sql = " LEFT JOIN {exemption} {$tablealias}
                            ON {$tablealias}.component = :exemptioncomponent
                           AND {$tablealias}.itemtype = :exemptionitemtype
                           AND {$tablealias}.itemid = {$joinitemid} ";

        $params = [
            'exemptioncomponent' => $this->component,
            'exemptionitemtype' => $itemtype,
        ];

        return [$sql, $params];
    }

    /**
     * Delete an exemption item from an area and from within a context.
     *
     * E.g. delete an exemption course from the area 'core_course', 'course' with itemid 3 and from within the CONTEXT_USER context.
     *
     * @param string $itemtype the type of the exempt item.
     * @param int $itemid the id of the item which was exempt (not the exemption's id).
     * @param \context $context the context of the item which was exempt.
     * @throws \moodle_exception if the user does not control the exemption, or it doesn't exist.
     */
    public function delete_exemption(string $itemtype, int $itemid, \context $context) {
        if (!in_array($this->component, \core_component::get_component_names())) {
            throw new \moodle_exception("Invalid component name '$this->component'");
        }

        // Business logic: check the user owns the exemption.
        try {
            $exemption = $this->repo->find_exemption($this->component, $itemtype, $itemid, $context->id);
        } catch (\moodle_exception $e) {
            throw new \moodle_exception("exemption does not exist for the user. Cannot delete.");
        }

        $this->repo->delete($exemption->id);
    }

    /**
     * Check whether an item has been marked as an exemption in the respective area.
     *
     * @param string $itemtype the type of the exempt item.
     * @param int $itemid the id of the item which was exempt (not the exemption's id).
     * @param \context $context the context of the item which was exempt.
     * @return bool true if the item is exempt, false otherwise.
     */
    public function exemption_exists(string $itemtype, int $itemid, \context $context): bool {
        return $this->repo->exists_by(
            [
                'component' => $this->component,
                'itemtype' => $itemtype,
                'itemid' => $itemid,
                'contextid' => $context->id,
            ]
        );
    }

    /**
     * Get the exemption.
     *
     * @param string $itemtype the type of the exempt item.
     * @param int $itemid the id of the item which was exempt (not the exemption's id).
     * @param \context $context the context of the item which was exempt.
     * @return exemption|null
     */
    public function get_exemption(string $itemtype, int $itemid, \context $context) {
        try {
            return $this->repo->find_exemption(
                $this->component,
                $itemtype,
                $itemid,
                $context->id
            );
        } catch (\dml_missing_record_exception $e) {
            return null;
        }
    }

    /**
     * Count the exemption by item type.
     *
     * @param string $itemtype the type of the exempt item.
     * @param \context|null $context the context of the item which was exempt.
     * @return int
     */
    public function count_exemptions_by_type(string $itemtype, ?\context $context = null) {
        $criteria = [
            'component' => $this->component,
            'itemtype' => $itemtype,
        ];

        if ($context) {
            $criteria['contextid'] = $context->id;
        }

        return $this->repo->count_by($criteria);
    }
}
