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
 * The chair member edit mod_stalloc configuration form.
 *
 * @package     mod_stalloc
 * @copyright   2025 Marc-André Schmidt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/stalloc/lib.php');

/**
 * Module instance settings form.
 *
 * @package     mod_stalloc
 * @copyright   2025 Marc-André Schmidt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_stalloc_form_chairmember_edit extends moodleform {

    /**
     * Defines forms elements
     */
    function definition() {
        global $CFG, $DB, $OUTPUT, $COURSE;
        $id = required_param('id', PARAM_INT);
        $chairmember_id = required_param('chairmember_id', PARAM_INT);

        $mform =& $this->_form;
        $context = \context_course::instance($COURSE->id);

        // Get Chair information from Database.
        $chair_member_data = $DB->get_record('stalloc_chair_member', ['id' => $chairmember_id]);
        $chair_data = $DB->get_records('stalloc_chair', ['cm_id' => $id, 'course_id' => $chair_member_data->course_id], "name ASC");

        $chair_selection = [];
        $default = 0;

        foreach ($chair_data as $chair) {
            $chair_selection[$chair->id] = $chair->name;

            if($chair->id == $chair_member_data->chair_id) {
                $default = $chair->id;
            }
        }

        // Dropdown element.
        $mform->addElement('select', 'chair_id', "Lehrstuhl", $chair_selection);
        $mform->addRule('chair_id', null, 'required');
        $mform->setType('chair_id', PARAM_INT);
        $mform->setDefault('chair_id', $default);

        // Basic Stuff.
        $mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'chairmember_id', $chairmember_id);
        $mform->setType('chairmember_id', PARAM_INT);
        $this->add_action_buttons(true, "Änderungen speichern");
    }
}
