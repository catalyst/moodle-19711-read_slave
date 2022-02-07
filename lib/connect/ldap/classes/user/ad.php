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
 * LDAP user class for MS ActiveDirectory
 *
 * @package    connect_ldap
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace connect_ldap\user;

use stdClass;

use connect_ldap\client;
use connect_ldap\exception\error,
    connect_ldap\exception\installation_error,
    connect_ldap\exception\no_attribute_value_error,
    connect_ldap\exception\ldap_command_error;

defined('MOODLE_INTERNAL') || die();

/**
 * LDAP user class for MS ActiveDirectory
 *
 *
 * @package    connect_ldap
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ad extends base {
    const DEFAULTS = [
        'user_objectclass' => '(samaccounttype=805306368)',
        'user_attribute' => 'cn',
        'member_attribute' => 'member',
        'member_attribute_isdn' => '1',
        'password_expiration_attribute' => 'pwdLastSet',
    ];

    // See http://support.microsoft.com/kb/305144 to interprete these values.
    const NORMAL_ACCOUNT = 0x0200;
    const ACCOUNT_DISABLE = 0x0002;
    const DO_NOT_EXPIRE_PASSWD = 0x00010000; // DO_NOT_EXPIRE_PASSWD value taken from MSDN directly

    const CONTROL_ATTRIBUTE = 'userAccountControl';
    const PSO_ATTRIBUTE = 'msDS-ResultantPSO';

    /**
     * Constructor
     *
     * @param string $username The username (without system magic quotes)
     * @param client $client LDAP client instance
     * @throw ldap_command_error
     */
    public function __construct($username, client $client) {
        // Fix MDL-10921
        $client->set_option(LDAP_OPT_REFERRALS, 0);

        parent::__construct($username, $client);
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
    public function login($password, $expiredok = false):bool {
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
            // If login fails retrieve the diagnostic
            // message to see if this is due to an expired password, or that the user is forced to
            // change the password on first login. If it is, only proceed if we can change
            // password from Moodle (otherwise we'll get stuck later in the login process).
            if ($expiredok) {

                if ($msg = $this->client->get_diagnostic_message(true)) {
                    // The format of the diagnostic message is (actual examples from W2003 and W2008):
                    // "80090308: LdapErr: DSID-0C090334, comment: AcceptSecurityContext error, data 52e, vece"  (W2003)
                    // "80090308: LdapErr: DSID-0C090334, comment: AcceptSecurityContext error, data 773, vece"  (W2003)
                    // "80090308: LdapErr: DSID-0C0903AA, comment: AcceptSecurityContext error, data 52e, v1771" (W2008)
                    // "80090308: LdapErr: DSID-0C0903AA, comment: AcceptSecurityContext error, data 773, v1771" (W2008)
                    // We are interested in the 'data nnn' part.
                    //   if nnn == 773 then user must change password on first login
                    //   if nnn == 532 then user password has expired
                    $bits = explode(',', $msg);
                    return count($bits) > 2 && preg_match('/data (773|532)/i', trim($diagmsg[2]));
                }
            }
            return false;
        } finally {
            // Rebind.
            $this->client->bind_admin();
        }
    }

    /**
     * Creates a new user on LDAP.
     *
     * @param array $newuser
     * @param string $hashedpassword
     * @throw error
     * @throw ldap_command_error
     */
    protected function _create(array $newuser, $hashedpassword): void {
        if (empty($this->config->user_create_context)) {
            throw new configuration_error('user_create_context');
        }

        // User account creation is a two step process with AD. First you
        // create the user object, then you set the password. If you try
        // to set the password while creating the user, the operation
        // fails.

        // Passwords in Active Directory must be encoded as Unicode
        // strings (UCS-2 Little Endian format) and surrounded with
        // double quotes. See http://support.microsoft.com/?kbid=269190
        if (!function_exists('mb_convert_encoding')) {
            throw new installation_error('needmbstring');
        }

        // Check for invalid sAMAccountName characters.
        if (preg_match('#[/\\[\]:;|=,+*?<>@"]#', $this->username)) {
            throw new error('ad_invalidchars', '', $this->username);
        }

        // First create the user account, and mark it as disabled.
        $newuser['objectClass'] = ['user', 'organizationalPerson', 'person', 'top'];
        $newuser['sAMAccountName'] = $this->username;
        $newuser[self::CONTROL_ATTRIBUTE] = self::NORMAL_ACCOUNT | self::ACCOUNT_DISABLE;
        $userdn = 'cn='.$this->client->add_slashes($this->username).','.$this->config->user_create_context;

        $this->client->add($userdn, $newuser);

        // Now set the password
        try {
            $this->_update_password($hashedpassword);
        } catch (\moodle_exception $e) {
            // Something went wrong: delete the user account and error out
            try {
                $this->client->delete($userdn);
            } finally {
                throw $e;
            }
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
        $attributes[] = self::CONTROL_ATTRIBUTE;
        $attributes[] = self::PSO_ATTRIBUTE;
        return parent::info_array($attributes);
    }

    /**
     * Returns control attribute value
     *
     * @return int
     * @throw user_not_found_error
     * @throw ldap_command_error
     */
    function control_attribute(): int {
        return $this->info([])[self::CONTROL_ATTRIBUTE];
    }

    /**
     * Returns control attribute value
     *
     * @return string with no magic quotes
     * @throw user_not_found_error
     * @throw ldap_command_error
     */
    function pso_attribute(): string {
        return $this->info([])[self::PSO_ATTRIBUTE];
    }

    /**
     * Returns Unix timestamp from password_expiration_attribute value
     *
     * @param ?array $info optional info array
     * @param string $expiry
     * @return int
     * @throw error
     * @throw no_attribute_value_error
     * @throw ldap_command_error
     */
    protected function _password_expiry_timestamp($expiry, $info = []): int {
        if (!function_exists('bcsub')) {
            throw new installation_error('needbcmath',);
        }

        // If DO_NOT_EXPIRE_PASSWD flag is set in user's
        // userAccountControl attribute, the password doesn't expire.
        if (array_key_exists(self::CONTROL_ATTRIBUTE, $info)) {
            $acctctrl = $info[self::CONTROL_ATTRIBUTE];
        } else {
            $acctctrl = $this->control_attribute();
        }
        if (!$acctctrl) {
            throw new no_attribute_value_error(self::CONTROL_ATTRIBUTE, $this->dn());
        }

        if ($acctctrl & self::DO_NOT_EXPIRE_PASSWD) {
            // Password doesn't expire.
            return 0;
        }

        // If pwdLastSet is zero, the user must change his/her password now
        // (unless DO_NOT_EXPIRE_PASSWD flag is set, but we already
        // tested this above)
        if ($expiry === '0') {
            // Password has expired
            return -1;
        }

        // ----------------------------------------------------------------
        // Password expiration time in Active Directory is the composition of
        // two values:
        //
        //   - User's pwdLastSet attribute, that stores the last time
        //     the password was changed.
        //
        //   - Domain's maxPwdAge attribute, that sets how long
        //     passwords last in this domain.
        //
        // We already have the first value (passed in as a parameter). We
        // need to get the second one. As we don't know the domain DN, we
        // have to query rootDSE's defaultNamingContext attribute to get
        // it. Then we have to query that DN's maxPwdAge attribute to get
        // the real value.
        //
        // Once we have both values, we just need to combine them. But MS
        // chose to use a different base and unit for time measurements.
        // So we need to convert the values to Unix timestamps (see
        // details below).
        // ----------------------------------------------------------------
        $maxpwdage = null;

        if (array_key_exists(self::PSO_ATTRIBUTE, $info)) {
            $userpso = $info[self::PSO_ATTRIBUTE];
        } else {
            $userpso = $this->pso_attribute();
        }
        if ($userpso) {
            // If a PSO exists, FGPP is being utilized.
            // Grab the new maxpwdage from the msDS-MaximumPasswordAge attribute of the PSO.
            $maxpwdage = $this->find_any($userpso, ['msDS-MaximumPasswordAge']);
        }

        if ($maxpwdage === null) {
            $namingcontexts = $this->client->global_attribute('defaultNamingContext');
            if (!$namingcontexts) {
                throw new no_attribute_value_error('defaultNamingContext', $this->config->root_ds);
            }
            $maxpwdage = $this->client->find_any($namingcontexts[0], ['maxPwdAge']);

            if ($maxpwdage === null) {
                throw new no_attribute_value_error('maxPwdAge', $namingcontexts[0]);
            }
        }
        $maxpwdage = (string) $maxpwdage;

        // ----------------------------------------------------------------
        // MSDN says that "pwdLastSet contains the number of 100 nanosecond
        // intervals since January 1, 1601 (UTC), stored in a 64 bit integer".
        //
        // According to Perl's Date::Manip, the number of seconds between
        // this date and Unix epoch is 11644473600. So we have to
        // substract this value to calculate a Unix time, once we have
        // scaled pwdLastSet to seconds. This is the script used to
        // calculate the value shown above:
        //
        //    #!/usr/bin/perl -w
        //
        //    use Date::Manip;
        //
        //    $date1 = ParseDate ("160101010000 UTC");
        //    $date2 = ParseDate ("197001010000 UTC");
        //    $delta = DateCalc($date1, $date2, \$err);
        //    $secs = Delta_Format($delta, 0, "%st");
        //    print "$secs \n";
        //
        // MSDN also says that "maxPwdAge is stored as a large integer that
        // represents the number of 100 nanosecond intervals from the time
        // the password was set before the password expires." We also need
        // to scale this to seconds. Bear in mind that this value is stored
        // as a _negative_ quantity (at least in my AD domain).
        //
        // As a last remark, if the low 32 bits of maxPwdAge are equal to 0,
        // the maximum password age in the domain is set to 0, which means
        // passwords do not expire (see
        // http://msdn2.microsoft.com/en-us/library/ms974598.aspx)
        //
        // As the quantities involved are too big for PHP integers, we
        // need to use BCMath functions to work with arbitrary precision
        // numbers.
        // ----------------------------------------------------------------

        // If the low order 32 bits are 0, then passwords do not expire in
        // the domain. Just do '$maxpwdage mod 2^32' and check the result
        // (2^32 = 4294967296)
        if (bcmod ($maxpwdage, '4294967296') === '0') {
            return 0;
        }

        // Add up pwdLastSet and maxPwdAge to get password expiration
        // time, in MS time units. Remember maxPwdAge is stored as a
        // _negative_ quantity, so we need to substract it in fact.
        $pwdexpire = bcsub ($expiry, $maxpwdage);

        // Scale the result to convert it to Unix time units and return
        // that value.
        return (int) bcsub( bcdiv($pwdexpire, '10000000'), '11644473600');
    }

    /**
     * Activates (enables) user in LDAP so user can login
     *
     * @throw no_attribute_value_error
     * @throw ldap_command_error
     */
    public function activate(): void {
        // We need to unset the ACCOUNT_DISABLE bit in the
        // userAccountControl attribute ( see
        // http://support.microsoft.com/kb/305144 )
        $acctctrl = $this->control_attribute();
        if ($acctctrl === null) {
            throw new no_attribute_value_error(self::CONTROL_ATTRIBUTE, $this->dn());
        }
        $this->modify([self::CONTROL_ATTRIBUTE => $acctctrl & (~self::ACCOUNT_DISABLE)]);
    }

    /**
     * Changes user password in LDAP
     *
     * @param string  $hashedpassword
     * @throw error
     * @throw ldap_command_error
     */
    protected function _update_password($hashedpassword): void {
        // Passwords in Active Directory must be encoded as Unicode
        // strings (UCS-2 Little Endian format) and surrounded with
        // double quotes. See http://support.microsoft.com/?kbid=269190
        if (!function_exists('mb_convert_encoding')) {
            throw new installation_error('needmbstring');
        }
        $extpassword = mb_convert_encoding('"'.$hashedpassword.'"', "UCS-2LE", 'UTF-8');
        $this->modify(['unicodePwd' => $hashedpassword]);
    }

    /**
     * Check if a user is suspended. MS Active Directory supports it and expose information
     * through a field.
     *
     * @param ?array $info optional info array
     * @return boolean
     * @throw no_attribute_value_error
     * @throw ldap_command_error
     */
    public function is_suspended($info = []): bool {
        if (empty($this->config->suspended_attribute)) {
            if (array_key_exists(self::CONTROL_ATTRIBUTE, $info)) {
                $acctctrl = $info[self::CONTROL_ATTRIBUTE];
            } else {
                $acctctrl = $this->control_attribute();
            }
            if ($acctctrl === null) {
                throw new no_attribute_value_error($ctrlattr, $this->dn());
            }
            return (bool) ($acctctrl & self::ACCOUNT_DISABLE);
        }

        return parent::is_suspended($info);
    }
}
