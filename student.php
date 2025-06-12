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
 * Prints an instance of mod_stalloc student page
 *
 * @package     mod_stalloc
 * @copyright   2025 Marc-André Schmidt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/config.php');
require_once(__DIR__.'/locallib.php');
require_once($CFG->libdir . '/csvlib.class.php');

$id = required_param('id', PARAM_INT);

list ($course, $cm) = get_course_and_cm_from_cmid($id, 'stalloc');
$instance = $DB->get_record('stalloc', ['id'=> $cm->instance], '*', MUST_EXIST);
$course_id = $course->id;
$context = context_course::instance($course_id);
require_login($course, false, $cm);

// Initialize the header
$paramsheader = initialize_stalloc_header(PAGE_STUDENT, $id, $course_id, $instance);

// First check if the user has the capability to be on this page! -> Admins/Teachers.
if (has_capability('mod/stalloc:admin', context_course::instance($course_id)) || has_capability('mod/stalloc:examinationmember', context_course::instance($course_id)))  {
    // Display the page layout.
    $strpage = get_string('pluginname', 'mod_stalloc');
    $PAGE->set_pagelayout('incourse');
    $PAGE->set_context(context_module::instance($cm->id));
    $PAGE->set_heading($strpage);
    $PAGE->set_url(new moodle_url('/mod/stalloc/student.php', ['id' => $id]));
    $PAGE->set_title($course->shortname.': '.$strpage);

    // Paramater Array.
    $params_student = [];

    // Admin View.
    if(has_capability('mod/stalloc:admin', context_course::instance($course_id))) {
        // Load students from the database which are connected to this course module.
        $student_data = $DB->get_records('stalloc_student', ['course_id' => $course_id, 'cm_id' => $id]);

        // How many ratings must a student make?
        $rating_number = $DB->get_record('stalloc', ['id' => $instance->id])->rating_number;

        $export_data = [];

        // Prepare the loaded Student data for the template and save this information in a parameter array.
        $index = 0;
        foreach($student_data as $key=>$student) {
            $user_data = $DB->get_record('user', ['id' => $student->moodle_user_id]);
            $params_student['student'][$index] = new stdClass();
            $export_data[$index] = new stdClass();
            $params_student['student'][$index]->index = $index+1;
            $export_data[$index]->index = $index+1;
            $params_student['student'][$index]->student_lastname = $user_data->lastname;
            $params_student['student'][$index]->student_firstname = $user_data->firstname;
            $export_data[$index]->student_name = $user_data->firstname ." ". $user_data->lastname;
            $params_student['student'][$index]->student_number = $user_data->idnumber;
            $export_data[$index]->student_number = $user_data->idnumber;
            $params_student['student'][$index]->student_mail = $user_data->email;
            $export_data[$index]->student_mail = $user_data->email;

            if($student->phone1 != "") {
                $params_student['student'][$index]->student_phone = $student->phone1;
            }
            if($student->phone2 != "") {
                $params_student['student'][$index]->student_mobile = $student->phone2;
            }
            if($student->declaration == 1) {
                $params_student['student'][$index]->student_declaration_true = true;
            } else {
                $params_student['student'][$index]->student_declaration_false = true;
            }
            if($student->permission == 1) {
                $params_student['student'][$index]->student_permission_true = true;
            } else {
                $params_student['student'][$index]->student_permission_false = true;
            }

            // Check for the Student Allocation.
            $allocation_data = $DB->get_record('stalloc_allocation', ['course_id' => $course_id, 'cm_id' => $id, 'user_id' => $student->id]);
            $params_student['student'][$index]->student_allocation = 'Pending...';

            if($allocation_data) {
                if($allocation_data->chair_id != -1) {
                    // There is an Allocation. Get the Name of the Chair.
                    $chair_data = $DB->get_record('stalloc_chair', ['id' => $allocation_data->chair_id]);
                    $params_student['student'][$index]->student_allocation = $chair_data->name;
                    $export_data[$index]->student_allocation = $chair_data->name;

                    if($allocation_data->direct_allocation == 1) {
                        $params_student['student'][$index]->direct_allocation = true;
                    } else {
                        $rating_data = $DB->get_record('stalloc_rating', ['course_id' => $course_id, 'cm_id' => $id, 'user_id' => $student->id, 'chair_id' => $allocation_data->chair_id]);
                        if($rating_data != null) {
                            $params_student['student'][$index]->student_rating = '(' . $rating_number - ($rating_data->rating -1) . ')';
                        }

                        if($student->rating == 1) {
                            $params_student['student'][$index]->student_has_rated = true;
                        }
                    }

                    if($allocation_data->checked == 1) {
                        $params_student['student'][$index]->student_thesis = $allocation_data->thesis_name;
                        $export_data[$index]->student_thesis = $allocation_data->thesis_name;
                        if($allocation_data->startdate != "") {
                            $params_student['student'][$index]->student_start_date =  date('d.m.Y',$allocation_data->startdate);
                            $export_data[$index]->student_start_date =  date('d.m.Y',$allocation_data->startdate);
                        } else {
                            $export_data[$index]->student_start_date =  "";
                        }

                        $export_data[$index]->examiners = "";

                        $examiner_index = 0;
                        $examiner_data = $DB->get_records('stalloc_allocation_examiner', ['course_id' => $course_id, 'cm_id' => $id, 'allocation_id' => $allocation_data->id]);
                        foreach ($examiner_data as $examiner) {
                            $examiner_user = $DB->get_record('stalloc_chair_member', ['id' => $examiner->chair_member_id]);
                            $moodle_user = $DB->get_record('user', ['id' => $examiner_user->moodle_user_id]);
                            $params_student['student'][$index]->examiners[$examiner_index] = new stdClass();
                            $params_student['student'][$index]->examiners[$examiner_index]->examiner_lastname = $moodle_user->lastname;
                            $params_student['student'][$index]->examiners[$examiner_index]->examiner_firstname = $moodle_user->firstname;
                            $export_data[$index]->examiners .= $moodle_user->firstname ." ". $moodle_user->lastname . ", ";
                            $examiner_index++;
                        }
                    } else {
                        $params_student['student'][$index]->not_checked_allocation = true;
                        $export_data[$index]->student_thesis = "";
                        $export_data[$index]->student_start_date = "";
                        $export_data[$index]->examiners = "";
                    }

                } else {
                    if($allocation_data->checked == -1) {
                        $params_student['student'][$index]->student_allocation = 'Pending...';
                        $export_data[$index]->student_allocation = 'Pending...';
                        $export_data[$index]->student_thesis = "";
                        $export_data[$index]->student_start_date = "";
                        $export_data[$index]->examiners = "";
                    } else {
                        $params_student['student'][$index]->student_allocation = "-";
                        $export_data[$index]->student_allocation = '-';
                        $export_data[$index]->student_thesis = "";
                        $export_data[$index]->student_start_date = "";
                        $export_data[$index]->examiners = "";

                        if($student->rating == 1) {
                            $params_student['student'][$index]->student_has_rated = true;
                        }
                    }
                }
            }

            $params_student['student'][$index]->delete_student_url = new moodle_url('/mod/stalloc/student_delete.php', ['student_id' => $student->id, 'id' => $id]);

            $index++;
        }

        // Check for CSV Download Button press.
        if(isset($_POST['download_csv'])) {
            export_students($export_data, $cm);
        }

        // Output the header.
        echo $OUTPUT->header();
        echo $OUTPUT->render_from_template('stalloc/header', $paramsheader);

        // Output the Chair Template
        echo $OUTPUT->render_from_template('stalloc/student', $params_student);

        // Teacher view!
    } else if (has_capability('mod/stalloc:examinationmember', context_course::instance($course_id))) {
        // Get Data of the current Chair Member.
        $chairmember_data = $DB->get_record('stalloc_chair_member', ['course_id' => $course_id, 'cm_id' => $id, 'moodle_user_id' => $USER->id]);
        // Load all pending students of this chair.
        $pending_students = $DB->get_records('stalloc_allocation', ['course_id' => $course_id, 'cm_id' => $id, 'chair_id' => $chairmember_data->chair_id, 'checked' => 0]);

        //Check for POST Events -> Was a student accepted or declined?
        foreach ($pending_students as $pending_student) {
            if(isset($_POST['accept_' . $pending_student->user_id])) {
                // Update the Database for this allocation! -> Student was accepted by the chair.
                $updateobject  = new stdClass();
                $updateobject->id = $pending_student->id;
                $updateobject->checked = 1;
                $DB->update_record('stalloc_allocation', $updateobject);
            } else if (isset($_POST['decline_' . $pending_student->user_id])) {
                // Update the Database for this allocation! -> Student was declined by the chair.
                $updateobject  = new stdClass();
                $updateobject->id = $pending_student->id;
                $updateobject->checked = -1;
                $updateobject->direct_allocation = 0;
                $updateobject->chair_id = -1;
                $DB->update_record('stalloc_allocation', $updateobject);
            }
        }

        // Load all pending and allocated students of this chair again!
        $pending_students = $DB->get_records('stalloc_allocation', ['course_id' => $course_id, 'cm_id' => $id, 'chair_id' => $chairmember_data->chair_id, 'checked' => 0]);
        $allocated_students = $DB->get_records('stalloc_allocation', ['course_id' => $course_id, 'cm_id' => $id, 'chair_id' => $chairmember_data->chair_id, 'checked' => 1]);
        $direct_allocated_students_number = $DB->count_records('stalloc_allocation', ['course_id' => $course_id, 'cm_id' => $id, 'chair_id' => $chairmember_data->chair_id, 'checked' => 1, 'direct_allocation' => 1]);

        // Load all chairs from the DB.
        $chair_data = $DB->get_records('stalloc_chair', ['course_id' => $course_id, 'cm_id' => $id], "name ASC");
        $this_chair_data = $DB->get_record('stalloc_chair', ['id' => $chairmember_data->chair_id]);

        // Calculate the sum of all chair distribution keys.
        $distrubition_key_total_sum = 0;
        foreach ($chair_data as $chair) {
            if($chair->active == 1) {
                $distrubition_key_total_sum += $chair->distribution_key;
            }
        }

        $declaration_data = $DB->get_record('stalloc_declaration_text', ['course_id' => $course_id, 'cm_id' => $id]);
        $declaration = 0;
        if($declaration_data->active == 1) {
            $declaration = 1;
        }

        // Calculate the number or registered students of this plugin.
        $student_number = $DB->count_records('stalloc_student', ['course_id' => $course_id, 'cm_id' => $id, 'declaration' => $declaration]);
        $student_number = ceil(($student_number + $student_number*STUDENT_BUFFER));
        $max_students = ceil(($student_number * $this_chair_data->distribution_key) / $distrubition_key_total_sum);
        $max_allocation_students = floor($max_students * MAX_STUDENT_ALLOCATIONS_PERCENT);
        $max_direct_students = $max_students - $max_allocation_students;
        $params_student['direct_allocated_students'] = $direct_allocated_students_number . '/' . $max_direct_students;

        // Prepare the pending student data for the template.
        $index = 0;
        foreach($pending_students as $key=>$pending_student) {
            $student_data = $DB->get_record('stalloc_student', ['id' => $pending_student->user_id]);
            $user_data = $DB->get_record('user', ['id' => $student_data->moodle_user_id]);

            $params_student['pending_student'][$index] = new stdClass();
            $params_student['pending_student'][$index]->index = $index+1;
            $params_student['pending_student'][$index]->student_lastname = $user_data->lastname;
            $params_student['pending_student'][$index]->student_firstname = $user_data->firstname;
            $params_student['pending_student'][$index]->student_number = $user_data->idnumber;
            $params_student['pending_student'][$index]->student_mail = $user_data->email;
            $params_student['pending_student'][$index]->student_id = $student_data->id;

            // check if this chair can accept more direct students.
            if($direct_allocated_students_number >= $max_direct_students) {
                $params_student['pending_student'][$index]->disable_pending = 'disabled';
            }

            $index++;
        }

        if($index != 0) {
            $params_student['pending'] = true;
        } else {
            $params_student['no_pending'] = true;
        }

        // Prepare the allocated student data for the template.
        $index = 0;
        foreach($allocated_students as $key=>$allocated_student) {
            $student_data = $DB->get_record('stalloc_student', ['id' => $allocated_student->user_id]);
            $user_data = $DB->get_record('user', ['id' => $student_data->moodle_user_id]);

            $params_student['allocated_student'][$index] = new stdClass();
            $params_student['allocated_student'][$index]->index = $index+1;
            $params_student['allocated_student'][$index]->student_lastname = $user_data->lastname;
            $params_student['allocated_student'][$index]->student_firstname = $user_data->firstname;
            $params_student['allocated_student'][$index]->student_number = $user_data->idnumber;
            $params_student['allocated_student'][$index]->student_mail = $user_data->email;
            $params_student['allocated_student'][$index]->student_thesis = $allocated_student->thesis_name;
            if($allocated_student->startdate != "") {
                $params_student['allocated_student'][$index]->student_start_date =  date('d.m.Y',$allocated_student->startdate);
            }
            $params_student['allocated_student'][$index]->edit_student_url = new moodle_url('/mod/stalloc/student_edit.php', ['student_id' => $allocated_student->user_id, 'alloc_id' => $allocated_student->id, 'id' => $id]);

            $examiner_index = 0;
            $examiner_data = $DB->get_records('stalloc_allocation_examiner', ['course_id' => $course_id, 'cm_id' => $id, 'allocation_id' => $allocated_student->id]);
            foreach ($examiner_data as $examiner) {
                $examiner_user = $DB->get_record('stalloc_chair_member', ['id' => $examiner->chair_member_id]);
                $moodle_user = $DB->get_record('user', ['id' => $examiner_user->moodle_user_id]);
                $params_student['allocated_student'][$index]->examiners[$examiner_index] = new stdClass();
                $params_student['allocated_student'][$index]->examiners[$examiner_index]->examiner_lastname = $moodle_user->lastname;
                $params_student['allocated_student'][$index]->examiners[$examiner_index]->examiner_firstname = $moodle_user->firstname;
                $examiner_index++;
            }
            $index++;
        }

        if($index != 0) {
            $params_student['allocated'] = true;
        } else {
            $params_student['no_allocated'] = true;
        }

        // Output the header.
        echo $OUTPUT->header();
        echo $OUTPUT->render_from_template('stalloc/header', $paramsheader);

        // Output the Chair Template
        echo $OUTPUT->render_from_template('stalloc/student_chairview', $params_student);
    }

    // Displaying the footer.
    echo $OUTPUT->footer();
} else {
    redirect(course_get_url($course->id), "Missing Capability!", 4, 'NOTIFY_ERROR');
}


function export_students($export_data, $cm) {

    $tmpcsvfile = new csv_export_writer ('semicolon', '', 'application/download', true);
    // Create filename.
    $filename = clean_filename ($cm->name .'-Student-Export');
    $tmpcsvfile->set_filename ($filename);

    // Export Rows titles.
    $header[] = '#';
    $header[] = 'Student-Name';
    $header[] = 'ID-Number';
    $header[] = 'E-Mail';
    $header[] = 'Chair';
    $header[] = 'Thesis-Name';
    $header[] = 'Thesis-Start';
    $header[] = 'Thesis-Examiner';

    // Save the header to the csv file.
    $tmpcsvfile->add_data($header);

    // Add the actual data to the export.
    foreach ($export_data as $data) {
        $tmpcsvfile->add_data((array) $data);
    }

    // Download the export file.
    $tmpcsvfile->download_file();
    exit;
}

