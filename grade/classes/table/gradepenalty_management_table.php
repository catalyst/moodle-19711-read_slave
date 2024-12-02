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

namespace core_grades\table;

use core_admin\table\plugin_management_table;
use moodle_url;

/**
 * Table to manage grade penalty plugin.
 *
 * @package   core_grades
 * @copyright 2024 Catalyst IT Australia Pty Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradepenalty_management_table extends plugin_management_table {

    /**
     * Return the penalty plugin type.
     *
     * @return string
     */
    protected function get_plugintype(): string {
        return 'gradepenalty';
    }

    /**
     * Get the URL to manage the penalty plugin.
     *
     * @param array $params
     * @return moodle_url
     */
    protected function get_action_url(array $params = []): moodle_url {
        return new moodle_url('/grade/penalty/manage_penalty_plugins.php', $params);
    }
}
