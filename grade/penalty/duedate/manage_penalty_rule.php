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
 * @package    gradepenalty_duedate
 * @copyright 2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use gradepenalty_duedate\output\form\edit_penalty_form;

require_once(__DIR__ . '/../../../config.php');
require_once("$CFG->libdir/adminlib.php");

// Page parameters.
$contextid = required_param('contextid', PARAM_INT);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

// Return to plugin category if no return URL is provided.
if (!$returnurl) {
    $returnurl = new moodle_url('/admin/category.php?category=gradepenalty_duedate');
}

list($context, $course, $cm) = get_context_info_array($contextid);

// Check login and permissions.
require_login($course, false, $cm);
require_capability('gradepenalty/duedate:manage', $context);
$PAGE->set_context($context);
$url = new moodle_url('/grade/penalty/duedate/manage_penalty_rule.php', ['contextid' => $contextid]);
$PAGE->set_url($url);

// Display page according to context.
if ($context->contextlevel == CONTEXT_COURSE) {
    $course = get_course($context->instanceid);
    $PAGE->set_heading($course->fullname);
} else if ($context->contextlevel == CONTEXT_MODULE) {
    $PAGE->set_heading($PAGE->activityrecord->name);
} else {
    $PAGE->set_heading(get_string('administrationsite'));
}

// Print the header and tabs.
$PAGE->set_cacheable(false);
$title = get_string('duedaterule', 'gradepenalty_duedate');
$PAGE->set_title($title);
$PAGE->set_pagelayout('admin');
$PAGE->activityheader->disable();

// Create a form to add / edit penalty rules.
$mform = new edit_penalty_form($url->out(), [
    'contextid' => $contextid,
]);

if ($mform->is_cancelled()) {
    redirect($returnurl);
} else if ($fromform = $mform->get_data()) {
    // Save the form data.
    $mform->save_data($fromform);

    // Redirect to the same page.
    redirect($url, get_string('changessaved'), 1, \core\output\notification::NOTIFY_SUCCESS);

} else {
    // Start output.
    echo $OUTPUT->header();

    // Add heading with help text.
    echo $OUTPUT->heading_with_help($title, 'penaltyrule', 'gradepenalty_duedate');

    // Display the form.
    echo $OUTPUT->box_start();
    $mform->display();
    echo $OUTPUT->box_end();

    // Footer.
    echo $OUTPUT->footer();
}
