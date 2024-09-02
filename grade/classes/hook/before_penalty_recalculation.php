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

use core\context\module;
use core\hook\stoppable_trait;
use Psr\EventDispatcher\StoppableEventInterface;

/**
 * Hook before grade penalty recalculation is applied.
 *
 * This hook will be dispatched before the penalty recalculation is applied to the grade.
 * Allow plugins to do penalty recalculation.
 *
 * @package   core_grades
 * @copyright 2024 Catalyst IT Australia Pty Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Notify plugins that grade penalty recalculation is needed.')]
#[\core\attribute\tags('grade')]
class before_penalty_recalculation implements StoppableEventInterface {
    use stoppable_trait;

    /** @var \context $context The context in which the recalculation applies. */
    public readonly \context $context;

    /** @var int|null $courseid The course id, if applicable. */
    public readonly ?int $courseid;

    /** @var \cm_info|null $cm The course module object, if applicable. */
    public readonly ?\cm_info $cm;

    /** @var int $userid The user who triggered the event. */
    public readonly int $userid;

    /** @var int $timestamp The timestamp when the event was triggered. */
    public readonly int $timestamp;


    /**
     * Constructor for the hook.
     *
     * @param \context $context The context object
     * @param int $userid The user who triggered the event
     */
    public function __construct(\context $context, ?int $userid = null) {
        global $USER;

        $courseid = null;
        $cm = null;

        switch ($context->contextlevel) {
            case CONTEXT_SYSTEM:
                break;
            case CONTEXT_COURSE:
                $this->courseid = $this->context->instanceid;
                break;
            case CONTEXT_MODULE:
                $courseid = $context->get_course_context()->instanceid;
                $cm = get_fast_modinfo($courseid)->get_cm($context->instanceid);
                break;
        }

        $this->context = $context;
        $this->courseid = $courseid;
        $this->cm = $cm;
        $this->userid = $userid ?? $USER->id;
        $this->timestamp = time();
    }
}
