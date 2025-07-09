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
 * Library of stalloc functions and constants.
 *
 * @package     mod_stalloc
 * @copyright   2025 Marc-André Schmidt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/config.php');
require_once(__DIR__.'/lib.php');
require_once(__DIR__.'/solver/edmonds-karp.php');
defined('MOODLE_INTERNAL') || die();

/**
 * Initializes the header menu.
 *
 * @param int $activeElement active element of the header.
 * @param int $id current course module id.
 * @param int $course_id current course id.
 * @param object $instance current database record of this course moduls instance.
 * @return array $params retuen a array with the header parameters for the header.mustache file.
 */
function initialize_stalloc_header($activeElement, $id, $course_id, $instance) {
    global $DB;
    $params = [];

    // Moodle-URL links for navigation.
    $params['home_url'] = new moodle_url('/mod/stalloc/view.php', ['id' => $id]);
    $params['settings_url'] = new moodle_url('/mod/stalloc/basic_settings.php', ['id' => $id]);
    $params['chair_url'] = new moodle_url('/mod/stalloc/chair.php', ['id' => $id]);
    $params['student_url'] = new moodle_url('/mod/stalloc/student.php', ['id' => $id]);
    $params['allocation_url'] = new moodle_url('/mod/stalloc/allocation.php', ['id' => $id]);
    $params['rating_url'] = new moodle_url('/mod/stalloc/rating.php', ['id' => $id]);
    $params['chairmember_url'] = new moodle_url('/mod/stalloc/chairmember.php', ['id' => $id]);

    // Check if the user has the full rights to use the admin function of this plugin.
    if (has_capability('mod/stalloc:examination_member', context_course::instance($course_id))) {
        $params['admin'] = true;
        $params['admin_header'] = true;
    } else if(has_capability('mod/stalloc:chairmember', context_course::instance($course_id))) {
        $params['chairmember'] = true;
        $params['chairmember_header'] = true;
    } else if(has_capability('mod/stalloc:student', context_course::instance($course_id))) {
        $params['student'] = true;
    }

    // Check for ratings in the database.
    $rating_data = $DB->count_records('stalloc_rating', ['course_id' => $course_id, 'cm_id' => $id]);
    if($rating_data > 0) {
        $params['ratings'] = true;
    }

    // Checks for the active element of the header and render it white.
    if($activeElement == PAGE_HOME) {
        $params['home_active'] = 'active';
    } else if($activeElement == PAGE_SETTINGS) {
        $params['settings_active'] = 'font-weight-bold';
    } else if($activeElement == PAGE_CHAIR) {
        $params['chair_active'] = 'font-weight-bold';
    } else if($activeElement == PAGE_STUDENT) {
        $params['student_active'] = 'font-weight-bold';
    } else if($activeElement == PAGE_ALLOCATION) {
        $params['allocation_active'] = 'font-weight-bold';
    } else if($activeElement == PAGE_RATING) {
        $params['rating_active'] = 'font-weight-bold';
    } else if($activeElement == PAGE_CHAIRMEMBER) {
        $params['chairmember_active'] = 'font-weight-bold';
    }

    return $params;
}

/**
 * Initializes the plugin the first time it is used.
 *
 * @param int $id current course module id.
 * @param int $course_id current course id.
 * @param int $instance_id current instance id.
 */
function initialize_stalloc_plugin($id, $course_id, $instance_id) {
    global $DB;

    $declaration_data = $DB->get_records('stalloc_declaration_text');
    if($declaration_data == null) {
        // Create Declaration Text Entry.
        $text = "Lorem ipsum dolor sit amet....";
        $DB->insert_record('stalloc_declaration_text', ['text' => $text, 'active' => 0]);
    }

    // Set the Init Record to 1
    $updateobject  = new stdClass();
    $updateobject->id = $instance_id;
    $updateobject->initialized = 1;
    $DB->update_record('stalloc', $updateobject);
}


/**
 * @param int $id current course module id.
 * @param int $course_id current course id.
 * @param object $instance current database record of this course moduls instance.
 * @throws coding_exception
 */
function process_action_start_distribution($id, $course_id, $instance)
{
    global $DB, $PAGE;
    // Start distribution and call default page after finishing.
    if (has_capability('mod/stalloc:examination_member', context_course::instance($course_id))) {

        // Try to get some more memory, 500 users in 10 groups take about 15mb.
        raise_memory_limit(MEMORY_EXTRA);
        core_php_time_limit::raise();
        // Distribute choices.
        $timeneeded = distrubute_choices($id, $course_id, $instance);
        redirect(new moodle_url($PAGE->url->out()), 'Allocation successfully saved.', null, \core\output\notification::NOTIFY_SUCCESS);

    } else {
        redirect(course_get_url($course_id), "Missing Capability!", null, 'NOTIFY_ERROR');
    }
}


/**
 * distribution of choices for each user
 * take care about max_execution_time and memory_limit
 * @param int $id current course module id.
 * @param int $course_id current course id.
 * @param object $instance current database record of this course moduls instance.
 */
function distrubute_choices($id, $course_id, $instance) {
    global $DB;

    // Update the database -> Allocation is now running!
    $updateobject = new stdClass();
    $updateobject->id = $instance->id;
    $updateobject->allocationstatus = ALLOCATION_RUNNING;
    $updateobject->allocationstarttime = time();
    $DB->update_record('stalloc', $updateobject);

    // Clear all allocations, which are not set directly
    $allocation_data = $DB->get_records('stalloc_allocation', ['course_id' => $course_id, 'cm_id' => $id, 'direct_allocation' => 0]);
    foreach ($allocation_data as $allocation) {
        $updateobject = new stdClass();
        $updateobject->id = $allocation->id;
        $updateobject->chair_id = -1;
        $updateobject->checked = 0;
        $DB->update_record('stalloc_allocation', $updateobject);
    }

    $distributor = new solver_edmonds_karp();
    $timestart = microtime(true);
    $distributor->distribute_users($id, $course_id, $instance);
    $timeneeded = (microtime(true) - $timestart);

    // Update the database -> Allocation is finished!
    $updateobject = new stdClass();
    $updateobject->id = $instance->id;
    $updateobject->allocationstatus = ALLOCATION_FINISHED;
    $DB->update_record('stalloc', $updateobject);

    return $timeneeded;
}


/**
 * Base Mail function of this Plugin
 * @param int $id current course module id.
 * @param int $course_id current course id.
 * @param int $user_id current course module id.
 * @param int $mail_action defines the mail action.
 * @return bool ture if mail was send successfully or false in case of error.
 */
function prepare_student_mail($id, $course_id, $user_id, $mail_action): bool {

    // Check which mail to send.
    if($mail_action == MAIL_STUDENT_RATINGS_SAVED) {
         return rating_saved_mail($id, $course_id, $user_id);
    } else if ($mail_action == MAIL_DIRECT_CHAIR_ACCEPTED) {
        return direct_chair_mail($id, $course_id, $user_id, MAIL_DIRECT_CHAIR_ACCEPTED);
    } else if ($mail_action == MAIL_DIRECT_CHAIR_DECLINED) {
        return direct_chair_mail($id, $course_id, $user_id, MAIL_DIRECT_CHAIR_DECLINED);
    }

    return false;
}

/**
 * Sends an E-Mail to the student to inform about the saved ratings and direct chair selection.
 * @param int $id current course module id.
 * @param int $course_id current course id.
 * @param int $user_id current course module id.
 * @return bool ture if mail was send successfully or false in case of error.
 */
function rating_saved_mail($id, $course_id, $user_id): bool {
    global $CFG, $DB, $OUTPUT;

    // Get the stalloc instance data.
    list ($course, $cm) = get_course_and_cm_from_cmid($id, 'stalloc');
    $instance = $DB->get_record('stalloc', ['id'=> $cm->instance], '*', MUST_EXIST);

    // Get the receiver user.
    $user_data = $DB->get_record('stalloc_student', ['id' => $user_id]);
    $moodle_user_data = $DB->get_record('user', ['id' => $user_data->moodle_user_id]);

    // Get the user allocation, ratings and chair data.
    $allocation_data = $DB->get_record('stalloc_allocation', ['course_id' => $course_id, 'cm_id' => $id, 'user_id' => $user_id]);
    $rating_data = $DB->get_records('stalloc_rating', ['course_id' => $course_id, 'cm_id' => $id, 'user_id' => $user_id], "rating DESC");
    $chair_data = $DB->get_records('stalloc_chair', []);

    // Template Parameters.
    $template_params = [];
    $template_params['student_name'] = $moodle_user_data->firstname ." ". $moodle_user_data->lastname;

    // Save the Phase Endings.
    $template_params['phase1_end'] = date('d.m.Y',$instance->end_phase1);
    $template_params['phase2_end'] = date('d.m.Y',$instance->end_phase2);
    $template_params['phase3_end'] = date('d.m.Y',$instance->end_phase3);

    // Get the direct Chair Name and save it to the template parameter.
    if($allocation_data->chair_id != -1) {
        foreach ($chair_data as $chair) {
            if($chair->id == $allocation_data->chair_id){
                $template_params['direct_chair'] = $chair->name;
                $template_params['direct_chair_info'] = true;
            break;
            }
        }
    } else {
        $template_params['direct_chair'] = 'Nicht vorhanden';
        $template_params['no_direct_chair_info'] = true;
    }

    // Get all chairs of the ratings and save them to the template parameter.
    $priority = 1;
    foreach ($rating_data as $rating) {
        $template_params['ratings'][$priority-1] = new stdClass();
        $template_params['ratings'][$priority-1]->priority = $priority;

        foreach ($chair_data as $chair) {
            if($chair->id == $rating->chair_id) {
                $template_params['ratings'][$priority-1]->chair_name = $chair->name;
                break;
            }
        }
        $priority++;
    }

    // Mail subject.
    $subject =  "WiWi-BOS - Deine Lehrstuhl Prioritäten";
    // Mail HTML message.
    $html_message = $OUTPUT->render_from_template('stalloc/mail/student_rating_mail', $template_params);

    // send-mail.
    $email_sent = email_to_user(
        $moodle_user_data,              // Mail Receiver.
        $CFG->noreplyaddress,           // Mail Sender.
        $subject,                       // Mail Subject.
        html_to_text($html_message),    // Text Message.
        $html_message                   // HTML Message.
    );

    if ($email_sent) {
        // Mail send successfully.
        return true;
    } else {
        // Error! ... Mail was not send.
        return false;
    }
}



/**
 * Sends an E-Mail to the student to inform that the chair has accepted/declined the direct allocation.
 * @param int $id current course module id.
 * @param int $course_id current course id.
 * @param int $user_id current course module id.
 * @return bool ture if mail was send successfully or false in case of error.
 */
function direct_chair_mail($id, $course_id, $user_id, $chair_action): bool {
    global $CFG, $DB, $OUTPUT;

    // Get the stalloc instance data.
    list ($course, $cm) = get_course_and_cm_from_cmid($id, 'stalloc');
    $instance = $DB->get_record('stalloc', ['id'=> $cm->instance], '*', MUST_EXIST);

    // Get the receiver user.
    $user_data = $DB->get_record('stalloc_student', ['id' => $user_id]);
    $moodle_user_data = $DB->get_record('user', ['id' => $user_data->moodle_user_id]);

    // Get the user allocation, ratings and chair data.
    $allocation_data = $DB->get_record('stalloc_allocation', ['course_id' => $course_id, 'cm_id' => $id, 'user_id' => $user_id]);
    $chair_data = $DB->get_record('stalloc_chair', ['id' => $allocation_data->chair_id]);

    // Template Parameters.
    $template_params = [];
    $template_params['student_name'] = $moodle_user_data->firstname ." ". $moodle_user_data->lastname;
    $template_params['phase3_end'] = date('d.m.Y',$instance->end_phase3);
    $template_params['phase4_end'] = date('d.m.Y',$instance->end_phase4);
    $template_params['chair_name'] = $chair_data->name;

    if($chair_action == MAIL_DIRECT_CHAIR_ACCEPTED) {
        $template_params['direct_chair_accepted'] = true;
    } else if ($chair_action == MAIL_DIRECT_CHAIR_DECLINED) {
        $template_params['direct_chair_declined'] = true;
    }

    // Mail subject.
    $subject =  "WiWi-BOS - Deine feste Lehrstuhl Zuweisung wurde bearbeitet";
    // Mail HTML message.
    $html_message = $OUTPUT->render_from_template('stalloc/mail/direct_chair_mail', $template_params);

    // send-mail.
    $email_sent = email_to_user(
        $moodle_user_data,              // Mail Receiver.
        $CFG->noreplyaddress,           // Mail Sender.
        $subject,                       // Mail Subject.
        html_to_text($html_message),    // Text Message.
        $html_message                   // HTML Message.
    );

    if ($email_sent) {
        // Mail send successfully.
        return true;
    } else {
        // Error! ... Mail was not send.
        return false;
    }
}
