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
class user_exemption_table extends table_sql {

    /** @var context context */
    protected $context = null;

    /** @var array users */
    protected $users = [];

    /** @var \course_modinfo modinfo */
    protected $modinfo;

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
            'itemid',
            'reason',
            'contextid',
        ]);

        // Add columns header.
        $this->define_headers([
            get_string('exemption_table:user', 'gradepenalty_duedate'),
            get_string('exemption_table:reason', 'gradepenalty_duedate'),
            get_string('exemption_table:actions', 'gradepenalty_duedate'),
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

        $sort = $this->get_sql_sort();

        $service = service_factory::get_service_for_component('gradepenalty_duedate');
        $options = [
            'itemtype' => 'user',
            'contextid' => $this->context->id,
        ];
        $userexems = $service->find_by($options);
        $this->users = user_get_users_by_id(array_column($userexems, 'itemid'));
        $this->rawdata = $userexems;
    }

    /**
     * Formats the itemid (user) column.
     *
     * @param mixed $row
     * @return string
     */
    public function col_itemid($row) {
        $name = fullname($this->users[$row->itemid]);
        return html_writer::link(new \moodle_url('/user/profile.php', ['id' => $row->itemid]), $name);
    }

    /**
     * Formats the actions column.
     *
     * @param mixed $row
     * @return string
     */
    public function col_contextid($row) {
        global $OUTPUT;

        // Edit action button.
        $options = [
            'contextid' => $this->context->id,
            'action' => 'edit',
            'id' => $row->id,
        ];
        $url = new \moodle_url('/grade/penalty/duedate/manage_exemptions.php', $options);
        $icon = $OUTPUT->pix_icon('i/settings', get_string('update'));
        $updatelink = html_writer::link($url, $icon, ['title' => get_string('update')]);

        // Delete action button.
        $options = [
            'contextid' => $this->context->id,
            'action' => 'delete',
            'id' => $row->id,
            'sesskey' => sesskey(),
        ];
        $url = new \moodle_url('/grade/penalty/duedate/manage_exemptions.php', $options);
        $icon = $OUTPUT->pix_icon('t/delete', get_string('delete'));
        $deletelink = html_writer::link($url, $icon, ['title' => get_string('delete')]);

        // Return the action links.
        return $updatelink . ' ' . $deletelink;
    }
}
