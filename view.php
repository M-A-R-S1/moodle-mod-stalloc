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
if(has_capability('mod/stalloc:student', context_course::instance($course_id)) && !is_siteadmin()) {
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
        $declaration_start = $stalloc_data->startdate_declaration;
        $declaration_end = $stalloc_data->enddate_declaration;
        $today = strtotime(date("Y-m-d"));
        $declarationDone = false;

        // Declaration is enabled!
        if($declaration_data->active == 1) {
            // Is there a time schedule?
            if($declaration_start != null && $declaration_end != null) {
                if( ($declaration_start <= $today) && ($declaration_end >= $today) ) {
                    // Check if the Student has accepted the declaration if not show the declaration to the student.
                    if($student_data->declaration == 0) {
                        $showDeclaration = true;
                        $viewparams['delcaration_text'] = $declaration_data->text;
                    } else {
                        $declarationDone = true;
                    }
                } else {
                    if($declaration_end > $today) {
                        if($student_data->declaration == 1) {
                            $declarationDone = true;
                        } else {
                            $viewparams['time_over_declaration'] = true;
                        }
                    } else {
                        $viewparams['waiting_declaration'] = true;
                    }
                }
            } else {
                // Check if the Student has accepted the declaration if not show the declaration to the student.
                if($student_data->declaration == 0) {
                    $showDeclaration = true;
                    $viewparams['delcaration_text'] = $declaration_data->text;
                } else {
                    $declarationDone = true;
                }
            }
        } else {
            $declarationDone = true;
        }

        // Proceed to the next Phase -> Direct Allocation Phase if the Declaration phase is finished.
        if($declarationDone) {
            // Get the timeframe of this phase.
            $direct_allocation_start = $stalloc_data->startdate_direct_alloc;
            $direct_allocation_end = $stalloc_data->enddate_direct_alloc;
            // Get Allocation Data for this student.
            $allocation_data = $DB->get_record('stalloc_allocation', ['course_id' => $course_id, 'cm_id' => $id, 'user_id' => $student_data->id]);
            $allocationDone = false;

            // Is there a time schedule?
            if($direct_allocation_start != null && $direct_allocation_end != null) {
                if( ($direct_allocation_start <= $today) && ($direct_allocation_end >= $today) ) {
                    // There is no allocation data! Or the allocation data was reset (Chair Member declined the direct allocation!)
                    if(($allocation_data == null || $allocation_data->checked == -1)) {
                        // Get All Chairs of this Course Module.
                        $chair_data = $DB->get_records('stalloc_chair', ['course_id' => $course_id, 'cm_id' => $id]);
                        // Set the 'NO' Selection.
                        $viewparams['chair'][0] = new stdClass();
                        $viewparams['chair'][0]->id = -1;
                        $viewparams['chair'][0]->chair_name = 'NO';

                        // Go through each chair and save the data to the template params.
                        $index = 1;
                        foreach($chair_data as $key=>$chair) {
                            $viewparams['chair'][$index] = new stdClass();
                            $viewparams['chair'][$index]->id = $chair->id;
                            $viewparams['chair'][$index]->chair_name = $chair->name;
                            $index++;
                        }

                        if($allocation_data != null) {
                            if($allocation_data->checked == -1) {
                                $viewparams['declined'] = true;
                            }
                        }

                        $showDirectAllocation = true;
                    } else {
                        $viewparams['waiting_direct_allocation'] = true;
                    }
                } else {
                    if($direct_allocation_end < $today) {
                        $allocationDone = true;
                    }
                }
            } else {
                // There is no allocation data! Or the allocation data was reset (Chair Member declined the direct allocation!)
                if(($allocation_data == null || $allocation_data->checked == -1)) {
                    // Get All Chairs of this Course Module.
                    $chair_data = $DB->get_records('stalloc_chair', ['course_id' => $course_id, 'cm_id' => $id]);
                    // Set the 'NO' Selection.
                    $viewparams['chair'][0] = new stdClass();
                    $viewparams['chair'][0]->id = -1;
                    $viewparams['chair'][0]->chair_name = 'NO';

                    // Go through each chair and save the data to the template params.
                    $index = 1;
                    foreach($chair_data as $key=>$chair) {
                        $viewparams['chair'][$index] = new stdClass();
                        $viewparams['chair'][$index]->id = $chair->id;
                        $viewparams['chair'][$index]->chair_name = $chair->name;
                        $index++;
                    }

                    if($allocation_data != null) {
                        if($allocation_data->checked == -1) {
                            $viewparams['declined'] = true;
                        }
                    }

                    $showDirectAllocation = true;
                } else {
                    $allocationDone = true;
                }
            }

            // Proceed to the next Phase -> Rating Phase.
            if($allocationDone) {

                // First Check again if there is a user allocation. Create one if necessary or update it!
                if($allocation_data == null ) {
                    // Create a new allocation for this user.
                    $DB->insert_record('stalloc_allocation', ['course_id' => $course_id, 'cm_id' => $id, 'user_id' => $student_data->id, 'chair_id' => -1, 'direct_allocation' => 0, 'checked' => 0, 'thesis_name' => ""]);
                } else if($allocation_data->checked == -1 || ($allocation_data->direct_allocation == 1 && $allocation_data->checked != 1) ) {
                    // Update an existing Allocation. -> User was rejected by a chair and did not switch the chair.
                    $updateobject  = new stdClass();
                    $updateobject->id = $allocation_data->id;
                    $updateobject->checked = 0;
                    $updateobject->chair_id = -1;
                    $updateobject->direct_allocation = 0;
                    $updateobject->thesis_name = "";
                    $DB->update_record('stalloc_allocation', $updateobject);
                }

                // Refresh the allocation data.
                $allocation_data = $DB->get_record('stalloc_allocation', ['course_id' => $course_id, 'cm_id' => $id, 'user_id' => $student_data->id]);

                // Get the timeframe of this phase.
                $rating_start = $stalloc_data->startdate_rating;
                $rating_end = $stalloc_data->enddate_rating;
                $ratingDone = false;

                // Is there a time schedule?
                if($rating_start != null && $rating_end != null) {
                    if( ($rating_start <= $today) && ($rating_end >= $today) ) {
                        // The Student now has to provide Ratings if the student has no direct allocation.
                        if($allocation_data->chair_id == -1) {
                            // Get Rating Data for this student.
                            $rating_data = $DB->get_records('stalloc_rating', ['user_id' => $student_data->id], "rating DESC");
                            $index = 0;

                            // There is already a student rating! Load and display it.
                            if($rating_data != null) {
                                $chair_data = $DB->get_records('stalloc_chair', ['course_id' => $course_id, 'cm_id' => $id, 'active' => 1], "name ASC");

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
                                //$ratingDone = true;
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
                        if($rating_end > $today) {
                            $ratingDone = true;

                            if($allocation_data->chair_id == -1) {
                                $viewparams['time_over_rating'] = true;
                            }
                        }
                    }
                } else {
                    // The Student now has to provide Ratings if the student has no direct allocation.
                    if($allocation_data->chair_id == -1) {
                        // Get Rating Data for this student.
                        $rating_data = $DB->get_records('stalloc_rating', ['user_id' => $student_data->id], "rating DESC");
                        $index = 0;

                        // There is already a student rating! Load and display it.
                        if($rating_data != null) {
                            $chair_data = $DB->get_records('stalloc_chair', ['course_id' => $course_id, 'cm_id' => $id, 'active' => 1], "name ASC");

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
                            $ratingDone = true;
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
                    } else {
                        $ratingDone = true;
                    }
                }

                // The student is done! From now on the student can access his information -> Which chair was he assigned too etc.
                if($allocation_data->chair_id != -1 && $ratingDone) {
                    // Get the timeframe of this phase.
                    $thesis_alloc_start = $stalloc_data->startdate_thesis_alloc;
                    $thesis_alloc_end = $stalloc_data->enddate_thesis_alloc;
                    $chair_data = $DB->get_record('stalloc_chair', ['id' => $allocation_data->chair_id]);

                    if($thesis_alloc_start != null && $thesis_alloc_end != null) {
                        if (($thesis_alloc_start <= $today) && ($thesis_alloc_end >= $today)) {
                            $viewparams['allocated_chair_name'] = $chair_data->name;
                        } else {
                            if($stalloc_data->publish_results == 1 && $thesis_alloc_end > $today) {
                                $viewparams['results_published'] = true;
                                $viewparams['chair_name'] = $chair_data->name;
                                $viewparams['thesis_name'] = $allocation_data->thesis_name;
                                $viewparams['start_date'] = date('d.m.Y',$allocation_data->startdate);

                                $examiner_index = 0;
                                $examiner_data = $DB->get_records('stalloc_allocation_examiner', ['course_id' => $course_id, 'cm_id' => $id, 'allocation_id' => $allocation_data->id]);
                                foreach ($examiner_data as $examiner) {
                                    $examiner_user = $DB->get_record('stalloc_chair_member', ['id' => $examiner->chair_member_id]);
                                    $moodle_user = $DB->get_record('user', ['id' => $examiner_user->moodle_user_id]);
                                    $viewparams['examiner'][$examiner_index] = new stdClass();
                                    $viewparams['examiner'][$examiner_index]->examiner_lastname = $moodle_user->lastname;
                                    $viewparams['examiner'][$examiner_index]->examiner_firstname = $moodle_user->firstname;
                                    $examiner_index++;
                                }
                            } else {
                                $viewparams['allocated_chair_name'] = $chair_data->name;
                            }
                        }
                    } else {
                        if($stalloc_data->publish_results == 1) {
                            $viewparams['results_published'] = true;
                            $viewparams['chair_name'] = $chair_data->name;
                            $viewparams['thesis_name'] = $allocation_data->thesis_name;
                            $viewparams['start_date'] = date('d.m.Y',$allocation_data->startdate);

                            $examiner_index = 0;
                            $examiner_data = $DB->get_records('stalloc_allocation_examiner', ['course_id' => $course_id, 'cm_id' => $id, 'allocation_id' => $allocation_data->id]);
                            foreach ($examiner_data as $examiner) {
                                $examiner_user = $DB->get_record('stalloc_chair_member', ['id' => $examiner->chair_member_id]);
                                $moodle_user = $DB->get_record('user', ['id' => $examiner_user->moodle_user_id]);
                                $viewparams['examiner'][$examiner_index] = new stdClass();
                                $viewparams['examiner'][$examiner_index]->examiner_lastname = $moodle_user->lastname;
                                $viewparams['examiner'][$examiner_index]->examiner_firstname = $moodle_user->firstname;
                                $examiner_index++;
                            }
                        } else {
                            $viewparams['allocated_chair_name'] = $chair_data->name;
                        }
                    }
                }

            }
        }
    }

} else if((has_capability('mod/stalloc:chairmember', context_course::instance($course_id)) || has_capability('mod/stalloc:examinationmember', context_course::instance($course_id))) && !is_siteadmin()) {

    // Check for Post Events.
    if(isset($_POST['save_chair_selection'])) {
        $selected_chair_id = $_POST['chair_select'];
        // Create a new Chair Member Record.
        $DB->insert_record('stalloc_chair_member', ['course_id' => $course_id, 'cm_id' => $id, 'chair_id' => $selected_chair_id, 'moodle_user_id' => $USER->id, 'is_examiner' => 0]);
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
    if((has_capability('mod/stalloc:chairmember', context_course::instance($course_id)) || has_capability('mod/stalloc:examinationmember', context_course::instance($course_id)))) {
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