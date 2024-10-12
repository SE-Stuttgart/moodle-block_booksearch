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
 * Function for the WebService
 *
 * @package    block_booksearch
 * @copyright  2022 University of Stuttgart <kasra.habib@iste.uni-stuttgart.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_booksearch\external;

use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;

use block_booksearch\local\data;
use block_booksearch\local\search;
use context_course;
use invalid_parameter_exception;
use stdClass;

/**
 * External class for the booksearch block.
 *
 * Let's a webservice use the booksearch functionality.
 */
class search_book_content extends external_api {

    /**
     * Returns description of method parameter
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters(
            [
                'courseid' => new external_value(
                    PARAM_INT,
                    get_string('parameter_course_id', 'block_booksearch'),
                    VALUE_REQUIRED
                ),
                'searchstring' => new external_value(
                    PARAM_TEXT,
                    get_string('parameter_search_string', 'block_booksearch'),
                    VALUE_REQUIRED
                ),
                'contextlength' => new external_value(
                    PARAM_INT,
                    get_string('parameter_context_length', 'block_booksearch'),
                    VALUE_DEFAULT,
                    0
                ),
            ]
        );
    }

    /**
     * Returns description of the method return values.
     * @return external_value
     */
    public static function execute_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'filename' => new external_value(PARAM_TEXT, get_string('parameter_file_name', 'block_booksearch')),
                'pagenumber' => new external_value(PARAM_INT, get_string('parameter_page_number', 'block_booksearch')),
                'bookchapterurl' => new external_value(PARAM_RAW, get_string('parameter_book_chapter_url', 'block_booksearch')),
                'contextsnippet' => new external_value(PARAM_RAW, get_string('parameter_context_snippet', 'block_booksearch')),
            ])
        );
    }

    /**
     * Get all occurences, their context and a link to the chapter in the eligable PDF-Book lectures in the given course.
     * @param int $courseid id of the course to search in
     * @param string $searchstring the string to search for
     * @param int $contextlength the size of the context snippet on each side of the found $search_string occurences in words
     * @return array [stdClass => filename, pagenumber, bookchapterurl, contextsnippet]
     * array of objects each describing one search term occurance with text snippet and location data.
     */
    public static function execute($courseid, $searchstring, $contextlength) {
        global $CFG, $DB;
        require_once(__DIR__ . '/../../locallib.php');

        // Validate parameter.
        $params = self::validate_parameters(
            self::execute_parameters(),
            [
                'courseid'             => $courseid,
                'searchstring'         => $searchstring,
                'contextlength'        => $contextlength,
            ]
        );

        // If an exception is thrown in the below code, all DB queries in this code will be rollback.
        $transaction = $DB->start_delegated_transaction();

        $courseid = $params['courseid'];
        $searchstring = $params['searchstring'];
        $contextlength = $params['contextlength'];

        // Check for valid context length.
        if ($contextlength < 0) {
            throw new invalid_parameter_exception(get_string('invalid_context_length', 'block_booksearch'));
        }

        // Check permissions.
        list($isvalid, $course, $error) = block_booksearch_validate_course_access($courseid, $USER->id);
        if (!$isvalid) {
            throw new invalid_parameter_exception(get_string('invalid_course', 'block_booksearch'));
        }

        $coursecontext = context_course::instance($course->id);
        self::validate_context($coursecontext);
        require_capability('block/booksearch:searchservice', $coursecontext);

        // Get all searchable content.
        list($content, $misconfiguredcontentinfo) = data::get_course_content($course->id);

        // Get Search Results & Context for PDFs.
        $data = search::get_search_results($content, $searchstring, $contextlength);

        // Format results.
        $results = [];
        foreach ($data as $file) {
            foreach ($file as $chapter) {
                $result = new stdClass();
                $result->filename = $chapter->filename;
                $result->pagenumber = $chapter->page;
                $result->bookchapterurl = $chapter->bookurl;
                $result->contextsnippet = $chapter->context;
                $results[] = $result;
            }
        }

        // Return.
        return $results;
    }
}
