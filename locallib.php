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
    if (has_capability('mod/stalloc:examination_member', context_module::instance($instance->id))) {
        $params['admin'] = true;
        $params['admin_header'] = true;
    } else if(has_capability('mod/stalloc:chairmember', context_module::instance($instance->id))) {
        $params['chairmember'] = true;
        $params['chairmember_header'] = true;
    } else if(has_capability('mod/stalloc:student', context_module::instance($instance->id))) {
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

    // Create Declaration Text Entry for this Module.
    $text = "Lorem ipsum dolor sit amet....";
    $DB->insert_record('stalloc_declaration_text', ['course_id' => $course_id, 'cm_id' => $id, 'text' => $text, 'active' => 0]);
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
    if (has_capability('mod/stalloc:examination_member', context_module::instance($instance->id))) {

        // Try to get some more memory, 500 users in 10 groups take about 15mb.
        raise_memory_limit(MEMORY_EXTRA);
        core_php_time_limit::raise();
        // Distribute choices.
        $timeneeded = distrubute_choices($id, $course_id, $instance);
        redirect(new moodle_url($PAGE->url->out()), 'Allocation successfully saved.', null, \core\output\notification::NOTIFY_SUCCESS);

        //$stalloc_data = $DB->get_record('stalloc', ['id' => $instance->id]);

        /*if ($stalloc_data->allocationstatus == ALLOCATION_RUNNING) {
            // Don't run, if an instance is already running.
            redirect(new moodle_url('/mod/stalloc/allocation.php', ['id' => $id]), 'The Allocation is currently running, please wait!', null, \core\output\notification::NOTIFY_INFO);
        } else {
            //$this->clear_distribute_unallocated_tasks();

            // Try to get some more memory, 500 users in 10 groups take about 15mb.
            raise_memory_limit(MEMORY_EXTRA);
            core_php_time_limit::raise();

            // Distribute choices.
            $timeneeded = distrubute_choices($id, $course_id, $instance);

            redirect(new moodle_url($PAGE->url->out()), 'Allocation successfully saved.', null, \core\output\notification::NOTIFY_SUCCESS);
        }*/

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