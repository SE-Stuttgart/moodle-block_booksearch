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
 * @copyright  University of Stuttgart
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");

class block_slidefinder_external extends external_api
{
    /**
     * Returns description of method parameter
     * @return external_function_parameters
     */
    public static function get_searched_locations_parameters()
    {
        return new external_function_parameters(
            array(
                'search_string' => new external_value(
                    PARAM_TEXT,
                    'String to search for in the course',
                    VALUE_REQUIRED
                ),
                'course_id' => new external_value(
                    PARAM_INT,
                    'Id of the course',
                    VALUE_REQUIRED
                ),
                'context_length' => new external_value(
                    PARAM_INT,
                    'Number of words surrounding the found query word in each direction'
                )
            )
        );
    }

    /**
     * Get all occurences, their context and a link to the chapter of $search_string in the eligable $PDF-Book lectures in the given course.
     *
     * @param string $search_string the string to search for
     * @param int $course_id id of the course to search in
     * @param int $context_length the size of the context snippet on each side of the found @param $seach_string occurences in words
     *
     * @return string json encoded array of arrays holding the 'filename', 'page_number', 'book_chapter_url' and 'context' of each chapter/pdf-page the $search_term was found
     * @return string return '' the $course_id was incorrect
     */
    public static function get_searched_locations($search_string, $course_id, $context_length)
    {
        global $CFG, $DB;
        require_once(__DIR__ . '/locallib.php');
        $context = context_course::instance($course_id);

        // Validate parameter
        $params = self::validate_parameters(
            self::get_searched_locations_parameters(),
            array(
                'search_string'         => $search_string,
                'course_id'             => $course_id,
                'context_length'        => $context_length
            )
        );

        $transaction = $DB->start_delegated_transaction();

        // Get Course
        if (!$course = $DB->get_record('course', array('id' => $course_id))) {
            return '';
        }
        $context = context_course::instance($course_id);

        // Get all Book-PDF matches
        $matches = block_lrf_get_all_book_pdf_matches_from_course($course);

        // Get PDF Content for matches
        $chapters = array();
        foreach ($matches as $match) {
            $chapters = array_merge($chapters, block_lrf_get_content_as_chapters($match));
        }

        // Get Search Results & Context for PDFs
        $results = array();
        foreach ($chapters as $chapter) {
            $result = self::lrf_search_content($chapter, $search_string, $context_length);
            if ($result) $results[] = [
                'filename' => $result->filename,
                'page_number' => $result->page,
                'book_chapter_url' => $result->book_url,
                'context_snippet' => $result->context
            ];
        }

        // Return
        return json_encode($results, JSON_UNESCAPED_SLASHES);
    }

    /**
     * Returns description of the method return values
     * @return external_value
     */
    public static function get_searched_locations_returns()
    {
        return new external_value(PARAM_TEXT, 'Search results', VALUE_REQUIRED);
    }

    /**
     * Searches for the $search_term in the given $page->content and returns the page with a $page->context context snippet if it was found. returns null if not.
     *
     * @param stdClass $page object that holds the $page->content and gets returned containing the $page->context
     * @param string $search_term the string to seach for in the $page->content
     * @param int $context_length word count returned as context snippet on each side of the found $search_term
     *
     * @return stdClass|null the given @param $page object with the additional $page->context or null if nothing was found
     */
    private static function lrf_search_content($page, $search_term, $context_length)
    {
        $content = ' ' . $page->content . ' ';

        // Is the searched word in this page?
        if (!stristr($content, $search_term)) return;

        // Create a String with all occurences & context
        $context = '';

        $index = self::indexOf($content, $search_term, 0);
        $index_end = -1;

        // For all $search_term occurances
        while (0 <= $index) {
            $i_start = $index;
            $i_end = $index;

            // Get Context Words
            for ($i = 0; $i < $context_length; $i++) {
                $i_start = self::lastIndexOf($content, ' ', $i_start - 1);
                $i_end = self::indexOf($content, ' ', $i_end + 1);
                if ($i_end < $i_start) {
                    $i_end = strlen($content) - 1;
                }
            }

            // Do the contexti have overlap or are they apart?
            if ($i_start > $index_end) {
                $context .= '...';
                $context .= self::substring($content, $i_start, $i_end);
            } else {
                $context .= self::substring($content, $index_end + 1, $i_end);
            }

            // Next $search_term occurance
            $index_end = $i_end;
            $index = self::indexOf($content, $search_term, $index + 1);
        }
        $context .= '...';

        $page->context = $context;
        return $page;
    }

    /**
     * Alternate function to PHPs substr() to put it more in line with the javascript equivalent. Returns the substring of a given string with start and end index given.
     *
     * @param string $string source string to  extract from
     * @param int $start the starting index for the extraction
     * @param int $end the ending index for the extraction
     *
     * @return string the extracted substring
     */
    private static function substring($string, $start, $end)
    {
        $start = min($start, $end, strlen($string) - 1);
        $end = min($end, strlen($string) - 1);

        $start = max($start, 0);
        $end = max($end, $start, 0);

        $sub = substr($string, $start, $end - $start);
        return $sub;
    }

    /**
     * Alternate function to PHPs stripos() to put it more in line with the javascript equivalent. Left to right search returning the index of the first occurence of the needle in the given haystack starting at index offset.
     *
     * @param string $haystack string to search in
     * @param string $needle string to search for
     * @param int $offset starting index of right-wards search
     *
     * @return int index of the first occurence found or -1 if nothing was found
     */
    private static function indexOf($haystack, $needle, $offset)
    {
        $offset = min(strlen($haystack) - 1, $offset);
        $offset = max(0, $offset);

        $index = stripos($haystack, $needle, $offset);
        if ($index === false) return -1;
        return $index;
    }

    /**
     * Alternate function to PHPs strripos() to put it more in line with the javascript equivalent. Right to left search returning the index of the first occurence of the needle in the given haystack starting at index offset.
     *
     * @param string $haystack string to search in
     * @param string $needle string to search for
     * @param int $offset starting index of left-wards search
     *
     * @return int index of the first occurence found or -1 if nothing was found
     */
    private static function lastIndexOf($haystack, $needle, $offset)
    {
        $offset = min(strlen($haystack) - 1, $offset);
        $offset = max(0, $offset);

        $index = strripos($haystack, $needle, $offset - strlen($haystack));
        if ($index === false) return -1;
        return $index;
    }
}
