<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace gradepenalty_duedate\output\form;

use core_exemptions\local\service\component_exemption_service;
use core_exemptions\service_factory;
use gradepenalty_duedate\exemption_helper;
use moodleform;
use context_course;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");

/**
 * Form for adding a new AI statement.
 *
 * @package     gradepenalty_duedate
 * @copyright   2024 Catalyst IT Australia
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exemption_form extends moodleform {
    /**
     * Form definition.
     *
     * @return void
     */
    public function definition() {
        $mform = $this->_form;

        // Add hidden contextid field to prevent the parent page from complaining.
        $mform->addElement('hidden', 'contextid', $this->_customdata['contextid']);
        $mform->setType('contextid', PARAM_INT);

        // Add hidden id field.
        $id = $this->_customdata['id'] ?? 0;
        $mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);

        // Add exemption type select element, with options for user and group exemptions.
        $mform->addElement('select', 'type', get_string('exemption_form:type', 'gradepenalty_duedate'), [
            'user' => get_string('exemption_form:type:user', 'gradepenalty_duedate'),
            'group' => get_string('exemption_form:type:group', 'gradepenalty_duedate'),
        ]);
        $mform->addHelpButton('type', 'exemption_form:type', 'gradepenalty_duedate');
        $mform->setType('type', PARAM_ALPHA);
        $mform->disabledIf('type', 'id', 'neq', 0);

        if (empty($id)) {
            // Add an autocomplete element for selecting students.
            $mform->addElement('autocomplete', 'users', get_string('exemption_form:users', 'gradepenalty_duedate'),
                $this->get_participants(), ['multiple' => true]);
            $mform->addHelpButton('users', 'exemption_form:users', 'gradepenalty_duedate');
            $mform->setType('users', PARAM_INT);
            $mform->hideIf('users', 'type', 'neq', 'user');

            // Add an autocomplete element for selecting groups.
            $mform->addElement('autocomplete', 'groups', get_string('exemption_form:groups', 'gradepenalty_duedate'),
                $this->get_groups(), ['multiple' => true]);
            $mform->addHelpButton('groups', 'exemption_form:groups', 'gradepenalty_duedate');
            $mform->setType('groups', PARAM_INT);
            $mform->hideIf('groups', 'type', 'neq', 'group');
        } else {
            $mform->addElement('static', 'users', get_string('exemption_form:users', 'gradepenalty_duedate'));
            $mform->disabledIf('users', 'id', 'neq', 0);
            $mform->hideIf('users', 'type', 'neq', 'user');

            $mform->addElement('static', 'groups', get_string('exemption_form:groups', 'gradepenalty_duedate'));
            $mform->disabledIf('groups', 'id', 'neq', 0);
            $mform->hideIf('groups', 'type', 'neq', 'group');
        }

        // Add an editor for the reason.
        $mform->addElement('editor', 'reason', get_string('exemption_form:reason', 'gradepenalty_duedate'), ['rows' => 10], [
            'maxfiles' => 0,
            'noclean' => true,
            'context' => context_course::instance($this->_customdata['courseid']),
        ]);
        $mform->addHelpButton('reason', 'exemption_form:reason', 'gradepenalty_duedate');
        $mform->setType('reason', PARAM_RAW);

        // Submit and cancel buttons.
        if (empty($id)) {
            $buttons = [
                $mform->createElement('submit', 'create', get_string('create')),
                $mform->createElement('cancel'),
            ];
        } else {
            $buttons = [
                $mform->createElement('submit', 'create', get_string('update')),
                $mform->createElement('cancel'),
            ];
        }

        $mform->addGroup($buttons, 'buttons', '', null, false);
    }

    /**
     * Validate form data.
     *
     * @param array $data Form data.
     * @param array $files Form files.
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (empty($data['id'])) {
            if ($data['type'] === 'user' && empty($data['users'])) {
                $errors['users'] = get_string('required');
            }

            if ($data['type'] === 'group' && empty($data['groups'])) {
                $errors['groups'] = get_string('required');
            }
        }

        return $errors;
    }

    /**
     * Process form submission.
     *
     * @param \stdClass $data Form data.
     * @return void
     */
    public function process(\stdClass $data): void {
        if (empty($data->id)) {
            foreach ($data->users as $userid) {
                exemption_helper::exempt_user($userid, $this->_customdata['contextid'],
                    $data->reason['text'], $data->reason['format']);
            }

            foreach ($data->groups as $groupid) {
                exemption_helper::exempt_group($groupid, $this->_customdata['contextid'],
                    $data->reason['text'], $data->reason['format']);
            }
        } else {
            $exemption = exemption_helper::get_exemption($data->id);
            $exemption->reason = $data->reason['text'];
            $exemption->reasonformat = $data->reason['format'];
            exemption_helper::update_exemption($exemption);
        }
    }

    /**
     * Get course participants.
     *
     * @return array
     */
    private function get_participants() {
        $users = get_enrolled_users(context_course::instance($this->_customdata['courseid']));
        $options = [];
        foreach ($users as $user) {
            $options[$user->id] = fullname($user);
        }
        return $options;
    }

    /**
     * Get course groups.
     *
     * @return array
     */
    private function get_groups() {
        $groups = groups_get_all_groups($this->_customdata['courseid']);
        $options = [];
        foreach ($groups as $group) {
            $options[$group->id] = $group->name;
        }
        return $options;
    }

    /**
     * Populate the form with data.
     *
     * @return void
     */
    public function self_populate() {
        global $DB;
        if (empty($this->_customdata['id'])) {
            return;
        }

        $exemption = exemption_helper::get_exemption($this->_customdata['id']);
        $data = (object) [
            'type' => $exemption->itemtype,
            'reason' => ['text' => $exemption->reason, 'format' => $exemption->reasonformat],
        ];

        if ($exemption->itemtype === 'user') {
            $user = $DB->get_record('user', ['id' => $exemption->itemid]);
            $data->users = fullname($user);

        } else if ($exemption->itemtype === 'group') {
            $group = groups_get_group($exemption->itemid);
            $data->groups = $group->name;
        }

        $this->set_data($data);
    }
}
