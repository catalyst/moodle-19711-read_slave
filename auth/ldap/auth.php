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
 * Authentication Plugin: LDAP Authentication
 * Authentication using LDAP (Lightweight Directory Access Protocol).
 *
 * @package auth_ldap
 * @author Martin Dougiamas
 * @author Iñaki Arenaza
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

defined('MOODLE_INTERNAL') || die();

if (!defined('AUTH_NTLMTIMEOUT')) {  // timewindow for the NTLM SSO process, in secs...
    define('AUTH_NTLMTIMEOUT', 10);
}
// Regular expressions for a valid NTLM username and domain name.
if (!defined('AUTH_NTLM_VALID_USERNAME')) {
    define('AUTH_NTLM_VALID_USERNAME', '[^/\\\\\\\\\[\]:;|=,+*?<>@"]+');
}
if (!defined('AUTH_NTLM_VALID_DOMAINNAME')) {
    define('AUTH_NTLM_VALID_DOMAINNAME', '[^\\\\\\\\\/:*?"<>|]+');
}
// Default format for remote users if using NTLM SSO
if (!defined('AUTH_NTLM_DEFAULT_FORMAT')) {
    define('AUTH_NTLM_DEFAULT_FORMAT', '%domain%\\%username%');
}
if (!defined('AUTH_NTLM_FASTPATH_ATTEMPT')) {
    define('AUTH_NTLM_FASTPATH_ATTEMPT', 0);
}
if (!defined('AUTH_NTLM_FASTPATH_YESFORM')) {
    define('AUTH_NTLM_FASTPATH_YESFORM', 1);
}
if (!defined('AUTH_NTLM_FASTPATH_YESATTEMPT')) {
    define('AUTH_NTLM_FASTPATH_YESATTEMPT', 2);
}

// Allows us to retrieve a diagnostic message in case of LDAP operation error
if (!defined('LDAP_OPT_DIAGNOSTIC_MESSAGE')) {
    define('LDAP_OPT_DIAGNOSTIC_MESSAGE', 0x0032);
}

require_once($CFG->libdir.'/authlib.php');
require_once($CFG->dirroot.'/user/lib.php');
require_once($CFG->dirroot.'/auth/ldap/locallib.php');

/**
 * LDAP authentication plugin.
 */
class auth_plugin_ldap extends auth_plugin_base {

    /** @var connect_ldap\users */
    private $_ldap_users;

    /**
     * Init plugin config from database settings depending on the plugin auth type.
     */
    function init_plugin($authtype) {
        $this->pluginconfig = 'auth_'.$authtype;
        $this->config = get_config($this->pluginconfig);
    }

    /**
     * Constructor with initialisation.
     */
    public function __construct() {
        $this->authtype = 'ldap';
        $this->roleauth = 'auth_ldap';
        $this->errorlogtag = '[AUTH LDAP] ';
        $this->init_plugin($this->authtype);
    }

    /**
     * Returns LDAP users object
     *
     * @return connect_ldap\users
     */
    function ldap_users(): connect_ldap\users {
        if (!$this->_ldap_users) {
            $this->_ldap_users = connect_ldap\users::from_config();
        }
        return $this->_ldap_users;
    }

    /**
     * Returns true if the username and password work and false if they are
     * wrong or don't exist.
     *
     * @param string $username The username (without system magic quotes)
     * @param string $password The password (without system magic quotes)
     * @return bool Authentication success or failure.
     */
    function user_login($username, $password) {
        if (!$username or !$password) {    // Don't allow blank usernames or passwords
            return false;
        }

        $user = $this->ldap_users()->user($username);

        // Check if this is an AD SSO login
        // if we succeed in this block, we'll return success early.
        //
        $key = sesskey();
        if (!empty($this->config->ntlmsso_enabled) && $key === $password) {
            $sessusername = get_cache_flag($this->pluginconfig.'/ntlmsess', $key);
            // We only get the cache flag if we retrieve it before
            // it expires (AUTH_NTLMTIMEOUT seconds).
            if (empty($sessusername)) {
                return false;
            }

            if ($username === $sessusername) {
                unset($sessusername);

                // Check that the user is inside one of the configured LDAP contexts
                // Shortcut here - SSO confirmed
                return $user->exists();
            }
        } // End SSO processing

        return $user->login($password);
    }

    /**
     * Reads user information from ldap and returns it in array()
     *
     * Function should return all information available. If you are saving
     * this information to moodle user-table you should honor syncronization flags
     *
     * @param string $username username
     * @return array with no magic quotes or false on error
     * @throw connect_ldap\exception\ldap_command_error
     */
    function get_userinfo($username) {
        $attrmap = $this->ldap_attributes();
        $search_attribs = [];
        foreach ($attrmap as $key => $values) {
            if (!is_array($values)) {
                $values = array($values);
            }
            foreach ($values as $value) {
                if (!in_array($value, $search_attribs)) {
                    array_push($search_attribs, $value);
                }
            }
        }

        $user = $this->ldap_users()->user($username);
        $entry = array_change_key_case($user->info($search_attribs));
        $result = [
            'suspended' => $user->is_suspended($entry),
        ];
        foreach ($attrmap as $key => $attrs) {
            if (!is_array($attrs)) {
                $attrs = [$attrs];
            }
            foreach (array_map(['core_text', 'strtolower'], $attrs) as $attr) {
                if (isset($entry[$attr])) {
                    $result[$key] = $entry[$attr];
                    break;
                }
            }
        }
        return $result;
    }

    /**
     * Reads user information from ldap and returns it in an object
     *
     * @param string $username username (with system magic quotes)
     * @return object
     * @throw connect_ldap\exception\ldap_command_error
     */
    function get_userinfo_asobj($username) {
        $user_array = $this->get_userinfo($username);
        if ($user_array == false) {
            return false; //error or not found
        }
        $user_array = truncate_userinfo($user_array);
        $user = new stdClass();
        foreach ($user_array as $key=>$value) {
            $user->{$key} = $value;
        }
        return $user;
    }

    /**
     * Returns all usernames from LDAP
     *
     * @return ?array of LDAP user names converted to UTF-8
     */
    function get_userlist() {
        return $this->ldap_users()->list();
    }

    /**
     * Creates a new user on LDAP.
     * By using information in userobject
     * Use user_exists to prevent duplicate usernames
     *
     * @param mixed $userobject  Moodle userobject
     * @param mixed $plainpass   Plaintext password
     */
    function user_create($userobject, $plainpass) {
        $attrmap = $this->ldap_attributes();
        $newuser = [];
        foreach ($attrmap as $key => $values) {
            if (!is_array($values)) {
                $values = array($values);
            }
            foreach ($values as $value) {
                if (!empty($userobject->$key) ) {
                    $newuser[$value] = $userobject->$key;
                }
            }
        }

        $user = $this->ldap_users()->user($userobject->username);
        $user->create($newuser, $plainpass);
    }

    /**
     * Returns true if plugin allows resetting of password from moodle.
     *
     * @return bool
     */
    function can_reset_password() {
        return !empty($this->config->stdchangepassword);
    }

    /**
     * Returns true if plugin can be manually set.
     *
     * @return bool
     */
    function can_be_manually_set() {
        return true;
    }

    /**
     * Returns true if plugin allows signup and user creation.
     *
     * @return bool
     */
    function can_signup() {
        return (!empty($this->config->auth_user_create));
    }

    /**
     * Sign up a new user ready for confirmation.
     * Password is passed in plaintext.
     *
     * @param object $user new user object
     * @param boolean $notify print notice with link and terminate
     * @return boolean success
     */
    function user_signup($user, $notify=true) {
        global $CFG, $DB, $PAGE, $OUTPUT;

        require_once($CFG->dirroot.'/user/profile/lib.php');
        require_once($CFG->dirroot.'/user/lib.php');

        $plainslashedpassword = $user->password;
        unset($user->password);

        $ldap_user = $this->ldap_users()->user($user->username);
        try {
            if ($ldap_user->exists()) {
                print_error('auth_ldap_user_exists', 'auth_ldap');
            } else {
                $this->user_create($user, $plainslashedpassword);
            }
        } catch (connect_ldap\exception\ldap_command_error $e) {
            print_error('auth_ldap_create_error', 'auth_ldap');
        }

        $user->id = user_create_user($user, false, false);

        user_add_password_history($user->id, $plainslashedpassword);

        // Save any custom profile field information
        profile_save_data($user);

        try {
            $this->update_user_record($user->username, false, false, $ldap_user->is_suspended());
        } catch (connect_ldap\exception\ldap_command_error $e) {
            // ?
        }

        // This will also update the stored hash to the latest algorithm
        // if the existing hash is using an out-of-date algorithm (or the
        // legacy md5 algorithm).
        update_internal_user_password($user, $plainslashedpassword);

        $user = $DB->get_record('user', array('id'=>$user->id));

        \core\event\user_created::create_from_userid($user->id)->trigger();

        if (! send_confirmation_email($user)) {
            print_error('noemail', 'auth_ldap');
        }

        if ($notify) {
            $emailconfirm = get_string('emailconfirm');
            $PAGE->set_url('/auth/ldap/auth.php');
            $PAGE->navbar->add($emailconfirm);
            $PAGE->set_title($emailconfirm);
            $PAGE->set_heading($emailconfirm);
            echo $OUTPUT->header();
            notice(get_string('emailconfirmsent', '', $user->email), "{$CFG->wwwroot}/index.php");
        } else {
            return true;
        }
    }

    /**
     * Returns true if plugin allows confirming of new users.
     *
     * @return bool
     */
    function can_confirm() {
        return $this->can_signup();
    }

    /**
     * Confirm the new user as registered.
     *
     * @param string $username
     * @param string $confirmsecret
     */
    function user_confirm($username, $confirmsecret) {
        global $DB;

        $user = get_complete_user_data('username', $username);

        if (!empty($user)) {
            if ($user->auth != $this->authtype) {
                return AUTH_CONFIRM_ERROR;

            } else if ($user->secret === $confirmsecret && $user->confirmed) {
                return AUTH_CONFIRM_ALREADY;

            } else if ($user->secret === $confirmsecret) {   // They have provided the secret key to get in
                if (!$this->user_activate($username)) {
                    return AUTH_CONFIRM_FAIL;
                }
                $user->confirmed = 1;
                user_update_user($user, false);
                return AUTH_CONFIRM_OK;
            }
        } else {
            return AUTH_CONFIRM_ERROR;
        }
    }

    /**
     * Return number of days to user password expires
     *
     * If userpassword does not expire it should return 0. If password is already expired
     * it should return negative value.
     *
     * @param mixed $username username
     * @return integer
     */
    function password_expire($username) {
        $user = $this->ldap_users()->user($username);
        try {
            return $user->password_expire();
        } catch (connect_ldap\exception\configuration_error $e) {
            return 0;
        }
    }

    /**
     * Syncronizes user fron external LDAP server to moodle user table
     *
     * Sync is now using username attribute.
     *
     * Syncing users removes or suspends users that dont exists anymore in external LDAP.
     * Creates new users and updates coursecreator status of users.
     *
     * @param bool $do_updates will do pull in data updates from LDAP if relevant
     */
    function sync_users($do_updates=true) {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/user/profile/lib.php');

        $dbman = $DB->get_manager();

    /// Define table user to be created
        $table = new xmldb_table('tmp_extuser');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('username', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('mnethostid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_index('username', XMLDB_INDEX_UNIQUE, array('mnethostid', 'username'));

        print_string('creatingtemptable', 'auth_ldap', 'tmp_extuser');
        $dbman->create_temp_table($table);

        ////
        //// get user's list from ldap to sql in a scalable fashion
        ////
        // prepare some data we'll need
        $this->ldap_users()->for_each(function ($username) {
            global $DB, $CFG;

            $username = core_text::strtolower($username); // usernames are __always__ lowercase.
            $DB->insert_record_raw('tmp_extuser', array('username'=>$username,
                                                        'mnethostid'=>$CFG->mnet_localhost_id), false, true);
            echo '.';
        });


        /// preserve our user database
        /// if the temp table is empty, it probably means that something went wrong, exit
        /// so as to avoid mass deletion of users; which is hard to undo
        $count = $DB->count_records_sql('SELECT COUNT(username) AS count, 1 FROM {tmp_extuser}');
        if ($count < 1) {
            print_string('didntgetusersfromldap', 'auth_ldap');
            $dbman->drop_table($table);
            return false;
        } else {
            print_string('gotcountrecordsfromldap', 'auth_ldap', $count);
        }


/// User removal
        // Find users in DB that aren't in ldap -- to be removed!
        // this is still not as scalable (but how often do we mass delete?)

        if ($this->config->removeuser == AUTH_REMOVEUSER_FULLDELETE) {
            $sql = "SELECT u.*
                      FROM {user} u
                 LEFT JOIN {tmp_extuser} e ON (u.username = e.username AND u.mnethostid = e.mnethostid)
                     WHERE u.auth = :auth
                           AND u.deleted = 0
                           AND e.username IS NULL";
            $remove_users = $DB->get_records_sql($sql, array('auth'=>$this->authtype));

            if (!empty($remove_users)) {
                print_string('userentriestoremove', 'auth_ldap', count($remove_users));
                foreach ($remove_users as $user) {
                    if (delete_user($user)) {
                        echo "\t"; print_string('auth_dbdeleteuser', 'auth_db', array('name'=>$user->username, 'id'=>$user->id)); echo "\n";
                    } else {
                        echo "\t"; print_string('auth_dbdeleteusererror', 'auth_db', $user->username); echo "\n";
                    }
                }
            } else {
                print_string('nouserentriestoremove', 'auth_ldap');
            }
            unset($remove_users); // Free mem!

        } else if ($this->config->removeuser == AUTH_REMOVEUSER_SUSPEND) {
            $sql = "SELECT u.*
                      FROM {user} u
                 LEFT JOIN {tmp_extuser} e ON (u.username = e.username AND u.mnethostid = e.mnethostid)
                     WHERE u.auth = :auth
                           AND u.deleted = 0
                           AND u.suspended = 0
                           AND e.username IS NULL";
            $remove_users = $DB->get_records_sql($sql, array('auth'=>$this->authtype));

            if (!empty($remove_users)) {
                print_string('userentriestoremove', 'auth_ldap', count($remove_users));

                foreach ($remove_users as $user) {
                    $updateuser = new stdClass();
                    $updateuser->id = $user->id;
                    $updateuser->suspended = 1;
                    user_update_user($updateuser, false);
                    echo "\t"; print_string('auth_dbsuspenduser', 'auth_db', array('name'=>$user->username, 'id'=>$user->id)); echo "\n";
                    \core\session\manager::kill_user_sessions($user->id);
                }
            } else {
                print_string('nouserentriestoremove', 'auth_ldap');
            }
            unset($remove_users); // Free mem!
        }

/// Revive suspended users
        if (!empty($this->config->removeuser) and $this->config->removeuser == AUTH_REMOVEUSER_SUSPEND) {
            $sql = "SELECT u.id, u.username
                      FROM {user} u
                      JOIN {tmp_extuser} e ON (u.username = e.username AND u.mnethostid = e.mnethostid)
                     WHERE (u.auth = 'nologin' OR (u.auth = ? AND u.suspended = 1)) AND u.deleted = 0";
            // Note: 'nologin' is there for backwards compatibility.
            $revive_users = $DB->get_records_sql($sql, array($this->authtype));

            if (!empty($revive_users)) {
                print_string('userentriestorevive', 'auth_ldap', count($revive_users));

                foreach ($revive_users as $user) {
                    $updateuser = new stdClass();
                    $updateuser->id = $user->id;
                    $updateuser->auth = $this->authtype;
                    $updateuser->suspended = 0;
                    user_update_user($updateuser, false);
                    echo "\t"; print_string('auth_dbreviveduser', 'auth_db', array('name'=>$user->username, 'id'=>$user->id)); echo "\n";
                }
            } else {
                print_string('nouserentriestorevive', 'auth_ldap');
            }

            unset($revive_users);
        }


/// User Updates - time-consuming (optional)
        if ($do_updates) {
            // Narrow down what fields we need to update
            $updatekeys = $this->get_profile_keys();

        } else {
            print_string('noupdatestobedone', 'auth_ldap');
        }
        if ($do_updates and !empty($updatekeys)) { // run updates only if relevant
            $users = $DB->get_records_sql('SELECT u.username, u.id
                                             FROM {user} u
                                            WHERE u.deleted = 0 AND u.auth = ? AND u.mnethostid = ?',
                                          array($this->authtype, $CFG->mnet_localhost_id));
            if (!empty($users)) {
                print_string('userentriestoupdate', 'auth_ldap', count($users));

                $transaction = $DB->start_delegated_transaction();
                $xcount = 0;
                $maxxcount = 100;

                foreach ($users as $user) {
                    echo "\t"; print_string('auth_dbupdatinguser', 'auth_db', array('name'=>$user->username, 'id'=>$user->id));
                    $ldap_user = $this->ldap_users()->user($user->username);
                    if (!$this->update_user_record($user->username, $updatekeys, true,
                            $ldap_user->is_suspended())) {
                        echo ' - '.get_string('skipped');
                    }
                    echo "\n";
                    $xcount++;

                    // Update system roles, if needed.
                    $this->sync_roles($user);
                }
                $transaction->allow_commit();
                unset($users); // free mem
            }
        } else { // end do updates
            print_string('noupdatestobedone', 'auth_ldap');
        }

/// User Additions
        // Find users missing in DB that are in LDAP
        // and gives me a nifty object I don't want.
        // note: we do not care about deleted accounts anymore, this feature was replaced by suspending to nologin auth plugin
        $sql = 'SELECT e.id, e.username
                  FROM {tmp_extuser} e
                  LEFT JOIN {user} u ON (e.username = u.username AND e.mnethostid = u.mnethostid)
                 WHERE u.id IS NULL';
        $add_users = $DB->get_records_sql($sql);

        if (!empty($add_users)) {
            print_string('userentriestoadd', 'auth_ldap', count($add_users));
            $errors = 0;

            $transaction = $DB->start_delegated_transaction();
            foreach ($add_users as $user) {
                $username = $user->username;
                $user = $this->get_userinfo_asobj($username);

                // Prep a few params
                $user->modified   = time();
                $user->confirmed  = 1;
                $user->auth       = $this->authtype;
                $user->mnethostid = $CFG->mnet_localhost_id;
                // get_userinfo_asobj() might have replaced $user->username with the value
                // from the LDAP server (which can be mixed-case). Make sure it's lowercase
                $user->username = trim(core_text::strtolower($username));
                // It isn't possible to just rely on the configured suspension attribute since
                // things like active directory use bit masks, other things using LDAP might
                // do different stuff as well.
                //
                // The cast to int is a workaround for MDL-53959.
                $user->suspended = (int)$user->suspended;

                if (empty($user->calendartype)) {
                    $user->calendartype = $CFG->calendartype;
                }

                // $id = user_create_user($user, false);
                try {
                    $id = user_create_user($user, false);
                } catch (Exception $e) {
                    print_string('invaliduserexception', 'auth_ldap', print_r($user, true) .  $e->getMessage());
                    $errors++;
                    continue;
                }
                echo "\t"; print_string('auth_dbinsertuser', 'auth_db', array('name'=>$user->username, 'id'=>$id)); echo "\n";
                $euser = $DB->get_record('user', array('id' => $id));

                if (!empty($this->config->forcechangepassword)) {
                    set_user_preference('auth_forcepasswordchange', 1, $id);
                }

                // Save custom profile fields.
                $this->update_user_record($user->username, $this->get_profile_keys(true), false);

                // Add roles if needed.
                $this->sync_roles($euser);

            }

            // Display number of user creation errors, if any.
            if ($errors) {
                print_string('invalidusererrors', 'auth_ldap', $errors);
            }

            $transaction->allow_commit();
            unset($add_users); // free mem
        } else {
            print_string('nouserstobeadded', 'auth_ldap');
        }

        $dbman->drop_table($table);

        return true;
    }

    /**
     * Activates (enables) user in external LDAP so user can login
     *
     * @param mixed $username
     * @return boolean result
     */
    function user_activate($username) {
        $user = $this->ldap_users()->user($username);
        $user->activate();
    }

    /**
     * Check if user has LDAP group membership.
     *
     * Returns true if user should be assigned role.
     *
     * @param mixed $username username (without system magic quotes).
     * @param array $role Array of role's shortname, localname, and settingname for the config value.
     * @return mixed result null if role/LDAP context is not configured, boolean otherwise.
     */
    private function is_role($username, $role) {
        if (empty($this->config->{$role['settingname']})) {
            return null;
        }

        $user = $this->ldap_users()->user($username);
        return $user->is_group_member(explode(';', $this->config->{$role['settingname']}));
    }

    /**
     * Called when the user record is updated.
     *
     * Modifies user in external LDAP server. It takes olduser (before
     * changes) and newuser (after changes) compares information and
     * saves modified information to external LDAP server.
     *
     * @param mixed $olduser     Userobject before modifications    (without system magic quotes)
     * @param mixed $newuser     Userobject new modified userobject (without system magic quotes)
     * @return boolean result
     *
     */
    function user_update($olduser, $newuser) {
        global $CFG;

        require_once($CFG->dirroot . '/user/profile/lib.php');

        if (isset($olduser->username) and isset($newuser->username) and $olduser->username != $newuser->username) {
            error_log($this->errorlogtag.get_string('renamingnotallowed', 'auth_ldap'));
            return false;
        }

        if (isset($olduser->auth) and $olduser->auth != $this->authtype) {
            return true; // just change auth and skip update
        }

        $attrmap = $this->ldap_attributes();
        // Before doing anything else, make sure we really need to update anything
        // in the external LDAP server.
        $update_external = false;
        foreach ($attrmap as $key => $ldapkeys) {
            if (!empty($this->config->{'field_updateremote_'.$key})) {
                $update_external = true;
                break;
            }
        }
        if (!$update_external) {
            return true;
        }

        $user = $this->ldap_users()->user($olduser->username);
        if(!$user->exists()) {
            return false;
        }

        $search_attribs = array();
        foreach ($attrmap as $key => $values) {
            if (!is_array($values)) {
                $values = array($values);
            }
            foreach ($values as $value) {
                if (!in_array($value, $search_attribs)) {
                    array_push($search_attribs, $value);
                }
            }
        }

        // Load old custom fields.
        $olduserprofilefields = (array) profile_user_record($olduser->id, false);

        $fields = array();
        foreach (profile_get_custom_fields(false) as $field) {
            $fields[$field->shortname] = $field;
        }

        $success = true;
        $user_entry = array_change_key_case($user->info($search_attribs));

        foreach ($attrmap as $key => $ldapkeys) {
            if (preg_match('/^profile_field_(.*)$/', $key, $match)) {
                // Custom field.
                $fieldname = $match[1];
                if (isset($fields[$fieldname])) {
                    $class = 'profile_field_' . $fields[$fieldname]->datatype;
                    $formfield = new $class($fields[$fieldname]->id, $olduser->id);
                    $oldvalue = isset($olduserprofilefields[$fieldname]) ? $olduserprofilefields[$fieldname] : null;
                } else {
                    $oldvalue = null;
                }
                $newvalue = $formfield->edit_save_data_preprocess($newuser->{$formfield->inputname}, new stdClass);
            } else {
                // Standard field.
                $oldvalue = isset($olduser->$key) ? $olduser->$key : null;
                $newvalue = isset($newuser->$key) ? $newuser->$key : null;
            }

            if ($newvalue !== null and $newvalue !== $oldvalue and !empty($this->config->{'field_updateremote_' . $key})) {
                // For ldap values that could be in more than one
                // ldap key, we will do our best to match
                // where they came from
                $ambiguous = true;
                $changed   = false;
                if (!is_array($ldapkeys)) {
                    $ldapkeys = array($ldapkeys);
                }
                if (count($ldapkeys) < 2) {
                    $ambiguous = false;
                }

                foreach (array_map(['core_text', 'strtolower'], $ldapkeys) as $ldapkey) {
                    // If the field is empty in LDAP there are two options:
                    // 1. We get the LDAP field using ldap_first_attribute.
                    // 2. LDAP don't send the field using  ldap_first_attribute.
                    // So, for option 1 we check the if the field is retrieve it.
                    // And get the original value of field in LDAP if the field.
                    // Otherwise, let value in blank and delegate the check in ldap_modify.
                    if (isset($user_entry[$ldapkey])) {
                        $ldapvalue = $user_entry[$ldapkey];
                    } else {
                        $ldapvalue = '';
                    }

                    if (!$ambiguous or $oldvalue === '' or $oldvalue === $ldapvalue) {
                        // Not ambiguous
                        // or value empty before in Moodle (and LDAP) - use 1st ldap candidate field, no need to guess
                        // or we found which ldap key to update!
                        // Skip update if the values already match.
                        if ($newvalue !== $ldapvalue) {
                            try {
                                $user->update([$ldapkey => $newvalue]);
                                $changed = true;
                            } catch (connect_ldap\exception\ldap_command_error $e) {
                                // This might fail due to schema validation
                                $success = false;
                                error_log($this->errorlogtag.get_string ('updateremfail', 'auth_ldap',
                                                                         array('errstring'=>$e->getMessage(),
                                                                               'key'=>$key,
                                                                               'ouvalue'=>$oldvalue,
                                                                               'nuvalue'=>$newvalue)));
                            }
                        }
                    }
                }

                if ($ambiguous and !$changed) {
                    $success = false;
                    error_log($this->errorlogtag.get_string ('updateremfailamb', 'auth_ldap',
                                                             array('key'=>$key,
                                                                   'ouvalue'=>$oldvalue,
                                                                   'nuvalue'=>$newvalue)));
                }
            }
        }

        return $success;

    }

    /**
     * Changes userpassword in LDAP
     *
     * Called when the user password is updated. It assumes it is
     * called by an admin or that you've otherwise checked the user's
     * credentials
     *
     * @param  object  $user        User table object
     * @param  string  $newpassword Plaintext password (not crypted/md5'ed)
     * @return boolean result
     *
     */
    function user_update_password($user, $newpassword) {
        global $USER;

        $result = false;
        $username = $user->username;
        $user = $this->ldap_users()->user($username);
        try {
            $user->update_password($newpassword);
            return true;
        } catch (connect_ldap\exception\ldap_command_error $e) {
                error_log($this->errorlogtag.get_string ('updatepasserror', 'auth_ldap',
                                                           array('errstring'=>$e->getMessage())));
            return false;
        }
    }

    /**
     * Returns user attribute mappings between moodle and LDAP
     *
     * @return array
     */

    function ldap_attributes () {
        $moodleattributes = array();
        // If we have custom fields then merge them with user fields.
        $customfields = $this->get_custom_user_profile_fields();
        if (!empty($customfields) && !empty($this->userfields)) {
            $userfields = array_merge($this->userfields, $customfields);
        } else {
            $userfields = $this->userfields;
        }

        foreach ($userfields as $field) {
            if (!empty($this->config->{"field_map_$field"})) {
                $moodleattributes[$field] = trim($this->config->{"field_map_$field"});
                if (preg_match('/,/', $moodleattributes[$field])) {
                    $moodleattributes[$field] = explode(',', $moodleattributes[$field]); // split ?
                }
            }
        }
        return $moodleattributes;
    }

    /**
     * Indicates if password hashes should be stored in local moodle database.
     *
     * @return bool true means flag 'not_cached' stored instead of password hash
     */
    function prevent_local_passwords() {
        return !empty($this->config->preventpassindb);
    }

    /**
     * Returns true if this authentication plugin is 'internal'.
     *
     * @return bool
     */
    function is_internal() {
        return false;
    }

    /**
     * Returns true if this authentication plugin can change the user's
     * password.
     *
     * @return bool
     */
    function can_change_password() {
        return !empty($this->config->stdchangepassword) or !empty($this->config->changepasswordurl);
    }

    /**
     * Returns the URL for changing the user's password, or empty if the default can
     * be used.
     *
     * @return moodle_url
     */
    function change_password_url() {
        if (empty($this->config->stdchangepassword)) {
            if (!empty($this->config->changepasswordurl)) {
                return new moodle_url($this->config->changepasswordurl);
            } else {
                return null;
            }
        } else {
            return null;
        }
    }

    /**
     * Will get called before the login page is shownr. Ff NTLM SSO
     * is enabled, and the user is in the right network, we'll redirect
     * to the magic NTLM page for SSO...
     *
     */
    function loginpage_hook() {
        global $CFG, $SESSION;

        // HTTPS is potentially required
        //httpsrequired(); - this must be used before setting the URL, it is already done on the login/index.php

        if (($_SERVER['REQUEST_METHOD'] === 'GET'         // Only on initial GET of loginpage
             || ($_SERVER['REQUEST_METHOD'] === 'POST'
                 && (get_local_referer() != strip_querystring(qualified_me()))))
                                                          // Or when POSTed from another place
                                                          // See MDL-14071
            && !empty($this->config->ntlmsso_enabled)     // SSO enabled
            && !empty($this->config->ntlmsso_subnet)      // have a subnet to test for
            && empty($_GET['authldap_skipntlmsso'])       // haven't failed it yet
            && (isguestuser() || !isloggedin())           // guestuser or not-logged-in users
            && address_in_subnet(getremoteaddr(), $this->config->ntlmsso_subnet)) {

            // First, let's remember where we were trying to get to before we got here
            if (empty($SESSION->wantsurl)) {
                $SESSION->wantsurl = null;
                $referer = get_local_referer(false);
                if ($referer &&
                        $referer != $CFG->wwwroot &&
                        $referer != $CFG->wwwroot . '/' &&
                        $referer != $CFG->wwwroot . '/login/' &&
                        $referer != $CFG->wwwroot . '/login/index.php') {
                    $SESSION->wantsurl = $referer;
                }
            }

            // Now start the whole NTLM machinery.
            if($this->config->ntlmsso_ie_fastpath == AUTH_NTLM_FASTPATH_YESATTEMPT ||
                $this->config->ntlmsso_ie_fastpath == AUTH_NTLM_FASTPATH_YESFORM) {
                if (core_useragent::is_ie()) {
                    $sesskey = sesskey();
                    redirect($CFG->wwwroot.'/auth/ldap/ntlmsso_magic.php?sesskey='.$sesskey);
                } else if ($this->config->ntlmsso_ie_fastpath == AUTH_NTLM_FASTPATH_YESFORM) {
                    redirect($CFG->wwwroot.'/login/index.php?authldap_skipntlmsso=1');
                }
            }
            redirect($CFG->wwwroot.'/auth/ldap/ntlmsso_attempt.php');
        }

        // No NTLM SSO, Use the normal login page instead.

        // If $SESSION->wantsurl is empty and we have a 'Referer:' header, the login
        // page insists on redirecting us to that page after user validation. If
        // we clicked on the redirect link at the ntlmsso_finish.php page (instead
        // of waiting for the redirection to happen) then we have a 'Referer:' header
        // we don't want to use at all. As we can't get rid of it, just point
        // $SESSION->wantsurl to $CFG->wwwroot (after all, we came from there).
        if (empty($SESSION->wantsurl)
            && (get_local_referer() == $CFG->wwwroot.'/auth/ldap/ntlmsso_finish.php')) {

            $SESSION->wantsurl = $CFG->wwwroot;
        }
    }

    /**
     * To be called from a page running under NTLM's
     * "Integrated Windows Authentication".
     *
     * If successful, it will set a special "cookie" (not an HTTP cookie!)
     * in cache_flags under the $this->pluginconfig/ntlmsess "plugin" and return true.
     * The "cookie" will be picked up by ntlmsso_finish() to complete the
     * process.
     *
     * On failure it will return false for the caller to display an appropriate
     * error message (probably saying that Integrated Windows Auth isn't enabled!)
     *
     * NOTE that this code will execute under the OS user credentials,
     * so we MUST avoid dealing with files -- such as session files.
     * (The caller should define('NO_MOODLE_COOKIES', true) before including config.php)
     *
     */
    function ntlmsso_magic($sesskey) {
        if (isset($_SERVER['REMOTE_USER']) && !empty($_SERVER['REMOTE_USER'])) {

            // HTTP __headers__ seem to be sent in ISO-8859-1 encoding
            // (according to my reading of RFC-1945, RFC-2616 and RFC-2617 and
            // my local tests), so we need to convert the REMOTE_USER value
            // (i.e., what we got from the HTTP WWW-Authenticate header) into UTF-8
            $username = core_text::convert($_SERVER['REMOTE_USER'], 'iso-8859-1', 'utf-8');

            switch ($this->config->ntlmsso_type) {
                case 'ntlm':
                    // The format is now configurable, so try to extract the username
                    $username = $this->get_ntlm_remote_user($username);
                    if (empty($username)) {
                        return false;
                    }
                    break;
                case 'kerberos':
                    // Format is username@DOMAIN
                    $username = substr($username, 0, strpos($username, '@'));
                    break;
                default:
                    error_log($this->errorlogtag.get_string ('ntlmsso_unknowntype', 'auth_ldap'));
                    return false; // Should never happen!
            }

            $username = core_text::strtolower($username); // Compatibility hack
            set_cache_flag($this->pluginconfig.'/ntlmsess', $sesskey, $username, AUTH_NTLMTIMEOUT);
            return true;
        }
        return false;
    }

    /**
     * Find the session set by ntlmsso_magic(), validate it and
     * call authenticate_user_login() to authenticate the user through
     * the auth machinery.
     *
     * It is complemented by a similar check in user_login().
     *
     * If it succeeds, it never returns.
     *
     */
    function ntlmsso_finish() {
        global $CFG, $USER, $SESSION;

        $key = sesskey();
        $username = get_cache_flag($this->pluginconfig.'/ntlmsess', $key);
        if (empty($username)) {
            return false;
        }

        // Here we want to trigger the whole authentication machinery
        // to make sure no step is bypassed...
        $reason = null;
        $user = authenticate_user_login($username, $key, false, $reason, false);
        if ($user) {
            complete_user_login($user);

            // Cleanup the key to prevent reuse...
            // and to allow re-logins with normal credentials
            unset_cache_flag($this->pluginconfig.'/ntlmsess', $key);

            // Redirection
            if (user_not_fully_set_up($USER, true)) {
                $urltogo = $CFG->wwwroot.'/user/edit.php';
                // We don't delete $SESSION->wantsurl yet, so we get there later
            } else if (isset($SESSION->wantsurl) and (strpos($SESSION->wantsurl, $CFG->wwwroot) === 0)) {
                $urltogo = $SESSION->wantsurl;    // Because it's an address in this site
                unset($SESSION->wantsurl);
            } else {
                // No wantsurl stored or external - go to homepage
                $urltogo = $CFG->wwwroot.'/';
                unset($SESSION->wantsurl);
            }
            // We do not want to redirect if we are in a PHPUnit test.
            if (!PHPUNIT_TEST) {
                redirect($urltogo);
            }
        }
        // Should never reach here.
        return false;
    }

    /**
     * Sync roles for this user.
     *
     * @param object $user The user to sync (without system magic quotes).
     */
    function sync_roles($user) {
        global $DB;

        $roles = get_ldap_assignable_role_names(2); // Admin user.

        foreach ($roles as $role) {
            $isrole = $this->is_role($user->username, $role);
            if ($isrole === null) {
                continue; // Nothing to sync - role/LDAP contexts not configured.
            }

            // Sync user.
            $systemcontext = context_system::instance();
            if ($isrole) {
                // Following calls will not create duplicates.
                role_assign($role['id'], $user->id, $systemcontext->id, $this->roleauth);
            } else {
                // Unassign only if previously assigned by this plugin.
                role_unassign($role['id'], $user->id, $systemcontext->id, $this->roleauth);
            }
        }
    }

    /**
     * Disconnects from a LDAP server
     *
     * @param force boolean Forces closing the real connection to the LDAP server, ignoring any
     *                      cached connections. This is needed when we've used paged results
     *                      and want to use normal results again.
     */
    function ldap_close($force=false) {
        $this->ldapconns--;
        if (($this->ldapconns == 0) || ($force)) {
            $this->ldapconns = 0;
            @ldap_close($this->ldapconnection);
            unset($this->ldapconnection);
        }
    }

    /**
     * When using NTLM SSO, the format of the remote username we get in
     * $_SERVER['REMOTE_USER'] may vary, depending on where from and how the web
     * server gets the data. So we let the admin configure the format using two
     * place holders (%domain% and %username%). This function tries to extract
     * the username (stripping the domain part and any separators if they are
     * present) from the value present in $_SERVER['REMOTE_USER'], using the
     * configured format.
     *
     * @param string $remoteuser The value from $_SERVER['REMOTE_USER'] (converted to UTF-8)
     *
     * @return string The remote username (without domain part or
     *                separators). Empty string if we can't extract the username.
     */
    protected function get_ntlm_remote_user($remoteuser) {
        if (empty($this->config->ntlmsso_remoteuserformat)) {
            $format = AUTH_NTLM_DEFAULT_FORMAT;
        } else {
            $format = $this->config->ntlmsso_remoteuserformat;
        }

        $format = preg_quote($format);
        $formatregex = preg_replace(array('#%domain%#', '#%username%#'),
                                    array('('.AUTH_NTLM_VALID_DOMAINNAME.')', '('.AUTH_NTLM_VALID_USERNAME.')'),
                                    $format);
        if (preg_match('#^'.$formatregex.'$#', $remoteuser, $matches)) {
            $user = end($matches);
            return $user;
        }

        /* We are unable to extract the username with the configured format. Probably
         * the format specified is wrong, so log a warning for the admin and return
         * an empty username.
         */
        error_log($this->errorlogtag.get_string ('auth_ntlmsso_maybeinvalidformat', 'auth_ldap'));
        return '';
    }

    /**
     * Check if the diagnostic message for the LDAP login error tells us that the
     * login is denied because the user password has expired or the password needs
     * to be changed on first login (using interactive SMB/Windows logins, not
     * LDAP logins).
     *
     * @param string the diagnostic message for the LDAP login error
     * @return bool true if the password has expired or the password must be changed on first login
     */
    protected function ldap_ad_pwdexpired_from_diagmsg($diagmsg) {
        // The format of the diagnostic message is (actual examples from W2003 and W2008):
        // "80090308: LdapErr: DSID-0C090334, comment: AcceptSecurityContext error, data 52e, vece"  (W2003)
        // "80090308: LdapErr: DSID-0C090334, comment: AcceptSecurityContext error, data 773, vece"  (W2003)
        // "80090308: LdapErr: DSID-0C0903AA, comment: AcceptSecurityContext error, data 52e, v1771" (W2008)
        // "80090308: LdapErr: DSID-0C0903AA, comment: AcceptSecurityContext error, data 773, v1771" (W2008)
        // We are interested in the 'data nnn' part.
        //   if nnn == 773 then user must change password on first login
        //   if nnn == 532 then user password has expired
        $diagmsg = explode(',', $diagmsg);
        if (preg_match('/data (773|532)/i', trim($diagmsg[2]))) {
            return true;
        }
        return false;
    }

    /**
     * Test a DN
     *
     * @param connect_ldap\client $ldap
     * @param string $dn The DN to check for existence
     * @param string $message The identifier of a string as in get_string()
     * @param string|object|array $a An object, string or number that can be used
     *      within translation strings as in get_string()
     * @return true or a message in case of error
     */
    private function test_dn(connect_ldap\client $ldap, $dn, $message, $a = null) {
        try {
            $ldap->read($dn, '(objectClass=*)', array());
            return true;
        } catch (connect_ldap\exception\ldap_command_error $e) {
            return get_string($message, 'auth_ldap', $e->getMessage());
        }
    }

    /**
     * Test if settings are correct, print info to output.
     */
    public function test_settings() {
        global $OUTPUT;

        if (!function_exists('ldap_connect')) { // Is php-ldap really there?
            echo $OUTPUT->notification(get_string('auth_ldap_noextension', 'auth_ldap'), \core\output\notification::NOTIFY_ERROR);
            return;
        }

        // Check to see if this is actually configured.
        if (empty($this->config->host_url)) {
            // LDAP is not even configured.
            echo $OUTPUT->notification(get_string('ldapnotconfigured', 'auth_ldap'), \core\output\notification::NOTIFY_ERROR);
            return;
        }

        if ($this->config->ldap_version != 3) {
            echo $OUTPUT->notification(get_string('diag_toooldversion', 'auth_ldap'), \core\output\notification::NOTIFY_WARNING);
        }

        try {
            $ldap = connect_ldap\client::from_config();
        } catch (connect_ldap\exception\error $e) {
            echo $OUTPUT->notification($e->getMessage(), \core\output\notification::NOTIFY_ERROR);
            return;
        }

        // Display paged file results.
        if (!$ldap->config->paged_results_supported) {
            echo $OUTPUT->notification(get_string('pagedresultsnotsupp', 'auth_ldap'), \core\output\notification::NOTIFY_INFO);
        }

        // Check contexts.
        foreach (explode(';', $ldap->config->user_contexts) as $context) {
            $context = trim($context);
            if (empty($context)) {
                echo $OUTPUT->notification(get_string('diag_emptycontext', 'auth_ldap'), \core\output\notification::NOTIFY_WARNING);
                continue;
            }

            $message = $this->test_dn($ldap, $context, 'diag_contextnotfound', $context);
            if ($message !== true) {
                echo $OUTPUT->notification($message, \core\output\notification::NOTIFY_WARNING);
            }
        }

        // Create system role mapping field for each assignable system role.
        $roles = get_ldap_assignable_role_names();
        foreach ($roles as $role) {
            foreach (explode(';', $this->config->{$role['settingname']}) as $groupdn) {
                if (empty($groupdn)) {
                    continue;
                }

                $role['group'] = $groupdn;
                $message = $this->test_dn($ldap, $groupdn, 'diag_rolegroupnotfound', $role);
                if ($message !== true) {
                    echo $OUTPUT->notification($message, \core\output\notification::NOTIFY_WARNING);
                }
            }
        }

        echo $OUTPUT->notification(get_string('connectingldapsuccess', 'auth_ldap'), \core\output\notification::NOTIFY_SUCCESS);
    }

    /**
     * Get the list of profile fields.
     *
     * @param   bool    $fetchall   Fetch all, not just those for update.
     * @return  array
     */
    protected function get_profile_keys($fetchall = false) {
        $keys = array_keys(get_object_vars($this->config));
        $updatekeys = [];
        foreach ($keys as $key) {
            if (preg_match('/^field_updatelocal_(.+)$/', $key, $match)) {
                // If we have a field to update it from and it must be updated 'onlogin' we update it on cron.
                if (!empty($this->config->{'field_map_'.$match[1]})) {
                    if ($fetchall || $this->config->{$match[0]} === 'onlogin') {
                        array_push($updatekeys, $match[1]); // the actual key name
                    }
                }
            }
        }

        if (!empty($this->config->sync_suspended)) {
            $updatekeys[] = 'suspended';
        }

        return $updatekeys;
    }
}
