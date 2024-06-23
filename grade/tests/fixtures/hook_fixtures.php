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

namespace core_grades\local\penalty\test\hook;

use core\check\performance\debugging;
use core_grades\hook\after_penalty_applied;
use core_grades\hook\before_penalty_applied;

defined('MOODLE_INTERNAL') || die();

/**
 * Hook fixtures for testing of hooks.
 *
 * @package   core_grades
 * @copyright 2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hook_fixtures {
    /**
     * Change submission and due dates.
     *
     * @param before_penalty_applied $hook
     * @return void
     */
    public static function change_dates(
        \core_grades\hook\before_penalty_applied $hook
    ): void {
        // Add a valid serverside and an invalid clientside filter.
        debugging('before_penalty_applied callback');
        $hook->set_submission_date(DAYSECS);
        $hook->set_due_date(DAYSECS * 2);
    }

    /**
     * Show debugging information.
     *
     * @param after_penalty_applied $hook
     * @return void
     */
    public static function show_debug_message(
        \core_grades\hook\after_penalty_applied $hook
    ): void {
        debugging('after_penalty_applied callback');
        debugging('Grade before penalty: ' . $hook->gradebefore);
        debugging('Penalty: ' . $hook->penalty);
    }
}

$callbacks = [
    [
        'hook' => \core_grades\hook\before_penalty_applied::class,
        'callback' => \core_grades\local\penalty\test\hook\hook_fixtures::class . '::change_dates',
    ],
    [
        'hook' => \core_grades\hook\after_penalty_applied::class,
        'callback' => \core_grades\local\penalty\test\hook\hook_fixtures::class . '::show_debug_message',
    ],
];
