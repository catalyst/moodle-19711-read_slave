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

namespace gradepenalty_fake;

use core\check\performance\debugging;
use core_grades\local\penalty\penalty_handler;

/**
 * Calculate penalty.
 *
 * @package    core_grades
 * @copyright  2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class handler extends penalty_handler {

    /**
     * Calculate penalty.
     *
     * @param float $finalgrade final grade.
     *
     * @return float Penalty.
     */
    public function calculate_penalty($finalgrade): float {
        debugging('Submission date: ' . $this->submissiondate);
        debugging('Due date: ' . $this->duedate);
        return 20;
    }
}
