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
 * LDAP user base class
 *
 * @package    connect_ldap
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace connect_ldap\user;

use stdClass;

use core_text;
use connect_ldap\client;
use connect_ldap\exception\configuration_error,
    connect_ldap\exception\no_attribute_value_error,
    connect_ldap\exception\user_not_found_error,
    connect_ldap\exception\ldap_command_error;

defined('MOODLE_INTERNAL') || die();

/**
 * LDAP user base class
 *
 *
 * @package    connect_ldap
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {
    /**
     * Initializes needed variables for pecific user type.
     *
     * All the values have to be written in lowercase, even if the
     * standard LDAP attributes are mixed-case.
     */
    const DEFAULTS = [
        'user_objectclass' => null,
        'user_attribute' => null,
        'member_attribute' => null,
        'member_attribute_isdn' => null,
        'password_expiration_attribute' => null,
    ];

    /** @var client */
    protected $client;

    /** @var stdClass */
    protected $config;

    /** @var string */
    protected $username;

    /** @var string */
    protected $_dn;

    /**
     * Constructor
     *
     * @param string $username The username (without system magic quotes)
     * @param client $client LDAP client instance
     */
    public function __construct($username, client $client) {
        $this->client = $client;
        $this->config = $client->config;
        if (empty($this->config->user_create_context)) {
            if (!empty($this->config->user_contexts)) {
                $contexts = explode(';', $this->config->user_contexts);
                if (count($contexts) == 1) {
                    $this->config->user_create_context = $contexts[0];
                }
            }
        }

        $this->username = core_text::strtolower(core_text::convert($username, 'utf-8', $this->config->encoding));
    }

    /**
     * Search specified contexts for username and return the user dn like:
     * cn=username,ou=suborg,o=org
     *
     * @return string
     * @throw user_not_found_error
     * @throw ldap_command_error
     */
    public function dn(): string {
        if ($this->_dn) {
            return $this->_dn;
        }

        $filter = '(&'.$this->config->user_objectclass.'('.$this->config->user_attribute.'='.$this->client->filter_add_slashes($this->username).'))';

        // Get all contexts and look for first matching user
        foreach (explode(';', $this->config->user_contexts) as $context) {
            $context = trim($context);
            if ($this->_dn = $this->client->get_dn($context, $filter, $this->config->user_attribute, $this->config->user_search_sub)) {
                return $this->_dn;
            }
        }

        throw new user_not_found_error($this->username);
    }

    /**
     * Returns escaped username
     *
     * @return string
     */
    public function username_with_slashes(): string {
        return client::filter_add_slashes($this->username);
    }

    /**
     * Return true if user exists
     *
     * @return bool
     * @throw ldap_command_error
     */
    public function exists(): bool {
        try {
            return (bool) $this->dn();
        } catch (user_not_found_error $e) {
            return false;
        }
    }

    /**
     * Modify user
     *
     * @param array  $entry modifications
     * @throw ldap_command_error
     *
     */
    public function modify($entry): void {
        $this->client->modify($this->dn(), $entry);
    }

    /**
     * Checks if user belongs to specific group(s) or is in a subtree.
     *
     * Returns true if user belongs to a group in grupdns string OR if the
     * DN of the user is in a subtree of the DN provided as "group"
     *
     * @param array $groupdns arrary of group dn
     * @return bool
     * @throw configuration_error
     * @throw ldap_command_error
     */
    public function is_group_member($groupdns): bool {
        if (empty($this->config->member_attribute)) {
            throw new configuration_error('member_attribute');
        }

        $userid = $this->config->member_attribute_isdn ? $this->dn() : $this->username;
        $filter = '('.$this->config->member_attribute.'='.$this->client->filter_add_slashes($this->username).')';

        foreach ($groupdns as $group) {
            // Check cheaply if the user's DN sits in a subtree of the
            // "group" DN provided. Granted, this isn't a proper LDAP
            // group, but it's a popular usage.
            if (stripos(strrev($this->username), strrev(core_text::strtolower($group))) === 0) {
                return true;
            }

            if ($this->client->find($group, $filter, [$this->config->member_attribute])) {
                return true;  // User is a member of the group.
            }
        }

        return false;
    }

    /**
     * Find the groups a user belongs to, both directly
     * and indirectly via nested groups membership.
     *
     * @return array with member groups' distinguished names (can be emtpy)
     * @throw ldap_command_error
     */
    public function groups(): array {
        $userid = $this->config->member_attribute_isdn ? $this->dn() : $this->username;
        $groups = [];
        $this->_groups($userid, $this->config->member_attribute, $groups);
        return $groups;
    }

    /**
     * Recursively process the groups the given member distinguished name
     * belongs to, adding them to the already processed groups array.
     *
     * @param string $dn distinguished name to search
     * @param array reference $groups
     * @throw ldap_command_error
     */
    private function _groups($dn, array &$groups): array {
        if ($entry = $this->client->find($dn, '(objectClass=*)', [$groupattr])) {
            $attr = core_text::strtolower($this->config->member_attribute);
            foreach ($entry as $key => $vals) {
                if (core_text::strtolower($key) == $attr) {
                    foreach ($vals as $val) {
                        if(!in_array($val, $groups)) {
                            // Only push and recurse if we haven't 'seen' this group before
                            // to prevent loops (MS Active Directory allows them!!).
                            $groups[] = $val;
                            $this->_groups($val, $this->config->member_attribute, $groups);
                        }
                    }
                }
            }
        }
    }

    /**
     * Returns true if the username and password work and false if they are
     * wrong or don't exist.
     *
     * @param string $password   The password (without system magic quotes)
     * @param ?bool  $expiredok  Consider OK login with expired password (if we can detect it)
     * @return bool Authentication success or failure.
     * @throw ldap_command_error
     */
    public function login($password, $expiredok = false): bool {
        try {
            $dn = $this->dn();
        } catch (ldap_command_error $e) {
            // If no dn, user does not exist
            return false;
        }

        // Try to bind with current username and password
        try {
            $this->client->bind($dn, $password);
            return true;
        } catch (ldap_command_error $e) {
            return false;
        } finally {
            // Rebind.
            $this->client->bind_admin();
        }
    }

    /**
     * Reads user information from LDAP and returns it as ['attr' => []].
     *
     * @param array $attributes required attributes
     * @return array with no magic quotes
     * @throw user_not_found_error
     * @throw ldap_command_error
     */
    public function info_array($attributes): array {
        $dn = $this->dn();

        foreach ([
            'user_attribute',
            'suspended_attribute',
            'password_expiration_attribute',
        ] as $attr) {
            if (!empty($this->config->$attr) and !in_array($this->config->$attr, $attributes)) {
                $attributes[] = $this->config->$attr;
            }
        }

        $entry = $this->client->find_any($dn, $attributes);
        if (!$entry) {
            throw new user_not_found_error($this->username);
        }

        $result = [];
        foreach ($attributes as $attr) {
            $lattr = core_text::strtolower($attr);
            if (($lattr == 'dn') || ($lattr == 'distinguishedname')) {
                $result[$attr] = $dn;
                continue;
            }

            foreach ($entry as $key => $val) {
                if (core_text::strtolower($key) == $lattr) {
                    if (is_array($val)) {
                        $val = array_map(function ($v) {
                            return core_text::convert($v, $this->config->encoding, 'utf-8');
                        }, $val);
                    } else {
                        $val = [core_text::convert($val, $this->config->encoding, 'utf-8')];
                    }
                    $result[$attr] = $val;
                }
            }
        }
        return $result;
    }

    /**
     * Reads user information from ldap and returns it as ['attr' => 'val']
     *
     * Function should return all information available. If you are saving
     * this information to moodle user-table you should honor syncronization flags
     *
     * @param array $attributes required attributes
     * @return array with no magic quotes
     * @throw user_not_found_error
     * @throw ldap_command_error
     */
    public function info($attributes): array {
        return array_map(function ($v) {
            return array_shift($v);
        }, $this->info_array($attributes));
    }

    /**
     * Returns attribute value
     *
     * @param string $attribute required attribute
     * @return mixed with no magic quotes
     * @throw user_not_found_error
     * @throw ldap_command_error
     */
    function attribute($attribute): mixed {
        if ($info = $this->info([$attribute])) {
            $attr = core_text::strtolower($attribute);
            foreach ($info as $key => $val) {
                if (core_text::strtolower($key) == $attr) {
                    return $val;
                }
            }
        }
        return null;
    }

    /**
     * Check if a user is suspended. This function is intended to be used after calling
     * get_userinfo_asobj. This is needed because LDAP doesn't have a notion of disabled
     * users.
     *
     * @param ?array $info optional info array
     * @return bool
     */
    public function is_suspended($info = []): bool {
        if (empty($this->config->suspended_attribute)) {
            return false;
        }

        $attr = core_text::strtolower($this->config->suspended_attribute);
        foreach ($info as $key => $val) {
            if (core_text::strtolower($key) == $attr) {
                return (bool) $val;
            }
        }

        return (bool) $this->attribute($this->config->suspended_attribute);
    }

    /**
     * Encode password
     *
     * @param string $password   Plaintext password
     * @return string encoded password
     */
    public function encode_password($password): string {
        $extpassword = core_text::convert($password, 'utf-8', $this->config->encoding);

        switch ($this->config->passtype) {
            case 'md5':
                $extpassword = '{MD5}' . base64_encode(pack('H*', md5($extpassword)));
                break;
            case 'sha1':
                $extpassword = '{SHA}' . base64_encode(pack('H*', sha1($extpassword)));
                break;
            case 'plaintext':
            default:
                break; // plaintext
        }

        return $extpassword;
    }

    /**
     * Creates a new user on LDAP.
     * By using information in userobject
     * Use user_exists to prevent duplicate usernames
     *
     * @param array $attributes required attributes
     * @param string $password   Plaintext password
     * @throw ldap_command_error
     */
    public function create($attributes, $password): void {
        $extpassword = $this->encode_password($password);

        $newuser = [];
        foreach ($attributes as $key => $value) {
            $newuser[$key] = is_array($value)
                ? array_map(function ($v) {
                    return core_text::convert($v, 'utf-8', $this->config->encoding);
                }, $value)
                : core_text::convert($value, 'utf-8', $this->config->encoding);
        }

        //Following sets all mandatory and other forced attribute values
        //User should be creted as login disabled untill email confirmation is processed
        //Feel free to add your user type and send patches to paca@sci.fi to add them
        //Moodle distribution

        $this->_create($newuser, $extpassword);
    }

    /**
     * Creates a new user on LDAP.
     *
     * @param array  $newuser
     * @param string $hashedpassword
     * @throw ldap_command_error
     */
    abstract protected function _create(array $newuser, $hashedpassword): void;

    /**
     * Return number of days to user password expires
     *
     * If userpassword does not expire it should return 0. If password is already expired
     * it should return negative value.
     *
     * @param ?array $info optional info array
     * @return integer
     * @throw configuration_error
     * @throw no_attribute_value_error
     * @throw ldap_command_error
     */
    public function password_expire($info = []): int {
        if (empty($this->config->password_expiration_attribute)) {
            throw new configuration_error('password_expiration_attribute');
        }

        $attr = core_text::strtolower($this->config->password_expiration_attribute);
        foreach ($info as $key => $val) {
            if (core_text::strtolower($key) == $attr) {
                $expiry = (string) $val;
            }
        }
        if (empty($expiry)) {
            $expiry = (string) $this->attribute($this->config->password_expiration_attribute);
        }
        if ($expiry === null || $expiry === '') {
            throw new no_attribute_value_error($this->config->password_expiration_attribute, $this->dn());
        }

        $expiretime = $this->_password_expiry_timestamp($expiry, $info);
        if ($expiretime > 0) {
            $now = time();
            $days = ($expiretime - $now) / DAYSECS;
            return $expiretime > $now ? ceil($days) : floor($days);
        }

        return $expiretime;
    }

    /**
     * Returns Unix timestamp from password_expiration_attribute value
     *
     * @param ?array $info optional info array
     * @param mixed $expiry
     * @return int
     */
    abstract protected function _password_expiry_timestamp($expiry, $info = []): int;

    /**
     * Activates (enables) user in LDAP so user can login
     *
     * @throw ldap_command_error
     */
    abstract public function activate(): void;

    /**
     * Update user attributes in LDAP
     *
     * @param array $attributes required attributes
     * @throw ldap_command_error
     */
    public function update($attributes): void {
        $update = [];
        foreach ($attributes as $key => $value) {
            $update[$key] = core_text::convert($value, 'utf-8', $this->config->encoding);
        }
        $this->client->modify($this->dn(), $update);
    }

    /**
     * Changes user password in LDAP
     *
     * @param  string  $newpassword Plaintext password (not crypted/md5'ed)
     * @throw ldap_command_error
     */
    public function update_password($newpassword): void {
        $extpassword = $this->encode_password($newpassword);
        $this->_update_password($extpassword);
    }

    /**
     * Changes user password in LDAP
     *
     * @param  string  $hashedpassword
     * @throw ldap_command_error
     */
    abstract protected function _update_password($hashedpassword): void;
}
