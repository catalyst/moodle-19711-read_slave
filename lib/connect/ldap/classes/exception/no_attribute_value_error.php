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
 * LDAP client
 *
 * LDAP connection and general-purpose LDAP functions and
 * data structures, useful for both ldap authentication (or ldap based
 * authentication like CAS) and enrolment plugins.
 *
 * @package    connect_ldap
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace connect_ldap\exception;

defined('MOODLE_INTERNAL') || die();

/**
 * Attribute value not found error exception
 *
 * @package    connect_ldap
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class no_attribute_value_error extends error {
    /**
     * Constructor
     * @param string $attribute
     * @param string $dn
     */
    function __construct($attribute, $dn) {
        parent::__construct('noattrval', '', "$attribute ($dn)");
    }
};
