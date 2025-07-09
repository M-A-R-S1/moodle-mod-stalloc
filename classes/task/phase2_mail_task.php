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
 * Prints an instance of mod_stalloc phase 2 mail task class page
 *
 * @package     mod_stalloc
 * @copyright   2025 Marc-André Schmidt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_stalloc\task;

defined('MOODLE_INTERNAL') || die();


class phase2_mail_task extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('phase2_mail_task', 'mod_stalloc');
    }

    public function execute() {
        global $DB;

        // Get the current time.
        $current_time = time();

        // SQL query for the stalloc database entries.
        $sql = "SELECT * FROM {stalloc} WHERE end_phase2 IS NOT NULL AND end_phase2 <= ?";
        $records_to_process = $DB->get_records_sql($sql, [$current_time]);

        // Go through all stalloc records.
        foreach ($records_to_process as $record) {
            // Get the specific course and activity ids.
            $cm = get_coursemodule_from_instance('stalloc', $record->id);
            $course_id = $cm->course;
            $activity_id = $cm->id;

            // Check for all allocations which have been allocated directly and did not received an email yet.
            $allocation_data = $DB->get_records('stalloc_allocation', ['course_id' => $course_id, 'cm_id' => $activity_id, 'checked' => 1, 'direct_allocation' => 1, 'direct_allocation_mail' => 0]);
            // Walk through all allocations.
            foreach ($allocation_data as $allocation) {
                // send confirmation e-mail.
                if($this->send_direct_allocation_mail($activity_id, $course_id, $allocation->user_id, 1)){
                    // If the mail was sent, delete all rating records of this student and update the allocation record
                    $DB->delete_records('stalloc_rating', ['course_id' => $course_id, 'cm_id' => $activity_id, 'user_id' => $allocation->user_id]);
                    $allocation->direct_allocation_mail = 1;
                    $DB->update_record('stalloc_allocation', $allocation);
                }
            }

            // Check for all allocations which have not been accepted/declined.
            $untouched_allocations = $DB->get_records('stalloc_allocation', ['course_id' => $course_id, 'cm_id' => $activity_id, 'checked' => 0, 'direct_allocation' => 1, 'direct_allocation_mail' => 0]);
            // Walk through all untouched allocations.
            foreach ($untouched_allocations as $allocation) {
                // send confirmation e-mail.
                if ($this->send_direct_allocation_mail($activity_id, $course_id, $allocation->user_id, 2)) {
                    // If the mail was sent, update the database entry. -> Reset the Allocation, so that these students will be randomly allocated later.
                    $allocation->checked = 0;
                    $allocation->direct_allocation = 0;
                    $allocation->chair_id = -1;
                    $allocation->direct_allocation_mail = 1;
                    $DB->update_record('stalloc_allocation', $allocation);
                }
            }

            // Check for all allocations which have been declined.
            $declined_allocations = $DB->get_records('stalloc_allocation', ['course_id' => $course_id, 'cm_id' => $activity_id, 'checked' => -1, 'direct_allocation' => 1, 'direct_allocation_mail' => 0]);
            // Walk through all declined allocations.
            foreach ($declined_allocations as $allocation) {
                // send confirmation e-mail.
                if ($this->send_direct_allocation_mail($activity_id, $course_id, $allocation->user_id, 2)) {
                    // If the mail was sent, update the database entry. -> Reset the Allocation, so that these students will be randomly allocated later.
                    $allocation->checked = 0;
                    $allocation->direct_allocation = 0;
                    $allocation->chair_id = -1;
                    $allocation->direct_allocation_mail = 1;
                    $DB->update_record('stalloc_allocation', $allocation);
                }
            }

        }
    }

    /**
     * Sends an E-Mail to the student to inform that the chair has accepted/declined the direct allocation.
     * @param int $id current course module id.
     * @param int $course_id current course id.
     * @param int $user_id current course module id.
     * @param int $chair_action defines which mail to send [0 = decline email | 1 = accept email]
     * @return bool ture if mail was send successfully or false in case of error.
     */
    private function send_direct_allocation_mail($id, $course_id, $user_id, $chair_action): bool {
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
        $template_params['phase3_end'] = date('d.m.Y H:i',$instance->end_phase3);
        $template_params['phase4_end'] = date('d.m.Y H:i',$instance->end_phase4);
        $template_params['chair_name'] = $chair_data->name;

        if($chair_action == 1) {
            $template_params['direct_chair_accepted'] = true;
        } else if ($chair_action == 2) {
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

}
