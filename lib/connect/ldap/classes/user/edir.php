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
 * LDAP user class for Novell Edirectory
 *
 * @package    connect_ldap
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace connect_ldap\user;

use stdClass;

use connect_ldap\exception\configuration_error,
    connect_ldap\exception\ldap_command_error;
use core_text;

defined('MOODLE_INTERNAL') || die();

/**
 * LDAP user class for Novell Edirectory
 *
 *
 * @package    connect_ldap
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edir extends base {
    const DEFAULTS = [
        'user_objectclass' => 'user',
        'user_attribute' => 'cn',
        'member_attribute' => 'member',
        'member_attribute_isdn' => '1',
        'password_expiration_attribute' => 'passwordExpirationTime',
    ];

    /**
     * Creates a new user on LDAP.
     *
     * @param array $newuser
     * @param string $hashedpassword
     * @throw configuration_error
     * @throw ldap_command_error
     */
    protected function _create(array $newuser, $hashedpassword): void {
        if (empty($this->config->user_create_context)) {
            throw new configuration_error('user_create_context');
        }

        $newuser['objectClass']   = ['inetOrgPerson', 'organizationalPerson', 'person', 'top'];
        $newuser['uniqueId']      = $this->username;
        $newuser['loginDisabled'] = 'TRUE';
        $newuser['userPassword']  = $hashedpassword;

        $this->client->add(
            $this->config->user_attribute.'='.$this->client->add_slashes($this->username).','.$this->config->user_create_context,
            $newuser
        );
    }

    /**
     * Returns Unix timestamp from password_expiration_attribute value
     *
     * @param string $expiry
     * @param ?array $info optional info array
     * @return int
     */
    protected function _password_expiry_timestamp($expiry, $info = []): int {
        $yr=substr($expiry, 0, 4);
        $mo=substr($expiry, 4, 2);
        $dt=substr($expiry, 6, 2);
        $hr=substr($expiry, 8, 2);
        $min=substr($expiry, 10, 2);
        $sec=substr($expiry, 12, 2);
        return mktime($hr, $min, $sec, $mo, $dt, $yr);
    }

    /**
     * Activates (enables) user in LDAP so user can login
     *
     * @throw ldap_command_error
     */
    public function activate(): void {
        $this->modify(['loginDisabled' => 'FALSE']);
    }

    /**
     * Changes user password in LDAP
     *
     * @param string  $hashedpassword
     * @throw ldap_command_error
     */
    protected function _update_password($hashedpassword): void {
        // Change password
        $newattrs = ['userPassword' => $hashedpassword];

        $search_attribs = [$this->config->password_expiration_attribute, 'passwordExpirationInterval', 'loginGraceLimit'];
        $info = $this->info($search_attribs);
        $pattr = strtolower($this->config->password_expiration_attribute);
        foreach ($info as $key => $val) {
            $lkey = strtolower($key);
            if ($lkey == $pattr) {
                $pexpiration = $val;
                continue;
            }
            if ($lkey == 'passwordexpirationinterval') {
                $pinterval = $val;
                continue;
            }
            if ($lkey == 'logingracelimit') {
                $plimit = $val;
                continue;
            }
        }

        if (!empty($pexpiration)) {
            // Set expiration time only if passwordExpirationInterval is defined
            if (!empty($pinterval)) {
               $expirationtime = time() + $pinterval;
               $newattrs[$this->config->password_expiration_attribute] = date('YmdHis', $time).'Z';
            }

            // Set gracelogin count
            if (!empty($plimit)) {
               $newattrs['loginGraceRemaining']= $plimit;
            }
        }

        // Store attribute changes in LDAP
        $this->modify($newattrs);
    }
}
