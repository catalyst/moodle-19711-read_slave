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

namespace mod_assign\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/assign/lib.php');
require_once($CFG->dirroot.'/course/lib.php');

use core\exception\moodle_exception;
use core\task\adhoc_task;

/**
 * Ad-hoc task to recalculate penalties for users in an assignment.
 *
 * @package    mod_assign
 * @copyright  2024 David Woloszyn <david.woloszyn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recalculate_penalties extends adhoc_task {

    /**
     * Execute the task.
     */
    public function execute(): void {
        global $DB;
        try {
            $assignid = $this->get_custom_data()->assignid;
            $assign = $DB->get_record('assign', ['id' => $assignid], '*', MUST_EXIST);
            $cm = get_coursemodule_from_instance('assign', $assignid, 0, false, MUST_EXIST);
            $assign->cmidnumber = $cm->idnumber;
            assign_update_grades($assign);
        } catch (moodle_exception $e) {
            debugging($e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Queue the task.
     *
     * @param int $assignid assignment id
     * @param int $usermodified user who triggered the recalculation
     */
    public static function queue(int $assignid, int $usermodified): void {
        $task = new self();
        $task->set_custom_data((object)[
            'assignid' => $assignid,
            'usermodified' => $usermodified,
        ]);
        \core\task\manager::queue_adhoc_task($task);
    }
}
