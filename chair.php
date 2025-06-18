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
 * Prints an instance of mod_stalloc chair page
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
$paramsheader = initialize_stalloc_header(PAGE_CHAIR, $id, $course_id, $instance);

// First check if the user has the capability to be on this page! -> Admins/Teachers.
if (has_capability('mod/stalloc:examination_member', context_module::instance($instance->id)) || has_capability('mod/stalloc:chairmember', context_module::instance($instance->id)))  {
    // Display the page layout.
    $strpage = get_string('pluginname', 'mod_stalloc');
    $PAGE->set_pagelayout('incourse');
    $PAGE->set_context(context_module::instance($cm->id));
    $PAGE->set_heading($strpage);
    $PAGE->set_url(new moodle_url('/mod/stalloc/chair.php', ['id' => $id]));
    $PAGE->set_title($course->shortname.': '.$strpage);

    // Output the header.
    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('stalloc/header', $paramsheader);

    // Paramater Array.
    $params_chair = [];

    // Load chairs from the database which are connected to this course module.
    $chair_data = $DB->get_records('stalloc_chair', ['course_id' => $course_id, 'cm_id' => $id], "name ASC");

    // Calculate the sum of all chair distribution keys.
    $distrubition_key_total_sum = 0;
    foreach ($chair_data as $chair) {
        if($chair->active == 1) {
            $distrubition_key_total_sum += $chair->distribution_key;
        }
    }

    // Get the total Number of students.
    $student_number = $DB->count_records('stalloc_student', ['course_id' => $course_id, 'cm_id' => $id, 'declaration' => 1]);
    $student_number = ceil(($student_number + $student_number*STUDENT_BUFFER));


    if (has_capability('mod/stalloc:examination_member', context_module::instance($instance->id))) {
        $params_chair['admin_settings'] = true;
    } else {
        // Load the chair of the current chair member.
        $current_chair_id = $DB->get_record('stalloc_chair_member', ['course_id' => $course_id, 'cm_id' => $id, 'moodle_user_id' => $USER->id])->chair_id;
        $chair_data = $DB->get_records('stalloc_chair', ['id' => $current_chair_id]);
    }

    // Prepare the loaded Chair data for the template and save this information in a parameter array.
    $index = 0;
    foreach($chair_data as $key=>$chair) {
        $params_chair['chair'][$index] = new stdClass();
        $params_chair['chair'][$index]->index = $index+1;
        $params_chair['chair'][$index]->chair_name = $chair->name;
        $params_chair['chair'][$index]->chair_holder = $chair->holder;
        $params_chair['chair'][$index]->chair_contact_name = $chair->contact_name;
        $params_chair['chair'][$index]->chair_contact_phone = $chair->contact_phone;
        $params_chair['chair'][$index]->chair_contact_mail = $chair->contact_mail;
        $params_chair['chair'][$index]->chair_distribution_key = $chair->distribution_key;
        $params_chair['chair'][$index]->chair_flexnow_id = $chair->flexnow_id;

        if($chair->active == 1) {
            $params_chair['chair'][$index]->active = true;
            $max_students = ceil(($student_number * $chair->distribution_key) / $distrubition_key_total_sum);
            $params_chair['chair'][$index]->max_students = $max_students;
            //$params_chair['chair'][$index]->max_allocation_students = floor($max_students * MAX_STUDENT_ALLOCATIONS_PERCENT);
            $params_chair['chair'][$index]->max_allocation_students = $max_students;
            $allocated_students = $DB->count_records('stalloc_allocation', ['course_id' => $course_id, 'cm_id' => $id, 'chair_id' => $chair->id, 'checked' => 1]);
            $params_chair['chair'][$index]->students = $allocated_students;
        } else {
            $params_chair['chair'][$index]->inactive = true;
            $params_chair['chair'][$index]->max_students = "-";
            $params_chair['chair'][$index]->max_allocation_students = "-";
            $params_chair['chair'][$index]->students = "-";
        }
        $params_chair['chair'][$index]->edit_chair_url = new moodle_url('/mod/stalloc/chair_edit.php', ['chair_id' => $chair->id, 'id' => $id]);
        $params_chair['chair'][$index]->delete_chair_url = new moodle_url('/mod/stalloc/chair_delete.php', ['chair_id' => $chair->id, 'id' => $id]);


        $chairmember_data = $DB->get_records('stalloc_chair_member', ['course_id' => $course_id, 'cm_id' => $id, 'chair_id' => $chair->id]);
        $member_index = 0;
        foreach ($chairmember_data as $chairmember) {
            $user_data = $DB->get_record('user', ['id' => $chairmember->moodle_user_id]);
            $params_chair['chair'][$index]->chair_member[$member_index] = new stdClass();
            $params_chair['chair'][$index]->chair_member[$member_index]->member_lastname = $user_data->lastname;
            $params_chair['chair'][$index]->chair_member[$member_index]->member_firstname = $user_data->firstname;
            $member_index++;
        }

        $index++;
    }
    $params_chair['new_chair_url'] = new moodle_url('/mod/stalloc/chair_add.php', ['id' => $id]);

    // Output the Chair Template
    echo $OUTPUT->render_from_template('stalloc/chair', $params_chair);

    // Displaying the footer.
    echo $OUTPUT->footer();
} else {
    redirect(course_get_url($course->id), "Missing Capability!", 4, 'NOTIFY_ERROR');
}

