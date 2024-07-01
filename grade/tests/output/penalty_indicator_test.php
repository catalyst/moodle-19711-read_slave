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

use advanced_testcase;

/**
 * Test class for penalty_indicator
 *
 * @package   core_grades
 * @copyright 2024 Catalyst IT Australia Pty Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class penalty_indicator_test extends advanced_testcase {
    /**
     * Data provider for test_export_for_template
     * @return array
     */
    public static function export_for_template_provider(): array {
        return [
            // Default icon, with max grade.
            [
                'html' => <<<EOD
<span class="penalty-indicator-icon" title="Late penalty applied -10.00 marks">
        <i class="icon fa fa-triangle-exclamation text-danger fa-fw " aria-hidden="true"  ></i>
    </span>
    <span class="penalty-indicator-value">
            90.00 / 100.00
    </span>
EOD,
                'icon' => [],
                'penalty' => 10,
                'finalgrade' => 90,
                'maxgrade' => 100,
            ],
            // Custom icon, without max grade.
            [
                'html' => <<<EOD
<span class="penalty-indicator-icon" title="Late penalty applied -10.00 marks">
        <i class="icon fa fa-flag fa-fw " aria-hidden="true"  ></i>
    </span>
    <span class="penalty-indicator-value">
            90.00
    </span>
EOD,
                'icon' => ['name' => 'i/flagged', 'component' => 'core'],
                'penalty' => 10,
                'finalgrade' => 90,
                'maxgrade' => null,
            ],
        ];
    }

    /**
     * Test penalty_indicator
     *
     * @dataProvider export_for_template_provider
     *
     * @covers \core_grades\output\penalty_indicator
     *
     * @param string $expectedhtml The expected html
     * @param array $icon icon to display before the penalty
     * @param float $penalty The penalty
     * @param float $finalgrade The final grade
     * @param float|null $maxgrade The max grade
     */
    public function test_export_for_template(string $expectedhtml, array $icon, float $penalty,
                                             float $finalgrade, ?float $maxgrade): void {
        global $PAGE;
        $indicator = new \core_grades\output\penalty_indicator(2, $penalty, $finalgrade, $maxgrade, $icon);
        $renderer = $PAGE->get_renderer('core_grades');
        $html = $renderer->render_penalty_indicator($indicator);

        $this->assertEquals($expectedhtml, $html);
    }
}
