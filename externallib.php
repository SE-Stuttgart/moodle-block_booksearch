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
 * @copyright  2022 Universtity of Stuttgart <kasra.habib@iste.uni-stuttgart.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");

/**
 * External class for the booksearch block.
 *
 * Let's a webservice use the booksearch functionality.
 */
class block_booksearch_external extends external_api {
    /**
     * Returns description of method parameter
     * @return external_function_parameters
     */
    public static function get_searched_locations_parameters() {
        return new external_function_parameters(
            [
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
                ),
            ]
        );
    }

    /**
     * Get all occurences, their context and a link to the chapter in the eligable PDF-Book lectures in the given course.
     *
     * @param int $userid id of the user who initiates the search
     * @param int $courseid id of the course to search in
     * @param string $searchstring the string to search for
     * @param int $contextlength the size of the context snippet on each side of the found $search_string occurences in words
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
            [
                'userid'               => $userid,
                'courseid'             => $courseid,
                'searchstring'         => $searchstring,
                'contextlength'        => $contextlength,
            ]
        );

        try {
            // User.
            if (!$user = $DB->get_record('user', ['id' => $userid])) {
                throw new moodle_exception(get_string('error_user_not_found', 'block_booksearch'));
            }
            // Course.
            if (!$course = $DB->get_record('course', ['id' => $courseid])) {
                throw new moodle_exception(get_string('error_course_not_found', 'block_booksearch'));
            }
            // Does the webservice and user have access to the course?
            if (!can_access_course($course) && !can_access_course($course, $user)) {
                throw new moodle_exception(get_string('error_course_access_denied', 'block_booksearch'));
            }
        } catch (\Throwable $th) {
            debugging($th);
            return '';
        }

        $coursecontext = context_course::instance($course->id);
        self::validate_context($coursecontext);

        // Get all searchable content.
        $sections = block_booksearch_get_all_content_of_course_as_sections_with_metadata($courseid, $userid)[0];

        // Get Search Results & Context for PDFs.
        $data = [];
        foreach ($sections as $section) {
            $data = self::search_content($data, $section, $searchstring, $contextlength);
        }

        // Format results.
        $results = [];
        foreach ($data as $file) {
            foreach ($file as $chapter) {
                $results[] = [
                    'filename' => $chapter->filename,
                    'page_number' => $chapter->page,
                    'book_chapter_url' => $chapter->bookurl,
                    'context_snippet' => $chapter->context,
                ];
            }
        }

        // Return.
        return json_encode($results, JSON_UNESCAPED_SLASHES);
    }

    /**
     * Returns description of the method return values.
     * @return external_value
     */
    public static function get_searched_locations_returns() {
        return new external_value(PARAM_TEXT, 'Search results', VALUE_REQUIRED);
    }

    /**
     * Searches for the $searchterm in the given $section->content and populates the given $results array.
     *
     * @param array $results the results so far.
     * @param stdClass $section object that holds the $section->content.
     * @param string $searchterm the string to seach for in the $section->content.
     * @param int $contextlength word count returned as context snippet on each side of the found $searchterm.
     *
     * @return array $results returns the updated $results array with the new data.
     */
    private static function search_content($results, $section, $searchterm, $contextlength) {
        $content = $section->content;

        // Is the searched word in this section?
        if (!stristr($content, $searchterm)) {
            return $results;
        }

        // Split the text into words.
        $words = preg_split('/\s+/', $content);

        $snippets = [];
        $snippetindex = 0;

        // Iterate through the words to find occurrences of the search word.
        // Save the context snippet indices.
        for ($i = 0; $i < count($words); $i++) {
            if (stristr($words[$i], $searchterm)) {
                // Calculate start and end indices for the context.
                $start = max(0, $i - $contextlength);
                $end = min(count($words) - 1, $i + $contextlength);

                if ($snippetindex > 0 && $start - $snippets[$snippetindex - 1][1] < $contextlength) {
                    $snippets[$snippetindex - 1][1] = $end;
                } else {
                    $snippets[] = [$start, $end];
                    $snippetindex++;
                }
            }
        }

        // Turn the snippet indices into actual text snippets.
        for ($i = 0; $i < count($snippets); $i++) {
            [$start, $end] = $snippets[$i];
            // Extract the context around the search word.
            $snippet = implode(' ', array_slice($words, $start, $end - $start + 1));

            // Add "..." at the beginning if not at the start of the text.
            if ($start > 0) {
                $snippet = '...' . $snippet;
            }

            // Add "..." at the end if not at the end of the text.
            if ($end < count($words) - 1) {
                $snippet .= '...';
            }

            // Update snippet with text.
            $snippets[$i] = $snippet;
        }

        // Create a String with all occurences & context.
        $context = implode(' ... ', $snippets);
        $section->context = $context;

        if (!array_key_exists($section->filename, $results)) {
            $results[$section->filename] = [];
        }
        if (!array_key_exists($section->page, $results[$section->filename])) {
            $results[$section->filename][$section->page] = $section;
        } else {
            $results[$section->filename][$section->page]->context .= " ... " . $section->context;
        }

        return $results;
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
