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
$showChairTemplate = false;
$showRatingSelection = false;

// Is the User a student?
if(has_capability('mod/stalloc:student', context_course::instance($course_id)) && !is_siteadmin()) {
    // Get Student data.
    $student_data = $DB->get_record('stalloc_student', ['course_id' => $course_id, 'cm_id' => $id, 'moodle_user_id' => $USER->id]);
    // Get basic module date from database.
    $stalloc_data = $DB->get_record('stalloc', ['id' => $instance->id]);

    // New Student? -> Create a new Database entry.
    if($student_data == null) {
        $student_id = $DB->insert_record('stalloc_student', ['course_id' => $course_id, 'cm_id' => $id, 'moodle_user_id' => $USER->id, 'phone1' => '', 'phone2' => '']);
        $student_data = $DB->get_record('stalloc_student', ['id' => $student_id]);
    }

    // Check for Post Events.
    $viewparams = checkFormActions($course_id, $id, $student_data->id, $instance->id, $DB, $viewparams);

    // Refresh student data.
    $student_data = $DB->get_record('stalloc_student', ['course_id' => $course_id, 'cm_id' => $id, 'moodle_user_id' => $USER->id]);

    // Check User Phone Data and update.
    $phone_pattern = "/^(\+49|0)\d{6,14}$/";

    $phone1 = str_replace('-', '', $USER->phone1);
    $phone1 = str_replace(" ", "", $phone1);
    $phone1 = str_replace("/", "", $phone1);
    if (!preg_match($phone_pattern, $phone1)) {
        $phone1 = '';
    }
    $phone2 = str_replace('-', '', $USER->phone2);
    $phone2 = str_replace(" ", "", $phone2);
    $phone2 = str_replace("/", "", $phone2);
    if (!preg_match($phone_pattern, $phone2)) {
        $phone2 = '';
    }

    $has_valid_phonenumber = $phone1 || $phone2;

    // Update the phone database entry.
    if($has_valid_phonenumber && ($phone1 != $student_data->phone1 || $phone2 != $student_data->phone2)) {
        $DB->update_record('stalloc_student', [
            'id' => $student_data->id,
            'phone1' => $phone1,
            'phone2' => $phone2
        ]);
        // Refresh student data.
        $student_data = $DB->get_record('stalloc_student', ['course_id' => $course_id, 'cm_id' => $id, 'moodle_user_id' => $USER->id]);
    }

    // Check if the phone number is required.
    if ($stalloc_data->phone_required == 1 && !$has_valid_phonenumber) {
        // There is currently no phone number saved in the plugins user data.
        $viewparams['no_phone_number'] = true;
        $viewparams['profile_link'] = new moodle_url('/user/edit.php', ['id' => $USER->id]);
    } else {
        // Check if student declaration is needed.
        $declaration_data = $DB->get_record('stalloc_declaration_text', []);
        $start_phase1 = $stalloc_data->start_phase1;
        $end_phase1 = $stalloc_data->end_phase1;
        $today = strtotime(date("Y-m-d H:i"));
        $declaration = false;

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

            // Proceed to the Direct Chair Allocation and Rating question.
            if($declaration) {
                $allocation_data = $DB->get_record('stalloc_allocation', ['course_id' => $course_id, 'cm_id' => $id, 'user_id' => $student_data->id]);

                // Get All Chairs of this Course Module.
                $chair_data = $DB->get_records('stalloc_chair', [], "name ASC");
                // Set the 'NO' Selection.
                $viewparams['chair'][0] = new stdClass();
                $viewparams['chair'][0]->id = -1;
                $viewparams['chair'][0]->chair_name = 'Nicht vorhanden';

                // Go through each chair and save the data to the template array.
                $index = 1;
                foreach($chair_data as $key=>$chair) {
                    $viewparams['chair'][$index] = new stdClass();
                    $viewparams['chair'][$index]->id = $chair->id;
                    $viewparams['chair'][$index]->chair_name = $chair->name;

                    if($allocation_data != null) {
                        if($allocation_data->chair_id == $chair->id) {
                            $viewparams['chair'][$index]->active_selection = 'selected';
                        }
                    }
                    $index++;
                }

                // Chair Rating Question Code.
                // Get Rating Data for this student.
                $rating_data = $DB->get_records('stalloc_rating', ['course_id' => $course_id, 'cm_id' => $id, 'user_id' => $student_data->id], "rating DESC");
                $index = 0;

                $viewparams['phase1_end'] = date('d.m.Y',$stalloc_data->end_phase1);

                // There is already a student rating! Load and display it.
                if($rating_data != null) {
                    $chair_data = $DB->get_records('stalloc_chair', ['active' => 1], "name ASC");

                    $viewparams['ratings_present'] = true;

                    while($index < $stalloc_data->rating_number) {
                        $post_chair_id = -1;

                        $viewparams['rating'][$index] = new stdClass();
                        $viewparams['rating'][$index]->index = ($index+1);
                        $option_index = 0;

                        if(isset($_POST['save_ratings'])) {
                            if($_POST['rating_select_'.($index+1)] != -1) {
                                $post_chair_id =  $_POST['rating_select_' . ($index+1)];
                            } else {
                                $viewparams['rating'][$index]->is_invalid = 'is-invalid';
                            }
                        }

                        foreach ($chair_data as $chair) {
                            $viewparams['rating'][$index]->option[$option_index] = new stdClass();
                            $viewparams['rating'][$index]->option[$option_index]->chair_id = $chair->id;
                            $viewparams['rating'][$index]->option[$option_index]->chair_name = $chair->name;


                            if(isset($_POST['save_ratings'])) {
                                if($post_chair_id == $chair->id) {
                                    $viewparams['rating'][$index]->option[$option_index]->selected = 'selected';

                                    // Check if this selection is unique! Walk through all other post events and check if another selection has the same chair id.
                                    for($current_index = 1; $current_index <= $stalloc_data->rating_number; $current_index++) {
                                        // Do not compare the same selection!
                                        if($current_index != ($index+1)) {
                                            if($_POST['rating_select_'.$current_index] == $chair->id) {
                                                $viewparams['rating'][$index]->is_invalid = 'is-invalid';
                                            }
                                        }
                                    }
                                }
                            } else {
                                foreach ($rating_data as $rating) {
                                    if($rating->rating == ($stalloc_data->rating_number-$index)) {
                                        if($rating->chair_id == $chair->id) {
                                            $viewparams['rating'][$index]->option[$option_index]->selected = 'selected';
                                        }
                                    }
                                }
                            }

                            $option_index++;
                        }
                        $index++;
                    }
                } else {
                    // Student has not provided a rating yet.
                    $chair_data = $DB->get_records('stalloc_chair', ['active' => 1], "name ASC");
                    while($index < $stalloc_data->rating_number) {
                        $post_chair_id = -1;

                        $viewparams['rating'][$index] = new stdClass();
                        $viewparams['rating'][$index]->index = ($index+1);
                        $viewparams['rating'][$index]->option[0] = new stdClass();
                        $viewparams['rating'][$index]->option[0]->chair_id = -1;
                        $viewparams['rating'][$index]->option[0]->chair_name = "Auswählen...";

                        if(isset($_POST['save_ratings'])) {
                            if($_POST['rating_select_'.($index+1)] != -1) {
                                $post_chair_id =  $_POST['rating_select_' . ($index+1)];
                            } else {
                                $viewparams['rating'][$index]->is_invalid = 'is-invalid';
                            }
                        }

                        if($post_chair_id == -1) {
                            $viewparams['rating'][$index]->option[0]->selected = 'selected';
                        }

                        $option_index = 1;
                        foreach ($chair_data as $chair) {
                            $viewparams['rating'][$index]->option[$option_index] = new stdClass();
                            $viewparams['rating'][$index]->option[$option_index]->chair_id = $chair->id;
                            $viewparams['rating'][$index]->option[$option_index]->chair_name = $chair->name;
                            if($post_chair_id == $chair->id) {
                                $viewparams['rating'][$index]->option[$option_index]->selected = 'selected';

                                // Check if this selection is unique! Walk through all other post events and check if another selection has the same chair id.
                                for($current_index = 1; $current_index <= $stalloc_data->rating_number; $current_index++) {
                                    // Do not compare the same selection!
                                    if($current_index != ($index+1)) {
                                        if($_POST['rating_select_'.$current_index] == $chair->id) {
                                            $viewparams['rating'][$index]->is_invalid = 'is-invalid';
                                        }
                                    }
                                }
                            }
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
                $rating_data = $DB->get_records('stalloc_rating', ['course_id' => $course_id, 'cm_id' => $id, 'user_id' => $student_data->id], "rating DESC");

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
                            $viewparams['phase4_start'] = date('d.m.Y H:i',$stalloc_data->start_phase4);
                            $viewparams['phase4_end'] = date('d.m.Y H:i',$stalloc_data->end_phase4);
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
                                    $viewparams['thesis_not_published'] = true;
                                }
                            } else {
                                $viewparams['thesis_not_published'] = true;
                            }
                        }
                    }
                } else {
                    $viewparams['no_allocation_after_phase1'] = true;
                }
            } else {
                // Phase 1 has not started yet. Display a Waiting text for the student.
                $viewparams['waiting_for_phase1'] = true;
                $viewparams['phase1_start'] = date('d.m.Y H:i',$stalloc_data->start_phase1);
                $viewparams['phase1_end'] = date('d.m.Y H:i',$stalloc_data->end_phase1);
            }
        }
    }

} else if(has_capability('mod/stalloc:chairmember', context_course::instance($course_id)) && !is_siteadmin()) {
    $showChairTemplate = true;

    // Check for Post Events.
    if(isset($_POST['save_chair_selection'])) {
        $selected_chair_id = $_POST['chair_select'];
        // Create a new Chair Member Record.
        $DB->insert_record('stalloc_chair_member', ['chair_id' => $selected_chair_id, 'moodle_user_id' => $USER->id]);
    }

    $chair_data = $DB->get_record('stalloc_chair_member', ['moodle_user_id' => $USER->id]);

    // New Chair Member? -> Create a new Chair Member entry.
    if($chair_data == null) {
        // Get All Chairs of this Course Module.
        $chair_data = $DB->get_records('stalloc_chair', []);
        $index = 0;
        foreach($chair_data as $key=>$chair) {
            $viewparams['chair'][$index] = new stdClass();
            $viewparams['chair'][$index]->id = $chair->id;
            $viewparams['chair'][$index]->chair_name = $chair->name;
            $index++;
        }
        $viewparams['new_chair'] = true;
    } else {
        $stalloc_data = $DB->get_record('stalloc', ['id' => $instance->id]);
        // Get Data of the current Chair Member.
        $chairmember_data = $DB->get_record('stalloc_chair_member', ['moodle_user_id' => $USER->id]);
        // Load all chairs from the DB.
        $chair_data = $DB->get_records('stalloc_chair', [], "name ASC");
        $this_chair_data = $DB->get_record('stalloc_chair', ['id' => $chairmember_data->chair_id]);

        // Some time data here ...
        $today = strtotime(date("Y-m-d H:i"));
        $start_phase1 = $stalloc_data->start_phase1;
        $end_phase1 = $stalloc_data->end_phase1;

        // Phase 1 active
        if( ($start_phase1 <= $today) && ($end_phase1 >= $today) ) {
            $viewparams['phase1_end'] = date('d.m.Y',$stalloc_data->end_phase1);
            $viewparams['current_numbers'] = true;
        }

        // Phase 1 finished
        if($today >= $stalloc_data->end_phase1) {
            $viewparams['fixed_numbers'] = true;
        }

        // Calculate the sum of all chair distribution keys.
        $distrubition_key_total_sum = 0;
        foreach ($chair_data as $chair) {
            if($chair->active == 1) {
                $distrubition_key_total_sum += $chair->distribution_key;
            }
        }

        // Calculate the number or registered students of this plugin.
        $student_number = $DB->count_records('stalloc_student', ['course_id' => $course_id, 'cm_id' => $id, 'declaration' => 1]);
        $viewparams['students_in_plugin'] = $student_number;
        $student_number = ceil(($student_number + $student_number*STUDENT_BUFFER));
        $max_students = ceil(($student_number * $this_chair_data->distribution_key) / $distrubition_key_total_sum);
        $max_allocation_students = floor($max_students * MAX_STUDENT_ALLOCATIONS_PERCENT);
        $max_direct_students = $max_students - $max_allocation_students;

        $viewparams['max_students'] = $max_students;
        $viewparams['max_draw_students'] = $max_allocation_students;
        $viewparams['max_direct_students'] = $max_direct_students;
        $viewparams['chair_overview_data'] = true;
    }

}


if($showDeclaration) {
    echo $OUTPUT->render_from_template('stalloc/declaration', $viewparams);
} else if ($showRatingSelection) {
    echo $OUTPUT->render_from_template('stalloc/direct_allocation', $viewparams);
} else {
    // Initialize the header -> Only for chair members, examination members and admins!
    if((has_capability('mod/stalloc:chairmember', context_course::instance($course_id)) || has_capability('mod/stalloc:examination_member', context_course::instance($course_id)))) {
        $paramsheader = initialize_stalloc_header(PAGE_HOME, $id, $course_id, $instance);
        echo $OUTPUT->render_from_template('stalloc/header', $paramsheader);
    }

    if($showChairTemplate) {
        // Display the Chair Members template.
        echo $OUTPUT->render_from_template('stalloc/home_chair', $viewparams);
    } else {
        // Display the Examination Members template.
        echo $OUTPUT->render_from_template('stalloc/view', $viewparams);
    }

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
        $declarationCheckbox_1 = isset($_POST['checkbox_declaration_1']);
        $declarationCheckbox_2 = isset($_POST['checkbox_declaration_2']);
        $declarationCheckbox_3 = isset($_POST['checkbox_declaration_3']);
        $declarationCheckbox_4 = isset($_POST['checkbox_declaration_4']);
        $declarationCheckbox_5 = isset($_POST['checkbox_declaration_5']);

        // Check if the Declaration was accepted.
        if($declarationCheckbox_1 && $declarationCheckbox_2 && $declarationCheckbox_3 && $declarationCheckbox_4 && $declarationCheckbox_5) {
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

            // Send confirmation mail.
            if(!prepare_student_mail($id, $course_id, $student_id, MAIL_STUDENT_RATINGS_SAVED)) {
                $viewparams['error_mail_not_send'] = true;
            }
        } else {
            $rating_data = $DB->get_records('stalloc_rating', ['course_id' => $course_id, 'cm_id' => $id, 'user_id' => $student_id]);
            if($rating_data == null) {
                $viewparams['error_save_ratings'] = true;
            } else {
                $viewparams['error_update_ratings'] = true;
            }

        }

    }

    return $viewparams;
}