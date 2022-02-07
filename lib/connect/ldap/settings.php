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
 * Admin settings and defaults.
 *
 * @package connect_ldap
 * @copyright  2017 Stephen Bourget
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {

    if (!function_exists('ldap_connect')) {
        $notify = new \core\output\notification(new lang_string('ldap_noextension', 'connect_ldap'),
            \core\output\notification::NOTIFY_WARNING);
        $settings->add(new admin_setting_heading('ldap_noextension', '', $OUTPUT->render($notify)));
    } else {

        // Introductory explanation.
        $settings->add(new admin_setting_heading('connect_ldap/pluginname', '',
                new lang_string('description', 'connect_ldap')));

        // LDAP server settings.
        $settings->add(new admin_setting_heading('connect_ldap/serversettings',
                new lang_string('server_settings', 'connect_ldap'), ''));

        // Missing: root_ds

        // Host.
        $settings->add(new admin_setting_configtext('connect_ldap/host_url',
                new lang_string('host_url_key', 'connect_ldap'),
                new lang_string('host_url', 'connect_ldap'), '', PARAM_RAW_TRIMMED));

        // Version.
        $versions = [];
        $versions[2] = '2';
        $versions[3] = '3';
        $settings->add(new admin_setting_configselect('connect_ldap/ldap_version',
                new lang_string('ldap_version_key', 'connect_ldap'),
                new lang_string('ldap_version', 'connect_ldap'), 3, $versions));

        // Start TLS.
        $yesno = array(
            new lang_string('no'),
            new lang_string('yes'),
        );
        $settings->add(new admin_setting_configselect('connect_ldap/start_tls',
                new lang_string('start_tls_key', 'connect_ldap'),
                new lang_string('start_tls', 'connect_ldap'), 0 , $yesno));


        // Encoding.
        $settings->add(new admin_setting_configtext('connect_ldap/encoding',
                new lang_string('encoding_key', 'connect_ldap'),
                new lang_string('encoding', 'connect_ldap'), 'utf-8', PARAM_RAW_TRIMMED));

        // Page Size. (Hide if not available).
        $settings->add(new admin_setting_configtext('connect_ldap/pagesize',
                new lang_string('pagesize_key', 'connect_ldap'),
                new lang_string('pagesize', 'connect_ldap'), '250', PARAM_INT));

        // Bind settings.
        $settings->add(new admin_setting_heading('connect_ldap/bindsettings',
                new lang_string('bind_settings', 'connect_ldap'), ''));

        // User ID.
        $settings->add(new admin_setting_configtext('connect_ldap/bind_dn',
                new lang_string('bind_dn_key', 'connect_ldap'),
                new lang_string('bind_dn', 'connect_ldap'), '', PARAM_RAW_TRIMMED));

        // Password.
        $settings->add(new admin_setting_configpasswordunmask('connect_ldap/bind_pw',
                new lang_string('bind_pw_key', 'connect_ldap'),
                new lang_string('bind_pw', 'connect_ldap'), ''));

        // User Lookup settings.
        $settings->add(new admin_setting_heading('connect_ldap/userlookup',
                new lang_string('user_settings', 'connect_ldap'), ''));

        // User Type.
        $settings->add(new admin_setting_configselect('connect_ldap/user_type',
                new lang_string('user_type_key', 'connect_ldap'),
                new lang_string('user_type', 'connect_ldap'), 'default', connect_ldap\users::USER_TYPE));

        // Contexts.
        $settings->add(new admin_setting_configtext('connect_ldap/user_contexts',
                new lang_string('user_contexts_key', 'connect_ldap'),
                new lang_string('user_contexts', 'connect_ldap'), '', PARAM_RAW_TRIMMED));

        // Context for new users.
        $settings->add(new admin_setting_configtext('connect_ldap/user_create_context',
                new lang_string('user_create_context_key', 'connect_ldap'),
                new lang_string('user_create_context', 'connect_ldap'), '', PARAM_RAW_TRIMMED));

        // Search subcontexts.
        $settings->add(new admin_setting_configselect('connect_ldap/user_search_sub',
                new lang_string('user_search_sub_key', 'connect_ldap'),
                new lang_string('user_search_sub', 'connect_ldap'), 0 , $yesno));

        // Dereference aliases.
        $optderef = [];
        $optderef[LDAP_DEREF_NEVER] = new lang_string('no');
        $optderef[LDAP_DEREF_ALWAYS] = new lang_string('yes');

        $settings->add(new admin_setting_configselect('connect_ldap/opt_deref',
                new lang_string('opt_deref_key', 'connect_ldap'),
                new lang_string('opt_deref', 'connect_ldap'), LDAP_DEREF_NEVER , $optderef));

        // User attribute.
        $settings->add(new admin_setting_configtext('connect_ldap/user_attribute',
                new lang_string('user_attribute_key', 'connect_ldap'),
                new lang_string('user_attribute', 'connect_ldap'), '', PARAM_RAW));

        // Password Type.
        $passtype = array();
        $passtype['plaintext'] = get_string('plaintext', 'auth');
        $passtype['md5']       = get_string('md5', 'auth');
        $passtype['sha1']      = get_string('sha1', 'auth');

        $settings->add(new admin_setting_configselect('connect_ldap/passtype',
                new lang_string('passtype_key', 'connect_ldap'),
                new lang_string('passtype', 'connect_ldap'), 'plaintext', $passtype));

        // Password Expiration attribute.
        $settings->add(new admin_setting_configtext('connect_ldap/password_expiration_attribute',
                new lang_string('password_expiration_attribute_key', 'connect_ldap'),
                new lang_string('password_expiration_attribute', 'connect_ldap'), '', PARAM_RAW));

        // Suspended attribute.
        $settings->add(new admin_setting_configtext('connect_ldap/suspended_attribute',
                new lang_string('suspended_attribute_key', 'connect_ldap'),
                new lang_string('suspended_attribute', 'connect_ldap'), '', PARAM_RAW));

        // Member attribute.
        $settings->add(new admin_setting_configtext('connect_ldap/member_attribute',
                new lang_string('member_attribute_key', 'connect_ldap'),
                new lang_string('member_attribute', 'connect_ldap'), '', PARAM_RAW));

        // Member attribute uses dn.
        $settings->add(new admin_setting_configselect('connect_ldap/member_attribute_isdn',
                new lang_string('member_attribute_isdn_key', 'connect_ldap'),
                new lang_string('member_attribute_isdn', 'connect_ldap'), 0, $yesno));

        // User object class.
        $settings->add(new admin_setting_configtext('connect_ldap/user_objectclass',
                new lang_string('user_objectclass_key', 'connect_ldap'),
                new lang_string('user_objectclass', 'connect_ldap'), '', PARAM_RAW_TRIMMED));
    }
}
