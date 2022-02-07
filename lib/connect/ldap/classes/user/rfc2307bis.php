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
 * LDAP user class for posixAccount (rfc2307bis)
 *
 * @package    connect_ldap
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace connect_ldap\user;

use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * LDAP user class for posixAccount (rfc2307bis)
 *
 *
 * @package    connect_ldap
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rfc2307bis extends rfc2307 {
    const DEFAULTS = [
        'user_objectclass' => 'posixAccount',
        'user_attribute' => 'uid',
        'member_attribute' => 'member',
        'member_attribute_isdn' => '1',
        'password_expiration_attribute' => 'shadowExpire',
    ];
}
