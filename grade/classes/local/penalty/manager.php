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

namespace core_grades\local\penalty;

use core\check\performance\debugging;
use core\di;
use core\hook;
use core\plugininfo\gradepenalty;
use core_component;
use core_grades\hook\after_penalty_applied;
use grade_item;

/**
 * Manager class for grade penalty.
 *
 * @package   core_grades
 * @copyright 2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /**
     * Lists of modules which support grade penalty feature.
     *
     * @return array list of supported modules.
     */
    public static function get_supported_modules(): array {
        $plugintype = 'mod';
        $mods = \core_component::get_plugin_list($plugintype);
        $supported = [];
        foreach ($mods as $mod => $plugindir) {
            if (plugin_supports($plugintype, $mod, FEATURE_GRADE_HAS_PENALTY)) {
                $supported[] = $mod;
            }
        }
        return $supported;
    }

    /**
     * Get the active grade penalty plugin.
     * At the moment, we restrict to have only one enabled plugin.
     * We may have improvement where there are multiple enabled plugins.
     * And we can have a setting to choose the active plugin in a context.
     *
     * @return string the active grade penalty plugin.
     */
    public static function get_active_grade_penalty_plugin(): string {
        // At the moment, we restrict to have only one enabled plugin.
        $plugins = gradepenalty::get_enabled_plugins();
        // That enabled plugin is also the active plugin.
        return reset($plugins);
    }

    /**
     * Get required handler class of specified plugin, which performs penalty calculation.
     *
     * @param string $plugin the plugin name.
     * @return string|null the required class.
     */
    public static function get_required_handler_class(string $plugin): ?string {
        $pluginclass = '\gradepenalty_' . $plugin . '\handler';
        // Check if the plugin class exists.
        if (!class_exists($pluginclass)) {
            debugging('The grade penalty plugin class ' . $pluginclass . ' does not exist.', DEBUG_DEVELOPER);
            return null;
        }
        return $pluginclass;
    }

    /**
     * This function is run after grade is updated/created for a user.
     *
     * @param int $userid ID of user
     * @param grade_item $gradeitem the grade item object
     * @param int $datesubmitted the date the submission was made.
     * @param ?int $duedate due date of activity
     *
     * @return bool if the penalty is applied successfully.
     */
    public static function apply_penalty(int $userid, grade_item $gradeitem, int $datesubmitted, ?int $duedate = null): bool {

        // Return if the grade penalty feature is disabled.
        if (!get_config('core', 'gradepenalty_enabled')) {
            return true;
        }

        // If the grade item belong to a supported module.
        $supportedmodules = get_config('core', 'gradepenalty_supportedplugins');
        if (!in_array($gradeitem->itemmodule, explode(',', $supportedmodules))) {
            return true;
        }

        // Get the active grade penalty plugin.
        $plugin = self::get_active_grade_penalty_plugin();
        // No active plugin, return.
        if (empty($plugin)) {
            return true;
        }

        // Hook for plugins to add override dates before the penalty is applied to the grade.
        $hook = new \core_grades\hook\before_penalty_applied($userid, $gradeitem, $datesubmitted, $duedate);
        di::get(hook\manager::class)->dispatch($hook);

        // Run the penalty calculation.
        $pluginclass = self::get_required_handler_class($plugin);
        if (empty($pluginclass)) {
            return false;
        }
        $penaltyplugin = new $pluginclass($userid, $gradeitem, $hook->get_submission_date(), $hook->get_due_date());

        // Apply the penalty.
        $success = $penaltyplugin->apply_penalty();

        // Hook for plugins to process further after the penalty is applied to the grade.
        $hook = new after_penalty_applied($userid, $gradeitem, $penaltyplugin->gradebefore, $penaltyplugin->penalty);
        di::get(hook\manager::class)->dispatch($hook);

        return $success;
    }
}
