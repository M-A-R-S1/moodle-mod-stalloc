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
 * JavaScript File for the basic_settings.php file
 *
 * @module     mod_stalloc/basic_settings
 * @copyright  2025 Marc-André Schmidt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


// import {get_string as getString} from 'core/str';

export const init = () => {

    // let deleteIcon = '';

    (async () => {
        // deleteIcon = await getString('icon_delete', 'mod_rubquiz');
    })();

    /**
     * This function removes or adds the 'disabled' parameter to the declaration textfield.
     * This function is called if a selection is changed.
     *
     * @param {integer} value The value of the declaration checkbox
     */
    function changeDelarationCheckbock(value) {
        if(value == 0) {
            document.getElementById('text_declaration').disabled = true;
        } else if(value == 1) {
            document.getElementById('text_declaration').disabled = false;
        }
    }

    // Create event listeners
    // DECLARATION CHECKBOX
    document.getElementById('checkbox_declaration').addEventListener(
        'change', function () {changeDelarationCheckbock(this.checked);}, false);
};



