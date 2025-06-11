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
defined('MOODLE_INTERNAL') || die();

/**
 * Saves a new instance of the mod_stalloc into the database.
 *
 * Given an object containing all the necessary data. This function will create a new instance and return the id
 * number of the instance.
 *
 * @param stdClass $data An object from the form.
 * @param mod_stalloc_mod_form $mform The form.
 * @return int The id of the newly inserted record.
 */
function stalloc_add_instance($data, $mform = null) {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;

    return $DB->insert_record('stalloc', $data);
}
/**
 * Updates an instance of the mod_stalloc in the database.
 *
 * Given an object containing all the necessary data (defined in mod_form.php),
 * this function will update an existing instance with new data.
 *
 * @param stdClass $data An object from the form in mod_form.php.
 * @param mod_stalloc_mod_form $mform The form.
 * @return bool True if successful, false otherwise.
 */
function stalloc_update_instance($data, $mform) {
    global $DB;

    $data->timemodified = time();
    $data->id = $data->instance;

    return $DB->update_record('stalloc', $data);
}

/**
 * Removes an instance of the mod_stalloc from the database.
 *
 * @param int $id Id of the module instance.
 * @return bool True if successful, false on failure.
 */
function stalloc_delete_instance($id) {
    global $DB;

    if (!$stalloc = $DB->get_record('stalloc', array('id'=>$id))) {
        return false;
    }
    $DB->delete_records('stalloc', array('id'=>$id));

    return true;
}

/**
 * Return the list if Moodle features this module supports
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function stalloc_supports($feature) {
    switch($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_ASSIGNMENT;
        default:
            return null;
    }
}