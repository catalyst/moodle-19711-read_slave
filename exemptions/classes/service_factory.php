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

namespace core_exemptions;

/**
 * Contains the service_factory, a locator for services for the exemptions subsystem.
 * Services encapsulate the business logic, and any data manipulation code, and are what clients should interact with.
 *
 * @package     core_exemptions
 * @author      Alexander Van der Bellen <alexandervanderbellen@catalyst-au.net>
 * @copyright   2018 Jake Dallimore <jrhdallimore@gmail.com>
 * @copyright   2024 Catalyst IT Australia
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class service_factory {
    /**
     * Returns a basic service object providing operations for exemptions belonging to a given component.
     *
     * @param string $component frankenstyle component name.
     * @return local\service\component_exemption_service the service object.
     */
    public static function get_service_for_component(string $component): local\service\component_exemption_service {
        return new local\service\component_exemption_service($component, new local\repository\exemption_repository());
    }
}

