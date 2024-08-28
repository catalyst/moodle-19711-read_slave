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
     * Create a new exemption.
     *
     * @param string $itemtype The type of the item being exempt.
     * @param int $itemid The id of the item which is to be exempt.
     * @param int $contextid The context of the item which is to be exempt.
     * @param string|null $reason The reason for the exemption.
     * @param int|null $reasonformat The format of the reason for the exemption.
     * @return exemption The exemption, once created.
     */
    public function create(string $itemtype, int $itemid, int $contextid, string $reason = null, int $reasonformat = null): exemption {
        $exemption = new exemption($this->component, $itemtype, $itemid, $contextid, $reason, $reasonformat);
        return $this->repo->add($exemption);
    }

    /**
     * Find an exemption.
     *
     * @param string $itemtype The type of the exempt item.
     * @param int $itemid The id of the exempt item.
     * @param int $contextid The context of the exempt item.
     *
     * @return exemption|null
     */
    public function find(string $itemtype, int $itemid, int $contextid) {
        try {
            return $this->repo->find_exemption($this->component, $itemtype, $itemid, $contextid);
        } catch (\dml_missing_record_exception $e) {
            return null;
        }
    }

    /**
     * Find exemptions by a set of criteria.
     * Returns an array of exemptions if multiple matches are found, a single exemption object if exactly one match is found, or null if no matches are found.
     *
     * @param array $options The criteria to find by.
     *
     * @return array|exemption|null
     */
    public function find_by(array $options): array|exemption|null {
        $options['component'] = $this->component;
        $found = $this->repo->find_by($options);

        if (empty($found)) {
            return null;
        }

        return (count($found) === 1) ? reset($found) : $found;
    }

    /**
     * Update an exemption.
     *
     * @param exemption $exemption the exemption to update.
     * @return exemption the updated exemption.
     */
    public function update(exemption $exemption): exemption {
        return $this->repo->update($exemption);
    }

    /**
     * Delete an exemption.
     *
     * @param string $itemtype the type of the exempt item.
     * @param int $itemid the id of the exempt item.
     * @param int $contextid the context of the exempt item.
     */
    public function delete(string $itemtype, int $itemid, int $contextid) {
        $this->repo->delete_by(
            [
                'component' => $this->component,
                'itemtype' => $itemtype,
                'itemid' => $itemid,
                'contextid' => $contextid,
            ]
        );
    }

    /**
     * Delete an exemption by a set of criteria.
     *
     * @param array $options the criteria to delete by.
     * @throws \moodle_exception if the provided delete options are invalid.
     */
    public function delete_by(array $options): void {
        // Prevent accidental deletion of all exemptions.
        if (empty($options['id']) && empty($options['itemtype']) && empty($options['contextid'])) {
            throw new \moodle_exception('Delete options must at least specify id, itemtype, or contextid');
        }

        $options['component'] = $this->component;
        $this->repo->delete_by($options);
    }

    /**
     * Check if an exemption exists.
     *
     * @param string $itemtype the type of the exempt item.
     * @param int $itemid the id of the exempt item.
     * @param int $contextid the context of the exempt item.
     *
     * @return bool
     */
    public function exists(string $itemtype, int $itemid, int $contextid): bool {
        return $this->repo->exists_by(
            [
                'component' => $this->component,
                'itemtype' => $itemtype,
                'itemid' => $itemid,
                'contextid' => $contextid,
            ]
        );
    }

    /**
     * Count the number of exemptions.
     *
     * @param array $options the criteria to find by.
     * @return int
     */
    public function count_by(array $options): int {
        $options['component'] = $this->component;
        return $this->repo->count_by($options);
    }

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

    // /**
    //  * Exempt an item defined by itemid/context, in the area defined by component/itemtype.
    //  *
    //  * @param string $itemtype the type of the item being exempt.
    //  * @param int $itemid the id of the item which is to be exempt.
    //  * @param int $contextid the context where the item is to be exempt.
    //  * @param array $options optional parameters including 'reason', 'reasonformat', 'ORDERing', and 'usermodified'.
    //  * @return exemption the exemption, once created.
    //  * @throws \moodle_exception if the component name is invalid, or if the repository encounters any errors.
    //  */
    // public function create_exemption(string $itemtype, int $itemid, int $contextid, array $options = []): exemption {
    //     // Access: Any component can ask to exempt something, we can't verify access to that 'something' here though.

    //     // Validate the component name.
    //     if (!in_array($this->component, \core_component::get_component_names())) {
    //         throw new \moodle_exception("Invalid component name '$this->component'");
    //     }

    //     // Extract optional parameters with defaults.
    //     $reason = $options['reason'] ?? null;
    //     $reasonformat = $options['reasonformat'] ?? null;
    //     $ORDERing = $options['ORDERing'] ?? null;
    //     $usermodified = $options['usermodified'] ?? null;

    //     $exemption = new exemption($this->component, $itemtype, $itemid, $contextid, $reason, $reasonformat, $usermodified);
    //     $exemption->ORDERing = $ORDERing > 0 ? $ORDERing : null;
    //     return $this->repo->add($exemption);
    // }

    // /**
    //  * Update an exemption item from an area and from within a context.
    //  *
    //  * @param exemption $exemption The exemption to update.
    //  * @return exemption
    //  */
    // public function update_exemption(exemption $exemption): exemption {
    //     return $this->repo->update($exemption);
    // }

    // /**
    //  * Delete an exemption item from an area and from within a context.
    //  *
    //  * @param string $itemtype the type of the exempt item.
    //  * @param int $itemid the id of the item which was exempt (not the exemption's id).
    //  * @param int $contextid the context of the item which was exempt.
    //  * @throws \dml_exception if any database errors are encountered.
    //  */
    // public function delete_exemption(string $itemtype, int $itemid, int $contextid) {
    //     $params = [
    //         'component' => $this->component,
    //         'itemtype' => $itemtype,
    //         'itemid' => $itemid,
    //         'contextid' => $contextid,
    //     ];

    //     $this->repo->delete_by($params);
    // }

    // /**
    //  * Delete an exemption by id.
    //  *
    //  * @param int $id the id of the exemption to delete.
    //  */
    // public function delete_exemption_by_id(int $id) {
    //     $this->repo->delete($id);
    // }

    // /**
    //  * Find a list of exemptions.
    //  *
    //  * @param array $options the options to filter the exemptions by, including 'itemtype', 'itemid' and 'contextid'.
    //  * @param int $limitfrom optional pagination control for returning a subset of records, starting at this point.
    //  * @param int $limitnum optional pagination control for returning a subset comprising this many records.
    //  *
    //  * @return array the list of exemptions found.
    //  * @throws \moodle_exception if the repository encounters any errors.
    //  */
    // public function find_exemptions(array $options, int $limitfrom = 0, int $limitnum = 0): array {
    //     $options['component'] = $this->component;
    //     return $this->repo->find_by($options);
    // }

    // /**
    //  * Find an exemption by id.
    //  *
    //  * @param int $id the id of the exemption to find.
    //  * @return exemption|null
    //  */
    // public function find_exemption_by_id(int $id): ?exemption {
    //     try {
    //         return $this->repo->find($id);
    //     } catch (\dml_missing_record_exception $e) {
    //         return null;
    //     }
    // }

    // /**
    //  * Delete a collection of exemptions by type and item, and optionally for a given context.
    //  *
    //  * E.g. delete all exemptions of type 'message_conversations' for the conversation '11' and in the CONTEXT_COURSE context.
    //  *
    //  * @param string $itemtype the type of the exempt items.
    //  * @param int $itemid the id of the item to which the exemptions relate
    //  * @param \context|null $context the context of the items which were exempt.
    //  */
    // public function delete_exemptions_by_type_and_item(string $itemtype, int $itemid, ?\context $context = null) {
    //     $criteria = ['component' => $this->component, 'itemtype' => $itemtype, 'itemid' => $itemid] +
    //         ($context ? ['contextid' => $context->id] : []);
    //     $this->repo->delete_by($criteria);
    // }

    // /**
    //  * Find a list of exemptions, by type and item, and optionally for a given context.
    //  *
    //  * @param string $itemtype the type of the exempt item.
    //  * @param int $itemid the id of the item which was exempt (not the exemption's id).
    //  * @param \context|null $context the context of the item which was exempt.
    //  *
    //  * @return array the list of exemptions found.
    //  * @throws \moodle_exception if the component name is invalid, or if the repository encounters any errors.
    //  */
    // public function find_exemptions_by_type_and_item(string $itemtype, int $itemid, ?\context $context = null): array {
    //     $criteria = [
    //         'component' => $this->component,
    //         'itemtype' => $itemtype,
    //         'itemid' => $itemid,
    //     ];

    //     if ($context) {
    //         $criteria['contextid'] = $context->id;
    //     }

    //     return $this->repo->find_by($criteria);
    // }


    // /**
    //  * Find a list of exemptions, by type, where type is the component/itemtype pair.
    //  *
    //  * E.g. "Find all exemption courses" might result in:
    //  * $exemcourses = find_exemptions_by_type('core_course', 'course');
    //  *
    //  * @param string $itemtype the type of the exempt item.
    //  * @param int $limitfrom optional pagination control for returning a subset of records, starting at this point.
    //  * @param int $limitnum optional pagination control for returning a subset comprising this many records.
    //  * @return array the list of exemptions found.
    //  * @throws \moodle_exception if the component name is invalid, or if the repository encounters any errors.
    //  */
    // public function find_exemptions_by_type(string $itemtype, int $limitfrom = 0, int $limitnum = 0): array {
    //     if (!in_array($this->component, \core_component::get_component_names())) {
    //         throw new \moodle_exception("Invalid component name '$this->component'");
    //     }
    //     return $this->repo->find_by(
    //         [
    //             'component' => $this->component,
    //             'itemtype' => $itemtype,
    //         ],
    //         $limitfrom,
    //         $limitnum
    //     );
    // }

    // /**
    //  * Find a list of exemptions, by multiple types within a component.
    //  *
    //  * E.g. "Find all exemptions in the activity chooser" might result in:
    //  * $exemcourses = find_all_exemptions('core_course', ['contentitem_mod_assign');
    //  *
    //  * @param array $itemtypes optional the type of the exempt item.
    //  * @param int $limitfrom optional pagination control for returning a subset of records, starting at this point.
    //  * @param int $limitnum optional pagination control for returning a subset comprising this many records.
    //  * @return array the list of exemptions found.
    //  * @throws \moodle_exception if the component name is invalid, or if the repository encounters any errors.
    //  */
    // public function find_all_exemptions(array $itemtypes = [], int $limitfrom = 0, int $limitnum = 0): array {
    //     if (!in_array($this->component, \core_component::get_component_names())) {
    //         throw new \moodle_exception("Invalid component name '$this->component'");
    //     }
    //     $params = [
    //         'component' => $this->component,
    //     ];
    //     if ($itemtypes) {
    //         $params['itemtype'] = $itemtypes;
    //     }

    //     return $this->repo->find_by(
    //         $params,
    //         $limitfrom,
    //         $limitnum
    //     );
    // }

    // /**
    //  * Returns the SQL required to include exemption information for a given component/itemtype combination.
    //  *
    //  * Generally, find_exemptions_by_type() is the recommended way to fetch exemptions.
    //  *
    //  * This method is used to include exemption information in external queries, for items identified by their
    //  * component and itemtype, matching itemid to the $joinitemid, and for the user to which this service is scoped.
    //  *
    //  * It uses a LEFT JOIN to preserve the original records. If you wish to restrict your records, please consider using a
    //  * "WHERE {$tablealias}.id IS NOT NULL" in your query.
    //  *
    //  * Example usage:
    //  *
    //  * list($sql, $params) = $service->get_join_sql_by_type('core_message', 'message_conversations', 'myexemptiontablealias',
    //  *                                                      'conv.id');
    //  * Results in $sql:
    //  *     "LEFT JOIN {exemption} exem
    //  *             ON exem.component = :exemptioncomponent
    //  *            AND exem.itemtype = :exemptionitemtype
    //  *            AND exem.itemid = conv.id"
    //  * and $params:
    //  *     ['exemptioncomponent' => 'core_message', 'exemptionitemtype' => 'message_conversations']
    //  *
    //  * @param string $itemtype the type of the exempt item.
    //  * @param string $tablealias the desired alias for the exemptions table.
    //  * @param string $joinitemid the table and column identifier which the itemid is joined to. E.g. conversation.id.
    //  * @return array the list of sql and params, in the format [$sql, $params].
    //  */
    // public function get_join_sql_by_type(string $itemtype, string $tablealias, string $joinitemid): array {
    //     $sql = " LEFT JOIN {exemption} {$tablealias}
    //                         ON {$tablealias}.component = :exemptioncomponent
    //                        AND {$tablealias}.itemtype = :exemptionitemtype
    //                        AND {$tablealias}.itemid = {$joinitemid} ";

    //     $params = [
    //         'exemptioncomponent' => $this->component,
    //         'exemptionitemtype' => $itemtype,
    //     ];

    //     return [$sql, $params];
    // }

    // /**
    //  * Check whether an item has been marked as an exemption in the respective area.
    //  *
    //  * @param string $itemtype the type of the exempt item.
    //  * @param int $itemid the id of the item which was exempt (not the exemption's id).
    //  * @param \context $context the context of the item which was exempt.
    //  * @return bool true if the item is exempt, false otherwise.
    //  */
    // public function exemption_exists(string $itemtype, int $itemid, \context $context): bool {
    //     return $this->repo->exists_by(
    //         [
    //             'component' => $this->component,
    //             'itemtype' => $itemtype,
    //             'itemid' => $itemid,
    //             'contextid' => $context->id,
    //         ]
    //     );
    // }

    // /**
    //  * Get the exemption.
    //  *
    //  * @param string $itemtype the type of the exempt item.
    //  * @param int $itemid the id of the item which was exempt (not the exemption's id).
    //  * @param int $contextid the context id of the item which was exempt.
    //  * @return exemption|null
    //  */
    // public function get_exemption(string $itemtype, int $itemid, int $contextid) {
    //     try {
    //         return $this->repo->find_exemption(
    //             $this->component,
    //             $itemtype,
    //             $itemid,
    //             $contextid
    //         );
    //     } catch (\dml_missing_record_exception $e) {
    //         return null;
    //     }
    // }

    // /**
    //  * Count the exemption by item type.
    //  *
    //  * @param string $itemtype the type of the exempt item.
    //  * @param \context|null $context the context of the item which was exempt.
    //  * @return int
    //  */
    // public function count_exemptions_by_type(string $itemtype, ?\context $context = null) {
    //     $criteria = [
    //         'component' => $this->component,
    //         'itemtype' => $itemtype,
    //     ];

    //     if ($context) {
    //         $criteria['contextid'] = $context->id;
    //     }

    //     return $this->repo->count_by($criteria);
    // }
}
