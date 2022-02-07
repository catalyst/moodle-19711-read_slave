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
 * Strings for component 'connect_ldap', language 'en'.
 *
 * @package   connect_ldap
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Settings.
$string['ldap_noextension'] = 'The PHP LDAP module does not seem to be present. Please ensure it is installed and enabled if you want to use this plugin.';
$string['description'] = 'This plugin provides external LDAP server connection capability.';
$string['server_settings'] = 'LDAP server settings';
$string['host_url'] = 'Specify LDAP host in URL-form like \'ldap://ldap.myorg.com/\' or \'ldaps://ldap.myorg.com/\'. Separate multiple servers with \';\' to get failover support.';
$string['host_url_key'] = 'Host URL';
$string['ldap_version'] = 'The version of the LDAP protocol your server is using.';
$string['ldap_version_key'] = 'Version';
$string['start_tls'] = 'Use regular LDAP service (port 389) with TLS encryption';
$string['start_tls_key'] = 'Use TLS';
$string['encoding'] = 'Encoding used by the LDAP server, most likely utf-8. If LDAP v2 is selected, Active Directory uses its configured encoding, such as cp1252 or cp1250.';
$string['encoding_key'] = 'LDAP encoding';
$string['pagesize'] = 'Make sure this value is smaller than your LDAP server result set size limit (the maximum number of entries that can be returned in a single query)';
$string['pagesize_key'] = 'Page size';
$string['bind_settings'] = 'Bind settings';
$string['bind_dn'] = 'If you want to use bind-user to search users, specify it here. Something like \'cn=ldapuser,ou=public,o=org\'';
$string['bind_dn_key'] = 'Distinguished name';
$string['bind_pw'] = 'Password for bind-user.';
$string['bind_pw_key'] = 'Password';
$string['user_settings'] = 'User lookup settings';
$string['user_type'] = 'Select how users are stored in LDAP. This setting also specifies how login expiry, grace logins and user creation will work.';
$string['user_type_key'] = 'User type';
$string['user_contexts'] = 'List of contexts where users are located. Separate different contexts with \';\'. For example: \'ou=users,o=org; ou=others,o=org\'';
$string['user_contexts_key'] = 'Contexts';
$string['user_create_context'] = 'If you enable user creation with email confirmation, specify the context where users are created. This context should be different from other users to prevent security issues. You don\'t need to add this context to ldap_context-variable, Moodle will search for users from this context automatically.<br /><b>Note!</b> You have to modify the method user_create() in file auth/ldap/auth.php to make user creation work';
$string['user_create_context_key'] = 'Context for new users';
$string['user_search_sub'] = 'Search users from subcontexts.';
$string['user_search_sub_key'] = 'Search subcontexts';
$string['opt_deref'] = 'Determines how aliases are handled during search. Select one of the following values: "No" (LDAP_DEREF_NEVER) or "Yes" (LDAP_DEREF_ALWAYS)';
$string['opt_deref_key'] = 'Dereference aliases';
$string['user_attribute'] = 'Optional: Overrides the attribute used to name/search users. Usually \'cn\'.';
$string['user_attribute_key'] = 'User attribute';
$string['passtype'] = 'Specify the format of new or changed passwords in LDAP server.';
$string['passtype_key'] = 'Password format';
$string['password_expiration_attribute'] = 'Optional: Overrides the LDAP attribute that stores password expiry time.';
$string['password_expiration_attribute_key'] = 'Expiry attribute';
$string['suspended_attribute'] = 'Optional: When provided this attribute will be used to enable/suspend the locally created user account.';
$string['suspended_attribute_key'] = 'Suspended attribute';
$string['member_attribute'] = 'Optional: Overrides user member attribute, when users belongs to a group. Usually \'member\'';
$string['member_attribute_key'] = 'Member attribute';
$string['member_attribute_isdn'] = 'Overrides handling of member attribute values';
$string['member_attribute_isdn_key'] = 'Member attribute uses dn';
$string['user_objectclass'] = 'Optional: Overrides objectClass used to name/search users on user_type. Usually you don\'t need to change this.';
$string['user_objectclass_key'] = 'User object class';

// Client.
$string['executeerror'] = 'LDAP function execute error: {$a}';
$string['configerror'] = 'LDAP {a} configuration error';
$string['usernotfound'] = 'User {a} not found in LDAP';
$string['noattrval'] = 'Attribute {a} has no value in LDAP';
$string['needmbstring'] = 'You need the mbstring extension to change passwords in Active Directory';
$string['needbcmath'] = 'You need the BCMath extension to use expired password checking with Active Directory.';
$string['explodegroupusertypenotsupported'] = 'Group explode not supported for user type {a}';

// Other
$string['privacy:metadata'] = 'The LDAP server connect plugin does not store any personal data.';
