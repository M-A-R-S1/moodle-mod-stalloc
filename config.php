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
 * Holds commonly used variables of the mod_stalloc.
 *
 * @package     mod_stalloc
 * @copyright   2025 Marc-André Schmidt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('PAGE_HOME', 0);
define('PAGE_SETTINGS', 1);
define('PAGE_CHAIR', 2);
define('PAGE_STUDENT', 3);
define('PAGE_ALLOCATION', 4);
define('PAGE_RATING', 5);
define('PAGE_CHAIRMEMBER', 6);

define('STUDENT_BUFFER', 0.10);
define('MAX_STUDENT_ALLOCATIONS_PERCENT', 0.334);

define('ALLOCATION_NOTSTARTED', 0);
define('ALLOCATION_RUNNING', 1);
define('ALLOCATION_FINISHED', 2);

define('MAIL_STUDENT_RATINGS_SAVED', 0);
define('MAIL_DIRECT_CHAIR_ACCEPTED', 1);
define('MAIL_DIRECT_CHAIR_DECLINED', 2);
