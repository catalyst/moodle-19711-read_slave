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

/**
 * Site configuration settings for the gradepenalty_duedate plugin
 *
 * @package   gradepenalty_duedate
 * @copyright 2024 Catalyst IT Australia Pty Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\notification;
use gradepenalty_duedate\exemption_helper;
use gradepenalty_duedate\output\form\edit_penalty_form;
use gradepenalty_duedate\penalty_rule;
use gradepenalty_duedate\table\user_exemption_table;
use gradepenalty_duedate\table\context_exemption_table;
use gradepenalty_duedate\table\group_exemption_table;
use gradepenalty_duedate\table\penalty_rule_table;
use gradepenalty_duedate\output\form\exemption_form;

require_once(__DIR__ . '/../../../config.php');

// Page parameters.
$contextid = required_param('contextid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);
$sesskey = optional_param('sesskey', '', PARAM_RAW);

// Check login and permissions.
[$context, $course, $cm] = get_context_info_array($contextid);
if ($context->contextlevel == CONTEXT_SYSTEM) {
    require_admin();
} else {
    require_login($course, false, $cm);
    require_capability('gradepenalty/duedate:manage', $context);
}

$PAGE->set_context($context);
$url = new moodle_url('/grade/penalty/duedate/manage_exemptions.php', ['contextid' => $contextid]);
$PAGE->set_url($url);

// Display page according to context.
switch ($context->contextlevel) {
    case CONTEXT_COURSE:
        $course = get_course($context->instanceid);
        $PAGE->set_heading($course->fullname);
        break;
    case CONTEXT_MODULE:
        $PAGE->set_heading($PAGE->activityrecord->name);
        break;
    default:
        $PAGE->set_heading(get_string('administrationsite'));
        break;
}

// Print the header and tabs.
$PAGE->set_cacheable(false);
$title = get_string('manage_exemptions:manage', 'gradepenalty_duedate');
$PAGE->set_title($title);
$PAGE->set_pagelayout('admin');
$PAGE->activityheader->disable();

$mform = new exemption_form(null, ['contextid' => $contextid, 'courseid' => $course->id, 'id' => $id]);
if ($mform->is_cancelled()) {
    redirect($url);
}

// Process deletion.
if ($id && $action === 'deleteconfirm' && confirm_sesskey()) {
    exemption_helper::delete_exemption($id);
    redirect($url, get_string('exemption_form:successdelete', 'gradepenalty_duedate'));
}

if ($data = $mform->get_data()) {
    $mform->process($data);

    $message = '';
    if ($data->create) {
        $message = get_string('exemption_form:success', 'gradepenalty_duedate');
    }
    redirect($url, $message);
}

// Start output.
echo $OUTPUT->header();

// Add heading with help text.
echo $OUTPUT->heading_with_help($title, 'manage_exemptions:manage', 'gradepenalty_duedate');

// Display confirmation screen when deleting.
if ($id && $action === 'delete' && confirm_sesskey()) {
    $yesurl = new moodle_url('/grade/penalty/duedate/manage_exemptions.php', [
        'id' => $id,
        'action' => 'deleteconfirm',
        'sesskey' => sesskey(),
        'contextid' => $contextid,
    ]);
    $nourl = new moodle_url('/grade/penalty/duedate/manage_exemptions.php', [
        'contextid' => $contextid,
    ]);
    echo $OUTPUT->confirm(get_string('manage_exemptions:deleteconfirm', 'gradepenalty_duedate'), $yesurl, $nourl);
    echo $OUTPUT->footer();
    exit;
}

if ($action === 'add' || ($mform->is_submitted() && empty($data))) {
    $mform->display();
} else if ($action === 'edit') {
    $mform->self_populate();
    $mform->display();
} else {
    $url = new moodle_url('/grade/penalty/duedate/manage_exemptions.php', ['contextid' => $contextid, 'action' => 'add']);
    echo $OUTPUT->box_start();
    echo $OUTPUT->single_button($url, get_string('manage_exemptions:new', 'gradepenalty_duedate'), 'get', ['type' => 'primary']);
    echo $OUTPUT->box_end();

    // Display the user exemption table.
    echo $OUTPUT->box_start();
    echo $OUTPUT->heading_with_help(get_string('manage_exemptions:usertable', 'gradepenalty_duedate'),
        'manage_exemptions:usertable', 'gradepenalty_duedate',
        '', '', 4);
    $penaltytable = new user_exemption_table('user_exemption_table', $contextid);
    $penaltytable->define_baseurl($url);
    $penaltytable->out(30, true);
    echo $OUTPUT->box_end();

    // Display the group exemption table.
    echo $OUTPUT->box_start();
    echo $OUTPUT->heading_with_help(get_string('manage_exemptions:grouptable', 'gradepenalty_duedate'),
        'manage_exemptions:grouptable', 'gradepenalty_duedate',
        '', '', 4);
    $groupexemptiontable = new group_exemption_table('group_exemption_table', $contextid);
    $groupexemptiontable->define_baseurl($url);
    $groupexemptiontable->out(30, true);
    echo $OUTPUT->box_end();

    // Display the context exemption table.
    echo $OUTPUT->box_start();
    echo $OUTPUT->heading_with_help(get_string('manage_exemptions:contexttable', 'gradepenalty_duedate'),
        'manage_exemptions:contexttable', 'gradepenalty_duedate',
        '', '', 4);
    $penaltytable = new context_exemption_table('context_exemption_table', $contextid);
    $penaltytable->define_baseurl($url);
    $penaltytable->out(30, true);
    echo $OUTPUT->box_end();
}

// Footer.
echo $OUTPUT->footer();
