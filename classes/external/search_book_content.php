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

require_once(__DIR__ . '/../../../../config.php');
require_once(__DIR__ . '/../../locallib.php');

use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;

use block_booksearch\data\data;
use block_booksearch\search\search;
use context_course;
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
                    'Id of the course the user wants to access',
                    VALUE_REQUIRED
                ),
                'searchstring' => new external_value(
                    PARAM_TEXT,
                    'String to search for in the course',
                    VALUE_REQUIRED
                ),
                'contextlength' => new external_value(
                    PARAM_INT,
                    'Number of words surrounding the found query word in each direction',
                    VALUE_DEFAULT,
                    1
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
                'filename' => new external_value(PARAM_TEXT, 'name of the pdf file that has a matching book.'),
                'pagenumber' => new external_value(PARAM_INT, 'page number this searched occurance happens in filename book.'),
                'bookchapterurl' => new external_value(PARAM_RAW, 'url to pagenumber book chapter.'),
                'contextsnippet' => new external_value(PARAM_RAW, 'text snippet around the occurance.'),
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

        // Check permissions.
        list($isvalid, $course, $error) = block_booksearch_validate_course_access($courseid, $USER->id);
        if (!$isvalid) {
            return '';
        }

        $coursecontext = context_course::instance($course->id);
        self::validate_context($coursecontext);
        require_capability('block/booksearch:searchservice', $coursecontext);

        // Get all searchable content.
        list($content, $misconfiguredcontentinfo) = data::get_course_content($course->id);

        // Get Search Results & Context for PDFs.
        $data = search::get_search_results($content, $searchstring, max(1, $contextlength));

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
