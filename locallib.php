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
 * Helper functions for the block_booksearch Plugin
 *
 * @package    block_booksearch
 * @copyright  2022 University of Stuttgart <kasra.habib@iste.uni-stuttgart.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/pdfparser/alt_autoload.php-dist');


/**
 * This function checks if the given course exists and can be accesses by the given user.
 * @param int $courseid The ID of the course the user wants to search in.
 * @param int $userid The ID of the user, that wants to search.
 */
function block_booksearch_validate_course_access($courseid, $userid) {
    global $DB;
    try {
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        if (!can_access_course($course, $userid)) {
            throw new moodle_exception(get_string('error_course_access_denied', 'block_booksearch'));
        }
    } catch (\Throwable $th) {
        debugging($th);
        return [false, null, $th];
    }
    return [true, $course, ''];
}
