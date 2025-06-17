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
 * Prints an instance of mod_stalloc student delete page
 *
 * @package     mod_stalloc
 * @copyright   2025 Marc-André Schmidt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/config.php');
require_once(__DIR__.'/locallib.php');
require_once(__DIR__ . '/form/form_student_delete.php');

$id = required_param('id', PARAM_INT);
$student_id = required_param('student_id', PARAM_INT);

list ($course, $cm) = get_course_and_cm_from_cmid($id, 'stalloc');
$instance = $DB->get_record('stalloc', ['id'=> $cm->instance], '*', MUST_EXIST);
$course_id = $course->id;
$context = context_course::instance($course_id);
require_login($course, false, $cm);

// Initialize the header
$paramsheader = initialize_stalloc_header(PAGE_STUDENT, $id, $course_id, $instance);

// First check if the user has the capability to be on this page! -> Admins/Manger.
if (has_capability('mod/stalloc:examination_member', context_module::instance($instance->id)))  {
    // Display the page layout.
    $strpage = get_string('pluginname', 'mod_stalloc');
    $PAGE->set_pagelayout('incourse');
    $PAGE->set_context(context_module::instance($cm->id));
    $PAGE->set_heading($strpage);
    $PAGE->set_url(new moodle_url('/mod/stalloc/student_delete.php', ['id' => $id, 'student_id' => $student_id]));
    $PAGE->set_title($course->shortname.': '.$strpage);

    // Output the header.
    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('stalloc/header', $paramsheader);

    // Get Database information of the student which is about to be deleted.
    $student_data = $DB->get_record('stalloc_student', ['id' => $student_id]);
    $user_data = $DB->get_record('user', ['id' => $student_data->moodle_user_id]);

    // Crate the form element.
    $form_student_delete = new mod_stalloc_form_student_delete(null, ['id' => $id, 'student_id' => $student_id]);

    // Different actions, depending on the user action.
    if ($form_student_delete->is_cancelled()) {
        redirect(new moodle_url('/mod/stalloc/student.php', ['id' => $id]), "Student Deletion Canceled!", 4, 'NOTIFY_INFO');
    } else if ($data = $form_student_delete->get_data()) {

        // Delete the student.
        $DB->delete_records('stalloc_student', ['id' => $student_id]);
        // Delete all allocations and associated connections to this allocation of this student.
        $allocation_data = $DB->get_record('stalloc_allocation', ['user_id' => $student_id]);
        if($allocation_data != null) {
            $DB->delete_records('stalloc_allocation', ['user_id' => $student_id]);
        }
        // Delete all ratings of this student.
        $DB->delete_records('stalloc_rating', ['user_id' => $student_id]);

        // All done! Redirect to the chair page.
        $redirecturl = new moodle_url('/mod/stalloc/student.php', ['id' => $id]);
        redirect($redirecturl, "Student Successfully Deleted", 2, \core\output\notification::NOTIFY_SUCCESS);
    }
    else {
        $params_student = [];
        $params_student['student'] = new stdClass();
        $params_student['student']->student_lastname = $user_data->lastname;
        $params_student['student']->student_firstname = $user_data->firstname;
        $params_student['student']->student_number = $user_data->idnumber;
        $params_student['student']->student_mail = $user_data->email;

        if($student_data->phone1 != "") {
            $params_student['student']->student_phone = $student_data->phone1;
        }
        if($student_data->phone2 != "") {
            $params_student['student']->student_mobile = $student_data->phone2;
        }

        // Output the Chair Template
        echo $OUTPUT->render_from_template('stalloc/student_delete', $params_student);

        // Display the Form.
        $form_student_delete->display();
    }

    // Displaying the footer.
    echo $OUTPUT->footer();
} else {
    redirect(course_get_url($course->id), "Missing Capability!", 4, 'NOTIFY_ERROR');
}

