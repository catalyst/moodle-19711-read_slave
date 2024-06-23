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

namespace gradepenalty_duedate\output\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once(__DIR__ . '/../../../lib.php');

use gradepenalty_duedate\penalty_rule;
use moodleform;

/**
 * Form to set up the penalty rules for the gradepenalty_duedate plugin.
 *
 * @package    gradepenalty_duedate
 * @copyright 2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_penalty_form extends moodleform {
    /** @var int contextid context id where the penalty rules are edited */
    protected $contextid = 0;

    /**
     * Define the form.
     *
     * @return void
     */
    public function definition() {
        $mform = $this->_form;
        $this->contextid = $this->_customdata['contextid'] ?? 0;

        // Hidden context id, value is stored in $mform.
        $mform->addElement('hidden', 'contextid');
        $mform->setType('contextid', PARAM_INT);
        $mform->setDefault('contextid', $this->contextid);

        // Get existing penalty rules.
        $rules = penalty_rule::get_records(['contextid' => $this->contextid]);
        $repeatcount = count($rules);
        $elements = [];
        $options = [];

        // Late for.
        $elements[] = $mform->createElement('static', '', '', get_string('latefor_label', 'gradepenalty_duedate'));
        $elements[] = $mform->createElement('static', '', '', '&le;');
        $elements[] = $mform->createElement('duration', 'latefor',
            get_string('latefor_label', 'gradepenalty_duedate'),
            ['optional' => false, 'defaultunit' => DAYSECS]);
        $options['latefor']['type'] = PARAM_INT;
        $options['latefor']['default'] = DAYSECS;

        // Penalty.
        $elements[] = $mform->createElement('static', '', '', get_string('penalty_label', 'gradepenalty_duedate'));
        $elements[] = $mform->createElement('text', 'penalty',
            get_string('penalty_label', 'gradepenalty_duedate'), 'maxlength="5" size="5"');
        $options['penalty']['type'] = PARAM_INT;
        $options['penalty']['default'] = 1;
        $elements[] = $mform->createElement('static', '', '', '%');

        // Delete button.
        $elements[] = $mform->createElement('submit', 'deleterule',
            get_string('delete'), [], false, ['customclassoverride' => 'btn btn-danger']);

        // Put them in a group.
        $group = $mform->createElement('group', 'rulegroup',
            get_string('penaltyrule_group', 'gradepenalty_duedate'), $elements, ['&nbsp;'], false);

        // Create repeatable elements.
        $this->repeat_elements([$group], $repeatcount, $options, 'rulegroupcount', 'ruleadd', 3,
            get_string('addnewrule', 'gradepenalty_duedate'), false, 'deleterule');

        // Set data.
        if (!empty($rules)) {
            $data = [];
            foreach ($rules as $rule) {
                $data['latefor[' . $rule->get('sortorder') . ']'] = $rule->get('latefor');
                $data['penalty[' . $rule->get('sortorder') . ']'] = $rule->get('penalty');
            }
            $this->set_data($data);
        }

        // Info box about the overdue time greater than the last rule.
        $mform->addElement('html', '<div class="alert alert-info">' .
                get_string('notice_last_rule', 'gradepenalty_duedate')
            . '</div>');

        // Add submit and cancel buttons.
        $this->add_action_buttons();
    }

    /**
     * Validate the form data.
     *
     * @param object $data form data
     * @param object $files form files
     * @return array of errors
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Check if there is any data.
        if (empty($data['latefor']) || empty($data['penalty'])) {
            return $errors;
        }

        // The late for and penalty values must be in ascending order.
        $lateforlowerbound = GRADEPENALTY_DUEDATE_LATEFOR_MIN - 1;
        $lateforupperbound = GRADEPENALTY_DUEDATE_LATEFOR_MAX + 1;
        $penaltylowerbound = GRADEPENALTY_DUEDATE_PENALTY_MIN - 1;
        $penaltyupperbound = GRADEPENALTY_DUEDATE_PENALTY_MAX + 1;

        // Go to each 'rulefields' group.
        for ($i = 0; $i < count($data['latefor']); $i++) {

            $rulegroupid = 'rulegroup[' . $i . ']';

            // Validate min value of late for.
            if ($data['latefor'][$i] <= $lateforlowerbound) {
                if ($lateforlowerbound == GRADEPENALTY_DUEDATE_LATEFOR_MIN - 1) {
                    // Minimum value a late for can have.
                    $errormessage = get_string('error_latefor_minvalue', 'gradepenalty_duedate',
                        format_time(GRADEPENALTY_DUEDATE_LATEFOR_MIN));
                } else {
                    // Must be greater than the previous late for.
                    $errormessage = get_string('error_latefor_abovevalue', 'gradepenalty_duedate',
                        format_time($lateforlowerbound));
                }
                $errors[$rulegroupid] = $errormessage;
            } else {
                $lateforlowerbound = $data['latefor'][$i];
            }

            // Validate max value of late for.
            if ($data['latefor'][$i] >= $lateforupperbound) {
                $errors[$rulegroupid] = get_string('error_latefor_maxvalue', 'gradepenalty_duedate',
                    format_time(GRADEPENALTY_DUEDATE_LATEFOR_MAX));
            }

            // Validate penalty.
            if ($data['penalty'][$i] <= $penaltylowerbound) {
                if ($penaltylowerbound == GRADEPENALTY_DUEDATE_PENALTY_MIN - 1) {
                    // Minimum value a penalty can have.
                    $errormessage = get_string('error_penalty_minvalue', 'gradepenalty_duedate',
                        format_float(GRADEPENALTY_DUEDATE_PENALTY_MIN));
                } else {
                    // Must be greater than the previous penalty.
                    $errormessage = get_string('error_penalty_abovevalue', 'gradepenalty_duedate',
                        format_float($penaltylowerbound));
                }

                if (isset($errors[$rulegroupid])) {
                    // Append to existing error message.
                    $errors[$rulegroupid] .= ' ' . $errormessage;
                } else {
                    // Create new error message.
                    $errors[$rulegroupid] = $errormessage;
                }
            } else {
                $penaltylowerbound = $data['penalty'][$i];
            }

            // Validate max value of penalty.
            if ($data['penalty'][$i] >= $penaltyupperbound) {
                $errors[$rulegroupid] = get_string('error_penalty_maxvalue', 'gradepenalty_duedate',
                    format_float(GRADEPENALTY_DUEDATE_PENALTY_MAX));
            }
        }

        return $errors;
    }

    /**
     * Save the form data.
     *
     * @param object $data form data
     * @return void
     */
    public function save_data($data) {
        // Get penalty rules.
        $rules = penalty_rule::get_records(['contextid' => $this->contextid], 'sortorder', 'ASC');

        // Go to each 'rulefields' group.
        $numofrules = !isset($data->latefor) ? 0 : count($data->latefor);
        for ($i = 0; $i < $numofrules; $i++) {

            // Create new rule if it does not exist.
            if (!isset($rules[$i])) {
                $rule = new penalty_rule();
            } else {
                $rule = $rules[$i];
            }

            // Set the values.
            $rule->set('contextid', $this->contextid);
            $rule->set('sortorder', $i);
            $rule->set('latefor', $data->latefor[$i]);
            $rule->set('penalty', $data->penalty[$i]);

            // Save the rule.
            $rule->save();
        }

        // Delete rules if there are more rules than the form data.
        if (count($rules) > $numofrules) {
            for ($i = $numofrules; $i < count($rules); $i++) {
                $rules[$i]->delete();
            }
        }
    }
}
