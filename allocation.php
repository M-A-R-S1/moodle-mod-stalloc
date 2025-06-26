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
 * Prints an instance of mod_stalloc allocation page
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
$paramsheader = initialize_stalloc_header(PAGE_ALLOCATION, $id, $course_id, $instance);

// First check if the user has the capability to be on this page! -> Admins/Manager.
if (has_capability('mod/stalloc:examination_member', context_course::instance($course_id)))  {
    // Display the page layout.
    $strpage = get_string('pluginname', 'mod_stalloc');
    $PAGE->set_pagelayout('incourse');
    $PAGE->set_context(context_module::instance($cm->id));
    $PAGE->set_heading($strpage);
    $PAGE->set_url(new moodle_url('/mod/stalloc/allocation.php', ['id' => $id]));
    $PAGE->set_title($course->shortname.': '.$strpage);

    // Output the header.
    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('stalloc/header', $paramsheader);

    // Check for allocation pressed Button
    if(isset($_POST['start_allocation'])) {
        process_action_start_distribution($id, $course_id, $instance);
    }

    // Paramater Array.
    $params_allocation = [];
    $stalloc_data = $DB->get_record('stalloc', ['id' => $instance->id]);

    $start_time = $stalloc_data->start_phase3;
    $end_time = $stalloc_data->end_phase3;
    $today = strtotime(date("Y-m-d"));

    if($start_time != null && $end_time != null) {
        if (($start_time <= $today) && ($end_time >= $today)) {

            // How many ratings can a student make?
            $rating_number = $stalloc_data->rating_number;
            $all_rating_data = $DB->get_records('stalloc_rating' ,['course_id' => $course_id, 'cm_id' => $id]);

            if($all_rating_data != null && $rating_number > 0) {

                // TODO ... Change it back! This permits the examination office to perform the allocation process only once!
                //if($stalloc_data->allocationstatus == 0) {
                //    $params_allocation['allocation_can_start'] = true;
                //}
                // TODO ... Remove this line after testing!
                $params_allocation['allocation_can_start'] = true;

                $rating_count = 0;

                foreach ($all_rating_data as $rating) {
                    $rating_count++;
                }
                $params_allocation['rated_student_count'] = $rating_count / $rating_number;

                // Initialize a rating array.
                for ($i = 0; $i < $rating_number; $i++) {
                    $params_allocation['rating'][$i] = new stdClass();
                    $params_allocation['rating'][$i]->count = 0;
                    $params_allocation['rating'][$i]->priority = $i + 1;
                }

                // Check for the Student Allocation.
                $allocation_data = $DB->get_records('stalloc_allocation', ['course_id' => $course_id, 'cm_id' => $id]);

                if ($allocation_data != null) {
                    $params_allocation['allocations'] = true;
                    $params_allocation['unallocated'] = 0;

                    foreach ($allocation_data as $allocation) {
                        if ($allocation->chair_id != -1 && $allocation->direct_allocation == 0) {
                            // There is an Allocation. Get the user ID and check which priority it is.
                            $rating_data = $DB->get_record('stalloc_rating', ['course_id' => $course_id, 'cm_id' => $id, 'user_id' => $allocation->user_id, 'chair_id' => $allocation->chair_id]);
                            $params_allocation['rating'][$rating_number - $rating_data->rating]->count = $params_allocation['rating'][$rating_number - $rating_data->rating]->count + 1;
                        }

                        if($allocation->chair_id == -1) {
                            $params_allocation['unallocated']++;
                        }
                    }
                }
            } else {
                $params_allocation['no_ratings'] = true;
            }

        } else {
            if($end_time < $today) {
                $params_allocation['allocation_time_over'] = true;
            } else {
                $params_allocation['allocation_time_not_started'] = true;
            }
        }
    } else {
        $params_allocation['allocation_time_needed'] = true;
    }

    // Output the Chair Template
    echo $OUTPUT->render_from_template('stalloc/allocation', $params_allocation);

    // Displaying the footer.
    echo $OUTPUT->footer();
} else {
    redirect(course_get_url($course->id), "Missing Capability!", 4, 'NOTIFY_ERROR');
}

