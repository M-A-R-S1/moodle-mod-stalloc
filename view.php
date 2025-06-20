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
 * Prints an instance of mod_stalloc home page
 *
 * @package     mod_stalloc
 * @copyright   2025 Marc-André Schmidt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');
require_once(__DIR__.'/locallib.php');

$id = required_param('id', PARAM_INT);
list ($course, $cm) = get_course_and_cm_from_cmid($id, 'stalloc');
$instance = $DB->get_record('stalloc', ['id'=> $cm->instance], '*', MUST_EXIST);
$course_id = $course->id;
require_login($course, false, $cm);


// Display the page layout.
$strpage = get_string('pluginname', 'mod_stalloc');
$PAGE->set_pagelayout('incourse');
$PAGE->set_context(context_module::instance($cm->id));
$PAGE->set_heading($strpage);
$PAGE->set_url(new moodle_url('/mod/stalloc/view.php', array('id' => $id)));
$PAGE->set_title($course->shortname.': '.$strpage);
echo $OUTPUT->header();

$viewparams['student_name'] = $USER->firstname. " " . $USER->lastname;
$phoneNumber = false;
$showDeclaration = false;
$showDirectAllocation = false;
$showChairSelection = false;
$showRatingSelection = false;

// Is the User a student?
if(has_capability('mod/stalloc:student', context_module::instance($instance->id)) && !is_siteadmin()) {
    // Get Student data.
    $student_data = $DB->get_record('stalloc_student', ['course_id' => $course_id, 'cm_id' => $id, 'moodle_user_id' => $USER->id]);
    // Get basic module date from database.
    $stalloc_data = $DB->get_record('stalloc', ['id' => $instance->id]);

    // New Student? -> Create a new Database entry.
    if($student_data == null) {
        $student_id = $DB->insert_record('stalloc_student', ['course_id' => $course_id, 'cm_id' => $id, 'moodle_user_id' => $USER->id, 'phone1' => $USER->phone1, 'phone2' => $USER->phone2]);
        $student_data = $DB->get_record('stalloc_student', ['id' => $student_id]);
    }

    // Check for Post Events.
    $viewparams = checkFormActions($course_id, $id, $student_data->id, $instance->id, $DB, $viewparams);

    // Refresh student data.
    $student_data = $DB->get_record('stalloc_student', ['course_id' => $course_id, 'cm_id' => $id, 'moodle_user_id' => $USER->id]);

    // Check User Phone Data and update.
    $updateobject  = new stdClass();
    $updateobject->id = $student_data->id;
    $update_phone = false;

    $phone1 = preg_replace("/[^0-9]/", '', $USER->phone1);
    if(strlen($phone1) >= 4) {
        if($phone1 != $student_data->phone1) {
            // New Phone Data -> Update the current user.
            $updateobject->phone1 = $phone1;
            $update_phone = true;
        }
    }
    $phone2 = preg_replace("/[^0-9]/", '', $USER->phone2);
    if(strlen($phone2) >= 4) {
        if($phone2 != $student_data->phone2) {
            // New Phone Data -> Update the current user.
            $updateobject->phone2 = $phone2;
            $update_phone = true;
        }
    }

    // Update the phone database entry.
    if($update_phone) {
        $DB->update_record('stalloc_student', $updateobject);
        // Refresh student data.
        $student_data = $DB->get_record('stalloc_student', ['course_id' => $course_id, 'cm_id' => $id, 'moodle_user_id' => $USER->id]);
    }

    // Check if the phone number is required.
    if($stalloc_data->phone_required == 1) {
        if($student_data->phone1 == "" && $student_data->phone2 == "") {
            // There is currently no phone number saved in the plugins user data.
            $viewparams['no_phone_number'] = true;
            $viewparams['profile_link'] = new moodle_url('/user/edit.php', ['id' => $USER->id]);
        } else {
            $phoneNumber = true;
        }
    }

    // NO Phone number required OR the phone number is saved by the user
    if($stalloc_data->phone_required == 0 || $phoneNumber) {
        // Check if student declaration is needed.
        $declaration_data = $DB->get_record('stalloc_declaration_text', ['course_id' => $course_id, 'cm_id' => $id]);
        $start_phase1 = $stalloc_data->start_phase1;
        $end_phase1 = $stalloc_data->end_phase1;
        $today = strtotime(date("Y-m-d"));
        $declaration = false;
        $directAllocation = false;
        $ratings = false;

        // Is Phase 1 active? -> Delcaration and Rating Phase
        if( ($start_phase1 <= $today) && ($end_phase1 >= $today) ) {
            // Is Declaration needed?
            if($declaration_data->active == 1) {
                // Check if the Student has accepted the declaration if not show the declaration to the student.
                if($student_data->declaration == 0) {
                    $showDeclaration = true;
                    $viewparams['delcaration_text'] = $declaration_data->text;
                } else {
                    $declaration = true;
                }
            } else {
                // Declaration is not needed.
                $declaration = true;
            }

            // Proceed to the Direct Chair Allocation question.
            if($declaration) {
                $allocation_data = $DB->get_record('stalloc_allocation', ['course_id' => $course_id, 'cm_id' => $id, 'user_id' => $student_data->id]);
                $allocationDone = false;

                // There is no allocation yet -> Show the Chair Selection to the student.
                if($allocation_data == null) {
                    // Get All Chairs of this Course Module.
                    $chair_data = $DB->get_records('stalloc_chair', ['course_id' => $course_id, 'cm_id' => $id]);
                    // Set the 'NO' Selection.
                    $viewparams['chair'][0] = new stdClass();
                    $viewparams['chair'][0]->id = -1;
                    $viewparams['chair'][0]->chair_name = 'NO';

                    // Go through each chair and save the data to the template array.
                    $index = 1;
                    foreach($chair_data as $key=>$chair) {
                        $viewparams['chair'][$index] = new stdClass();
                        $viewparams['chair'][$index]->id = $chair->id;
                        $viewparams['chair'][$index]->chair_name = $chair->name;
                        $index++;
                    }

                    $showDirectAllocation = true;
                } else {
                    // The Student has already selected a direct chair.
                    $directAllocation = true;
                }
            }

            // Proceed to the Chair Rating question.
            if($directAllocation) {
                // Get Rating Data for this student.
                $rating_data = $DB->get_records('stalloc_rating', ['user_id' => $student_data->id], "rating DESC");
                $index = 0;

                // There is already a student rating! Load and display it.
                if($rating_data != null) {
                    $chair_data = $DB->get_records('stalloc_chair', ['course_id' => $course_id, 'cm_id' => $id, 'active' => 1], "name ASC");
                    $ratings = true;

                    $viewparams['ratings_present'] = true;
                    $viewparams['phase1_end'] = date('d.m.Y',$stalloc_data->end_phase1);

                    while($index < $stalloc_data->rating_number) {
                        $viewparams['rating'][$index] = new stdClass();
                        $viewparams['rating'][$index]->index = ($index+1);
                        $option_index = 0;
                        foreach ($chair_data as $chair) {
                            $viewparams['rating'][$index]->option[$option_index] = new stdClass();
                            $viewparams['rating'][$index]->option[$option_index]->chair_id = $chair->id;
                            $viewparams['rating'][$index]->option[$option_index]->chair_name = $chair->name;

                            foreach ($rating_data as $rating) {
                                if($rating->rating == ($stalloc_data->rating_number-$index)) {
                                    if($rating->chair_id == $chair->id) {
                                        $viewparams['rating'][$index]->option[$option_index]->selected = 'selected';
                                    }
                                }
                            }
                            $option_index++;
                        }
                        $index++;
                    }
                } else {
                    // Student has not provided a rating yet.
                    $chair_data = $DB->get_records('stalloc_chair', ['course_id' => $course_id, 'cm_id' => $id, 'active' => 1], "name ASC");
                    while($index < $stalloc_data->rating_number) {
                        $viewparams['rating'][$index] = new stdClass();
                        $viewparams['rating'][$index]->index = ($index+1);
                        $viewparams['rating'][$index]->option[0] = new stdClass();
                        $viewparams['rating'][$index]->option[0]->chair_id = -1;
                        $viewparams['rating'][$index]->option[0]->chair_name = "Choose...";
                        $viewparams['rating'][$index]->option[0]->selected = true;

                        $option_index = 1;
                        foreach ($chair_data as $chair) {
                            $viewparams['rating'][$index]->option[$option_index] = new stdClass();
                            $viewparams['rating'][$index]->option[$option_index]->chair_id = $chair->id;
                            $viewparams['rating'][$index]->option[$option_index]->chair_name = $chair->name;
                            $option_index++;
                        }
                        $index++;
                    }
                }
                $showRatingSelection = true;
            }
        } else {
            // Is Phase 1 already over?
            if($today > $end_phase1) {
                $allocation_data = $DB->get_record('stalloc_allocation', ['course_id' => $course_id, 'cm_id' => $id, 'user_id' => $student_data->id]);
                $rating_data = $DB->get_records('stalloc_rating', ['user_id' => $student_data->id], "rating DESC");

                // Check if the student should be here -> Is there a proper allocation?
                if($allocation_data != null && $rating_data != null) {
                    // Get Phase 4 Schedule data.
                    $start_phase4 = $stalloc_data->start_phase4;
                    $end_phase4 = $stalloc_data->end_phase4;
                    $chair_data = $DB->get_record('stalloc_chair', ['id' => $allocation_data->chair_id]);

                    // Phase 4 is active -> Thesis Definition Phase.
                    if (($start_phase4 <= $today) && ($end_phase4 >= $today)) {
                        $viewparams['allocated_chair_name'] = $chair_data->name;
                    } else {
                        // Phase 4 has not started yet. Display waiting text to the student.
                        if($today < $start_phase4) {
                            $viewparams['waiting_for_phase4'] = true;
                            $viewparams['phase4_start'] = date('d.m.Y',$stalloc_data->start_phase4);
                            $viewparams['phase4_end'] = date('d.m.Y',$stalloc_data->end_phase4);
                        } else {
                            // Phase 4 is over.
                            // Get the thesis start date.
                            $thesis_start = $allocation_data->startdate;

                            if($thesis_start != null) {

                                // Get the Thesis Date from the database.
                                $viewparams['chair_name'] = $chair_data->name;
                                $viewparams['thesis_name'] = $allocation_data->thesis_name;
                                $viewparams['start_date'] = date('d.m.Y',$allocation_data->startdate);
                                $viewparams['examiner_two'] = $allocation_data->examiner_two;

                                // The Thesis time has started -> Show Thesis information to the student.
                                if($today >= $thesis_start) {
                                    $viewparams['thesis_started'] = true;
                                } else {
                                    if($stalloc_data->publish_results == 1) {
                                        // Thesis has not started but the management has published the results.
                                        $viewparams['thesis_dates_published'] = true;
                                    } else {
                                        // Thesis has not started AND the management has NOT published the results.
                                        $viewparams['thesis_not_published'] = true;
                                    }
                                }
                            } else {
                                $viewparams['thesis_no_start_date'] = true;
                            }
                        }
                    }
                } else {
                    $viewparams['no_allocation_after_phase1'] = true;
                }
            } else {
                // Phase 1 has not started yet. Display a Waiting text for the student.
                $viewparams['waiting_for_phase1'] = true;
                $viewparams['phase1_start'] = date('d.m.Y',$stalloc_data->start_phase1);
                $viewparams['phase1_end'] = date('d.m.Y',$stalloc_data->end_phase1);
            }
        }
    }

} else if(has_capability('mod/stalloc:chairmember', context_module::instance($instance->id)) && !is_siteadmin()) {

    // Check for Post Events.
    if(isset($_POST['save_chair_selection'])) {
        $selected_chair_id = $_POST['chair_select'];
        // Create a new Chair Member Record.
        $DB->insert_record('stalloc_chair_member', ['course_id' => $course_id, 'cm_id' => $id, 'chair_id' => $selected_chair_id, 'moodle_user_id' => $USER->id]);
    }

    $chair_data = $DB->get_record('stalloc_chair_member', ['course_id' => $course_id, 'cm_id' => $id, 'moodle_user_id' => $USER->id]);

    // New Chair Member? -> Create a new Chair Member entry.
    if($chair_data == null) {
        // Get All Chairs of this Course Module.
        $chair_data = $DB->get_records('stalloc_chair', ['course_id' => $course_id, 'cm_id' => $id]);
        $index = 0;
        foreach($chair_data as $key=>$chair) {
            $viewparams['chair'][$index] = new stdClass();
            $viewparams['chair'][$index]->id = $chair->id;
            $viewparams['chair'][$index]->chair_name = $chair->name;
            $index++;
        }
        $showChairSelection = true;
    }

    $viewparams['role'] = "CHAIR MEMBER!";
}


if($showDeclaration) {
    echo $OUTPUT->render_from_template('stalloc/declaration', $viewparams);
} else if($showDirectAllocation) {
    echo $OUTPUT->render_from_template('stalloc/direct_allocation', $viewparams);
} else if($showChairSelection) {
    echo $OUTPUT->render_from_template('stalloc/chair_selection', $viewparams);
} else if ($showRatingSelection) {
    echo $OUTPUT->render_from_template('stalloc/rating_selection', $viewparams);
} else {
    // Initialize the header -> Only for chair members, examination members and admins!
    if((has_capability('mod/stalloc:chairmember', context_module::instance($instance->id)) || has_capability('mod/stalloc:examination_member', context_module::instance($instance->id)))) {
        $paramsheader = initialize_stalloc_header(PAGE_HOME, $id, $course_id, $instance);
        echo $OUTPUT->render_from_template('stalloc/header', $paramsheader);
    }

    // Display the Page.
    echo $OUTPUT->render_from_template('stalloc/view', $viewparams);
}

// Displaying the footer.
echo $OUTPUT->footer();


/**
 * Checks if the user used a Form action.
 *
 * @param int $course_id The current Course ID.
 * @param int $id The current Course Module ID.
 * @param int $student_id The Student ID.
 * @param int $instance_id current instance id.
 * @param moodle_database $DB The database Element.
 * @param array $viewparams The Array with the template Data.
 * @return array
 * @throws dml_exception
 * @throws moodle_exception
 */
function checkFormActions(int $course_id, int $id, int $student_id, int $instance_id, moodle_database $DB, array $viewparams): array {
    if(isset($_POST['accept_declaration'])) {
        $declarationCheckbox = isset($_POST['checkbox_declaration']);
        // Check if the Declaration was accepted.
        if($declarationCheckbox) {

            // Update the student entry
            $updateobject  = new stdClass();
            $updateobject->id = $student_id;
            $updateobject->declaration = 1;

            // Update the database entry.
            $DB->update_record('stalloc_student', $updateobject);
            $viewparams['accepted_declaration'] = true;

        } else {
            // Throw an error! The Student has to accept the Declaration!
            $viewparams['error_declaration_not_accepted'] = true;
        }
    }

    if(isset($_POST['save_direct_allocation'])) {
        $selected_chair_id = $_POST['chair_select'];

        // Get Allocation Data for this student.
        $allocation_data = $DB->get_record('stalloc_allocation', ['course_id' => $course_id, 'cm_id' => $id, 'user_id' => $student_id]);

        if($allocation_data == null) {
            // Create a new Allocation record.
            if($selected_chair_id == -1) {
                $direct_allocation = 0;
            } else {
                $direct_allocation = 1;
            }

            $DB->insert_record('stalloc_allocation', ['course_id' => $course_id, 'cm_id' => $id, 'user_id' => $student_id, 'chair_id' => $selected_chair_id, 'direct_allocation' => $direct_allocation, 'checked' => 0, 'thesis_name' => ""]);
        } else {
            // Update an existing Allocation record.
            $updateobject  = new stdClass();
            $updateobject->id = $allocation_data->id;
            $updateobject->checked = 0;
            $updateobject->chair_id = $selected_chair_id;

            if($selected_chair_id == -1) {
                $direct_allocation = 0;
            } else {
                $direct_allocation = 1;
            }

            $updateobject->direct_allocation = $direct_allocation;
            $DB->update_record('stalloc_allocation', $updateobject);
        }
    }

    if(isset($_POST['save_ratings'])) {
        $stalloc_data = $DB->get_record('stalloc', ['id' => $instance_id]);
        $max_index = $stalloc_data->rating_number;
        $chair_ids = [];
        $error = false;

        // First - Save the Post values (Chair id's in an array).
        for($current_index = 1; $current_index <= $max_index; $current_index++) {
            if($_POST['rating_select_'.$current_index] != -1) {
                $chair_ids[$current_index - 1] = $_POST['rating_select_' . $current_index];
            } else {
                $error = true;
                break;
            }
        }

        // Check, if all priorities are unique.
        $chair_ids_check = array_unique($chair_ids);
        if(count($chair_ids_check) != count($chair_ids)) {
            $error = true;
        }

        // No Errors! Input the priorities/ratings into the database!
        if(!$error) {
            // First - Delete all ratings before inserting the new ones.
            $DB->delete_records('stalloc_rating', ['user_id' => $student_id]);

            for($index = 0; $index < $max_index; $index++) {
                $DB->insert_record('stalloc_rating', ['course_id' => $course_id, 'cm_id' => $id, 'user_id' => $student_id, 'chair_id' => $chair_ids[$index], 'rating' => ($max_index - $index)]);
            }
            $viewparams['save_ratings'] = true;

            // Update the student entry.
            $updateobject  = new stdClass();
            $updateobject->id = $student_id;
            $updateobject->rating = 1;
            // Update the database entry.
            $DB->update_record('stalloc_student', $updateobject);


        } else {
            $viewparams['error_save_ratings'] = true;
        }

    }


    return $viewparams;
}