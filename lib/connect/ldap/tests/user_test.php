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
 * LDAP user tests.
 *
 * @package    connect_ldap
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace connect_ldap\test;

use connect_ldap\client,
    connect_ldap\users,
    connect_ldap\user\ad,
    connect_ldap\user\rfc2307;
use connect_ldap\exception\installation_error;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper that exposes config
 *
 * @package    connect_ldap
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class t_users extends users {
    public $config;
}

/**
 * LDAP user tests.
 *
 * @package    connect_ldap
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_test extends ldap_testcase {
    /**
     * General LDAP user class testcase
     */
    public function test_create() {
        $uid = 'test_username';
        $password = 'password';
        $u = [
            'givenName' => 'Firstname',
            'sn'        => 'Lastname',
            'mail'      => "$uid@example.com",
            // 'member'  => ['group1'], cannot do that we may not have a memberOf overlay
        ];
        $update = [
            'givenName' => 'Blah',
        ];

        $ldap = $this->client();
        $users = new t_users($ldap);
        $user = $users->user($uid);

        $this->assertEquals([], $users->list());
        $this->assertFalse($user->exists());

        try {
            $user->create($u, $password);
            $this->assertTrue($user->exists());

            $expected = $u;
            $expected[$users->config->user_attribute] = $uid;
            $this->assertEquals($expected, $user->info(array_keys($u)));

            $user->activate();
            $this->assertTrue($user->login($password));

            $password = 'password2';
            $user->update_password('password2');
            $this->assertTrue($user->login($password));

            $before = $user->info(array_keys($u));
            $user->update($update);
            $this->assertEquals(array_merge($before, $update), $user->info(array_keys($u)));

            // $this->assertEquals(['group1'], $user->groups('memberOf'));

            $this->assertEquals(['test_username'], $users->list());

            $users->config->member_attribute_isdn = 1;
            $this->assertEquals([$uid], $users->get_uids([$user->dn()]));
        } finally {
            self::$setupclient->delete($user->dn());
        }
    }

    /**
     * Test user type ad
     */
    public function test_user_ad(): void {
        $config = $this->config_setup([
            'user_type' => 'ad',
        ]);

        $uid = 'test_username';
        $password1 = 'password';
        $password2 = 'password2';
        $userdn = "cn=$uid,".static::$usersdn;

        $this->t_user(
            $config,
            $uid,
            $userdn,
            [$password1, $password2],
            [
                'givenName' => 'Firstname',
                'sn'        => 'Lastname',
                'mail'      => "$uid@example.com",
            ],
            function ($user) use ($uid, $userdn, $password1, $password2, $config) {
                return [
                    [
                        $this->equalTo('ldap_add'),
                        $this->equalTo([$userdn, [
                            'givenName' => 'Firstname',
                            'sn'        => 'Lastname',
                            'mail'      => "$uid@example.com",
                            'sAMAccountName' => 'test_username',
                            'userAccountControl' => ad::NORMAL_ACCOUNT | ad::ACCOUNT_DISABLE,
                            'objectClass' => ['user', 'organizationalPerson', 'person', 'top'],
                        ]])
                    ],
                    [
                        $this->equalTo('ldap_modify'),
                        $this->equalTo([$userdn, [
                            'unicodePwd' => $user->encode_password($password1)
                        ]])
                    ],
                    [
                        $this->equalTo('ldap_read'),
                        $this->equalTo([$userdn, '(objectClass=*)', [
                            'userAccountControl',
                            'msDS-ResultantPSO',
                            'cn',
                            'pwdLastSet',
                        ]])
                    ],
                    [
                        $this->equalTo('ldap_modify'),
                        $this->equalTo([$userdn, [
                            'userAccountControl' => ad::NORMAL_ACCOUNT,
                        ]])
                    ],
                    [
                        $this->equalTo('ldap_read'),
                        $this->equalTo([$userdn, '(objectClass=*)', [
                            'userAccountControl',
                            'msDS-ResultantPSO',
                            'cn',
                            'pwdLastSet',
                        ]])
                    ],
                    [
                        $this->equalTo('ldap_bind'),
                        $this->equalTo([$userdn, $password1])
                    ],
                    [
                        $this->equalTo('ldap_bind'),
                        $this->equalTo([$config['bind_dn'], $config['bind_pw']])
                    ],
                    [
                        $this->equalTo('ldap_modify'),
                        $this->equalTo([$userdn, [
                            'unicodePwd' => $user->encode_password($password2)
                        ]])
                    ],
                    [
                        $this->equalTo('ldap_read'),
                        $this->equalTo([$userdn, '(objectClass=*)', [
                            'pwdLastSet',
                            'userAccountControl',
                            'msDS-ResultantPSO',
                            'cn',
                        ]])
                    ],
                ];
            },
            function ($attr, $user, $calledcnt) use ($password1, $password2) {
                if ($attr == 'userAccountControl') {
                    switch ($calledcnt) {
                        case 1: return ad::NORMAL_ACCOUNT | ad::ACCOUNT_DISABLE;
                        default: return ad::NORMAL_ACCOUNT;
                    }
                }
                return $attr;
            }
        );

        $group = 'testgrp';
        $members = ['member1', 'member2'];
        $memberattribute = 'isMember';

        $this->t_group(
            $config,
            $group,
            $memberattribute,
            $members,
            array_merge(
                [
                    [
                        $this->equalTo('ldap_read'),
                        $this->equalTo([$group, '(objectClass=*)', ['objectClass', $memberattribute]])
                    ],
                ],
                array_map(function ($m) use ($memberattribute) {
                    return [
                        $this->equalTo('ldap_read'),
                        $this->equalTo([$m, '(objectClass=*)', ['objectClass', $memberattribute]])
                    ];
                }, $members)
            ),
            function ($attr, $g, $calledcnt) use ($memberattribute, $members) {
                if ($attr == 'objectClass') {
                    switch ($calledcnt) {
                        case 1: return 'group';
                        default: return "user";
                    }
                }
                if ($attr == $memberattribute) {
                    switch ($calledcnt) {
                        case 1: return $members;
                        default: return [];
                    }
                }
                return $attr;
            }
        );
    }

    /**
     * Test user type edir
     */
    public function test_user_edir(): void {
        $config = $this->config_setup([
            'user_type' => 'edir',
        ]);

        $uid = 'test_username';
        $password1 = 'password';
        $password2 = 'password2';
        $userdn = "cn=$uid,".static::$usersdn;

        $this->t_user(
            $config,
            $uid,
            $userdn,
            [$password1, $password2],
            [
                'givenName' => 'Firstname',
                'sn'        => 'Lastname',
                'mail'      => "$uid@example.com",
            ],
            function ($user) use ($uid, $userdn, $password1, $password2, $config) {
                return [
                    [
                        $this->equalTo('ldap_add'),
                        $this->equalTo([$userdn, [
                            'givenName' => 'Firstname',
                            'sn'        => 'Lastname',
                            'mail'      => "$uid@example.com",
                            'objectClass' => ['inetOrgPerson', 'organizationalPerson', 'person', 'top'],
                            'uniqueId' => 'test_username',
                            'loginDisabled' => 'TRUE',
                            'userPassword' => $user->encode_password($password1),
                        ]])
                    ],
                    [
                        $this->equalTo('ldap_modify'),
                        $this->equalTo([$userdn, [
                            'loginDisabled' => 'FALSE',
                        ]])
                    ],
                    [
                        $this->equalTo('ldap_bind'),
                        $this->equalTo([$userdn, $password1])
                    ],
                    [
                        $this->equalTo('ldap_bind'),
                        $this->equalTo([$config['bind_dn'], $config['bind_pw']])
                    ],
                    [
                        $this->equalTo('ldap_read'),
                        $this->equalTo([$userdn, '(objectClass=*)', [
                            'passwordExpirationTime',
                            'passwordExpirationInterval',
                            'loginGraceLimit',
                            'cn',
                        ]])
                    ],
                    [
                        $this->equalTo('ldap_modify'),
                        $this->equalTo([$userdn, [
                            'userPassword' => $user->encode_password($password2),
                            'loginGraceRemaining' => '1',
                        ]])
                    ],
                    [
                        $this->equalTo('ldap_read'),
                        $this->equalTo([$userdn, '(objectClass=*)', ['passwordExpirationTime', 'cn']])
                    ],
                ];
            },
            function ($attr, $user, $calledcnt) use ($password1, $password2) {
                if ($attr == 'loginDisabled') {
                    switch ($calledcnt) {
                        case 1: return 'TRUE';
                        default: return 'FALSE';
                    }
                }
                if ($attr == 'passwordExpirationTime') {
                    $dt = new \DateTime();
                    $dt->add(new \DateInterval("P365D"));
                    return $dt->format('YmdHis');
                }
                if ($attr == 'passwordExpirationInterval') {
                    return 0;
                }
                if ($attr == 'loginGraceLimit') {
                    return 1;
                }
                return $attr;
            },
            365
        );
    }

    /**
     * Test user type rfc2307
     */
    public function test_user_rfc2307(): void {
        $config = $this->config_setup([
            'user_type' => 'rfc2307',
        ]);

        $uid = 'test_username';
        $password1 = 'password';
        $password2 = 'password2';
        $userdn = "uid=$uid,".static::$usersdn;

        $this->t_user(
            $config,
            $uid,
            $userdn,
            [$password1, $password2],
            [
                'givenName' => 'Firstname',
                'sn'        => 'Lastname',
                'mail'      => "$uid@example.com",
            ],
            function ($user) use ($uid, $userdn, $password1, $password2, $config) {
                return [
                    [
                        $this->equalTo('ldap_add'),
                        $this->equalTo([$userdn, [
                            'givenName' => 'Firstname',
                            'sn'        => 'Lastname',
                            'mail'      => "$uid@example.com",
                            'objectClass' => ['posixAccount', 'inetOrgPerson', 'organizationalPerson', 'person', 'top'],
                            'cn' => 'test_username',
                            'uid' => 'test_username',
                            'uidNumber' => rfc2307::UID_NOBODY,
                            'gidNumber' => rfc2307::GID_NOGROUP,
                            'homeDirectory' => '/',
                            'loginShell' => '/bin/false',
                            'userPassword' => '*'.$user->encode_password($password1),
                        ]])
                    ],
                    [
                        $this->equalTo('ldap_read'),
                        $this->equalTo([$userdn, '(objectClass=*)', ['userPassword', 'uid', 'shadowExpire']])
                    ],
                    [
                        $this->equalTo('ldap_modify'),
                        $this->equalTo([$userdn, [
                            'userPassword' => $user->encode_password($password1),
                        ]])
                    ],
                    [
                        $this->equalTo('ldap_bind'),
                        $this->equalTo([$userdn, $password1])
                    ],
                    [
                        $this->equalTo('ldap_bind'),
                        $this->equalTo([$config['bind_dn'], $config['bind_pw']])
                    ],
                    [
                        $this->equalTo('ldap_modify'),
                        $this->equalTo([$userdn, [
                            'userPassword' => $user->encode_password($password2),
                        ]])
                    ],
                    [
                        $this->equalTo('ldap_read'),
                        $this->equalTo([$userdn, '(objectClass=*)', ['shadowExpire', 'uid']])
                    ],
                ];
            },
            function ($attr, $user, $calledcnt) use ($password1, $password2) {
                if ($attr == 'userPassword') {
                    switch ($calledcnt) {
                        case 1: return '*'.$user->encode_password($password1);
                        default: return $user->encode_password($password1);
                    }
                }
                if ($attr == 'shadowExpire') {
                    return 0;
                }
                return $attr;
            }
        );

        $group = 'testgrp';
        $memberattribute = 'isMember';

        $this->t_group(
            $config,
            $group,
            $memberattribute,
            [$group],
            []
        );
        $this->assertDebuggingCalled(
            get_string('explodegroupusertypenotsupported', 'connect_ldap', 'rfc2307'),
            DEBUG_NORMAL
        );
    }

    /**
     * Test user type rfc2307bis
     */
    public function test_user_rfc2307bis(): void {
        $config = $this->config_setup([
            'user_type' => 'rfc2307bis',
        ]);

        $uid = 'test_username';
        $password1 = 'password';
        $password2 = 'password2';
        $userdn = "uid=$uid,".static::$usersdn;

        $this->t_user(
            $config,
            $uid,
            $userdn,
            [$password1, $password2],
            [
                'givenName' => 'Firstname',
                'sn'        => 'Lastname',
                'mail'      => "$uid@example.com",
            ],
            function ($user) use ($uid, $userdn, $password1, $password2, $config) {
                return [
                    [
                        $this->equalTo('ldap_add'),
                        $this->equalTo([$userdn, [
                            'givenName' => 'Firstname',
                            'sn'        => 'Lastname',
                            'mail'      => "$uid@example.com",
                            'objectClass' => ['posixAccount', 'inetOrgPerson', 'organizationalPerson', 'person', 'top'],
                            'cn' => 'test_username',
                            'uid' => 'test_username',
                            'uidNumber' => rfc2307::UID_NOBODY,
                            'gidNumber' => rfc2307::GID_NOGROUP,
                            'homeDirectory' => '/',
                            'loginShell' => '/bin/false',
                            'userPassword' => '*'.$user->encode_password($password1),
                        ]])
                    ],
                    [
                        $this->equalTo('ldap_read'),
                        $this->equalTo([$userdn, '(objectClass=*)', ['userPassword', 'uid', 'shadowExpire']])
                    ],
                    [
                        $this->equalTo('ldap_modify'),
                        $this->equalTo([$userdn, [
                            'userPassword' => $user->encode_password($password1),
                        ]])
                    ],
                    [
                        $this->equalTo('ldap_bind'),
                        $this->equalTo([$userdn, $password1])
                    ],
                    [
                        $this->equalTo('ldap_bind'),
                        $this->equalTo([$config['bind_dn'], $config['bind_pw']])
                    ],
                    [
                        $this->equalTo('ldap_modify'),
                        $this->equalTo([$userdn, [
                            'userPassword' => $user->encode_password($password2),
                        ]])
                    ],
                    [
                        $this->equalTo('ldap_read'),
                        $this->equalTo([$userdn, '(objectClass=*)', ['shadowExpire', 'uid']])
                    ],
                ];
            },
            function ($attr, $user, $calledcnt) use ($password1, $password2) {
                if ($attr == 'userPassword') {
                    switch ($calledcnt) {
                        case 1: return '*'.$user->encode_password($password1);
                        default: return $user->encode_password($password1);
                    }
                }
                if ($attr == 'shadowExpire') {
                    return 0;
                }
                return $attr;
            }
        );
    }

    /**
     * Test user type samba
     */
    public function test_user_samba(): void {
        $config = $this->config_setup([
            'user_type' => 'samba',
            'password_expiration_attribute' => 'passwordExpires',
        ]);

        $uid = 'test_username';
        $password1 = 'password';
        $password2 = 'password2';
        $userdn = "uid=$uid,".static::$usersdn;

        $this->t_user(
            $config,
            $uid,
            $userdn,
            [$password1, $password2],
            [
                'givenName' => 'Firstname',
                'sn'        => 'Lastname',
                'mail'      => "$uid@example.com",
            ],
            function ($user) use ($uid, $userdn, $password1, $password2, $config) {
                return [
                    [
                        $this->equalTo('ldap_add'),
                        $this->equalTo([$userdn, [
                            'givenName' => 'Firstname',
                            'sn'        => 'Lastname',
                            'mail'      => "$uid@example.com",
                            'objectClass' => ['posixAccount', 'inetOrgPerson', 'organizationalPerson', 'person', 'top'],
                            'cn' => 'test_username',
                            'uid' => 'test_username',
                            'uidNumber' => rfc2307::UID_NOBODY,
                            'gidNumber' => rfc2307::GID_NOGROUP,
                            'homeDirectory' => '/',
                            'loginShell' => '/bin/false',
                            'userPassword' => '*'.$user->encode_password($password1),
                        ]])
                    ],
                    [
                        $this->equalTo('ldap_read'),
                        $this->equalTo([$userdn, '(objectClass=*)', ['userPassword', 'uid', 'passwordExpires']])
                    ],
                    [
                        $this->equalTo('ldap_modify'),
                        $this->equalTo([$userdn, [
                            'userPassword' => $user->encode_password($password1),
                        ]])
                    ],
                    [
                        $this->equalTo('ldap_bind'),
                        $this->equalTo([$userdn, $password1])
                    ],
                    [
                        $this->equalTo('ldap_bind'),
                        $this->equalTo([$config['bind_dn'], $config['bind_pw']])
                    ],
                    [
                        $this->equalTo('ldap_modify'),
                        $this->equalTo([$userdn, [
                            'userPassword' => $user->encode_password($password2),
                        ]])
                    ],
                    [
                        $this->equalTo('ldap_read'),
                        $this->equalTo([$userdn, '(objectClass=*)', ['passwordExpires', 'uid']])
                    ],
                ];
            },
            function ($attr, $user, $calledcnt) use ($password1, $password2) {
                if ($attr == 'userPassword') {
                    switch ($calledcnt) {
                        case 1: return '*'.$user->encode_password($password1);
                        default: return $user->encode_password($password1);
                    }
                }
                if ($attr == 'passwordExpires') {
                    return 0;
                }
                return $attr;
            }
        );
    }

    /**
     * Test user type classes
     *
     * @param array $config
     * @param string $username
     * @param string $userdn
     * @param array $passwords
     * @param array $userattrs
     * @param mixed $executecalls
     * @param mixed $readvals
     * @param ?int $passexpire
     */
    private function t_user(
        array $config,
        $username,
        $userdn,
        array $passwords,
        array $attrs,
        mixed $executecalls,
        mixed $readvals,
        $passexpire = 0
    ): void {
        list ($password1, $password2) = $passwords;

        $mockldap = $this->getMockBuilder(client::class)
                         ->onlyMethods([
                             'execute',
                             'for_each_entry',
                             'connect',
                             'global_attribute',
                             'get_dn',
                         ])
                         ->setConstructorArgs([$config['host_url'], (object) $config])
                         ->getMock();
        $users = new users($mockldap);
        $user = $users->user($username);

        if (is_callable($executecalls)) {
            $executecalls = $executecalls($user);
        }

        $mockldap->expects($this->any())
                 ->method('get_dn')
                 ->willReturnCallback(
                     function ($context, $filter, $user_attribute) use ($userdn) {
                         return $userdn;
                     });
        $mockldap->expects($this->exactly(count($executecalls)))
                 ->method('execute')
                 ->withConsecutive(...$executecalls)
                 ->willReturnCallback(
                     function ($fn, $args) use ($readvals, $user) {
                         static $calledcnt = 0;

                         if ($fn == 'ldap_read') {
                             $calledcnt++;
                             list ($dn, $filter, $attrs) = $args;
                             $ret = [];
                             foreach ($attrs as $a) {
                                 if ($readvals === null) {
                                     $val = $a;
                                 } elseif (is_callable($readvals)) {
                                     $val = $readvals($a, $user, $calledcnt);
                                 } else {
                                     $val = array_key_exists($a, $readvals) ? $readvals[$a] : $a;
                                 }
                                 $ret[$a] = $val;
                             }
                             return [$ret];
                         }
                         return true;
                     });
        $mockldap->expects($this->any())
                 ->method('for_each_entry')
                 ->willReturnCallback(
                     function ($recs, callable $cb) {
                         foreach ($recs as $r) {
                             $r1 = [];
                             foreach ($r as $k => $v) {
                                 $r1[$k] = is_array($v) ? $v : [$v];
                             }
                             $cb($r1);
                         }
                         return (bool) $recs;
                     });
        $user->create($attrs, $password1);
        $user->activate();
        $this->assertFalse($user->is_suspended());
        $this->assertTrue($user->login($password1));
        $user->update_password($password2);
        $this->assertEquals($username, $user->username_with_slashes());
        try {
            $this->assertEquals($passexpire, $user->password_expire());
        } catch (installation_error $e) {
        }
    }

    /**
     * Test user type classes
     *
     * @param array $config
     * @param string $groupname
     * @param string $memberattribute
     * @param array $exploded
     * @param mixed $executecalls
     * @param mixed $readvals
     */
    private function t_group(
        array $config,
        $groupname,
        $memberattribute,
        array $exploded,
        mixed $executecalls,
        mixed $readvals = null
    ): void {
        $mockldap = $this->getMockBuilder(client::class)
                         ->onlyMethods([
                             'execute',
                             'for_each_entry',
                             'connect',
                         ])
                         ->setConstructorArgs([$config['host_url'], (object) $config])
                         ->getMock();
        $users = new users($mockldap);
        $group = $users->group($groupname);

        if (is_callable($executecalls)) {
            $executecalls = $executecalls($group);
        }

        $mockldap->expects($this->exactly(count($executecalls)))
                 ->method('execute')
                 ->withConsecutive(...$executecalls)
                 ->willReturnCallback(
                     function ($fn, $args) use ($readvals, $group) {
                         static $calledcnt = 0;

                         if ($fn == 'ldap_read') {
                             $calledcnt++;
                             list ($dn, $filter, $attrs) = $args;
                             $ret = [];
                             foreach ($attrs as $a) {
                                 if ($readvals === null) {
                                     $val = $a;
                                 } elseif (is_callable($readvals)) {
                                     $val = $readvals($a, $group, $calledcnt);
                                 } else {
                                     $val = array_key_exists($a, $readvals) ? $readvals[$a] : $a;
                                 }
                                 $ret[$a] = $val;
                             }
                             return [$ret];
                         }
                         return true;
                     });
        $mockldap->expects($this->any())
                 ->method('for_each_entry')
                 ->willReturnCallback(
                     function ($recs, callable $cb) {
                         foreach ($recs as $r) {
                             $r1 = [];
                             foreach ($r as $k => $v) {
                                 $r1[$k] = is_array($v) ? $v : [$v];
                             }
                             $cb($r1);
                         }
                         return (bool) $recs;
                     });
        $this->assertEquals($exploded, $group->explode($memberattribute));
    }
}
