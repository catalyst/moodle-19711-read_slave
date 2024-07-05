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

namespace core_exemptions\local\repository;
use core_exemptions\local\entity\exemption;

/**
 * Class exemption_repository.
 *
 * This class handles persistence of exemptions.
 *
 * @package     core_exemptions
 * @author      Alexander Van der Bellen <alexandervanderbellen@catalyst-au.net>
 * @copyright   2018 Jake Dallimore <jrhdallimore@gmail.com>
 * @copyright   2024 Catalyst IT Australia
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exemption_repository implements exemption_repository_interface {

    /**
     * @var string the name of the table which exemptions are stored in.
     */
    protected $exemptiontable = 'exemption';

    /**
     * Get an exemption object, based on a full record.
     *
     * @param \stdClass $record the record we wish to hydrate.
     * @return exemption the exemption record.
     */
    protected function get_exemption_from_record(\stdClass $record): exemption {
        $exemption = new exemption(
            $record->component,
            $record->itemtype,
            $record->itemid,
            $record->contextid,
            $record->reason,
            $record->reasonformat,
        );
        $exemption->id = $record->id;
        $exemption->timecreated = $record->timecreated ?? null;
        $exemption->timemodified = $record->timemodified ?? null;
        $exemption->usermodified = $record->usermodified ?? null;

        return $exemption;
    }

    /**
     * Get a list of exemption objects, based on a list of records.
     *
     * @param array $records the record we wish to hydrate.
     * @return array the list of exemptions.
     */
    protected function get_list_of_exemptions_from_records(array $records): array {
        $list = [];
        foreach ($records as $index => $record) {
            $list[$index] = $this->get_exemption_from_record($record);
        }
        return $list;
    }

    /**
     * Basic validation, confirming we have the minimum field set needed to save a record to the store.
     *
     * @param exemption $exemption the exemption record to validate.
     * @throws \moodle_exception if the supplied exemption has missing or unsupported fields.
     */
    protected function validate(exemption $exemption) {

        $exemption = (array)$exemption;

        // The allowed fields, and whether or not each is required to create a record.
        // The timecreated, timemodified and id fields are generated during create/update.
        $allowedfields = [
            'userid' => false,
            'component' => true,
            'itemtype' => true,
            'itemid' => true,
            'contextid' => true,
            'timecreated' => false,
            'timemodified' => false,
            'usermodified' => false,
            'reason' => false,
            'reasonformat' => false,
            'id' => false,
            'uniquekey' => false,
        ];

        $requiredfields = array_filter($allowedfields, function($field) {
            return $field;
        });

        if ($missingfields = array_keys(array_diff_key($requiredfields, $exemption))) {
            throw new \moodle_exception("Missing object property(s) '" . join(', ', $missingfields) . "'.");
        }

        // If the record contains fields we don't allow, throw an exception.
        if ($unsupportedfields = array_keys(array_diff_key($exemption, $allowedfields))) {
            throw new \moodle_exception("Unexpected object property(s) '" . join(', ', $unsupportedfields) . "'.");
        }
    }

    /**
     * Add an exemption to the repository.
     *
     * @param exemption $exemption the exemption to add.
     * @return exemption the exemption which has been stored.
     * @throws \dml_exception if any database errors are encountered.
     * @throws \moodle_exception if the exemption has missing or invalid properties.
     */
    public function add(exemption $exemption): exemption {
        global $DB, $USER;
        $this->validate($exemption);
        $exemption = (array)$exemption;
        $time = time();
        $exemption['timecreated'] = $time;
        $exemption['timemodified'] = $time;
        $exemption['usermodified'] = $USER->id;
        $id = $DB->insert_record($this->exemptiontable, $exemption);
        return $this->find($id);
    }

    /**
     * Add a collection of exemptions to the repository.
     *
     * @param array $exemptions the list of exemptions to add.
     * @return array the list of exemptions which have been stored.
     * @throws \dml_exception if any database errors are encountered.
     * @throws \moodle_exception if any of the exemptions have missing or invalid properties.
     */
    public function add_all(array $exemptions): array {
        global $DB, $USER;
        $time = time();
        foreach ($exemptions as $item) {
            $this->validate($item);
            $exemption = (array)$item;
            $exemption['timecreated'] = $time;
            $exemption['timemodified'] = $time;
            $exemption['usermodified'] = $USER->id;
            $ids[] = $DB->insert_record($this->exemptiontable, $exemption);
        }
        [$insql, $params] = $DB->get_in_or_equal($ids);
        $records = $DB->get_records_select($this->exemptiontable, "id $insql", $params);
        return $this->get_list_of_exemptions_from_records($records);
    }

    /**
     * Find an exemption by id.
     *
     * @param int $id the id of the exemption.
     * @return exemption the exemption.
     * @throws \dml_exception if any database errors are encountered.
     */
    public function find(int $id): exemption {
        global $DB;
        $record = $DB->get_record($this->exemptiontable, ['id' => $id], '*', MUST_EXIST);
        return $this->get_exemption_from_record($record);
    }

    /**
     * Return all items in this repository, as an array, indexed by id.
     *
     * @param int $limitfrom optional pagination control for returning a subset of records, starting at this point.
     * @param int $limitnum optional pagination control for returning a subset comprising this many records.
     * @return array the list of all exemptions stored within this repository.
     * @throws \dml_exception if any database errors are encountered.
     */
    public function find_all(int $limitfrom = 0, int $limitnum = 0): array {
        global $DB;
        $records = $DB->get_records($this->exemptiontable, null, '', '*', $limitfrom, $limitnum);
        return $this->get_list_of_exemptions_from_records($records);
    }

    /**
     * Return all items matching the supplied criteria (a [key => value,..] list).
     *
     * @param array $criteria the list of key/value(s) criteria pairs.
     * @param int $limitfrom optional pagination control for returning a subset of records, starting at this point.
     * @param int $limitnum optional pagination control for returning a subset comprising this many records.
     * @return array the list of exemptions matching the criteria.
     * @throws \dml_exception if any database errors are encountered.
     */
    public function find_by(array $criteria, int $limitfrom = 0, int $limitnum = 0): array {
        global $DB;
        $conditions = [];
        $params = [];
        foreach ($criteria as $field => $value) {
            if (is_array($value) && count($value)) {
                [$insql, $inparams] = $DB->get_in_or_equal($value, SQL_PARAMS_NAMED);
                $conditions[] = "$field $insql";
                $params = array_merge($params, $inparams);
            } else {
                $conditions[] = "$field = :$field";
                $params = array_merge($params, [$field => $value]);
            }
        }

        $records = $DB->get_records_select($this->exemptiontable, implode(' AND ', $conditions), $params,
            '', '*', $limitfrom, $limitnum);

        return $this->get_list_of_exemptions_from_records($records);
    }

    /**
     * Find a specific exemption, based on the properties known to identify it.
     *
     * Used if we don't know its id.
     *
     * @param string $component the frankenstyle component name.
     * @param string $itemtype the type of the exempt item.
     * @param int $itemid the id of the item which was exempt (not the exemption's id).
     * @param int $contextid the contextid of the item which was exempt.
     * @return exemption the exemption.
     * @throws \dml_exception if any database errors are encountered or if the record could not be found.
     */
    public function find_exemption(string $component, string $itemtype, int $itemid, int $contextid): exemption {
        global $DB;
        // Exemptions model: We know that only one exemption can exist based on these properties.
        $record = $DB->get_record($this->exemptiontable, [
            'component' => $component,
            'itemtype' => $itemtype,
            'itemid' => $itemid,
            'contextid' => $contextid,
        ], '*', MUST_EXIST);
        return $this->get_exemption_from_record($record);
    }

    /**
     * Check whether an exemption exists in this repository, based on its id.
     *
     * @param int $id the id to search for.
     * @return bool true if the exemption exists, false otherwise.
     * @throws \dml_exception if any database errors are encountered.
     */
    public function exists(int $id): bool {
        global $DB;
        return $DB->record_exists($this->exemptiontable, ['id' => $id]);
    }

    /**
     * Check whether an item exists in this repository, based on the specified criteria.
     *
     * @param array $criteria the list of key/value criteria pairs.
     * @return bool true if the exemption exists, false otherwise.
     * @throws \dml_exception if any database errors are encountered.
     */
    public function exists_by(array $criteria): bool {
        global $DB;
        return $DB->record_exists($this->exemptiontable, $criteria);
    }

    /**
     * Update an exemption.
     *
     * @param exemption $exemption the exemption to update.
     * @return exemption the updated exemption.
     * @throws \dml_exception if any database errors are encountered.
     */
    public function update(exemption $exemption): exemption {
        global $DB, $USER;
        $time = time();
        $exemption->timemodified = $time;
        $exemption->usermodified = $USER->id;
        $DB->update_record($this->exemptiontable, $exemption);
        return $this->find($exemption->id);
    }

    /**
     * Delete an exemption, by id.
     *
     * @param int $id the id of the exemption to delete.
     * @throws \dml_exception if any database errors are encountered.
     */
    public function delete(int $id) {
        global $DB;
        $DB->delete_records($this->exemptiontable, ['id' => $id]);
    }

    /**
     * Delete all exemptions matching the specified criteria.
     *
     * @param array $criteria the list of key/value criteria pairs.
     * @throws \dml_exception if any database errors are encountered.
     */
    public function delete_by(array $criteria) {
        global $DB;
        $conditions = [];
        $params = [];
        foreach ($criteria as $field => $value) {
            if (is_array($value) && count($value)) {
                [$insql, $inparams] = $DB->get_in_or_equal($value, SQL_PARAMS_NAMED);
                $conditions[] = "$field $insql";
                $params = array_merge($params, $inparams);
            } else {
                $conditions[] = "$field = :$field";
                $params = array_merge($params, [$field => $value]);
            }
        }

        $DB->delete_records_select($this->exemptiontable, implode(' AND ', $conditions), $params);
    }

    /**
     * Return the total number of exemptions in this repository.
     *
     * @return int the total number of items.
     * @throws \dml_exception if any database errors are encountered.
     */
    public function count(): int {
        global $DB;
        return $DB->count_records($this->exemptiontable);
    }

    /**
     * Return the number of user exemptions matching the specified criteria.
     *
     * @param array $criteria the list of key/value criteria pairs.
     * @return int the number of exemptions matching the criteria.
     * @throws \dml_exception if any database errors are encountered.
     */
    public function count_by(array $criteria): int {
        global $DB;
        return $DB->count_records($this->exemptiontable, $criteria);
    }
}
