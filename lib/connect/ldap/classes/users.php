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
 * LDAP users
 *
 * @package    connect_ldap
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace connect_ldap;

use stdClass;

use core_text;
use connect_ldap\exception\error,
    connect_ldap\exception\ldap_command_error;

defined('MOODLE_INTERNAL') || die();

/**
 * LDAP users
 *
 *
 * @package    connect_ldap
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class users {
    const USER_TYPE = [
        'ad' => 'MS ActiveDirectory',
        'edir' => 'Novell Edirectory',
        'rfc2307' => 'posixAccount (rfc2307)',
        'rfc2307bis' => 'posixAccount (rfc2307bis)',
        'samba' => 'sambaSamAccount (v.3.0.7)',
    ];

    /** @var client */
    protected $client;

    /** @var stdClass */
    protected $config;

    /** @var string */
    protected $usercls;

    /**
     * Tries to instantiate class with the configured client
     *
     * @return users
     */
    public static function from_config(): users {
        return new static(client::from_config());
    }

    /**
     * Constructor
     *
     * @param client $client LDAP client instance
     * @throw error
     */
    public function __construct(client $client) {
        $this->client = $client;
        $this->config = $client->config;

        $this->usercls = "connect_ldap\\user\\".$this->config->user_type;
        if (!class_exists($this->usercls)) {
            throw new error('unsupportedusertype', '', $this->config->user_type_name);
        }
        $this->config->user_type_name = self::USER_TYPE[$this->config->user_type];
        foreach ($this->usercls::DEFAULTS as $key => $val) {
            // watch out - 0, false are correct values too
            if (!isset($this->config->$key) or $this->config->$key == '') {
                $this->config->$key = $val;
            }
        }
        $this->config->user_objectclass = client::normalise_objectclass($this->config->user_objectclass);

        $this->groupcls = "connect_ldap\\group_".$this->config->user_type;
        if (!class_exists($this->groupcls)) {
            $this->groupcls = "connect_ldap\\group";
        }

        if (empty($this->config->user_search_sub)) {
            $this->config->user_search_sub = false;
        }
    }

    /**
     * Execute callback($username) for each user
     *
     * @param callable $callback callback($username) to be executed for each listed user
     * @param ?string  $filter   additional filter to apply, on top of the standatd users
     * @throw ldap_command_error
     */
    public function for_each(callable $cb, $filter = ''): void {
        $filter = '(&('.$this->config->user_attribute.'=*)'.$this->config->user_objectclass.')' . $filter;
        $userattr = strtolower($this->config->user_attribute);

        // Get all contexts and look for first matching user
        foreach (explode(';', $this->config->user_contexts) as $context) {
            $context = trim($context);
            $this->client->for_each(
                $context,
                function ($u) use ($cb, $userattr) {
                    foreach ($u as $key => $val) {
                        if (strtolower($key) == $userattr) {
                            return $cb(core_text::convert($val[0], $this->config->encoding, 'utf-8'));
                        }
                    }
                    throw new error('attributenotfound', $this->config->user_attribute, $u);
                },
                $filter,
                [$this->config->user_attribute],
                $this->config->user_search_sub
            );
        }
    }

    /**
     * Returns all usernames from LDAP
     *
     * @return array of LDAP user names core_text::converted to UTF-8
     * @throw ldap_command_error
     */
    public function list(): array {
        // Get all contexts and look for first matching user
        $result = [];
        $this->for_each(function ($username) use (&$result) {
            $result[] = $username;
        });
        return $result;
    }

    /**
     * Returns user unstance of appropriate type for username
     *
     * param string $username The username (without system magic quotes)
     * @return user\base
     */
    public function user($username): user\base {
        return new $this->usercls($username, $this->client);
    }

    /**
     * Returns group unstance of appropriate type for groupname
     *
     * param string $groupname The group dn
     * @return group
     */
    public function group($groupdn): group {
        return new $this->groupcls($groupdn, $this->client);
    }

    /**
     * Transforms DNs to usernames if needed
     *
     * Deal with the case where the attribute holds distinguished names,
     * but only if the user attribute is not a distinguished name itself.
     *
     * param array $names
     * @return array
     */
    public function get_uids(array $names): array {
        if (!$this->config->member_attribute_isdn) {
            return $names;
        }
        $lattr = core_text::strtolower($this->config->user_attribute);
        if (($lattr == 'dn') || ($lattr == 'distinguishedname')) {
            return $names;
        }

        // We need to retrieve the idnumber for all the users in $ldapmembers,
        // as the idnumber does not match their dn and we get dn's from membership.
        $memberidnumbers = [];
        foreach ($names as $dn) {
            $entry = array_change_key_case(
                $this->client->find($dn, $this->config->user_objectclass, [$this->config->user_attribute])
            );
            $values = $entry[$lattr];
            $memberidnumbers[] = $values[0];
        }

        return $memberidnumbers;
    }
}

/**
 * LDAP group
 *
 *
 * @package    connect_ldap
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class group {

    /** @var string */
    protected $dn;

    /** @var client */
    protected $client;

    /**
     * Constructor
     *
     * @param string $group group name
     * @param client $client LDAP client instance
     * @throw error
     */
    public function __construct($dn, client $client) {
        $this->dn = $dn;
        $this->client = $client;
        $this->config = $client->config;
    }

    /**
     * Get the list of users belonging to this group.
     *
     * @param string $memberattibute the attribute that holds the members of the group
     * @return array the list of users belonging to the group. If $group
     *         is not actually a group, returns array($group).
     * @throw ldap_command_error
     */
    public function explode($memberattribute): array {
        debugging(
            get_string('explodegroupusertypenotsupported', 'connect_ldap', $this->config->user_type_name),
            DEBUG_NORMAL
        );

        return [$this->dn];
    }
}

/**
 * LDAP group for Active Directory
 *
 *
 * @package    connect_ldap
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class group_ad extends group {
    /**
     * Get the list of users belonging to this group. If the group has nested groups, expand all
     * the intermediate groups and return the full list of users that
     * directly or indirectly belong to the group.
     *
     * @param string $memberattibute the attribute that holds the members of the group
     * @return array the list of users belonging to the group. If $group
     *         is not actually a group, returns array($group).
     * @throw ldap_command_error
     */
    public function explode($memberattribute): array {
        // $group is already the distinguished name to search.
        return $this->_explode($this->dn, $memberattribute);
    }

    /**
     * Given a group name (either a RDN or a DN), get the list of users
     * belonging to that group. If the group has nested groups, expand all
     * the intermediate groups and return the full list of users that
     * directly or indirectly belong to the group.
     *
     * @param string $dn
     * @param string $memberattibute the attribute that holds the members of the group
     * @return array the list of users belonging to the group. If $group
     *         is not actually a group, returns array($group).
     * @throw ldap_command_error
     */
    private function _explode($dn, $memberattribute): array {
        if ($entries = $this->client->read($dn, '(objectClass=*)', ['objectClass', $memberattribute])) {
            $users = [];
            $attr = core_text::strtolower($memberattribute);
            foreach ($entries as $entry) {
                $objectclasses = $entry['objectClass'];
                if (!in_array('group', $objectclasses)) {
                    // Not a group, so return immediately.
                    $users[] = $dn;
                } else {
                    foreach ($entry as $key => $vals) {
                        if (core_text::strtolower($key) == $attr) {
                            foreach ($vals as $val) {
                                $users = array_merge($users, $this->_explode($val, $memberattribute));
                            }
                        }
                    }
                }
            }
            return $users;
        }

        return [$dn];
    }
}
