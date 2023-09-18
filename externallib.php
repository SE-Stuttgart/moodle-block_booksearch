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
 * @package    block_slidefinder
 * @copyright  2022 Universtity of Stuttgart <kasra.habib@iste.uni-stuttgart.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");

/**
 * External class for the slidefinder block.
 *
 * Let's a webservice use the slidefinder functionality.
 */
class block_slidefinder_external extends external_api {
    /**
     * Returns description of method parameter
     * @return external_function_parameters
     */
    public static function get_searched_locations_parameters() {
        return new external_function_parameters(
            array(
                'userid' => new external_value(
                    PARAM_INT,
                    'Id of the user using the webservice',
                    VALUE_REQUIRED
                ),
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
                    'Number of words surrounding the found query word in each direction'
                )
            )
        );
    }

    /**
     * Get all occurences, their context and a link to the chapter in the eligable PDF-Book lectures in the given course.
     *
     * @param int $userid id of the user who initiates the search
     * @param int $courseid id of the course to search in
     * @param string $searchstring the string to search for
     * @param int $contextlength the size of the context snippet on each side of the found $seach_string occurences in words
     *
     * @return string json encoded array of arrays holding the 'filename', 'page_number', 'book_chapter_url' and 'context'
     * of each chapter/pdf-page the $searchterm was found
     * @return string return '' if there is an error
     */
    public static function get_searched_locations($userid, $courseid, $searchstring, $contextlength) {
        global $CFG, $DB;
        require_once(__DIR__ . '/locallib.php');

        // Validate parameter.
        $params = self::validate_parameters(
            self::get_searched_locations_parameters(),
            array(
                'userid'               => $userid,
                'courseid'             => $courseid,
                'searchstring'         => $searchstring,
                'contextlength'        => $contextlength
            )
        );

        $transaction = $DB->start_delegated_transaction();

        try {
            // User.
            if (!$user = $DB->get_record('user', array('id' => $userid))) {
                throw new moodle_exception(get_string('error_user_not_found', 'block_slidefinder'));
            }
            // Course.
            if (!$course = $DB->get_record('course', array('id' => $courseid))) {
                throw new moodle_exception(get_string('error_course_not_found', 'block_slidefinder'));
            }
            // Does the user have access to the course?
            if (!can_access_course($course, $user)) {
                throw new moodle_exception(get_string('error_course_access_denied', 'block_slidefinder'));
            }
        } catch (\Throwable $th) {
            debugging($th);
            return '';
        }

        [$chapters, $misconfiguredchapters] =
            block_slidefinder_get_content_as_chapters_for_all_book_pdf_matches_from_course($courseid, $userid);

        // Get Search Results & Context for PDFs.
        $results = array();
        foreach ($chapters as $chapter) {
            $result = self::search_content($chapter, $searchstring, $contextlength);
            if ($result) {
                $results[] = [
                    'filename' => $result->filename,
                    'page_number' => $result->page,
                    'book_chapter_url' => $result->bookurl,
                    'context_snippet' => $result->context
                ];
            }
        }

        // Return.
        return json_encode($results, JSON_UNESCAPED_SLASHES);
    }

    /**
     * Returns description of the method return values
     * @return external_value
     */
    public static function get_searched_locations_returns() {
        return new external_value(PARAM_TEXT, 'Search results', VALUE_REQUIRED);
    }

    /**
     * Searches for the $searchterm in the given $page->content and
     * returns the page with a $page->context context snippet if it was found. returns null if not.
     *
     * @param stdClass $page object that holds the $page->content and gets returned containing the $page->context
     * @param string $searchterm the string to seach for in the $page->content
     * @param int $contextlength word count returned as context snippet on each side of the found $searchterm
     *
     * @return stdClass|null the given $page object with the additional $page->context or null if nothing was found
     */
    private static function search_content($page, $searchterm, $contextlength) {
        $content = ' ' . $page->content . ' ';

        // Is the searched word in this page?
        if (!stristr($content, $searchterm)) {
            return;
        }

        // Create a String with all occurences & context.
        $context = '';

        $index = self::index_of($content, $searchterm, 0);
        $finalendindex = -1;

        // For all $searchterm occurances.
        while (0 <= $index) {
            $startindex = $index;
            $tempendindex = $index;

            // Get Context Words.
            for ($i = 0; $i < $contextlength; $i++) {
                $startindex = self::lastindex_of($content, ' ', $startindex - 1);
                $tempendindex = self::index_of($content, ' ', $tempendindex + 1);
                if ($tempendindex < $startindex) {
                    $tempendindex = strlen($content) - 1;
                }
            }

            // Do the contexti have overlap or are they apart?
            if ($startindex > $finalendindex) {
                $context .= '...';
                $context .= self::substring($content, $startindex, $tempendindex);
            } else {
                $context .= self::substring($content, $finalendindex + 1, $tempendindex);
            }

            // Next $searchterm occurance.
            $finalendindex = $tempendindex;
            $index = self::index_of($content, $searchterm, $index + 1);
        }
        $context .= '...';

        $page->context = $context;
        return $page;
    }

    /**
     * Alternate function to PHPs substr() to put it more in line with the javascript equivalent.
     * Returns the substring of a given string with start and end index given.
     *
     * @param string $string source string to  extract from
     * @param int $start the starting index for the extraction
     * @param int $end the ending index for the extraction
     *
     * @return string the extracted substring
     */
    private static function substring($string, $start, $end) {
        $start = min($start, $end, strlen($string) - 1);
        $end = min($end, strlen($string) - 1);

        $start = max($start, 0);
        $end = max($end, $start, 0);

        $sub = substr($string, $start, $end - $start);
        return $sub;
    }

    /**
     * Alternate function to PHPs stripos() to put it more in line with the javascript equivalent.
     * Left to right search returns the index of the first occurence of the needle in the given haystack starting at index offset.
     *
     * @param string $haystack string to search in
     * @param string $needle string to search for
     * @param int $offset starting index of right-wards search
     *
     * @return int index of the first occurence found or -1 if nothing was found
     */
    private static function index_of($haystack, $needle, $offset) {
        $offset = min(strlen($haystack) - 1, $offset);
        $offset = max(0, $offset);

        $index = stripos($haystack, $needle, $offset);
        if ($index === false) {
            return -1;
        }
        return $index;
    }

    /**
     * Alternate function to PHPs strripos() to put it more in line with the javascript equivalent.
     * Right to left search returns the index of the first occurence of the needle in the given haystack starting at index offset.
     *
     * @param string $haystack string to search in
     * @param string $needle string to search for
     * @param int $offset starting index of left-wards search
     *
     * @return int index of the first occurence found or -1 if nothing was found
     */
    private static function lastindex_of($haystack, $needle, $offset) {
        $offset = min(strlen($haystack) - 1, $offset);
        $offset = max(0, $offset);

        $index = strripos($haystack, $needle, $offset - strlen($haystack));
        if ($index === false) {
            return -1;
        }
        return $index;
    }
}
