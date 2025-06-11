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
 * Prints an instance of mod_stalloc student edit page
 *
 * @package     mod_stalloc
 * @copyright   2025 Marc-André Schmidt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/config.php');
require_once(__DIR__.'/locallib.php');

$id = required_param('id', PARAM_INT);
$student_id = required_param('student_id', PARAM_INT);
$alloc_id = required_param('alloc_id', PARAM_INT);

list ($course, $cm) = get_course_and_cm_from_cmid($id, 'stalloc');
$instance = $DB->get_record('stalloc', ['id'=> $cm->instance], '*', MUST_EXIST);
$course_id = $course->id;
$context = context_course::instance($course_id);
require_login($course, false, $cm);

// Initialize the header
$paramsheader = initialize_stalloc_header(PAGE_STUDENT, $id, $course_id, $instance);

// First check if the user has the capability to be on this page! -> Admins
if (has_capability('mod/stalloc:admin', context_course::instance($course_id)))  {
    // Display the page layout.
    $strpage = get_string('pluginname', 'mod_stalloc');
    $PAGE->set_pagelayout('incourse');
    $PAGE->set_context(context_module::instance($cm->id));
    $PAGE->set_heading($strpage);
    $PAGE->set_url(new moodle_url('/mod/stalloc/student_edit.php', ['id' => $id, 'student_id' => $student_id, 'alloc_id' => $alloc_id]));
    $PAGE->set_title($course->shortname.': '.$strpage);

    // Output the header.
    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('stalloc/header', $paramsheader);

    echo '<h2 class="mt-3 mb-3 ml-3">EDIT STUDENT</h2>';

    // Displaying the footer.
    echo $OUTPUT->footer();

    // Check if the user has the capability to be on this page! -> Teacher!
} else if(has_capability('mod/stalloc:examinationmember', context_course::instance($course_id))){

    // Display the page layout.
    $strpage = get_string('pluginname', 'mod_stalloc');
    $PAGE->set_pagelayout('incourse');
    $PAGE->set_context(context_module::instance($cm->id));
    $PAGE->set_heading($strpage);
    $PAGE->set_url(new moodle_url('/mod/stalloc/student_edit.php', ['id' => $id, 'student_id' => $student_id, 'alloc_id' => $alloc_id]));
    $PAGE->set_title($course->shortname.': '.$strpage);

    // Output the header.
    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('stalloc/header', $paramsheader);

    // Paramater Array.
    $params_student = [];

    // Check for Post Events
    if (isset($_POST['save_student'])) {
        $post_data_okey = true;

        // Check the thesis name.
        if(isset($_POST['thesis_name'])) {
            $thesis_name = trim($_POST['thesis_name']);

            if($thesis_name == "") {
                $post_data_okey = false;
                $params_student['error_thesis_empty'] = true;
            }
        } else {
            $post_data_okey = false;
            $params_student['error_thesis_empty'] = true;
        }

        // Check the Date.
        if(isset($_POST['start_date'])) {
            if(strtotime ($_POST['start_date']) > strtotime(date("Y-m-d"))) {
                $start_date = strtotime ($_POST['start_date']);
            } else {
                $post_data_okey = false;
                $params_student['error_date_in_past'] = true;
            }
        } else {
            $post_data_okey = false;
            $params_student['error_date_empty'] = true;
        }

        // Check the Examiners.
        if($_POST['examiner_1'] != $_POST['examiner_2']) {
            $examiner_1_id = $_POST['examiner_1'];
            $examiner_2_id = $_POST['examiner_2'];
        } else {
            $post_data_okey = false;
            $params_student['error_examiner_overlap'] = true;
        }

        // Data looks okey -> Update the Database!
        if($post_data_okey) {
            // Update the Database for this allocation!
            $updateobject  = new stdClass();
            $updateobject->id = $alloc_id;
            $updateobject->thesis_name = $thesis_name;
            $updateobject->startdate = $start_date;
            $DB->update_record('stalloc_allocation', $updateobject);

            $DB->delete_records('stalloc_allocation_examiner', ['course_id' => $course_id, 'cm_id' => $id, 'allocation_id' => $alloc_id]);
            $DB->insert_record('stalloc_allocation_examiner', ['course_id' => $course_id, 'cm_id' => $id, 'allocation_id' => $alloc_id, 'chair_member_id' => $examiner_1_id]);
            if($examiner_2_id != -1) {
                $DB->insert_record('stalloc_allocation_examiner', ['course_id' => $course_id, 'cm_id' => $id, 'allocation_id' => $alloc_id, 'chair_member_id' => $examiner_2_id]);
            }

            $params_student['saved_student'] = true;
        }
    }

    $allocation_data = $DB->get_record('stalloc_allocation', ['id' => $alloc_id]);
    $params_student['thesis_name'] = $allocation_data->thesis_name;
    $params_student['start_date'] = date("Y-m-d", $allocation_data->startdate);
    $params_student['back_link'] = new moodle_url('/mod/stalloc/student.php', ['id' => $id]);

    $chair_member_data = $DB->get_records('stalloc_chair_member', ['chair_id' => $allocation_data->chair_id]);

    $index = 0;
    $examiner_count = 0;
    foreach ($chair_member_data as $chair_member) {
        $moodle_user_data = $DB->get_record('user', ['id' => $chair_member->moodle_user_id]);
        $params_student['examiners'][$index] = new stdClass();
        $params_student['examiners'][$index]->examiner_lastname = $moodle_user_data->lastname;
        $params_student['examiners'][$index]->examiner_firstname = $moodle_user_data->firstname;
        $params_student['examiners'][$index]->examiner_id = $chair_member->id;

        $examiner_data = $DB->get_record('stalloc_allocation_examiner', ['course_id' => $course_id, 'cm_id' => $id, 'allocation_id' => $alloc_id, 'chair_member_id' => $chair_member->id]);
        if($examiner_data != null) {
            if($examiner_count == 0) {
                $params_student['examiners'][$index]->examiner_selected_1 = 'selected';
                $examiner_count++;
            } else {
                $params_student['examiners'][$index]->examiner_selected_2 = 'selected';
                $examiner_count++;
            }
        }
        $index++;
    }

    if($examiner_count <= 1) {
        $params_student['examiner_none'] = 'selected';
    }

    // Output the Chair Template
    echo $OUTPUT->render_from_template('stalloc/student_edit', $params_student);

    // Displaying the footer.
    echo $OUTPUT->footer();

} else {
    redirect(course_get_url($course->id), "Missing Capability!", 4, 'NOTIFY_ERROR');
}

