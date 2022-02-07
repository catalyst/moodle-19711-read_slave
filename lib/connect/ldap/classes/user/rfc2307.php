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
 * LDAP user class for posixAccount (rfc2307)
 *
 * @package    connect_ldap
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace connect_ldap\user;

use stdClass;

use connect_ldap\exception\ldap_command_error;
use core_text;

defined('MOODLE_INTERNAL') || die();

/**
 * LDAP user class for posixAccount (rfc2307)
 *
 *
 * @package    connect_ldap
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rfc2307 extends base {
    const DEFAULTS = [
        'user_objectclass' => 'posixAccount',
        'user_attribute' => 'uid',
        'member_attribute' => 'member',
        'member_attribute_isdn' => '0',
        'password_expiration_attribute' => 'shadowExpire',
    ];

    // The Posix uid and gid of the 'nobody' account and 'nogroup' group.
    const UID_NOBODY = -2;
    const GID_NOGROUP = -2;

    /**
     * Creates a new user on LDAP.
     *
     * @param array $newuser
     * @param string $hashedpassword
     * @throw ldap_command_error
     */
    protected function _create(array $newuser, $hashedpassword): void {
        if (empty($this->config->user_create_context)) {
            throw new configuration_error('user_create_context');
        }

        // posixAccount object class forces us to specify a uidNumber
        // and a gidNumber. That is quite complicated to generate from
        // Moodle without colliding with existing numbers and without
        // race conditions. As this user is supposed to be only used
        // with Moodle (otherwise the user would exist beforehand) and
        // doesn't need to login into a operating system, we assign the
        // user the uid of user 'nobody' and gid of group 'nogroup'. In
        // addition to that, we need to specify a home directory. We
        // use the root directory ('/') as the home directory, as this
        // is the only one can always be sure exists. Finally, even if
        // it's not mandatory, we specify '/bin/false' as the login
        // shell, to prevent the user from login in at the operating
        // system level (Moodle ignores this).

        $newuser['objectClass']   = ['posixAccount', 'inetOrgPerson', 'organizationalPerson', 'person', 'top'];
        $newuser['cn']            = $this->username;
        $newuser['uid']           = $this->username;
        $newuser['uidNumber']     = self::UID_NOBODY;
        $newuser['gidNumber']     = self::GID_NOGROUP;
        $newuser['homeDirectory'] = '/';
        $newuser['loginShell']    = '/bin/false';

        // IMPORTANT:
        // We have to create the account locked, but posixAccount has
        // no attribute to achive this reliably. So we are going to
        // modify the password in a reversable way that we can later
        // revert in user_activate().
        //
        // Beware that this can be defeated by the user if we are not
        // using MD5 or SHA-1 passwords. After all, the source code of
        // Moodle is available, and the user can see the kind of
        // modification we are doing and 'undo' it by hand (but only
        // if we are using plain text passwords).
        //
        // Also bear in mind that you need to use a binding user that
        // can create accounts and has read/write privileges on the
        // 'userPassword' attribute for this to work.

        $newuser['userPassword']  = '*'.$hashedpassword;

        $this->client->add(
            $this->config->user_attribute.'='.$this->client->add_slashes($this->username).','.$this->config->user_create_context,
            $newuser
        );
    }

    /**
     * Returns Unix timestamp from password_expiration_attribute value
     *
     * @param ?array $info optional info array
     * @param string $expiry
     * @return int
     */
    protected function _password_expiry_timestamp($expiry, $info = []): int {
        return (int) $expiry * DAYSECS; // The shadowExpire contains the number of DAYS between 01/01/1970 and the actual expiration date
    }

    /**
     * Activates (enables) user in LDAP so user can login
     *
     * @throw ldap_command_error
     */
    public function activate(): void {
        // Remember that we add a '*' character in front of the
        // external password string to 'disable' the account. We just
        // need to remove it.
        $password = $this->attribute('userPassword');
        $this->modify(['userPassword' => ltrim($password, '*')]);
    }

    /**
     * Changes user password in LDAP
     *
     * @param  string  $hashedpassword
     * @throw ldap_command_error
     */
    protected function _update_password($hashedpassword): void {
        $this->modify(['userPassword' => $hashedpassword]);
    }
}
