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
 * LDAP  client tests.
 *
 * @package    connect_ldap
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace connect_ldap\test;

use connect_ldap\client;

defined('MOODLE_INTERNAL') || die();

/**
 * LDAP  client tests.
 *
 * @package    connect_ldap
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class client_test extends ldap_testcase {

    /**
     * Tests for add_slashes
     *
     * See http://tools.ietf.org/html/rfc4514#section-5.2 if you want
     * to add additional tests.
     */
    public function test_add_slashes(): void {
        $tests = [
            [
                'test' => 'Simplest',
                'expected' => 'Simplest',
            ],
            [
                'test' => 'Simple case',
                'expected' => 'Simple\\20case',
            ],
            [
                'test' => 'Medium ‒ case',
                'expected' => 'Medium\\20‒\\20case',
            ],
            [
                'test' => '#Harder+case#',
                'expected' => '\\23Harder\\2bcase\\23',
            ],
            [
                'test' => ' Harder (and); harder case ',
                'expected' => '\\20Harder\\20(and)\\3b\\20harder\\20case\\20',
            ],
            [
                'test' => 'Really \\0 (hard) case!\\',
                'expected' => 'Really\\20\\5c0\\20(hard)\\20case!\\5c',
            ],
            [
                'test' => 'James "Jim" = Smith, III',
                'expected' => 'James\\20\\22Jim\22\\20\\3d\\20Smith\\2c\\20III',
            ],
            [
                'test' => '  <jsmith@example.com> ',
                'expected' => '\\20\\20\\3cjsmith@example.com\\3e\\20',
            ],
        ];


        foreach ($tests as $test) {
            $this->assertSame($test['expected'], client::add_slashes($test['test']));
        }
    }

    /**
     * Tests for strip_slashes
     *
     * See http://tools.ietf.org/html/rfc4514#section-5.2 if you want
     * to add additional tests.
     */
    public function test_strip_slashes(): void {
        // IMPORTANT NOTICE: While add_slashes() only produces one
        // of the two defined ways of escaping/quoting (the ESC HEX
        // HEX way defined in the grammar in Section 3 of RFC-4514)
        // strip_slashes() has to deal with both of them. So in
        // addition to testing the same strings we test in
        // test_strip_slashes(), we need to also test strings
        // using the second method.

        $tests = [
            [
                'test' => 'Simplest',
                'expected' => 'Simplest',
            ],
            [
                'test' => 'Simple\\20case',
                'expected' => 'Simple case',
            ],
            [
                'test' => 'Simple\\ case',
                'expected' => 'Simple case',
            ],
            [
                'test' => 'Simple\\ \\63\\61\\73\\65',
                'expected' => 'Simple case',
            ],
            [
                'test' => 'Medium\\ ‒\\ case',
                'expected' => 'Medium ‒ case',
            ],
            [
                'test' => 'Medium\\20‒\\20case',
                'expected' => 'Medium ‒ case',
            ],
            [
                'test' => 'Medium\\20\\E2\\80\\92\\20case',
                'expected' => 'Medium ‒ case',
            ],
            [
                'test' => '\\23Harder\\2bcase\\23',
                'expected' => '#Harder+case#',
            ],
            [
                'test' => '\\#Harder\\+case\\#',
                'expected' => '#Harder+case#',
            ],
            [
                'test' => '\\20Harder\\20(and)\\3b\\20harder\\20case\\20',
                'expected' => ' Harder (and); harder case ',
            ],
            [
                'test' => '\\ Harder\\ (and)\\;\\ harder\\ case\\ ',
                'expected' => ' Harder (and); harder case ',
            ],
            [
                'test' => 'Really\\20\\5c0\\20(hard)\\20case!\\5c',
                'expected' => 'Really \\0 (hard) case!\\',
            ],
            [
                'test' => 'Really\\ \\\\0\\ (hard)\\ case!\\\\',
                'expected' => 'Really \\0 (hard) case!\\',
            ],
            [
                'test' => 'James\\20\\22Jim\\22\\20\\3d\\20Smith\\2c\\20III',
                'expected' => 'James "Jim" = Smith, III',
            ],
            [
                'test' => 'James\\ \\"Jim\\" \\= Smith\\, III',
                'expected' => 'James "Jim" = Smith, III',
            ],
            [
                'test' => '\\20\\20\\3cjsmith@example.com\\3e\\20',
                'expected' => '  <jsmith@example.com> ',
            ],
            [
                'test' => '\\ \\<jsmith@example.com\\>\\ ',
                'expected' => ' <jsmith@example.com> ',
            ],
            [
                'test' => 'Lu\\C4\\8Di\\C4\\87',
                'expected' => 'Lučić',
            ],
        ];

        foreach ($tests as $test) {
            $this->assertSame($test['expected'], client::strip_slashes($test['test']));
        }
    }

    /**
     * Tests for normalise_objectclass.
     *
     * @dataProvider normalise_objectclass_provider
     * @param array $args Arguments passed to normalise_objectclass
     * @param string $expected The expected objectclass filter
     */
    public function test_normalise_objectclass($arg, $expected): void {
        $this->assertEquals($expected, client::normalise_objectclass($arg));
    }

    /**
     * Data provider for the test_normalise_objectclass testcase.
     *
     * @return array of testcases.
     */
    public function normalise_objectclass_provider(): array {
        return [
            'Empty value' => [
                null,
                '(objectClass=*)',
            ],
            'Supplied unwrapped objectClass' => [
                'objectClass=tiger',
                '(objectClass=tiger)',
            ],
            'Supplied string value' => [
                'leopard',
                '(objectClass=leopard)',
            ],
            'Supplied complex' => [
                '(&(objectClass=cheetah)(enabledMoodleUser=1))',
                '(&(objectClass=cheetah)(enabledMoodleUser=1))',
            ],
        ];
    }

    /**
     * Tests for read().
     */
    public function test_read(): void {
        global $CFG;

        $ldap = $this->client();

        // Add all the test objects.
        $testobjects = $this->get_entries_test_objects();
        $this->add_test_objects($ldap, self::$containerdn, $testobjects);

        // Now query about them and compare results.
        foreach ($testobjects as $object) {
            $dn = $this->get_object_dn($object, self::$containerdn);
            $filter = $object['query']['filter'];
            $attributes = $object['query']['attributes'];

            $entries = $ldap->read($dn, $filter, $attributes);
            $actual = array_keys($entries[0]);

            // We need to sort both arrays to be able to compare them, as the LDAP server
            // might return attributes in any order.
            $expected = $attributes;
            if (!in_array('dn', $expected)) {
                $expected[] = 'dn';
            }
            sort($expected);
            sort($actual);
            $this->assertEquals($expected, $actual);
        }
    }

    /**
     * Provide the array of test objects for the get_entries_moodle test case.
     *
     * @return array of test objects
     */
    protected function get_entries_test_objects(): array {
        return [
            // Test object 1.
            [
                // Add/remove this object to LDAP directory? There are existing standard LDAP
                // objects that we might want to test, but that we shouldn't add/remove ourselves.
                'addremove' => true,
                // Relative (to test container) or absolute distinguished name (DN).
                'relativedn' => true,
                // Distinguished name for this object (interpretation depends on 'relativedn').
                'dn' => 'cn=test1',
                // Values to add to LDAP directory.
                'values' => [
                    'objectClass' => ['inetOrgPerson', 'organizationalPerson', 'person', 'posixAccount'],
                    'cn' => 'test1',  // We don't care about the actual values, as long as they are unique.
                    'sn' => 'test1',
                    'givenName' => 'test1',
                    'uid' => 'test1',
                    'uidNumber' => '20001',  // Start from 20000, then add test number.
                    'gidNumber' => '20001',  // Start from 20000, then add test number.
                    'homeDirectory' => '/',
                    'userPassword' => '*',
                ],
                // Attributes to query the object for.
                'query' => [
                    'filter' => '(objectClass=posixAccount)',
                    'attributes' => [
                        'cn',
                        'sn',
                        'givenName',
                        'uid',
                        'uidNumber',
                        'gidNumber',
                        'homeDirectory',
                        'userPassword'
                    ],
                ],
            ],
            // Test object 2.
            [
                'addremove' => true,
                'relativedn' => true,
                'dn' => 'cn=group2',
                'values' => [
                    'objectClass' => ['top', 'posixGroup'],
                    'cn' => 'group2',  // We don't care about the actual values, as long as they are unique.
                    'gidNumber' => '20002',  // Start from 20000, then add test number.
                    'memberUid' => '20002',  // Start from 20000, then add test number.
                ],
                'query' => [
                    'filter' => '(objectClass=posixGroup)',
                    'attributes' => [
                        'cn',
                        'gidNumber',
                        'memberUid'
                    ],
                ],
            ],
            // Test object 3.
            [
                'addremove' => false,
                'relativedn' => false,
                'dn' => '',  // To query the RootDSE, we must specify the empty string as the absolute DN.
                'values' => [
                ],
                'query' => [
                    'filter' => '(objectClass=*)',
                    'attributes' => [
                        'supportedControl',
                        'namingContexts'
                    ],
                ],
            ],
        ];
    }

    /**
     * Add the test objects to the test container.
     *
     * @param client $ldap        LDAP client
     * @param string $containerdn The distinguished name of the container for the created objects.
     * @param array $testobjects Array of the tests objects to create. The structure of
     *              the array elements *must* follow the structure of the value returned
     *              by get_entries_test_objects() member function.
     */
    protected function add_test_objects($ldap, $containerdn, $testobjects): void {
        foreach ($testobjects as $object) {
            if ($object['addremove'] !== true) {
                continue;
            }

            $dn = $this->get_object_dn($object, $containerdn);
            $entry = $object['values'];
            $ldap->add($dn, $entry);
        }
    }

    /**
     * Remove the test objects from the test container.
     *
     * @param client $ldap        LDAP client
     * @param string $containerdn The distinguished name of the container for the objects to remove.
     * @param array $testobjects Array of the tests objects to create. The structure of
     *              the array elements *must* follow the structure of the value returned
     *              by get_entries_test_objects() member function.
     *
     */
    protected function remove_test_objects($ldap, $containerdn, $testobjects): void {
        foreach ($testobjects as $object) {
            if ($object['addremove'] !== true) {
                continue;
            }
            $dn = $this->get_object_dn($object, $containerdn);
            $ldap->delete($dn);
        }
    }

    /**
     * Get the distinguished name (DN) for a given object.
     *
     * @param object $object The LDAP object to calculate the DN for.
     * @param string $containerdn The DN of the container to use for objects with relative DNs.
     *
     * @return string The calculated DN.
     */
    protected function get_object_dn($object, $containerdn): string {
        if ($object['relativedn']) {
            $dn = $object['dn'].','.$containerdn;
        } else {
            $dn = $object['dn'];
        }
        return $dn;
    }
}
