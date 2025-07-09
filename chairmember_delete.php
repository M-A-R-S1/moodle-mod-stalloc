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
 * Prints an instance of mod_stalloc chairmember delete page
 *
 * @package     mod_stalloc
 * @copyright   2025 Marc-André Schmidt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/config.php');
require_once(__DIR__.'/locallib.php');
require_once(__DIR__ . '/form/form_chairmember_delete.php');

$id = required_param('id', PARAM_INT);
$chairmember_id = required_param('chairmember_id', PARAM_INT);

list ($course, $cm) = get_course_and_cm_from_cmid($id, 'stalloc');
$instance = $DB->get_record('stalloc', ['id'=> $cm->instance], '*', MUST_EXIST);
$course_id = $course->id;
$context = context_course::instance($course_id);
require_login($course, false, $cm);

// Initialize the header
$paramsheader = initialize_stalloc_header(PAGE_CHAIRMEMBER, $id, $course_id, $instance);

// First check if the user has the capability to be on this page! -> Admins/Manager.
if (has_capability('mod/stalloc:examination_member', context_course::instance($course_id)))  {
    // Display the page layout.
    $strpage = get_string('pluginname', 'mod_stalloc');
    $PAGE->set_pagelayout('incourse');
    $PAGE->set_context(context_module::instance($cm->id));
    $PAGE->set_heading($strpage);
    $PAGE->set_url(new moodle_url('/mod/stalloc/chairmember_delete.php', ['id' => $id, 'chairmember_id' => $chairmember_id_id]));
    $PAGE->set_title($course->shortname.': '.$strpage);

    // Output the header.
    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('stalloc/header', $paramsheader);

    // Get the chair member data.
    $chairmember_data = $DB->get_record('stalloc_chair_member', ['id' => $chairmember_id]);
    $moodle_user_data = $DB->get_record('user', ['id' => $chairmember_data->moodle_user_id]);

    // Get information of the chair which is connected to this chair member.
    $chair_data = $DB->get_record('stalloc_chair', ['id' => $chairmember_data->chair_id]);

    // Crate the form element.
    $form_chairmember_delete = new mod_stalloc_form_chairmember_delete(null, ['id' => $id, 'chairmember_id' => $chairmember_id]);

    // Template Parameters.
    $params_chairmember = [];
    $params_chairmember['member'] = new stdClass();
    $params_chairmember['member']->chair_name = $chair_data->name;
    $params_chairmember['member']->member_name = $moodle_user_data->lastname . ", " . $moodle_user_data->firstname;


    // Different actions, depending on the user action.
    if ($form_chairmember_delete->is_cancelled()) {
        redirect(new moodle_url('/mod/stalloc/chairmember.php', ['id' => $id]), "Aktion abgebrochen!", 0, 'NOTIFY_INFO');
    } else if ($data = $form_chairmember_delete->get_data()) {

        $DB->delete_records('stalloc_chair_member', ['id' => $chairmember_id]);

        // All done! Redirect to the chair page.
        $redirecturl = new moodle_url('/mod/stalloc/chairmember.php', ['id' => $id]);
        redirect($redirecturl, "Lehrstuhl-Mitarbeiter erfolgreich gelöscht.", 0, \core\output\notification::NOTIFY_SUCCESS);
    }
    else {
        // Output the Chair Template
        echo $OUTPUT->render_from_template('stalloc/chairmember_delete', $params_chairmember);
        $form_chairmember_delete->display();
    }

    // Displaying the footer.
    echo $OUTPUT->footer();
} else {
    redirect(course_get_url($course->id), "Missing Capability!", 4, 'NOTIFY_ERROR');
}

