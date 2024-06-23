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

namespace gradepenalty_duedate;

use context_module;
use context_course;
use context_system;
use core_grades\local\penalty\penalty_handler;

/**
 * Calculate penalty.
 *
 * @package    gradepenalty_duedate
 * @copyright  2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class handler extends penalty_handler {

    /**
     * Mark will be deducted from student grade.
     *
     * @param float $finalgrade Final grade.
     * @return float the penalty.
     */
    public function calculate_penalty($finalgrade): float {
        $penalty = 0.0;

        // Calculate the difference between the submission date and the due date.
        $diff = $this->submissiondate - $this->duedate;

        // If the submission date is after the due date, calculate the penalty.
        if ($diff > 0) {
            // Get all penalty rules, ordered by the highest penalty first.
            $penaltyrules = $this->find_effective_penalty_rules();

            // Check each rule to see which rule will apply.
            if (!empty($penaltyrules)) {
                // Check if the diff is greater than the last rule.
                if ($diff > $penaltyrules[count($penaltyrules) - 1]->get('latefor')) {
                    // We will have the same penalty as the last rule.
                    $penalty = $penaltyrules[count($penaltyrules) - 1]->get('penalty');
                } else {
                    foreach ($penaltyrules as $penaltyrule) {
                        if ($diff <= $penaltyrule->get('latefor')) {
                            $penalty = $penaltyrule->get('penalty');
                            break;
                        }
                    }
                }
            }
        }

        // Calculate the deducted grade.
        return $finalgrade * $penalty / 100;
    }

    /**
     * Find effective penalty rule.
     *
     * @return array
     */
    private function find_effective_penalty_rules(): array {
        // Course module context id.
        $modulecontext = context_module::instance($this->cm->id);

        // Get all penalty rules, ordered by the highest penalty first.
        $penaltyrules = penalty_rule::get_records(['contextid' => $modulecontext->id], 'sortorder');

        // If there is no penalty rule, go to the course context.
        if (empty($penaltyrules)) {
            // Find course content.
            $course = get_course($this->cm->course);
            $coursecontext = context_course::instance($course->id);

            $penaltyrules = penalty_rule::get_records(['contextid' => $coursecontext->id], 'sortorder');
        }

        // If there is no penalty rule, go to the system context.
        if (empty($penaltyrules)) {
            $systemcontext = context_system::instance();
            $penaltyrules = penalty_rule::get_records(['contextid' => $systemcontext->id], 'sortorder');
        }

        return $penaltyrules;
    }
}
