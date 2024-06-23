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
 * Strings for component 'gradepenalty_duedate', language 'en'.
 *
 * @package    gradepenalty_duedate
 * @copyright 2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Minimum late for value */
define('GRADEPENALTY_DUEDATE_LATEFOR_MIN', 1);

/** Maximum late for value */
define('GRADEPENALTY_DUEDATE_LATEFOR_MAX', YEARSECS);

/** Minimum penalty value */
define('GRADEPENALTY_DUEDATE_PENALTY_MIN', 0);

/** Maximum penalty value */
define('GRADEPENALTY_DUEDATE_PENALTY_MAX', 100);

/**
 * Extend the course navigation with a penalty rule settings.
 *
 * @param settings_navigation $navigation The settings navigation object
 * @param stdClass $course The course
 * @param stdclass $context Course context
 * @return void
 */
function gradepenalty_duedate_extend_navigation_course($navigation, $course, $context): void {
    // Get plugin info of this plugin.
    $penaltyplugins = core_plugin_manager::instance()->get_plugins_of_type('gradepenalty');

    // Return if the plugin is not enabled.
    if (!$penaltyplugins['duedate']->is_enabled()) {
        return;
    }

    if (has_capability('gradepenalty/duedate:manage', $context)) {
        $url = new moodle_url('/grade/penalty/duedate/manage_penalty_rule.php', ['contextid' => $context->id]);

        $settingsnode = navigation_node::create(get_string('penaltyrule', 'gradepenalty_duedate'),
            $url, navigation_node::TYPE_SETTING,
            null, 'penaltyrule', new pix_icon('i/settings', ''));
        $navigation->add_node($settingsnode);
    }
}
