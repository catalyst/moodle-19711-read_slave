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
 * @package auth_ldap
 * @copyright  2017 Stephen Bourget
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {

    if (!function_exists('ldap_connect')) {
        $notify = new \core\output\notification(get_string('auth_ldap_noextension', 'auth_ldap'),
            \core\output\notification::NOTIFY_WARNING);
        $settings->add(new admin_setting_heading('auth_ldap_noextension', '', $OUTPUT->render($notify)));
    } else {

        // We use a couple of custom admin settings since we need to massage the data before it is inserted into the DB.
        require_once($CFG->dirroot.'/auth/ldap/classes/admin_setting_special_lowercase_configtext.php');
        require_once($CFG->dirroot.'/auth/ldap/classes/admin_setting_special_contexts_configtext.php');
        require_once($CFG->dirroot.'/auth/ldap/classes/admin_setting_special_ntlm_configtext.php');

        // We need to use some of the Moodle LDAP constants / functions to create the list of options.
        require_once($CFG->dirroot.'/auth/ldap/auth.php');

        // Introductory explanation.
        $settings->add(new admin_setting_heading('auth_ldap/pluginname', '',
                new lang_string('auth_ldapdescription', 'auth_ldap')));

        $yesno = array(
            new lang_string('no'),
            new lang_string('yes'),
        );

        // Force Password change Header.
        $settings->add(new admin_setting_heading('auth_ldap/ldapforcepasswordchange',
                new lang_string('forcechangepassword', 'auth'), ''));

        // Force Password change.
        $settings->add(new admin_setting_configselect('auth_ldap/forcechangepassword',
                new lang_string('forcechangepassword', 'auth'),
                new lang_string('forcechangepasswordfirst_help', 'auth'), 0 , $yesno));

        // Standard Password Change.
        $settings->add(new admin_setting_configselect('auth_ldap/stdchangepassword',
                new lang_string('stdchangepassword', 'auth'), new lang_string('stdchangepassword_expl', 'auth') .' '.
                get_string('stdchangepassword_explldap', 'auth'), 0 , $yesno));

        // Password change URL.
        $settings->add(new admin_setting_configtext('auth_ldap/changepasswordurl',
                get_string('auth_ldap_changepasswordurl_key', 'auth_ldap'),
                get_string('changepasswordhelp', 'auth'), '', PARAM_URL));

        // Password Expiration Header.
        $settings->add(new admin_setting_heading('auth_ldap/passwordexpire',
                new lang_string('auth_ldap_passwdexpire_settings', 'auth_ldap'), ''));

        // Password Expiration.

        // Create the description lang_string object.
        $strno = get_string('no');
        $strldapserver = get_string('pluginname', 'auth_ldap');
        $langobject = new stdClass();
        $langobject->no = $strno;
        $langobject->ldapserver = $strldapserver;
        $description = new lang_string('auth_ldap_expiration_desc', 'auth_ldap', $langobject);

        // Now create the options.
        $expiration = array();
        $expiration['0'] = $strno;
        $expiration['1'] = $strldapserver;

        // Add the setting.
        $settings->add(new admin_setting_configselect('auth_ldap/expiration',
                new lang_string('auth_ldap_expiration_key', 'auth_ldap'),
                $description, 0 , $expiration));

        // Password Expiration warning.
        $settings->add(new admin_setting_configtext('auth_ldap/expiration_warning',
                get_string('auth_ldap_expiration_warning_key', 'auth_ldap'),
                get_string('auth_ldap_expiration_warning_desc', 'auth_ldap'), '', PARAM_RAW));

        // Grace Logins.
        $settings->add(new admin_setting_configselect('auth_ldap/gracelogins',
                new lang_string('auth_ldap_gracelogins_key', 'auth_ldap'),
                new lang_string('auth_ldap_gracelogins_desc', 'auth_ldap'), 0 , $yesno));

        // Grace logins attribute.
        $settings->add(new auth_ldap_admin_setting_special_lowercase_configtext('auth_ldap/graceattr',
                get_string('auth_ldap_gracelogin_key', 'auth_ldap'),
                get_string('auth_ldap_graceattr_desc', 'auth_ldap'), '', PARAM_RAW));

        // User Creation.
        $settings->add(new admin_setting_heading('auth_ldap/usercreation',
                new lang_string('auth_user_create', 'auth'), ''));

        // Create users externally.
        $settings->add(new admin_setting_configselect('auth_ldap/auth_user_create',
                new lang_string('auth_ldap_auth_user_create_key', 'auth_ldap'),
                new lang_string('auth_user_creation', 'auth'), 0 , $yesno));

        // System roles mapping header.
        $settings->add(new admin_setting_heading('auth_ldap/systemrolemapping',
                                        new lang_string('systemrolemapping', 'auth_ldap'), ''));

        // Create system role mapping field for each assignable system role.
        $roles = get_ldap_assignable_role_names();
        foreach ($roles as $role) {
            // Before we can add this setting we need to check a few things.
            // A) It does not exceed 100 characters otherwise it will break the DB as the 'name' field
            //    in the 'config_plugins' table is a varchar(100).
            // B) The setting name does not contain hyphens. If it does then it will fail the check
            //    in parse_setting_name() and everything will explode. Role short names are validated
            //    against PARAM_ALPHANUMEXT which is similar to the regex used in parse_setting_name()
            //    except it also allows hyphens.
            // Instead of shortening the name and removing/replacing the hyphens we are showing a warning.
            // If we were to manipulate the setting name by removing the hyphens we may get conflicts, eg
            // 'thisisashortname' and 'this-is-a-short-name'. The same applies for shortening the setting name.
            if (core_text::strlen($role['settingname']) > 100 || !preg_match('/^[a-zA-Z0-9_]+$/', $role['settingname'])) {
                $url = new moodle_url('/admin/roles/define.php', array('action' => 'edit', 'roleid' => $role['id']));
                $a = (object)['rolename' => $role['localname'], 'shortname' => $role['shortname'], 'charlimit' => 93,
                    'link' => $url->out()];
                $settings->add(new admin_setting_heading('auth_ldap/role_not_mapped_' . sha1($role['settingname']), '',
                    get_string('cannotmaprole', 'auth_ldap', $a)));
            } else {
                $settings->add(new admin_setting_configtext('auth_ldap/' . $role['settingname'],
                    get_string('auth_ldap_rolecontext', 'auth_ldap', $role),
                    get_string('auth_ldap_rolecontext_help', 'auth_ldap', $role), '', PARAM_RAW_TRIMMED));
            }
        }

        // User Account Sync.
        $settings->add(new admin_setting_heading('auth_ldap/syncusers',
                new lang_string('auth_sync_script', 'auth'), ''));

        // Remove external user.
        $deleteopt = array();
        $deleteopt[AUTH_REMOVEUSER_KEEP] = get_string('auth_remove_keep', 'auth');
        $deleteopt[AUTH_REMOVEUSER_SUSPEND] = get_string('auth_remove_suspend', 'auth');
        $deleteopt[AUTH_REMOVEUSER_FULLDELETE] = get_string('auth_remove_delete', 'auth');

        $settings->add(new admin_setting_configselect('auth_ldap/removeuser',
                new lang_string('auth_remove_user_key', 'auth'),
                new lang_string('auth_remove_user', 'auth'), AUTH_REMOVEUSER_KEEP, $deleteopt));

        // Sync Suspension.
        $settings->add(new admin_setting_configselect('auth_ldap/sync_suspended',
                new lang_string('auth_sync_suspended_key', 'auth'),
                new lang_string('auth_sync_suspended', 'auth'), 0 , $yesno));

        // NTLM SSO Header.
        $settings->add(new admin_setting_heading('auth_ldap/ntlm',
                new lang_string('auth_ntlmsso', 'auth_ldap'), ''));

        // Enable NTLM.
        $settings->add(new admin_setting_configselect('auth_ldap/ntlmsso_enabled',
                new lang_string('auth_ntlmsso_enabled_key', 'auth_ldap'),
                new lang_string('auth_ntlmsso_enabled', 'auth_ldap'), 0 , $yesno));

        // Subnet.
        $settings->add(new admin_setting_configtext('auth_ldap/ntlmsso_subnet',
                get_string('auth_ntlmsso_subnet_key', 'auth_ldap'),
                get_string('auth_ntlmsso_subnet', 'auth_ldap'), '', PARAM_RAW_TRIMMED));

        // NTLM Fast Path.
        $fastpathoptions = array();
        $fastpathoptions[AUTH_NTLM_FASTPATH_YESFORM] = get_string('auth_ntlmsso_ie_fastpath_yesform', 'auth_ldap');
        $fastpathoptions[AUTH_NTLM_FASTPATH_YESATTEMPT] = get_string('auth_ntlmsso_ie_fastpath_yesattempt', 'auth_ldap');
        $fastpathoptions[AUTH_NTLM_FASTPATH_ATTEMPT] = get_string('auth_ntlmsso_ie_fastpath_attempt', 'auth_ldap');

        $settings->add(new admin_setting_configselect('auth_ldap/ntlmsso_ie_fastpath',
                new lang_string('auth_ntlmsso_ie_fastpath_key', 'auth_ldap'),
                new lang_string('auth_ntlmsso_ie_fastpath', 'auth_ldap'),
                AUTH_NTLM_FASTPATH_ATTEMPT, $fastpathoptions));

        // Authentication type.
        $types = array();
        $types['ntlm'] = 'NTLM';
        $types['kerberos'] = 'Kerberos';

        $settings->add(new admin_setting_configselect('auth_ldap/ntlmsso_type',
                new lang_string('auth_ntlmsso_type_key', 'auth_ldap'),
                new lang_string('auth_ntlmsso_type', 'auth_ldap'), 'ntlm', $types));

        // Remote Username format.
        $settings->add(new auth_ldap_admin_setting_special_ntlm_configtext('auth_ldap/ntlmsso_remoteuserformat',
                get_string('auth_ntlmsso_remoteuserformat_key', 'auth_ldap'),
                get_string('auth_ntlmsso_remoteuserformat', 'auth_ldap'), '', PARAM_RAW_TRIMMED));
    }

    // Display locking / mapping of profile fields.
    $authplugin = get_auth_plugin('ldap');
    $help  = get_string('auth_ldapextrafields', 'auth_ldap');
    $help .= get_string('auth_updatelocal_expl', 'auth');
    $help .= get_string('auth_fieldlock_expl', 'auth');
    $help .= get_string('auth_updateremote_expl', 'auth');
    $help .= '<hr />';
    $help .= get_string('auth_updateremote_ldap', 'auth');
    display_auth_lock_options($settings, $authplugin->authtype, $authplugin->userfields,
            $help, true, true, $authplugin->get_custom_user_profile_fields());
}
