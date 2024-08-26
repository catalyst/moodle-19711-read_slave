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

namespace gradepenalty_duedate\table;

use context;
use context_system;
use html_writer;
use table_sql;
use core_exemptions\service_factory;

/**
 * Table for penalty rule.
 *
 * @package   gradepenalty_duedate
 * @copyright 2024 Catalyst IT Australia Pty Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class context_exemption_table extends table_sql {

    /** @var context context */
    protected $context = null;

    /**
     * Sets up the table_log parameters.
     *
     * @param string $uniqueid A unique id for the table.
     * @param int $contextid The context id.
     */
    public function __construct($uniqueid, $contextid) {
        parent::__construct($uniqueid);

        $this->context = context::instance_by_id($contextid);

        // Add columns.
        $this->define_columns([
            'id',
        ]);

        // Add columns header.
        $this->define_headers([
            get_string('exemption_table:user', 'gradepenalty_duedate'),
        ]);

        // Disable sorting.
        $this->sortable(false);

        // Disable hiding columns.
        $this->collapsible(false);
    }

    /**
     * Query the DB.
     *
     * @param int $pagesize size of page for paginated displayed table.
     * @param bool $useinitialsbar do you want to use the initials bar.
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        global $DB;

        $contextids = array_filter(explode('/', $this->context->path ?? ''), 'is_numeric');

        $service = service_factory::get_service_for_component('gradepenalty_duedate');
        $userexems = $service->find_by(['itemtype' => 'user', 'contextid' => $contextids]);
        $userids = array_column($userexems, 'itemid');

        $groupexems = $service->find_by(['itemtype' => 'group', 'contextid' => $contextids]);
        foreach ($groupexems as $groupexem) {
            $groupmembers = groups_get_members($groupexem->itemid);
            $userids = array_merge($userids, array_column($groupmembers, 'id'));
        }

        $this->rawdata = user_get_users_by_id(array_unique($userids));
    }

    /**
     * Formats the itemid (user) column.
     *
     * @param mixed $row
     * @return string
     */
    public function col_id($row) {
        return html_writer::link(new \moodle_url('/user/profile.php', ['id' => $row->id]), fullname($row));
    }
}
