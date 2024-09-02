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
 * Hook callbacks.
 *
 * @package    mod_assign
 * @copyright  2024 Catalyst IT Australia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_assign;

use core_grades\hook\before_penalty_recalculation;
use mod_assign\penalty\helper;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/assign/locallib.php');

/**
 * Hook callbacks.
 *
 * @package    mod_assign
 * @copyright  2024 Catalyst IT Australia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {

    public static function extend_penalty_recalculation(before_penalty_recalculation $hook): void {
        global $DB;

        switch ($hook->context->contextlevel) {
            case CONTEXT_MODULE:
                if ($hook->cm->modname === 'assign') {
                    helper::apply_penalties($hook->cm->instance);
                }
                break;
            case CONTEXT_COURSE:
                $modinfo = get_fast_modinfo($hook->courseid);
                foreach ($modinfo->get_instances_of('assign') as $cm) {
                    helper::apply_penalties($cm->instance);
                }
                break;
            case CONTEXT_SYSTEM:
                $sql = 'SELECT DISTINCT assignment FROM {assign_grades}';
                $records = $DB->get_records_sql($sql);
                foreach ($records as $record) {
                    helper::apply_penalties($record->assignment);
                }
                break;
        }
    }
}
