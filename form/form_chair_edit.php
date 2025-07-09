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
 * The chair edit mod_stalloc configuration form.
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
class mod_stalloc_form_chair_edit extends moodleform {

    /**
     * Defines forms elements
     */
    function definition() {
        global $CFG, $DB, $OUTPUT, $COURSE;

        $mform =& $this->_form;
        $context = \context_course::instance($COURSE->id);

        // Get Chair information from Database.
        $chair_data = $DB->get_record('stalloc_chair', ['id' => required_param('chair_id', PARAM_INT)]);

        // Name element.
        $mform->addElement('text', 'chair_name', "Lehrstuhl Name");
        $mform->addRule('chair_name', null, 'required');
        $mform->setType('chair_name', PARAM_TEXT);
        $mform->setDefault('chair_name', $chair_data->name);
        //$mform->addHelpButton('chair_name', 'category_name_label', 'mod_stalloc');

        // Holder element.
        $mform->addElement('text', 'chair_holder', "Inhaber");
        $mform->addRule('chair_holder', null, 'required');
        $mform->setType('chair_holder', PARAM_TEXT);
        $mform->setDefault('chair_holder', $chair_data->holder);

        // Flexnow ID element.
        $mform->addElement('text', 'flexnow_id', "Flexnow-ID");
        $mform->addRule('flexnow_id', null, 'required');
        $mform->setType('flexnow_id', PARAM_INT);
        $mform->setDefault('flexnow_id', $chair_data->flexnow_id);

        // Contact Name element.
        $mform->addElement('text', 'contact_name', "Kontakt Name");
        $mform->addRule('contact_name', null, 'required');
        $mform->setType('contact_name', PARAM_TEXT);
        $mform->setDefault('contact_name', $chair_data->contact_name);

        // Contact Phone element.
        $mform->addElement('text', 'contact_phone', "Kontakt Telefonnummer");
        $mform->addRule('contact_phone', null, 'required');
        $mform->setType('contact_phone', PARAM_TEXT);
        $mform->setDefault('contact_phone', $chair_data->contact_phone);

        // Contact Mail element.
        $mform->addElement('text', 'contact_mail', "Kontakt E-Mail");
        $mform->addRule('contact_mail', null, 'required');
        $mform->setType('contact_mail', PARAM_TEXT);
        $mform->setDefault('contact_mail', $chair_data->contact_mail);

        // Distribution Key element.
        $mform->addElement('text', 'distribution_key', "Verteilungsschlüssel");
        $mform->addRule('distribution_key', null, 'required');
        $mform->setType('distribution_key', PARAM_FLOAT);
        $mform->setDefault('distribution_key', "0.00");
        $mform->setDefault('distribution_key', $chair_data->distribution_key);

        // Active element.
        $mform->addElement('checkbox', 'active', "Aktiv");
        $mform->setType('active', PARAM_BOOL);
        $mform->setDefault('active', $chair_data->active);

        // Basic Stuff.
        $mform->addElement('hidden', 'id', required_param('id', PARAM_INT));
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'chair_id', required_param('chair_id', PARAM_INT));
        $mform->setType('chair_id', PARAM_INT);
        $this->add_action_buttons(true, "Änderungen speichern");
    }
}
