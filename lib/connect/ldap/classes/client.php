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
 * LDAP client
 *
 * LDAP connection and general-purpose LDAP functions and
 * data structures, useful for both ldap authentication (or ldap based
 * authentication like CAS) and enrolment plugins.
 *
 * @package    connect_ldap
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace connect_ldap;

use stdClass;

use connect_ldap\exception\installation_error,
    connect_ldap\exception\configuration_error,
    connect_ldap\exception\ldap_command_error;
use core_text;

defined('MOODLE_INTERNAL') || die();

/**
 * LDAP client
 *
 * LDAP connection and general-purpose LDAP functions and
 * data structures, useful for both ldap authentication (or ldap based
 * authentication like CAS) and enrolment plugins.
 *
 * @package    connect_ldap
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class client {

    const DEFAULT_PROTOCOL_VERSION = 3;

    const DN_SPECIAL_CHARS = 0;
    const DN_SPECIAL_CHARS_QUOTED_NUM = 1;
    const DN_SPECIAL_CHARS_QUOTED_ALPHA = 2;
    const DN_SPECIAL_CHARS_QUOTED_ALPHA_REGEX = 3;

    const PAGED_RESULTS_CONTROL = '1.2.840.113556.1.4.319';

    /** @var stdClass */
    public $config;

    /** @var \LDAP\Connection */
    protected $conn;

    /**
     * Tries connect to LDAP servers specified in config. Returns a valid LDAP
     * connection or false.
     *
     * @return client connection result or false.
     * @throw configuration_error
     * @throw error
     */
    public static function from_config(): client {
        $config = get_config('connect_ldap');

        if (empty($config->host_url)) {
            throw new configuration_error('host_url');
        }

        $urls = explode(';', $config->host_url);
        foreach ($urls as $url) {
            $url = trim($url);
            if (empty($url)) {
                continue;
            }

            try {
                return new static($url, $config);
            } catch (ldap_command_error $e) {
                debugging($e->getMessage(), DEBUG_NORMAL);
            }
        }
        throw $e;
    }

    /**
     * Constructor, tries connect to specified ldap server.
     *
     * @param string $url
     * @param stdClass $config connect_ldap config
     * @throw error
     */
    public function __construct($url, stdClass $config) {
        if (!function_exists('ldap_connect')) {
            throw new installation_error('ldap_noextension'); // PHP ldap extension not available.
        }

        $config = clone $config;

        $config->ldap_version = empty($config->ldap_version) ? self::DEFAULT_PROTOCOL_VERSION : (int) $config->ldap_version;

        if (empty($config->root_ds)) {
            $config->root_ds = '';
        }

        if (empty($config->encoding)) {
            $config->encoding = 'utf-8';
        }

        $this->url = $url;
        $this->config = $config;

        $this->connect();

        $this->config->paged_results_supported = false;
        if ($config->ldap_version >= 3) {
            $attribute = 'supportedControl';

            // Get the supported controls.
            if ($controls = $this->global_attribute($attribute)) {
                $this->config->paged_results_supported = in_array(self::PAGED_RESULTS_CONTROL, $controls);
                if ($this->config->paged_results_supported and empty($this->config->pagesize)) {
                    $this->config->pagesize = 100;
                }
            }
        }
    }

    /**
     * Execute throwing a ldap_command_error on error
     *
     * @param string $fn   LDAP function
     * @param ?array $args LDAP function args
     * @return mixed whatever $fn returns
     * @throw ldap_command_error
     *
     */
    protected function execute($fn, $args = []): mixed {
        $res = @$fn($this->conn, ...$args);
        // $res = call_user_func_array($fn, array_unshift($args, $this->conn));
        if ($res === false) {
            throw new ldap_command_error($fn, ldap_error($this->conn), $args);
        }
        return $res;
    }

    /**
     * Connect to specified ldap server.
     *
     * @throw ldap_command_error
     */
    protected function connect(): void {
        if ($this->conn) {
            $this->execute('ldap_unbind');
        }

        $this->conn = ldap_connect($this->url); // ldap_connect returns ALWAYS true

        $this->set_option(LDAP_OPT_PROTOCOL_VERSION, $this->config->ldap_version);

        if (!empty($this->config->opt_deref)) {
            $this->set_option(LDAP_OPT_DEREF, $this->config->opt_deref);
        }

        if (!empty($this->config->start_tls)) {
            $this->execute('ldap_start_tls');
        }

        $this->bind_admin();
    }

    /**
     * Set connection option
     *
     * @param int    $option    LDAP_ option
     * @param string $value
     * @throw ldap_command_error
     */
    public function set_option($option, $value): void {
        $this->execute('ldap_set_option', [$option, $value]);
    }

    /**
     * Bind admin
     *
     * @throw ldap_command_error
     */
    public function bind_admin(): void {
        $bindargs = empty($this->config->bind_dn) ? [] : [$this->config->bind_dn, $this->config->bind_pw];
        $this->bind(...$bindargs);
    }

    /**
     * Bind user
     *
     * If $dn is not given, binds annonymously.
     *
     * @param ?string $dn       LDAP user DN
     * @param ?string $password LDAP user password (without system magic quotes)
     * @throw ldap_command_error
     */
    public function bind($dn = null, $password = null): void {
        $bindargs = $dn ? [$dn, $password ? core_text::convert($password, 'utf-8', $this->config->encoding) : null] : [];
        $this->execute('ldap_bind', $bindargs);
    }

    /**
     * Get last diagnostic message
     *
     * Needs to be called immediately after an operation.
     *
     * @param ?bool $dn       LDAP user DN
     * @return ?string
     * @throw ldap_command_error
     */
    public function get_diagnostic_message($safe = false): ?string {
        if (@ldap_get_option($this->conn, LDAP_OPT_DIAGNOSTIC_MESSAGE, $diagmsg) === false) {
            $err = "ldap_get_option: ".ldap_error($this->conn);
            if ($safe) {
                debugging($err, DEBUG_NORMAL);
                return null;
            }
            throw new ldap_command_error('ldap_get_option', $err);
        }
        return $diagmsg;
    }

    /**
     * Read from LDAP directory with the scope LDAP_SCOPE_BASE
     *
     * See https://www.php.net/manual/en/function.ldap-read.php
     * For search filter, see https://wiki.mozilla.org/Mozilla_LDAP_SDK_Programmer%27s_Guide/Searching_the_Directory_With_LDAP_C_SDK
     *
     * @param string $dn         DN for the directory
     * @param string $filter     search filter
     * @param ?array $attributes required attributes in the result
     * @return array
     * @throw ldap_command_error
     */
    public function read($dn, $filter, $attributes = []): array {
        $sr = $this->execute('ldap_read', [$dn, $filter, $attributes]);
        return $this->get_entries($sr);
    }

    /**
     * Read from LDAP directory and return first entry
     *
     * See https://www.php.net/manual/en/function.ldap-read.php
     * For search filter, see https://wiki.mozilla.org/Mozilla_LDAP_SDK_Programmer%27s_Guide/Searching_the_Directory_With_LDAP_C_SDK
     *
     * @param string $dn         DN for the directory
     * @param string $filter     search filter
     * @param ?array $attributes required attributes in the result
     * @return ?array
     * @throw ldap_command_error
     */
    public function find($dn, $filter, $attributes = []): ?array {
        if ($entries = $this->read($dn, $filter, $attributes)) {
            return count($entries) > 0 ? $entries[0] : null;
        }
        return null;
    }

    /**
     * Read from LDAP directory and return first entry, non-filtered
     *
     * See https://www.php.net/manual/en/function.ldap-read.php
     * For search filter, see https://wiki.mozilla.org/Mozilla_LDAP_SDK_Programmer%27s_Guide/Searching_the_Directory_With_LDAP_C_SDK
     *
     * @param string $dn         DN for the directory
     * @param array $attributes required attributes in the result
     * @return ?array
     * @throw ldap_command_error
     */
    public function find_any($dn, $attributes): ?array {
        return $this->find($dn, '(objectClass=*)',  $attributes);
    }

    /**
     * Search LDAP directory
     *
     * See https://www.php.net/manual/en/function.ldap-search.php
     * For search filter, see https://wiki.mozilla.org/Mozilla_LDAP_SDK_Programmer%27s_Guide/Searching_the_Directory_With_LDAP_C_SDK
     *
     * @param string $dn         DN for the directory
     * @param string $filter     search filter
     * @param ?array $attributes required attributes in the result
     * @param ?bool  $sub        search subdirs, scope LDAP_SCOPE_SUBTREE (ldap_search) or LDAP_SCOPE_ONELEVEL (ldap_list)
     * @return array
     * @throw ldap_command_error
     */
    public function search($dn, $filter, $attributes = null, $sub = true): array {
        $sr = $this->execute($sub ? 'ldap_search' : 'ldap_list', [$dn, $filter, $attributes]);
        return $this->get_entries($sr);
    }

    /**
     * Execute callback($entry) for each result entry
     *
     * Search LDAP directory paged if possible.
     * Entry is an array ['attribute' => [values]]
     *
     * See https://www.php.net/manual/en/function.ldap-search.php
     * For search filter, see https://wiki.mozilla.org/Mozilla_LDAP_SDK_Programmer%27s_Guide/Searching_the_Directory_With_LDAP_C_SDK
     *
     * @param string $dn         DN for the directory
     * @param callable $callback callback($entry) to be executed for each result entry
     * @param string $filter     search filter
     * @param ?array $attributes required attributes in the result
     * @param ?bool  $sub        search subdirs, scope LDAP_SCOPE_SUBTREE (ldap_search) or LDAP_SCOPE_ONELEVEL (ldap_list)
     * @throw ldap_command_error
     */
    public function for_each($dn, callable $cb, $filter, $attributes = null, $sub = true): void {
        $fn = $sub ? 'ldap_search' : 'ldap_list';
        if (!$this->config->paged_results_supported) {
            $sr = $this->execute($sub ? 'ldap_search' : 'ldap_list', [$dn, $filter, $attributes]);
            $this->for_each_entry($sr, $cb);
            return;
        }

        $controls = [
            [
                'oid' => LDAP_CONTROL_PAGEDRESULTS,
                'value' => [
                    'size' => $this->config->pagesize,
                    'cookie' => '',
                ],
            ]
        ];
        do {
            $sr = $this->execute($fn, [
                $dn,
                $filter,
                $attributes,
                0,
                0,
                0,
                empty($this->config->opt_deref) ? LDAP_DEREF_NEVER : $this->config->opt_deref,
                $controls
            ]);
            // Cannot execute(), passing args by ref.
            if (@ldap_parse_result($this->conn, $sr, $errcode, $matcheddn, $errmsg, $referrals, $controls) === false) {
                throw new ldap_command_error('ldap_parse_result', ldap_error($this->conn));
            }
            if ($errcode != 0) {
                throw new ldap_command_error($fn, "$errmsg ($errcode)");
            }

            if (!$this->for_each_entry($sr, $cb)) {
                break;
            }

            if (empty($controls[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie'])) {
                break;
            }
        } while(true);

        // If LDAP paged results were used, the current connection must be completely
        // closed and a new one created, to work without paged results from here on.
        $this->connect();
    }

    /**
     * Execute callback($entry) for each result entry
     *
     * Entry is an array ['attribute' => [values]].
     *
     * @param  \LDAP\Result $searchresult A search result from ldap_search, ldap_list, etc.
     * @param callable $callback callback($entry) to be executed for each result entry
     * @return bool had entries
     */
    protected function for_each_entry($searchresult, callable $cb): bool {
        $entry = @ldap_first_entry($this->conn, $searchresult);
        if (!$entry) {
            return false;
        }
        while ($entry) {
            $attributes = [];
            $attribute = @ldap_first_attribute($this->conn, $entry);
            while ($attribute !== false) {
                $values = $this->execute('ldap_get_values_len', [$entry, $attribute]);
                if (is_array($values)) {
                    if (array_key_exists('count',  $values)) {
                        unset($values['count']);
                    }
                } else {
                    $values = [$values];
                }
                $attributes[$attribute] = $values;

                $attribute = @ldap_next_attribute($this->conn, $entry);
            }
            if (empty($attributes['dn'])) {
                $attributes['dn'] = $this->execute('ldap_get_dn', [$entry]);
            }
            $cb($attributes);
            $entry = @ldap_next_entry($this->conn, $entry);
        }
        return true;
    }

    /**
     * Returns all resultset entries as arrays ['attribute' => [values]]
     *
     * @param  \LDAP\Result $searchresult A search result from ldap_search, ldap_list, etc.
     * @return array        ldap-entries with attributes as indexes
     */
    protected function get_entries($searchresult): array {
        $result = [];
        $this->for_each_entry($searchresult, function ($entry) use (&$result) {
            $result[] = $entry;
        });
        return $result;
    }

    /**
     * Search specified contexts for attribute and return the dn like:
     * cn=username,ou=suborg,o=org
     *
     * @param string $dn         context DN
     * @param string $filter     search filter
     * @param ?array $attribute  attributes to look for
     * @param ?bool  $sub        search subdirs, scope LDAP_SCOPE_SUBTREE (ldap_search) or LDAP_SCOPE_ONELEVEL (ldap_list)
     * @return ?string the dn (external LDAP encoding, no db slashes)
     *
     */
    public function get_dn($dn, $filter, $attribute, $sub = true): ?string {
        $sr = $this->execute($sub ? 'ldap_search' : 'ldap_list', [$dn, $filter, [$attribute]]);

        if ($entry = @ldap_first_entry($this->conn, $sr)) {
            return $this->execute('ldap_get_dn', [$entry]);
        }
        return null;
    }

    /**
     * Add entry to the LDAP directory
     *
     * See https://www.php.net/manual/en/function.ldap-add.php
     *
     * @param string $dn    DN for the directory
     * @param array  $entry entry object to be added
     * @throw ldap_command_error
     *
     */
    public function add($dn, $entry): void {
        $this->execute('ldap_add', [$dn, $entry]);
    }

    /**
     * Modify entry in the LDAP directory
     *
     * See https://www.php.net/manual/en/function.ldap-modify.php
     *
     * @param string $dn    DN for the directory
     * @param array  $entry modifications
     * @throw ldap_command_error
     *
     */
    public function modify($dn, $entry): void {
        $this->execute('ldap_modify', [$dn, $entry]);
    }

    /**
     * Delete entry from the LDAP directory
     *
     * See https://www.php.net/manual/en/function.ldap-delete.php
     *
     * @param string $dn    DN for the directory
     * @param array  $entry entry object to be added
     * @throw ldap_command_error
     *
     */
    public function delete($dn): void {
        $this->execute('ldap_delete', [$dn]);
    }

    /**
     * Returns global attribute values
     *
     * @param string $attribute required attribute
     * @return ?array with no magic quotes
     * @throw ldap_command_error
     */
    public function global_attribute($attribute): ?array {
        // Connect to the root DS and get attribute
        if ($entry = $this->find_any($this->config->root_ds, [$attribute])) {
            $attr = core_text::strtolower($attribute);
            foreach ($entry as $key => $val) {
                if (core_text::strtolower($key) == $attr) {
                    return $val;
                }
            }
        }
        return null;
    }

    /**
     * Normalise the supplied objectclass filter.
     *
     * This normalisation is a rudimentary attempt to format the objectclass filter correctly.
     *
     * @param ?string $objectclass The objectclass to normalise
     * @return string The normalised objectclass.
     */
    public static function normalise_objectclass($objectclass = null): string {
        if (empty($objectclass)) {
            // Can't send empty filter.
            return '(objectClass=*)';
        }

        if (stripos($objectclass, 'objectClass=') === 0) {
            // Value is 'objectClass=some-string-here', so just add () around the value (filter _must_ have them).
            return sprintf('(%s)', $objectclass);
        }

        if (stripos($objectclass, '(') !== 0) {
            // Value is 'some-string-not-starting-with-left-parentheses', which is assumed to be the objectClass matching value.
            // Build a valid filter using the value it.
            return sprintf('(objectClass=%s)', $objectclass);
        }

        // There is an additional possible value '(some-string-here)', that can be used to specify any valid filter
        // string, to select subsets of users based on any criteria.
        //
        // For example, we could select the users whose objectClass is 'user' and have the 'enabledMoodleUser'
        // attribute, with something like:
        //
        // (&(objectClass=user)(enabledMoodleUser=1))
        //
        // In this particular case we don't need to do anything, so leave $objectclass as is.
        return $objectclass;
    }

    /**
     * Quote control characters in texts used in LDAP filters - see RFC 4515/2254
     *
     * @param string filter string to quote
     * @return string the filter string quoted
     */
    public static function filter_add_slashes($text): string {
        $text = str_replace('\\', '\\5c', $text);
        $text = str_replace(array('*',    '(',    ')',    "\0"),
                            array('\\2a', '\\28', '\\29', '\\00'), $text);
        return $text;
    }

    /**
     * The order of the special characters in these arrays _IS IMPORTANT_.
     * Make sure '\\5C' (and '\\') are the first elements of the arrays.
     * Otherwise we'll double replace '\' with '\5C' which is Bad(tm)
     *
     * @return array
     */
    public static function dn_special_chars(): array {
        static $specialchars = null;

        if ($specialchars !== null) {
            return $specialchars;
        }

        $specialchars = [
            self::DN_SPECIAL_CHARS              => ['\\',  ' ',   '"',   '#',   '+',   ',',   ';',   '<',   '=',   '>',   "\0"],
            self::DN_SPECIAL_CHARS_QUOTED_NUM   => ['\\5c','\\20','\\22','\\23','\\2b','\\2c','\\3b','\\3c','\\3d','\\3e','\\00'],
            self::DN_SPECIAL_CHARS_QUOTED_ALPHA => ['\\\\','\\ ', '\\"', '\\#', '\\+', '\\,', '\\;', '\\<', '\\=', '\\>', '\\00'],
        ];
        $alpharegex = implode('|', array_map (function ($expr) { return preg_quote($expr); },
                                              $specialchars[self::DN_SPECIAL_CHARS_QUOTED_ALPHA]));
        $specialchars[self::DN_SPECIAL_CHARS_QUOTED_ALPHA_REGEX] = $alpharegex;

        return $specialchars;
    }

    /**
     * Quote control characters in AttributeValue parts of a RelativeDistinguishedName
     * used in LDAP distinguished names - See RFC 4514/2253
     *
     * @param string the AttributeValue to quote
     * @return string the AttributeValue quoted
     */
    public static function add_slashes($text): string {
        $specialchars = self::dn_special_chars();

        // Use the preferred/universal quotation method: ESC HEX HEX
        // (i.e., the 'numerically' quoted characters)
        return str_replace ($specialchars[self::DN_SPECIAL_CHARS],
                            $specialchars[self::DN_SPECIAL_CHARS_QUOTED_NUM],
                            $text);
    }

    /**
     * Unquote control characters in AttributeValue parts of a RelativeDistinguishedName
     * used in LDAP distinguished names - See RFC 4514/2253
     *
     * @param string the AttributeValue quoted
     * @return string the AttributeValue unquoted
     */
    public static function strip_slashes($text): string {
        $specialchars = self::dn_special_chars();

        // We can't unquote in two steps, as we end up unquoting too much in certain cases. So
        // we need to build a regexp containing both the 'numerically' and 'alphabetically'
        // quoted characters. We don't use DN_SPECIAL_CHARS_QUOTED_NUM because the
        // standard allows us to quote any character with this encoding, not just the special
        // ones.
        // @TODO: This still misses some special (and rarely used) cases, but we need
        // a full state machine to handle them.
        $quoted = '/(\\\\[0-9A-Fa-f]{2}|' . $specialchars[self::DN_SPECIAL_CHARS_QUOTED_ALPHA_REGEX] . ')/';
        return preg_replace_callback(
            $quoted,
            function ($match) use ($specialchars) {
                if (ctype_xdigit(ltrim($match[1], '\\'))) {
                    return chr(hexdec(ltrim($match[1], '\\')));
                } else {
                    return str_replace($specialchars[self::DN_SPECIAL_CHARS_QUOTED_ALPHA],
                        $specialchars[self::DN_SPECIAL_CHARS],
                        $match[1]);
                }
            },
            $text
        );
    }
}
