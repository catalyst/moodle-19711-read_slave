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
 * Defines classes used for plugin info.
 *
 * @package    core
 * @copyright  2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core\plugininfo;

use core_component;
use moodle_url;

/**
 * Class for admin tool plugins
 */
class gradepenalty extends base {


    /**
     * Allow the plugin to be uninstalled.
     *
     * @return true
     */
    public function is_uninstall_allowed() {
        return true;
    }

    /**
     * Get the URL to manage the penalty plugin.
     *
     * @return moodle_url
     */
    public static function get_manage_url() {
        return new moodle_url('/grade/penalty/manage_penalty_plugins.php');
    }

    /**
     * Support disabling the plugin.
     *
     * @return bool
     */
    public static function plugintype_supports_disabling(): bool {
        return true;
    }

    /**
     * Get the enabled plugins.
     *
     * @return array
     */
    public static function get_enabled_plugins() {
        $plugin = get_config('core_grades', 'gradepenalty_enabled_plugin');
        return $plugin ? [$plugin => $plugin] : [];
    }

    /**
     * Enable or disable a plugin.
     *
     * @param string $pluginname The name of the plugin.
     * @param int $enabled Whether to enable or disable the plugin.
     * @return bool
     */
    public static function enable_plugin(string $pluginname, int $enabled): bool {
        if (!$enabled) {
            $currentplugin = get_config('core_grades', 'gradepenalty_enabled_plugin');
            if ($currentplugin === $pluginname) {
                set_config('gradepenalty_enabled_plugin', '', 'core_grades');
            }
            return true;
        }
        set_config('gradepenalty_enabled_plugin', $pluginname, 'core_grades');
        return true;
    }

    /**
     * Check if the plugin is enabled.
     *
     * @return bool
     * @throws \dml_exception
     */
    public function is_enabled(): bool {
        return get_config('core_grades', 'gradepenalty_enabled_plugin') === $this->name;
    }

    /**
     * Get the settings section name.
     * Required for the settings page.
     *
     * @return string
     */
    public function get_settings_section_name() {
        return $this->component;
    }
}
