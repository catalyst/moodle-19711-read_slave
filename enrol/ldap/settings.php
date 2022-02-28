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
 * LDAP enrolment plugin settings and presets.
 *
 * @package    enrol_ldap
 * @author     Iñaki Arenaza
 * @copyright  2010 Iñaki Arenaza <iarenaza@eps.mondragon.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    if (!function_exists('ldap_connect')) {
        $notify = new \core\output\notification(get_string('phpldap_noextension', 'enrol_ldap'),
            \core\output\notification::NOTIFY_WARNING);
        $settings->add(new admin_setting_heading('enrol_phpldap_noextension', '', $OUTPUT->render($notify)));
        $settings->add(new admin_setting_heading('enrol_ldap_settings', '', get_string('pluginname_desc', 'enrol_ldap')));
    } else {

        $settings->add(new admin_setting_heading('enrol_ldap_settings', '', get_string('pluginname_desc', 'enrol_ldap')));

        require_once($CFG->dirroot.'/enrol/ldap/settingslib.php');

        $yesno = array(get_string('no'), get_string('yes'));

        //--- role mapping settings ---
        $settings->add(new admin_setting_heading('enrol_ldap_roles', get_string('roles', 'enrol_ldap'), ''));
        if (!during_initial_install()) {
            $settings->add(new admin_setting_ldap_rolemapping('enrol_ldap/role_mapping', get_string ('role_mapping_key', 'enrol_ldap'), get_string ('role_mapping', 'enrol_ldap'), ''));
        }
        $options = $yesno;
        $settings->add(new admin_setting_configselect('enrol_ldap/course_search_sub', get_string('course_search_sub_key', 'enrol_ldap'), get_string('course_search_sub', 'enrol_ldap'), 0, $options));

        //--- course mapping settings ---
        $settings->add(new admin_setting_heading('enrol_ldap_course_settings', get_string('course_settings', 'enrol_ldap'), ''));
        $settings->add(new admin_setting_configtext_trim_lower('enrol_ldap/objectclass', get_string('objectclass_key', 'enrol_ldap'), get_string('objectclass', 'enrol_ldap'), ''));
        $settings->add(new admin_setting_configtext_trim_lower('enrol_ldap/course_idnumber', get_string('course_idnumber_key', 'enrol_ldap'), get_string('course_idnumber', 'enrol_ldap'), '', true, true));

        $coursefields = array ('shortname', 'fullname', 'summary');
        foreach ($coursefields as $field) {
            $settings->add(new admin_setting_configtext_trim_lower('enrol_ldap/course_'.$field, get_string('course_'.$field.'_key', 'enrol_ldap'), get_string('course_'.$field, 'enrol_ldap'), '', true, true));
        }

        $settings->add(new admin_setting_configcheckbox('enrol_ldap/ignorehiddencourses', get_string('ignorehiddencourses', 'enrol_database'), get_string('ignorehiddencourses_desc', 'enrol_database'), 0));
        $options = array(ENROL_EXT_REMOVED_UNENROL        => get_string('extremovedunenrol', 'enrol'),
                         ENROL_EXT_REMOVED_KEEP           => get_string('extremovedkeep', 'enrol'),
                         ENROL_EXT_REMOVED_SUSPEND        => get_string('extremovedsuspend', 'enrol'),
                         ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'));
        $settings->add(new admin_setting_configselect('enrol_ldap/unenrolaction', get_string('extremovedaction', 'enrol'), get_string('extremovedaction_help', 'enrol'), ENROL_EXT_REMOVED_UNENROL, $options));

        //--- course creation settings ---
        $settings->add(new admin_setting_heading('enrol_ldap_autocreation_settings', get_string('autocreation_settings', 'enrol_ldap'), ''));
        $options = $yesno;
        $settings->add(new admin_setting_configselect('enrol_ldap/autocreate', get_string('autocreate_key', 'enrol_ldap'), get_string('autocreate', 'enrol_ldap'), 0, $options));
        $settings->add(new enrol_ldap_admin_setting_category('enrol_ldap/category', get_string('category_key', 'enrol_ldap'), get_string('category', 'enrol_ldap')));
        $settings->add(new admin_setting_configtext_trim_lower('enrol_ldap/template', get_string('template_key', 'enrol_ldap'), get_string('template', 'enrol_ldap'), ''));

        //--- course update settings ---
        $settings->add(new admin_setting_heading('enrol_ldap_autoupdate_settings', get_string('autoupdate_settings', 'enrol_ldap'), get_string('autoupdate_settings_desc', 'enrol_ldap')));
        $options = $yesno;
        foreach ($coursefields as $field) {
            $settings->add(new admin_setting_configselect('enrol_ldap/course_'.$field.'_updateonsync', get_string('course_'.$field.'_updateonsync_key', 'enrol_ldap'), get_string('course_'.$field.'_updateonsync', 'enrol_ldap'), 0, $options));
        }

        //--- nested groups settings ---
        $settings->add(new admin_setting_heading('enrol_ldap_nested_groups_settings', get_string('nested_groups_settings', 'enrol_ldap'), ''));
        $options = $yesno;
        $settings->add(new admin_setting_configselect('enrol_ldap/nested_groups', get_string('nested_groups_key', 'enrol_ldap'), get_string('nested_groups', 'enrol_ldap'), 0, $options));
    }
}
