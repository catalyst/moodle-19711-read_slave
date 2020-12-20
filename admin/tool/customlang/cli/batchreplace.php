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
 * Batch search and replace custom language strings.
 *
 * @package    tool_customlang
 * @subpackage customlang
 * @copyright  2020 Scott Verbeek <scottverbeek@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once("$CFG->libdir/clilib.php");
require_once($CFG->libdir.'/adminlib.php');
require_once("$CFG->dirroot/$CFG->admin/tool/customlang/locallib.php");

$usage = <<<EOF
Batch search and replace language strings
Useful for changing strings that occur in a lot of language strings.
Default mode of this script is interactive, use -y or -n for non-interactive.

Options:
-s, --search            Case sensitive string to search for
-r, --replace           Case sensitive string that replaces --search
-x, --regex             Search database using regex; uses --search if not specified
-l, --lang              Comma seperated language ids to export, default: $CFG->lang
-c, --components        Comma seperated components to export, default: all
-y, --yes, --assume-yes The script will run without user interaction and will anwser yes to all matches
-n, --no, --assume-no   The script will run without user interaction and will answer no to all questions
-p, --prefix            Case sensitive prefix that when found in a search, it is considered safe to replace, default = null
-f, --suffix            Case sensitive suffix that when found in a search, it is considered safe to replace, default = null

-h, --help              Print out this help

Examples:
Search and replace language files:
\$ php admin/tool/customlang/cli/batchreplace.php -s='course' -r='subject'

Search and replace language files with search that could have a suffix
\$ php admin/tool/customlang/cli/batchreplace.php -s='course' -r='subject' -f='s'

Search and replace the Dutch files of moodle core and the activity 'quiz':
\$ php admin/tool/customlang/cli/batchreplace.php --lang='nl' --components='moodle,quiz' --search='course' --replace='subject'

EOF;

// Get cli options.
list($options, $unrecognized) = cli_get_params(
    [
        'components' => '',
        'help' => false,
        'assume-yes' => false,
        'assume-no' => false,
        'lang' => '',
        'regex' => null,
        'replace' => null,
        'search' => null,
        'prefix' => null,
        'suffix' => null
    ],
    [
        'c' => 'components',
        'h' => 'help',
        'l' => 'lang',
        'n' => 'assume-no',
        'no' => 'assume-no',
        'r' => 'replace',
        'R' => 'run',
        's' => 'search',
        'x' => 'regex',
        'y' => 'assume-yes',
        'yes' => 'assume-yes',
        'p' => 'prefix',
        'f' => 'suffix'
    ]
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    echo $usage;
    exit(1);
}

if ($options['assume-yes'] && $options['assume-no']) {
    // If both assume-yes and assume-no options been set? Prompt that this can't happen and stop program.
    cli_writeln(get_string('batchreplaceassumeerror', 'tool_customlang'));
    exit(1);
}

// No search option set? Stop program.
if (empty($options['search']) ) {
    cli_writeln(get_string('batchreplacenosearch', 'tool_customlang'));
    echo $usage;
    exit(1);
}
$search = $options['search'];

// No replace option set? Stop program.
if ($options['replace'] == null ) {
    cli_writeln(get_string('batchreplacenoreplace', 'tool_customlang'));
    echo $usage;
    exit(1);
}
$replace = $options['replace'];

if ($options['lang'] == '') {
    // No language option set? Default to english.
    $lang = $CFG->lang;
} else {
    // English option set? Get string.
    $lang = $options['lang'];
}

// Get all language packs.
$langs = array_keys(get_string_manager()->get_list_of_translations(true));
if ( !in_array($lang, $langs) ) {
    // Lang does not exist? Then stop the program.
    cli_writeln(get_string('batchreplacelangnotfound', 'tool_customlang'));
    exit(1);
}

// We need a bit of extra execution time and memory here.
core_php_time_limit::raise(HOURSECS);
raise_memory_limit(MEMORY_EXTRA);

// Update the translator database.
cli_writeln(get_string('checkoutinprogress', 'tool_customlang'));
tool_customlang_utils::checkout($lang);
cli_writeln(get_string('checkoutdone', 'tool_customlang'));

$components = [];
if ($options['components']) {
    // If components option isset? Then set components.
    $components = explode(',', $options['components']);
} else {
    // No components set. We fetch all installed components, default.
    $components = tool_customlang_utils::list_components();
}

try {
    $strings = empty($options['regex'])
        ? tool_customlang_utils::search($lang, $components, $search)
        : tool_customlang_utils::search_regex($lang, $components, $options['regex']);
} catch (moodle_exception $e) {
    cli_writeln($e->getMessage());
    exit(1);
}
$numofrows = count($strings);

if ($numofrows == 0) {
    // No results? Stop program.
    cli_writeln(get_string('nostringsfound', 'tool_customlang'));
    exit(1);
}

$componentsstring = ($options['components'] == '') ? 'all' : implode($components);
cli_writeln(get_string('batchreplacematches', 'tool_customlang', [
    'numofrows' => $numofrows,
    'componentsstring' => $componentsstring,
    'lang' => $lang
    ]));

$matchnumber = 0;
$acceptall = false;
$acceptnone = $options['assume-no'];
/*
 * Callback for tool_customlang_utils::replace() that prompts for replace confirmation
 *
 * @param object $string tool_customlang record
 * @param string $safesubject subject to replace
 * @param array $hashvalues possible replacement hashvalues, strings
 * @return bool replacement confirmation
 */
function prompt(object $string, string $safesubject, array $hashvalues) {
    global $search, $replace, $options, $matchnumber, $numofrows, $acceptall, $acceptnone;

    if ($acceptnone) {
        // If option --assume-no has been set? Then do a different loop.
        $highlightedsearch = str_replace($search, '<colour:black><bgcolour:white>'.$search.'<colour:normal>', $safesubject);
        $highlightedsearch = tool_customlang_utils::replace_hash_to_string($highlightedsearch, $hashvalues);
        $stringoptions = [
            "lang" => $string->lang,
            "component" => $string->component,
            "stringid" => $string->stringid,
            "subject" => $highlightedsearch,
        ];
        cli_writeln( cli_ansi_format( get_string('batchreplacestageassumeno', 'tool_customlang', $stringoptions) ) );
        return false;
    }

    $highlightedsearch = str_replace($search, '<colour:white><bgcolour:red>'.$search.'<colour:red>', $safesubject);
    $highlightedreplace = str_replace($search, '<colour:white><bgcolour:green>'.$replace.'<colour:green>', $safesubject);

    $highlightedsearch = tool_customlang_utils::replace_hash_to_string($highlightedsearch, $hashvalues);
    $highlightedreplace = tool_customlang_utils::replace_hash_to_string($highlightedreplace, $hashvalues);

    $stringoptions = [
        "lang" => $string->lang,
        "component" => $string->component,
        "stringid" => $string->stringid,
        "subject" => $highlightedsearch,
        "match" => $highlightedreplace,
        "matchnumber" => $matchnumber,
        "totalmatches" => $numofrows,
    ];

    $safe = tool_customlang_utils::is_safe_to_replace(
        $safesubject, $search, $options['prefix'], $options['suffix']
    );

    if (($safe && $acceptall) || $options['assume-yes']) {
        cli_writeln( cli_ansi_format( get_string('batchreplacestageall', 'tool_customlang', $stringoptions) ) );
    } else {
        // Get the answer for this match.
        $acceptedanswers = ['y', 'n', 'N'];
        if ($safe) {
            $acceptedanswers[] = 'a';
        }
        $prompt = 'batchreplacestage' . ($safe ? '' : 'danger');
        $help = 'batchreplacestagehelp' . ($safe ? '' : 'danger');
        while (true) {
            $answer = cli_input(cli_ansi_format(get_string($prompt, 'tool_customlang', $stringoptions)));
            if (in_array($answer, $acceptedanswers)) {
                break;
            }
            cli_writeln( cli_ansi_format( get_string($help, 'tool_customlang') ) );
        }
        if ($answer == 'a') {
            $acceptall = true;
        }

        if ($answer == 'N') {
            // Answer equal to 'N'? Then break out of foreach.
            $acceptnone = true;
            return false;
        }

        if ($answer == 'n') {
            // Answer equal to 'n'? Go to next $string in $strings one.
            return false;
        }
    }

    return true;
}

foreach ($strings as $string) {
    if (
        tool_customlang_utils::replace(
            $string, $search, $replace,
            function ($safesubject, $hashvalues) use ($string) {
                return prompt($string, $safesubject, $hashvalues);
            }
        ) === null) {

        // No subject found? Continue to next $string in $strings.
        $numofrows--;
        continue;
    }

    $matchnumber++;
}

// Make sure we want to execute this.
$acceptedanswers = ['y', 'n'];
if ($options['assume-yes']) {
    $answer = 'y';
} else if ($options['assume-no']) {
    $answer = 'n';
} else {
    $answer = strtolower( cli_input( get_string('batchreplaceconfirm', 'tool_customlang') ) );
    while (!in_array($answer, $acceptedanswers)) {
        cli_writeln( cli_ansi_format( get_string('batchreplacestagehelpdanger', 'tool_customlang') ) );
        $answer = cli_input(cli_ansi_format(get_string('batchreplacestagedanger', 'tool_customlang', $stringoptions)));
    }
}
if ($answer == 'n') {
    // If answer no on last prompt? Then stop program.
    exit(2);
}

cli_writeln(get_string('batchreplacecheckin', 'tool_customlang'));
tool_customlang_utils::checkin($lang);
cli_writeln(get_string('batchreplacesuccess', 'tool_customlang'));
