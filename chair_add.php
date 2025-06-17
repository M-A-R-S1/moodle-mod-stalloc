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
 * Prints an instance of mod_stalloc chair add page
 *
 * @package     mod_stalloc
 * @copyright   2025 Marc-André Schmidt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/config.php');
require_once(__DIR__.'/locallib.php');
require_once(__DIR__ . '/form/form_chair_add.php');

$id = required_param('id', PARAM_INT);

list ($course, $cm) = get_course_and_cm_from_cmid($id, 'stalloc');
$instance = $DB->get_record('stalloc', ['id'=> $cm->instance], '*', MUST_EXIST);
$course_id = $course->id;
$context = context_course::instance($course_id);
require_login($course, false, $cm);

// Initialize the header
$paramsheader = initialize_stalloc_header(PAGE_CHAIR, $id, $course_id, $instance);

// First check if the user has the capability to be on this page! -> Admins/Manager.
if (has_capability('mod/stalloc:examination_member', context_module::instance($instance->id)))  {
    // Display the page layout.
    $strpage = get_string('pluginname', 'mod_stalloc');
    $PAGE->set_pagelayout('incourse');
    $PAGE->set_context(context_module::instance($cm->id));
    $PAGE->set_heading($strpage);
    $PAGE->set_url(new moodle_url('/mod/stalloc/chair_add.php', ['id' => $id]));
    $PAGE->set_title($course->shortname.': '.$strpage);

    // Output the header.
    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('stalloc/header', $paramsheader);

    // Crate the form element.
    $form_chair_create = new mod_stalloc_form_chair_add(null, ['id' => $id]);

    // Different actions, depending on the user action.
    if ($form_chair_create->is_cancelled()) {
        redirect(new moodle_url('/mod/stalloc/chair.php', ['id' => $id]), "Chair Addition Canceled!", 4, 'NOTIFY_INFO');
    } else if ($data = $form_chair_create->get_data()) {

        $active_value = 0;
        if(isset($data->active)) {
            $active_value = 1;
        }

        $recordid = $DB->insert_record('stalloc_chair', ['course_id' => $course_id, 'cm_id' => $id, 'name' => $data->chair_name, 'holder' => $data->chair_holder, 'contact_name' => $data->contact_name,
            'contact_phone' => $data->contact_phone, 'contact_mail' => $data->contact_mail, 'distribution_key' => $data->distribution_key, 'active' => $active_value, 'flexnow_id' => $data->flexnow_id]);

        // All done! Redirect to the chair page.
        $redirecturl = new moodle_url('/mod/stalloc/chair.php', ['id' => $id]);
        redirect($redirecturl, "Chair Successfully Added", 2, \core\output\notification::NOTIFY_SUCCESS);
    }


    echo '<h2 class="mt-3 mb-3 ml-3">ADD NEW CHAIR</h2>';

    // Display the Form.
    $form_chair_create->display();

    // Displaying the footer.
    echo $OUTPUT->footer();
} else {
    redirect(course_get_url($course->id), "Missing Capability!", 4, 'NOTIFY_ERROR');
}

