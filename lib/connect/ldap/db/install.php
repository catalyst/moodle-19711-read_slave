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
 * External LDAP server support install
 *
 * @package    connect_ldap
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * External LDAP server support install
 *
 * @return bool
 */
function xmldb_connect_ldap_install(): bool {
    global $CFG, $DB;

    $config = get_config('auth_ldap');
    if (!empty($config->host_url)) {
        set_config('encoding', 'connect_ldap', $config->ldapencoding);
        set_config('user_contexts', 'connect_ldap', $config->contexts);
        set_config('user_create_context', 'connect_ldap', $config->create_context);
        set_config('user_search_sub', 'connect_ldap', $config->search_sub);
        set_config('user_objectclass', 'connect_ldap', $config->objectclass);
        set_config('password_expiration_attribute', 'connect_ldap', $config->expireattr);
        set_config('member_attribute', 'connect_ldap', $config->memberattribute);
        set_config('member_attribute_isdn', 'connect_ldap', $config->memberattribute_isdn);
        foreach ([
            'host_url',
            'ldap_version',
            'start_tls',
            'pagesize',
            'bind_dn',
            'bind_pw',
            'user_type',
            'opt_deref',
            'user_attribute',
            'passtype',
            'suspended_attribute',
        ] as $c) {
            set_config($c, 'connect_ldap', $config->$c);
        }

        return true;
    }

    $config = get_config('enrol_ldap');
    if (!empty($config->host_url)) {
        set_config('encoding', 'connect_ldap', $config->ldapencoding);
        set_config('member_attribute', 'connect_ldap', $config->group_memberofattribute);
        set_config('member_attribute_isdn', 'connect_ldap', $config->memberattribute_isdn);
        set_config('user_attribute', 'connect_ldap', $config->idnumber_attribute);
        foreach ([
            'host_url',
            'ldap_version',
            'start_tls',
            'pagesize',
            'bind_dn',
            'bind_pw',
            'user_contexts',
            'user_search_sub',
            'user_type',
            'opt_deref',
        ] as $c) {
            set_config($c, 'connect_ldap', $config->$c);
        }

        return true;
    }

    return true;
}
