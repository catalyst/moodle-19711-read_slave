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
 * LDAP testcase class.
 *
 * NOTE: in order to execute LDAP tests you need to set up OpenLDAP server with core,
 *       cosine, nis and internet schemas and add configuration values to
 *       config.php configuration file.  The bind users *needs*
 *       permissions to create objects in the LDAP server, under the bind domain.
 *
 * $CFG->phpunit_ldap = [
 *     'host_url'  => 'ldap://172.0.0.1',
 *     'bind_dn'   => 'cn=admin,dc=example,dc=org',
 *     'bind_pw'   => 'password',
 *     'domain'    => 'dc=example,dc=org',
 *     'user_type' => one of connect_ldap\client::USER_TYPE
 * ];
 *
 * @package    connect_ldap
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace connect_ldap\test;

use connect_ldap\client;
use connect_ldap\exception\ldap_command_error;

defined('MOODLE_INTERNAL') || die();

/**
 * LDAP testcase class.
 *
 * @package    connect_ldap
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ldap_testcase extends \advanced_testcase {

    const TEST_CONTAINER = 'moodletest';

    protected static $setupclient;
    protected static $containerdn;
    protected static $usersdn;

    public static function setUpBeforeClass(): void {
        global $CFG;

        parent::setUpBeforeClass();

        if (empty($CFG->phpunit_ldap)) {
            return;
        }

        $url = $CFG->phpunit_ldap['host_url'];
        $domain = $CFG->phpunit_ldap['domain'];

        self::$setupclient = new client($url, (object) $CFG->phpunit_ldap);
        self::$containerdn = sprintf("dc=%s,%s", self::TEST_CONTAINER, $domain);
        self::$usersdn = sprintf("ou=users,%s", self::$containerdn);

        try {
            self::delete_test_container();
        } catch (ldap_command_error $e) {
            //Ignore.
        }

        $object = [
            'objectClass' => ['dcObject', 'organizationalUnit'],
            'dc' => self::TEST_CONTAINER,
            'ou' => self::TEST_CONTAINER,
        ];
        self::$setupclient->add(self::$containerdn, $object);

        $object = [
            'objectClass' => ['organizationalUnit'],
            'ou' => 'users',
        ];
        self::$setupclient->add(self::$usersdn, $object);
    }

    public static function tearDownAfterClass(): void {
        if (self::$setupclient) {
            self::delete_test_container();
        }

        parent::tearDownAfterClass();
    }

    protected static function delete_test_container(): void {
        $filter = 'dc='.self::TEST_CONTAINER;
        $filter = '(objectClass=*)';
        try {
            if ($res = self::$setupclient->search(self::$containerdn, 'cn=*', ['dn'])) {
                foreach ($res as $i) {
                    if (isset($i['dn'])) {
                        self::$setupclient->delete($i['dn']);
                    }
                }
            }
            if ($res = self::$setupclient->search(self::$containerdn, 'ou=*', ['dn'])) {
                foreach ($res as $i) {
                    if (isset($i['dn']) and $res[0]['dn'] != $i['dn']) {
                        self::$setupclient->delete($i['dn']);
                    }
                }
            }
        } catch (ldap_command_error $e) {
            //Ignore.
        }

        self::$setupclient->delete(self::$containerdn);
    }

    /**
     * Set up connect_ldap config
     *
     * @param ?array $config Extra connect_ldap config values to set
     * @return array $config config values
     */
    protected function config_setup(array $config = []): array {
        global $CFG;

        $this->resetAfterTest();

        if (!self::$containerdn) {
            $this->markTestSkipped('Test LDAP test server not configured.');
        }

        $plugin = 'connect_ldap';
        unset_all_config_for_plugin($plugin);
        $config = array_merge(
            $CFG->phpunit_ldap,
            [
                'user_contexts' => self::$usersdn,
                'user_search_sub' => false,
                'passtype' => 'md5',
            ],
            $config
        );

        foreach ($config as $key => $val) {
            set_config($key, $val, $plugin);
        }

        return $config;
    }

    /**
     * Set up connect_ldap config and instantiate client
     *
     * @param ?array $config Extra connect_ldap config values to set
     * @return client
     */
    protected function client(array $config = []): client {
        $config = $this->config_setup($config);

        return new client($config['host_url'], (object) $config);
    }
}
