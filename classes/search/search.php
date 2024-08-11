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
 * Text Content Search Capabilities
 *
 * @package    block_booksearch
 * @copyright  2024 University of Stuttgart <kasra.habib@iste.uni-stuttgart.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_booksearch\search;

use stdClass;

/**
 * A class for performing search operations on content sections.
 * This class provides methods to search through an array of content sections
 * and extract formatted search results based on a given search term and context length.
 * It processes each section of content, identifies occurrences of the search term,
 * and returns the results with contextual information.
 */
class search {

    /**
     * Processes an array of content sections to extract and format search results based on a search term and context length.
     * @param array $coursecontent Array of content sections, where each section is an associative array
     * with keys 'content', 'filename', and 'page'.
     * @param string $searchterm The term to search for within the content sections.
     * @param int $contextlength The number of words to include before and after each occurrence of the search term.
     * @return array The search results, with filenames as keys and sections as values.
     * [filename[page[section => filename, url, bookurl, context]]]
     */
    public static function get_search_results(array $coursecontent, string $searchterm, int $contextlength) {
        $results = [];

        foreach ($coursecontent as $section) {
            // Get any search results with context from the section.
            $context = self::get_section_search_result_context($section->content, $searchterm, $contextlength);

            // Add result to the section object.
            $section->context = $context;

            // We have not context (no result), we can skip this section.
            if (strlen($context) < 1) {
                continue;
            }

            // Create new file entry in results if it does not exist.
            if (!array_key_exists($section->filename, $results)) {
                $results[$section->filename] = [];
            }

            // Set chapter entry as section or add section context to existing chapter entry.
            if (!array_key_exists($section->page, $results[$section->filename])) {
                $results[$section->filename][$section->page] = $section;
            } else {
                // Append to existing context if the section already exists.
                $results[$section->filename][$section->page]->context .= $section->context;
            }
        }

        return $results;
    }


    /**
     * Get a combined string of any found searchterm occurences in the content with the surrounding words as context.
     * @param string $content The text content to search in.
     * @param string $searchterm The term to search for in this content.
     * @param string $contextlength The amount of words on each side surrounding the found occurence to be returned as context.
     * @return string Text snippets for each term occurence with their context, combined as one.
     */
    private static function get_section_search_result_context($content, $searchterm, $contextlength) {
        $searchcontent = strtolower($content);
        $searchterm = strtolower($searchterm);

        // Check if the search term is present in the content.
        if (strpos($searchcontent, $searchterm) === false) {
            return "";
        }

        // Get the text indexes of the term occurences. Array of objects with 'start' and 'end' properties.
        $occurenceindexes = self::find_occurences($searchcontent, $searchterm);

        // Get the text as words and word starting indexes.
        list($words, $wordindexes) = self::split_text_into_words($content);

        // Get the word number positions of the context we want to return. Objects with 'start' and 'length' properties.
        $contextpositions = self::get_context_positions($occurenceindexes, $wordindexes, $contextlength);

        // Get the combined string context.
        $context = self::get_context($words, $contextpositions);

        return $context;
    }


    /**
     * Searches for occurrences of a term in a given text and returns an array of occurrence objects.
     * Each occurrence object contains the start index (position in text) and the end index.
     * @param string $text The text in which to search for the term.
     * @param string $term The term to search for within the text.
     * @return array An array of objects, each with 'start' and 'end' properties.
     */
    private static function find_occurences($text, $term) {
        $occurrences = [];
        $termlength = strlen($term);

        // Use strpos to find the occurrences of the term in the text.
        $offset = 0;
        while (($index = strpos($text, $term, $offset)) !== false) {
            $occurence = new stdClass();
            $occurence->start = $index;
            $occurence->end = $index + $termlength;
            $occurrences[] = $occurence;
            // Update the offset to search for the next occurrence.
            $offset = $index + 1;
        }

        return $occurrences;
    }


    /**
     * Splits the text into words and returns an array of word strings and an array of word starting indexes.
     * @param string $text The original text.
     * @return array Pair of arrays [array of string words, array of word starting indexes].
     */
    private static function split_text_into_words($text) {
        $words = [];
        $wordindexes = [];

        $regex = '/\S+/'; // Matches any non-whitespace sequence.

        if (preg_match_all($regex, $text, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $words[] = $match[0]; // Gather the word string.
                $wordindexes[] = $match[1]; // Gather the word starting index.
            }
        }

        return [$words, $wordindexes];
    }


    /**
     * Returns an array of positional data for each occurence set including starting wordnumber, ending wordnumber and wordcount.
     * @param array $occurrences Array of search term occurrence objects each with 'start' and 'end'properties.
     * @param array $wordindexes Array of word indexes that indicate the start position of each word in the text.
     * @param int $contextlength The amount of words to include as context on each side of the search term occurence.
     * @return array An array of occurrence position objects, each with 'start' (first word number of context)
     * and 'length' (number of words part of this occurence context, or null if at end of text) properties.
     */
    private static function get_context_positions($occurrences, $wordindexes, $contextlength) {
        $occurencecontextpositions = self::get_each_occurences_context_position($occurrences, $wordindexes, $contextlength);
        $occurencecontextpositions = self::merge_occurence_context_positions($occurencecontextpositions);
        return $occurencecontextpositions;
    }


    /**
     * Returns an array of positional data for each occurence including starting wordnumber and ending wordnumber.
     * @param array $occurrences Array of search term occurrence objects each with 'start' and 'end'properties.
     * @param array $wordindexes Array of word indexes that indicate the start position of each word in the text.
     * @param int $contextlength The amount of words to include as context on each side of the search term occurence.
     * @return array An array of occurrence position objects, each with 'start' (first word number of context)
     * and 'end' (last word number of context or null if at end of text) properties.
     */
    private static function get_each_occurences_context_position($occurrences, $wordindexes, $contextlength) {
        $results = [];

        $currentoccurrenceindex = 0;

        // Wordnumber describes its index in a list of words. Wordindex describes its starting position in the text.
        for ($wordnumber = 0; $wordnumber < count($wordindexes); $wordnumber++) {
            // We have no more occurences to check.
            if ($currentoccurrenceindex >= count($occurrences)) {
                break;
            }

            // This is the last word.
            if ($wordnumber + 1 >= count($wordindexes)) {
                $start = max(0, $wordnumber - $contextlength);
                $length = null;
                $results[] = [$start, $length];
            }

            // The current occurence to check against.
            $currentoccurence = $occurrences[$currentoccurrenceindex];

            // This word is not (yet) important for the context
            if ($wordindexes[$wordnumber + 1] <= $currentoccurence->start) {
                continue;
            }

            // This word begins an occurence
            $start = max(0, $wordnumber - $contextlength);
            $end = self::get_context_end($wordindexes, $wordnumber, $currentoccurence->end, $contextlength);

            $position = new stdClass();
            $position->start = $start;
            $position->end = $end;

            $results[] = $position;

            $currentoccurrenceindex++;
        }

        return $results;
    }


    /**
     * Retuns the number of the last word still in the context. Returns null of the context is the rest of all words.
     * @param array $wordindexes An array that has the text starting index for each word.
     * @param int $startnumber What word number does the occurence start in.
     * @param int $endindex What text index does the occurence end in.
     * @param int $contextlength The amount of words that get returned on each side of the occurrence as context.
     * @return ?int The word number of the last word in the context or null if it ends with the text.
     */
    private static function get_context_end($wordindexes, $startnumber, $endindex, $contextlength) {
        for ($i = $startnumber; $i < count($wordindexes); $i++) {
            // Would the context reach the last word.
            if ($i + $contextlength + 1 >= count($wordindexes)) {
                return null;
            }

            // The occurence is part of the next word.
            if ($wordindexes[$i + 1] <= $endindex) {
                continue;
            }

            // We calculate the last wordnumber in context.
            $endindex = $i + $contextlength;

            return $endindex;
        }
        return null;
    }


    /**
     * Merge overlapping occurence positions together.
     * @param array $contextpositions Array of position object with 'start' (first word in context)
     * and 'end' (last word in context or null if at end of text) properties.
     * @return array An array of occurrence position objects, each with 'start' (first word number of context)
     * and 'length' (number of words part of this occurence context, or null if at end of text) properties.
     */
    private static function merge_occurence_context_positions($contextposition) {
        $results = [];

        for ($i = 0; $i < count($contextposition); $i++) {
            // The first position.
            $position = $contextposition[$i];
            $start = $position->start;
            $end = $position->end;

            // Other positions.
            while (
                $i + 1 < count($contextposition) && // Check if there is a next position.
                $contextposition[$i]->end && // Check if 'end' is null. We can then ignore all upcoming positions.
                $contextposition[$i]->end >= $contextposition[$i + 1]->start // Check if this and the next positions overlap.
            ) {
                $end = $contextposition[$i + 1]->end; // The positions overlap so the end gets set to ht next positions end.
                $i++;
            }

            $mergedposition = new stdClass();
            $mergedposition->start = $start;
            $mergedposition->length = null;
            if ($end) { // If end is not null we can set a significant length.
                $mergedposition->length = 1 + $end - $start;
            }

            $results[] = $mergedposition;

            if (!$end) { // We can ignore all later positions as we already are at the end of possible context.
                break;
            }
        }

        return $results;
    }


    /**
     * Based on given context positions return a combined string from a list of words.
     * @param array $words List of all words.
     * @param array $contextpositions An array of occurrence position objects, each with 'start' (first word number of context)
     * and 'length' (number of words part of this occurence context, or null if at end of text) properties.
     * @return string A combined string of all given positions.
     */
    private static function get_context($words, $contextpositions) {
        $context = "... ";

        foreach ($contextpositions as $position) {
            $subcontextwords = array_slice($words, $position->start, $position->length);
            $context .= implode(" ", $subcontextwords);
            $context .= " ... ";
        }

        return $context;
    }
}
