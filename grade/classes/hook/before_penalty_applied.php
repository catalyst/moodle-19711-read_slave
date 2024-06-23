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

namespace core_grades\hook;

use core\hook\stoppable_trait;
use grade_item;
use Psr\EventDispatcher\StoppableEventInterface;

/**
 * Hook before penalty is applied.
 *
 * This hook will be dispatched before the penalty is applied to the grade.
 *
 * @package    core_grades
 * @copyright  2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to add required data before the penalty is applied to the grade.')]
#[\core\attribute\tags('grade')]
class before_penalty_applied implements StoppableEventInterface {

    use stoppable_trait;

    /**
     * Constructor for the hook.
     *
     * @param int $userid the user id
     * @param grade_item $gradeitem the grade item object
     * @param int|null $submissiondate submission date
     * @param int|null $duedate due date
     */
    public function __construct(
        /** @var int The user id */
        public readonly int $userid,
        /** @var grade_item $gradeitem the grade item object*/
        public readonly grade_item $gradeitem,
        /** @var ?int submission date */
        protected ?int $submissiondate,
        /** @var ?int due date */
        protected ?int $duedate,
    ) {
    }

    /**
     * Set the submission date.
     *
     * @param int $submissiondate The submission date.
     */
    public function set_submission_date(int $submissiondate): void {
        $this->submissiondate = $submissiondate;
    }

    /**
     * Set the due date.
     *
     * @param int $duedate The due date.
     */
    public function set_due_date(int $duedate): void {
        $this->duedate = $duedate;
    }

    /**
     * Get the submission date.
     *
     * @return int The submission date.
     */
    public function get_submission_date(): int {
        return $this->submissiondate;
    }

    /**
     * Get the due date.
     *
     * @return int The due date.
     */
    public function get_due_date(): int {
        return $this->duedate;
    }
}
