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

namespace core_exemptions\local\entity;

/**
 * Contains the exemption class, each instance being a representation of a DB row for the 'exemption' table.
 *
 * @package     core_exemptions
 * @author      Alexander Van der Bellen <alexandervanderbellen@catalyst-au.net>
 * @copyright   2024 Catalyst IT Australia
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exemption {
    /** @var int $id the id of the exemption.*/
    public $id;

    /** @var string $component the frankenstyle name of the component containing the exempt item. E.g. 'core_course'.*/
    public $component;

    /** @var string $itemtype the type of the item being marked as an exemption. E.g. 'course', 'conversation', etc.*/
    public $itemtype;

    /** @var int $itemid the id of the item that is being marked as an exemption. e.g course->id, conversation->id, etc.*/
    public $itemid;

    /** @var int $contextid the id of the context in which this exemption was created.*/
    public $contextid;

    /** @var int $timecreated the time at which the exemption was created.*/
    public $timecreated;

    /** @var int $timemodified the time at which the last modification of the exemption took place.*/
    public $timemodified;

    /** @var int|null $usercreated the id of the user who created the exemption.*/
    public $usermodified;

    /** @var string|null $reason the reason for the exemption.*/
    public $reason;

    /** @var int|null $reasonformat the format of the reason for the exemption.*/
    public $reasonformat;

    /** @var string $uniquekey exemption unique key.*/
    public $uniquekey;

    /**
     * Exemption constructor.
     *
     * @param string $component the frankenstyle name of the component containing the exempt item. E.g. 'core_course'.
     * @param string $itemtype the type of the item being marked as an exemption. E.g. 'course', 'conversation', etc.
     * @param int $itemid the id of the item that is being marked as an exemption. e.g course->id, conversation->id, etc.
     * @param int $contextid the id of the context in which this exemption was created.
     * @param string|null $reason the reason for the exemption.
     * @param int|null $reasonformat the format of the reason for the exemption.
     */
    public function __construct(string $component, string $itemtype, int $itemid, int $contextid,
        ?string $reason = null, ?int $reasonformat = null) {
        $this->component = $component;
        $this->itemtype = $itemtype;
        $this->itemid = $itemid;
        $this->contextid = $contextid;
        $this->reason = $reason;
        $this->reasonformat = $reasonformat;
    }
}
