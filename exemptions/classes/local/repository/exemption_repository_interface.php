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
 * Contains the exemption_repository interface.
 *
 * @package     core_exemptions
 * @author      Alexander Van der Bellen <alexandervanderbellen@catalyst-au.net>
 * @copyright   2018 Jake Dallimore <jrhdallimore@gmail.com>
 * @copyright   2024 Catalyst IT Australia
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core_exemptions\local\repository;
use core_exemptions\local\entity\exemption;

/**
 * The exemption_repository interface, defining the basic CRUD operations for exemption type items within core_exemptions.
 */
interface exemption_repository_interface {
    /**
     * Add one item to this repository.
     *
     * @param exemption $exemption the item to add.
     * @return exemption the item which was added.
     */
    public function add(exemption $exemption): exemption;

    /**
     * Add all the items in the list to this repository.
     *
     * @param array $exemptions the list of items to add.
     * @return array the list of items added to this repository.
     */
    public function add_all(array $exemptions): array;

    /**
     * Find an item in this repository based on its id.
     *
     * @param int $id the id of the item.
     * @return exemption the item.
     */
    public function find(int $id): exemption;

    /**
     * Find all items in this repository.
     *
     * @param int $limitfrom optional pagination control for returning a subset of records, starting at this point.
     * @param int $limitnum optional pagination control for returning a subset comprising this many records.
     * @return array list of all items in this repository.
     */
    public function find_all(int $limitfrom = 0, int $limitnum = 0): array;

    /**
     * Find all items with attributes matching certain values.
     *
     * @param array $criteria the array of attribute/value pairs.
     * @param int $limitfrom optional pagination control for returning a subset of records, starting at this point.
     * @param int $limitnum optional pagination control for returning a subset comprising this many records.
     * @return array the list of items matching the criteria.
     */
    public function find_by(array $criteria, int $limitfrom = 0, int $limitnum = 0): array;

    /**
     * Check whether an item exists in this repository, based on its id.
     *
     * @param int $id the id to search for.
     * @return bool true if the item could be found, false otherwise.
     */
    public function exists(int $id): bool;

    /**
     * Check whether an item exists in this repository, based on the specified criteria.
     *
     * @param array $criteria the list of key/value criteria pairs.
     * @return bool true if the exemption exists, false otherwise.
     */
    public function exists_by(array $criteria): bool;

    /**
     * Return the total number of items in this repository.
     *
     * @return int the total number of items.
     */
    public function count(): int;

    /**
     * Return the number of exemptions matching the specified criteria.
     *
     * @param array $criteria the list of key/value criteria pairs.
     * @return int the number of exemptions matching the criteria.
     */
    public function count_by(array $criteria): int;

    /**
     * Update an item within this repository.
     *
     * @param exemption $exemption the item to update.
     * @return exemption the updated item.
     */
    public function update(exemption $exemption): exemption;

    /**
     * Delete an item by id.
     *
     * @param int $id the id of the item to delete.
     * @return void
     */
    public function delete(int $id);

    /**
     * Delete all exemptions matching the specified criteria.
     *
     * @param array $criteria the list of key/value criteria pairs.
     * @return void.
     */
    public function delete_by(array $criteria);

    /**
     * Find a single exemption, based on it's unique identifiers.
     *
     * @param string $component the frankenstyle component name.
     * @param string $itemtype the type of the exempt item.
     * @param int $itemid the id of the item which was exempt (not the exemption's id).
     * @param int $contextid the contextid of the item which was exempt.
     * @return exemption the exemption.
     */
    public function find_exemption(string $component, string $itemtype, int $itemid, int $contextid): exemption;
}
