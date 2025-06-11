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
 * Prints an instance of mod_stalloc settings page
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
$paramsheader = initialize_stalloc_header(PAGE_SETTINGS, $id, $course_id, $instance);

// First check if the user has the capability to be on this page! -> Admins/Teachers.
if (has_capability('mod/stalloc:admin', context_course::instance($course_id)))  {

    // Template Data Array.
    $params_settings = [];

    // Check for POST events.
    $params_settings = checkFormActions($course_id, $id, $instance->id, $DB, $params_settings);

    // Initialize the Plugin (Done only once!).
    $stalloc_data = $DB->get_record('stalloc', ['id' => $instance->id]);
    if($stalloc_data->initialized == "0") {
        initialize_stalloc_plugin($id, $course_id, $instance->id);
    }

    // Load declaration data from the database which is connected to this course module.
    $declaration_data = $DB->get_record('stalloc_declaration_text', ['course_id' => $course_id, 'cm_id' => $id]);
    // Collect "Declaration" Data for the Template.
    if($declaration_data->active == 0) {
        $params_settings['disabled_declaration'] = 'disabled';
    } else {
        $params_settings['checkbox_declaration'] = 'checked';
    }
    $params_settings['text_declaration'] = $declaration_data->text;

    // Load the rating values from the database.
    $params_settings['rating_value'] = $stalloc_data->rating_number;


    // Count Rating and Allocation Data.
    $rating_count = $DB->count_records('stalloc_rating', ['course_id' => $course_id, 'cm_id' => $id]);
    $allocation_count = $DB->count_records('stalloc_allocation', ['course_id' => $course_id, 'cm_id' => $id]);

    if($rating_count > 0 || $allocation_count > 0) {
        $params_settings['rating_change_disabled'] = true;
    } else {
        $params_settings['rating_value_enabled'] = true;
    }

    // Load the Phone settings.
    if($stalloc_data->phone_required == 1) {
        $params_settings['checkbox_phone'] = 'checked';
    }

    // Load Published Settings.
    if($stalloc_data->publish_results == 1) {
        $params_settings['checkbox_publish'] = 'checked';
    }

    // Load the Dates from the database.
    if($stalloc_data->startdate_declaration != null) {
        $params_settings['start_date_declaration'] = date("Y-m-d", $stalloc_data->startdate_declaration);
    }
    if($stalloc_data->enddate_declaration != null) {
        $params_settings['end_date_declaration'] = date("Y-m-d", $stalloc_data->enddate_declaration);
    }
    if($stalloc_data->startdate_direct_alloc != null) {
        $params_settings['start_date_direct_alloc'] = date("Y-m-d", $stalloc_data->startdate_direct_alloc);
    }
    if($stalloc_data->enddate_direct_alloc != null) {
        $params_settings['end_date_direct_alloc'] = date("Y-m-d", $stalloc_data->enddate_direct_alloc);
    }
    if($stalloc_data->startdate_rating != null) {
        $params_settings['start_date_rating'] = date("Y-m-d", $stalloc_data->startdate_rating);
    }
    if($stalloc_data->enddate_rating != null) {
        $params_settings['end_date_rating'] = date("Y-m-d", $stalloc_data->enddate_rating);
    }
    if($stalloc_data->startdate_rating_alloc != null) {
        $params_settings['start_date_alloc'] = date("Y-m-d", $stalloc_data->startdate_rating_alloc);
    }
    if($stalloc_data->enddate_rating_alloc != null) {
        $params_settings['end_date_alloc'] = date("Y-m-d", $stalloc_data->enddate_rating_alloc);
    }
    if($stalloc_data->startdate_thesis_alloc != null) {
        $params_settings['start_date_thesis_alloc'] = date("Y-m-d", $stalloc_data->startdate_thesis_alloc);
    }
    if($stalloc_data->enddate_thesis_alloc != null) {
        $params_settings['end_date_thesis_alloc'] = date("Y-m-d", $stalloc_data->enddate_thesis_alloc);
    }

    // Display the page layout.
    $PAGE->requires->js_call_amd('mod_stalloc/basic_settings', 'init');
    $strpage = get_string('pluginname', 'mod_stalloc');
    $PAGE->set_pagelayout('incourse');
    $PAGE->set_context(context_module::instance($cm->id));
    $PAGE->set_heading($strpage);
    $PAGE->set_url(new moodle_url('/mod/stalloc/basic_settings.php', ['id' => $id]));
    $PAGE->set_title($course->shortname.': '.$strpage);

    // Output the header.
    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('stalloc/header', $paramsheader);

    // Output the Settings Template
    echo $OUTPUT->render_from_template('stalloc/basic_settings', $params_settings);

    // Displaying the footer.
    echo $OUTPUT->footer();
} else {
    redirect(course_get_url($course->id), "Missing Capability!", 4, 'NOTIFY_ERROR');
}


/**
 * Checks if the user pressed the save button. If so save or change stuff.
 *
 * @param int $course_id The course ID.
 * @param int $id The ID of the course module.
 * @param int $instance_id current instance id.
 * @param moodle_database $DB The database Element.
 * @param array $params_settings The Array with the template Data.
 * @return array
 * @throws dml_exception
 * @throws moodle_exception
 */
function checkFormActions(int $course_id, int $id, int $instance_id, moodle_database $DB, array $params_settings): array {
    if(isset($_POST['save_declaration'])) {

        $declarationData = $DB->get_record('stalloc_declaration_text', ['course_id' => $course_id, 'cm_id' => $id]);
        $declarationCheckbox = isset($_POST['checkbox_declaration']);
        $declarationText = trim($_POST['text_declaration']);

        // Check if the Declaration Text is not Empty.
        if( ($declarationText != '' && $declarationCheckbox) || !$declarationCheckbox) {

            // Update the already existing entry.
            $updateobject  = new stdClass();
            $updateobject->id = $declarationData->id;
            $updateobject->text = $declarationText;

            if($declarationCheckbox) {
                $updateobject->active = 1;
            } else {
                $updateobject->active = 0;
            }

            // Update the declaration text database entry.
            $DB->update_record('stalloc_declaration_text', $updateobject);
            $params_settings['saved_declaration'] = true;

        } else {
            // Throw an error! There was no Declaration Text!
            $params_settings['error_declaration_empty'] = true;
        }
    } else if(isset($_POST['save_phone'])) {

        $phoneCheckbox = 0;
        if(isset($_POST['checkbox_phone'])) {
            $phoneCheckbox = 1;
        }

        // Update the already existing entry.
        $updateobject  = new stdClass();
        $updateobject->id = $instance_id;
        $updateobject->phone_required = $phoneCheckbox;

        // Update the phone requirement database entry.
        $DB->update_record('stalloc', $updateobject);
        $params_settings['saved_phone'] = true;

    } else if(isset($_POST['save_rating'])) {
        $chair_number = $DB->count_records('stalloc_chair', ['course_id' => $course_id, 'cm_id' => $id, 'active' => 1]);
        $rating_number = trim($_POST['rating_counter']);

        if($rating_number != null && is_number($rating_number) && $rating_number <= $chair_number) {
            // Update the rating number in the database!
            $updateobject  = new stdClass();
            $updateobject->id = $instance_id;
            $updateobject->rating_number = $rating_number;
            $DB->update_record('stalloc', $updateobject);

            $params_settings['saved_rating'] = true;
        } else {
            //Show error!
            $params_settings['error_rating_number'] = true;
        }
    } else if(isset($_POST['save_schedule'])) {

        $stalloc_data = $DB->get_record('stalloc', ['id' => $instance_id]);
        $updateobject  = new stdClass();
        $updateobject->id = $instance_id;
        $update_dates = false;
        // Check if the submitted dates are ok!
        // 1. Only Pairs are allowed!
        // 2. Start Date < End Date.

        // Check the Declaration Dates
        $date_check_ok = true;
        if(isset($_POST['start_date_declaration']) || isset($_POST['end_date_declaration'])) {
            // Both dates submitted?
            if(isset($_POST['start_date_declaration']) && isset($_POST['end_date_declaration'])) {
                // Start Date < End Date?
                if(strtotime ($_POST['start_date_declaration']) < (strtotime ($_POST['end_date_declaration']) + 86400)) {
                    $start_date_declaration = strtotime ($_POST['start_date_declaration']);
                    $end_date_declaration = strtotime ($_POST['end_date_declaration']);
                } else {
                    $params_settings['error_end_date_before_start_date'] = true;
                    $date_check_ok = false;
                }
            }  else {
                $params_settings['error_no_date_pair'] = true;
                $date_check_ok = false;
            }

            // No Date Errors!
            if($date_check_ok) {
                // Is the new time different from the time allready saved in the database? If so, save it!
                if($stalloc_data->startdate_declaration != $start_date_declaration || $stalloc_data->enddate_declaration != $end_date_declaration) {
                    $updateobject->startdate_declaration = $start_date_declaration;
                    $updateobject->enddate_declaration = $end_date_declaration;
                    $params_settings['saved_declaration_date'] = true;
                    $update_dates = true;
                }
            } else {
                $params_settings['error_declaration_date'] = true;
            }
        }

        // Check the Direct Allocation Dates
        $date_check_ok = true;
        if(isset($_POST['start_date_direct_alloc']) || isset($_POST['end_date_direct_alloc'])) {
            // Both dates submitted?
            if(isset($_POST['start_date_direct_alloc']) && isset($_POST['end_date_direct_alloc'])) {
                // Start Date < End Date?
                if(strtotime ($_POST['start_date_direct_alloc']) < (strtotime ($_POST['end_date_direct_alloc']) + 86400)) {
                    $start_date_direct_alloc = strtotime ($_POST['start_date_direct_alloc']);
                    $end_date_direct_alloc = strtotime ($_POST['end_date_direct_alloc']);
                } else {
                    $params_settings['error_end_date_before_start_date'] = true;
                    $date_check_ok = false;
                }
            }  else {
                $params_settings['error_no_date_pair'] = true;
                $date_check_ok = false;
            }

            // No Date Errors!
            if($date_check_ok) {
                // Is the new time different from the time allready saved in the database? If so, save it!
                if($stalloc_data->startdate_direct_alloc != $start_date_direct_alloc || $stalloc_data->enddate_direct_alloc != $end_date_direct_alloc) {
                    $updateobject->startdate_direct_alloc = $start_date_direct_alloc;
                    $updateobject->enddate_direct_alloc = $end_date_direct_alloc;
                    $params_settings['saved_direct_allocation_date'] = true;
                    $update_dates = true;
                }
            } else {
                $params_settings['error_direct_allocation_date'] = true;
            }
        }

        // Check the Rating Dates
        $date_check_ok = true;
        if(isset($_POST['start_date_rating']) || isset($_POST['end_date_rating'])) {
            // Both dates submitted?
            if(isset($_POST['start_date_rating']) && isset($_POST['end_date_rating'])) {
                // Start Date < End Date?
                if(strtotime ($_POST['start_date_rating']) < (strtotime ($_POST['end_date_rating']) + 86400)) {
                    $start_date_rating = strtotime ($_POST['start_date_rating']);
                    $end_date_rating = strtotime ($_POST['end_date_rating']);
                } else {
                    $params_settings['error_end_date_before_start_date'] = true;
                    $date_check_ok = false;
                }
            }  else {
                $params_settings['error_no_date_pair'] = true;
                $date_check_ok = false;
            }

            // No Date Errors!
            if($date_check_ok) {
                // Is the new time different from the time allready saved in the database? If so, save it!
                if($stalloc_data->startdate_rating != $start_date_rating || $stalloc_data->enddate_rating != $end_date_rating) {
                    $updateobject->startdate_rating = $start_date_rating;
                    $updateobject->enddate_rating = $end_date_rating;
                    $params_settings['saved_rating_date'] = true;
                    $update_dates = true;
                }
            } else {
                $params_settings['error_rating_date'] = true;
            }
        }

        // Check the Allocation Dates
        $date_check_ok = true;
        if(isset($_POST['start_date_alloc']) || isset($_POST['end_date_alloc'])) {
            // Both dates submitted?
            if(isset($_POST['start_date_alloc']) && isset($_POST['end_date_alloc'])) {
                // Start Date < End Date?
                if(strtotime ($_POST['start_date_alloc']) < (strtotime ($_POST['end_date_alloc']) + 86400)) {
                    $start_date_alloc = strtotime ($_POST['start_date_alloc']);
                    $end_date_alloc = strtotime ($_POST['end_date_alloc']);
                } else {
                    $params_settings['error_end_date_before_start_date'] = true;
                    $date_check_ok = false;
                }
            }  else {
                $params_settings['error_no_date_pair'] = true;
                $date_check_ok = false;
            }

            // No Date Errors!
            if($date_check_ok) {
                // Is the new time different from the time allready saved in the database? If so, save it!
                if($stalloc_data->startdate_rating_alloc != $start_date_alloc || $stalloc_data->enddate_rating_alloc != $end_date_alloc) {
                    $updateobject->startdate_rating_alloc = $start_date_alloc;
                    $updateobject->enddate_rating_alloc = $end_date_alloc;
                    $params_settings['saved_allocation_date'] = true;
                    $update_dates = true;
                }
            } else {
                $params_settings['error_allocation_date'] = true;
            }
        }

        // Check the Thesis Allocation Dates
        $date_check_ok = true;
        if(isset($_POST['start_date_thesis_alloc']) || isset($_POST['end_date_thesis_alloc'])) {
            // Both dates submitted?
            if(isset($_POST['start_date_thesis_alloc']) && isset($_POST['end_date_thesis_alloc'])) {
                // Start Date < End Date?
                if(strtotime ($_POST['start_date_thesis_alloc']) < (strtotime ($_POST['end_date_thesis_alloc']) + 86400)) {
                    $start_date_thesis_alloc = strtotime ($_POST['start_date_thesis_alloc']);
                    $end_date_thesis_alloc = strtotime ($_POST['end_date_thesis_alloc']);
                } else {
                    $params_settings['error_end_date_before_start_date'] = true;
                    $date_check_ok = false;
                }
            }  else {
                $params_settings['error_no_date_pair'] = true;
                $date_check_ok = false;
            }

            // No Date Errors!
            if($date_check_ok) {
                // Is the new time different from the time allready saved in the database? If so, save it!
                if($stalloc_data->startdate_thesis_alloc != $start_date_thesis_alloc || $stalloc_data->enddate_thesis_alloc != $end_date_thesis_alloc) {
                    $updateobject->startdate_thesis_alloc = $start_date_thesis_alloc;
                    $updateobject->enddate_thesis_alloc = $end_date_thesis_alloc;
                    $params_settings['saved_thesis_allocation_date'] = true;
                    $update_dates = true;
                }
            } else {
                $params_settings['error_thesis_allocation_date'] = true;
            }
        }

        // Date Changed detected -> Update the Database.
        if($update_dates) {
            $DB->update_record('stalloc', $updateobject);
        }
    } else if(isset($_POST['publish'])) {

        $publishCheckbox = 0;
        if(isset($_POST['checkbox_publish'])) {
            $publishCheckbox = 1;
        }

        // Update the already existing entry.
        $updateobject  = new stdClass();
        $updateobject->id = $instance_id;
        $updateobject->publish_results = $publishCheckbox;

        // Update the database entry.
        $DB->update_record('stalloc', $updateobject);
        $params_settings['saved_published'] = true;
    }

    return $params_settings;
}
