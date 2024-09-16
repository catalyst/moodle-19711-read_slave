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
 * Search strings throughout all texts in the whole database.
 *
 * @package    tool_replace
 * @copyright  2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__.'/../../../../config.php');
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->libdir.'/adminlib.php');

$help =
    "Search text throughout the whole database.

Options:
--search=STRING       String to search for.
--skiptables=STRING   Skip these tables (comma separated list of tables).
                      The
--summary             Summary mode, only shows column/table where the text is found.
                      If not specified, run in detail mode, which shows the full text where the search string is found.
-h, --help            Print out this help.

Example:
\$ sudo -u www-data /usr/bin/php admin/tool/replace/cli/find.php --search=thelostsoul --summary
";

list($options, $unrecognized) = cli_get_params(
    array(
        'search'  => null,
        'skiptables' => '',
        'summary' => false,
        'help'    => false,
    ),
    array(
        'h' => 'help',
    )
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

// Ensure that we have required parameters.
if ($options['help'] || !is_string($options['search'])) {
    echo $help;
    exit(0);
}

try {
    $search = validate_param($options['search'], PARAM_RAW);
    $skiptables = validate_param($options['skiptables'], PARAM_RAW);
} catch (invalid_parameter_exception $e) {
    cli_error(get_string('invalidcharacter', 'tool_replace'));
}

// Perform the search.
$result = db_search($search, $skiptables, $options['summary']);

// Output the result.
foreach ($result as $table => $columns) {
    foreach ($columns as $column => $rows) {
        if ($options['summary']) {
            echo "$table, $column\n";
        } else {
            foreach ($rows as $row) {
                $data = $row->$column;
                echo "$table, $column, \"$data\"\n";
            }
        }
    }
}

exit(0);
