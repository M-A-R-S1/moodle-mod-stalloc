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
 * Prints an instance of mod_stalloc chair delete page
 *
 * @package     mod_stalloc
 * @copyright   2025 Marc-André Schmidt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/config.php');
require_once(__DIR__.'/locallib.php');
require_once(__DIR__ . '/form/form_chair_delete.php');

$id = required_param('id', PARAM_INT);
$chair_id = required_param('chair_id', PARAM_INT);

list ($course, $cm) = get_course_and_cm_from_cmid($id, 'stalloc');
$instance = $DB->get_record('stalloc', ['id'=> $cm->instance], '*', MUST_EXIST);
$course_id = $course->id;
$context = context_course::instance($course_id);
require_login($course, false, $cm);

// Initialize the header
$paramsheader = initialize_stalloc_header(PAGE_CHAIR, $id, $course_id, $instance);

// First check if the user has the capability to be on this page! -> Admins/Manager.
if (has_capability('mod/stalloc:examination_member', context_course::instance($course_id)))  {
    // Display the page layout.
    $strpage = get_string('pluginname', 'mod_stalloc');
    $PAGE->set_pagelayout('incourse');
    $PAGE->set_context(context_module::instance($cm->id));
    $PAGE->set_heading($strpage);
    $PAGE->set_url(new moodle_url('/mod/stalloc/chair_delete.php', ['id' => $id, 'chair_id' => $chair_id]));
    $PAGE->set_title($course->shortname.': '.$strpage);

    // Output the header.
    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('stalloc/header', $paramsheader);

    // Get Database information of the chair which is about to be deleted.
    $chair_data = $DB->get_record('stalloc_chair', ['id' => $chair_id]);

    // Crate the form element.
    $form_chair_delete = new mod_stalloc_form_chair_delete(null, ['id' => $id, 'chair_id' => $chair_id]);

    // Count Rating and Allocation Data.
    $rating_count = $DB->count_records('stalloc_rating', ['course_id' => $course_id, 'cm_id' => $id]);
    $allocation_count = $DB->count_records('stalloc_allocation', ['course_id' => $course_id, 'cm_id' => $id]);

    // Template Parameters.
    $params_chair = [];
    $params_chair['chair'][0] = new stdClass();
    $params_chair['chair'][0]->chair_name = $chair_data->name;
    $params_chair['chair'][0]->chair_holder = $chair_data->holder;
    $params_chair['chair'][0]->chair_contact_name = $chair_data->contact_name;
    $params_chair['chair'][0]->chair_contact_phone = $chair_data->contact_phone;
    $params_chair['chair'][0]->chair_contact_mail = $chair_data->contact_mail;
    $params_chair['chair'][0]->chair_distribution_key = $chair_data->distribution_key;

    if($rating_count > 0 || $allocation_count > 0) {
        $params_chair['error_chair_in_use'] = true;
        $params_chair['link_back'] = new moodle_url('/mod/stalloc/chair.php', ['id' => $id]);
    }

    // Different actions, depending on the user action.
    if ($form_chair_delete->is_cancelled()) {
        redirect(new moodle_url('/mod/stalloc/chair.php', ['id' => $id]), "Chair Deletion Canceled!", 4, 'NOTIFY_INFO');
    } else if ($data = $form_chair_delete->get_data()) {

        $DB->delete_records('stalloc_chair', ['id' => $chair_id]);

        // All done! Redirect to the chair page.
        $redirecturl = new moodle_url('/mod/stalloc/chair.php', ['id' => $id]);
        redirect($redirecturl, "Chair Successfully Deleted", 2, \core\output\notification::NOTIFY_SUCCESS);
    }
    else {
        // Output the Chair Template
        echo $OUTPUT->render_from_template('stalloc/chair_delete', $params_chair);

        if($rating_count == 0 && $allocation_count == 0) {
            // Display the Form.
            $form_chair_delete->display();
        }
    }

    // Displaying the footer.
    echo $OUTPUT->footer();
} else {
    redirect(course_get_url($course->id), "Missing Capability!", 4, 'NOTIFY_ERROR');
}

