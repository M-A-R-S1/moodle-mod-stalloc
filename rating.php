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
 * Prints an instance of mod_stalloc rating page
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
$paramsheader = initialize_stalloc_header(PAGE_RATING, $id, $course_id, $instance);

// First check if the user has the capability to be on this page! -> Admins/Teachers.
if (has_capability('mod/stalloc:chairmember', context_course::instance($course_id)) || has_capability('mod/stalloc:examination_member', context_course::instance($course_id)))  {
    // Display the page layout.
    $strpage = get_string('pluginname', 'mod_stalloc');
    $PAGE->set_pagelayout('incourse');
    $PAGE->set_context(context_module::instance($cm->id));
    $PAGE->set_heading($strpage);
    $PAGE->set_url(new moodle_url('/mod/stalloc/rating.php', ['id' => $id]));
    $PAGE->set_title($course->shortname.': '.$strpage);

    // Output the header.
    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('stalloc/header', $paramsheader);

    // Paramater Array.
    $params_rating = [];

    // How many ratings must a student make?
    $rating_number = $DB->get_record('stalloc', ['id' => $instance->id])->rating_number;

    for($i=0; $i<$rating_number; $i++) {
        $params_rating['table_header'][$i] = new stdClass();
        $params_rating['table_header'][$i]->number = $i+1;
    }

    if (has_capability('mod/stalloc:examination_member', context_course::instance($course_id))) {
        // Load all active chairs
        $chair_data = $DB->get_records('stalloc_chair', ['course_id' => $course_id, 'cm_id' => $id, 'active' => 1], "name ASC");
    } else {
        // Load the chair of the current chair member.
        $current_chair_id = $DB->get_record('stalloc_chair_member', ['course_id' => $course_id, 'cm_id' => $id, 'moodle_user_id' => $USER->id])->chair_id;
        $chair_data = $DB->get_records('stalloc_chair', ['id' => $current_chair_id]);
    }

    $index = 0;
    foreach ($chair_data as $chair) {
        $params_rating['chair'][$index] = new stdClass();
        $params_rating['chair'][$index]->name = $chair->name;

        for($rating_index = 0; $rating_index < $rating_number; $rating_index++) {
            $current_rating = $rating_number - $rating_index;
            $params_rating['chair'][$index]->ratings[$rating_index] = new stdClass();
            $params_rating['chair'][$index]->ratings[$rating_index]->rating = $DB->count_records('stalloc_rating', ['course_id' => $course_id, 'cm_id' => $id, 'rating' => $current_rating, 'chair_id' => $chair->id]);
        }
        $index++;
    }

    // Output the Chair Template
    echo $OUTPUT->render_from_template('stalloc/rating', $params_rating);

    // Displaying the footer.
    echo $OUTPUT->footer();
} else {
    redirect(course_get_url($course->id), "Missing Capability!", 4, 'NOTIFY_ERROR');
}

