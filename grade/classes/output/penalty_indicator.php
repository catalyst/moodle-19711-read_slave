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

namespace core_grades\output;

use core\output\renderer_base;
use templatable;
use renderable;

/**
 * The base class for the action bar in the gradebook pages.
 *
 * @package    core_grades
 * @copyright  2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class penalty_indicator implements templatable, renderable {
    /** @var int $decimals the decimal places */
    protected int $decimals;

    /** @var float $penalty the deducted grade */
    protected float $penalty;

    /** @var float|null $finalgrade the final grade */
    protected ?float $finalgrade;

    /** @var float|null $maxgrade the maximum grade */
    protected ?float $maxgrade;

    /** @var array|null $icon icon data */
    protected ?array $icon;

    /**
     * The class constructor.
     *
     * @param int $decimals the decimal places
     * @param float $penalty the deducted grade
     * @param float|null $finalgrade the final grade
     * @param float|null $maxgrade the maximum grade
     * @param array|null $icon icon data
     */
    public function __construct(int $decimals, float $penalty, ?float $finalgrade = null,
                                ?float $maxgrade = null, ?array $icon = null) {
        $this->icon = $icon;
        $this->decimals = $decimals;
        $this->penalty = $penalty;
        $this->finalgrade = $finalgrade;
        $this->maxgrade = $maxgrade;
    }

    /**
     * Returns the template for the actions bar.
     *
     * @return string
     */
    public function get_template(): string {
        return 'core_grades/penalty_indicator';
    }

    /**
     * Export the data for the mustache template.
     *
     * @param \renderer_base $output renderer to be used to render the penalty indicator.
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        $context = [
            'penalty' => format_float($this->penalty, $this->decimals),
            'finalgrade' => $this->finalgrade ? format_float($this->finalgrade, $this->decimals) : null,
            'maxgrade' => $this->maxgrade ? format_float($this->maxgrade, $this->decimals) : null,
            'icon' => $this->icon ?: ['name' => 'i/risk_xss', 'component' => 'core'] ,
            'info' => get_string('gradepenalty_indicator_info', 'core_grades', format_float($this->penalty, $this->decimals)),
        ];

        return $context;
    }
}
