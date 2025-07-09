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
 * Prints an instance of mod_stalloc chairmember page
 *
 * @package     mod_stalloc
 * @copyright   2025 Marc-André Schmidt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/config.php');
require_once(__DIR__.'/locallib.php');

$id = required_param('id', PARAM_INT);

list ($course, $cm) = get_course_and_cm_from_cmid($id, 'stalloc');
$instance = $DB->get_record('stalloc', ['id'=> $cm->instance], '*', MUST_EXIST);
$course_id = $course->id;
$context = context_course::instance($course_id);
require_login($course, false, $cm);

// Initialize the header
$paramsheader = initialize_stalloc_header(PAGE_CHAIRMEMBER, $id, $course_id, $instance);

// First check if the user has the capability to be on this page! -> Admins/Teachers.
if (has_capability('mod/stalloc:examination_member', context_course::instance($course_id)))  {
    // Display the page layout.
    $strpage = get_string('pluginname', 'mod_stalloc');
    $PAGE->set_pagelayout('incourse');
    $PAGE->set_context(context_module::instance($cm->id));
    $PAGE->set_heading($strpage);
    $PAGE->set_url(new moodle_url('/mod/stalloc/chairmember.php', ['id' => $id]));
    $PAGE->set_title($course->shortname.': '.$strpage);

    // Output the header.
    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('stalloc/header', $paramsheader);

    // Paramater Array.
    $params_chair = [];

    // Load all Chairs.
    $chair_data = $DB->get_records('stalloc_chair', [], "name ASC");
    $index = 0;

    foreach ($chair_data as $chair) {
        // Load all member of a chair.
        $member_data = $DB->get_records('stalloc_chair_member', ['chair_id' => $chair->id]);
        foreach ($member_data as $member) {
            $moodle_user_data = $DB->get_record('user', ['id' => $member->moodle_user_id]);
            $params_chair['member'][$index] = new stdClass();
            $params_chair['member'][$index]->index = $index+1;
            $params_chair['member'][$index]->chair_name = $chair->name;
            $params_chair['member'][$index]->member_name = $moodle_user_data->lastname . ", " . $moodle_user_data->firstname;
            $params_chair['member'][$index]->edit_url = new moodle_url('/mod/stalloc/chairmember_edit.php', ['chairmember_id' => $member->id, 'id' => $id]);
            $params_chair['member'][$index]->delete_url = new moodle_url('/mod/stalloc/chairmember_delete.php', ['chairmember_id' => $member->id, 'id' => $id]);
            $index++;
        }
    }

    // Output the Chair Member Template
    echo $OUTPUT->render_from_template('stalloc/chairmember', $params_chair);

    // Displaying the footer.
    echo $OUTPUT->footer();
} else {
    redirect(course_get_url($course->id), "Missing Capability!", 4, 'NOTIFY_ERROR');
}

