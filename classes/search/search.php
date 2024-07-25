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
     * @param array $content Array of content sections, where each section is an associative array
     * with keys 'content', 'filename', and 'page'.
     * @param string $searchterm The term to search for within the content sections.
     * @param int $contextlength The number of words to include before and after each occurrence of the search term.
     * @return array The search results, with filenames as keys and sections as values.
     * [filename[page[section => filename, url, bookurl, context]]]
     */
    public static function get_search_results(array $content, string $searchterm, int $contextlength = 1): array {
        $results = [];

        foreach ($content as $section) {
            self::get_section_results($section, $searchterm, $contextlength, $results);
        }

        return $results;
    }


    /**
     * Processes a section of content to extract and format search results based on a search term and context length.
     * @param stdClass $section An array representing a section of content with keys 'content' (text) and 'filename' (file name).
     * @param string $searchterm The term to search for within the section content.
     * @param int $contextlength The number of words to include before and after each occurrence of the search term.
     * @param array $searchresults A reference to an array to store the search results.
     * [filename => [page => [section => filename, url, bookurl, context]]]
     */
    private static function get_section_results($section, $searchterm, $contextlength, &$searchresults) {
        // Get the section content.
        $content = strtolower($section->content);
        $term = strtolower($searchterm);

        // Check if the search term is present in the content.
        if (strpos($content, $term) === false) {
            return;
        }

        // Get all term occurrences in the content with index.
        $occurrenceindexes = self::get_term_occurrences($content, $term);

        // Expand and combine the occurrences into ranges with start and end index.
        $occurrenceranges = self::expand_occurrence_data($occurrenceindexes, strlen($term));

        // Get all words in content with starting index.
        $words = self::split_text_with_indexes($content);

        // Combine all words belonging to the same search result occurrence to have the best context.
        $contextwords = self::combine_words_in_occurrences($words, $occurrenceranges);

        // Get all occurrence indexes for the contextwords array.
        $contextsnippetindexes = self::process_context_words($contextwords, $contextlength);

        // Combine all words within context size range of one search term occurrence into one result. Discard unnecessary words.
        $contextsnippets = self::create_context_snippets($contextwords, $contextsnippetindexes);

        // Combine all occurrences into a single string.
        $result = self::generate_final_result($contextwords, $contextsnippetindexes, $contextsnippets);

        // Add result to the section object.
        $section->context = $result;

        // Create new file entry in results if it does not exist.
        if (!array_key_exists($section->filename, $searchresults)) {
            $searchresults[$section->filename] = [];
        }

        // Set chapter entry as section or add section context to existing chapter entry.
        if (!array_key_exists($section->page, $searchresults[$section->filename])) {
            $searchresults[$section->filename][$section->page] = $section;
        } else {
            // Append to existing context if the section already exists.
            $searchresults[$section->filename][$section->page]->context .= " ... " . $section->context;
        }
    }


    /**
     * Generates the final result string with context snippets and ellipses.
     * @param array $contextwords Array of combined words with starting indexes.
     * @param array $contextsnippetindexes Array of start and end indexes for context snippets.
     * @param array $contextsnippets Array of context snippets as strings.
     * @return string Final result string with ellipses.
     */
    private static function generate_final_result($contextwords, $contextsnippetindexes, $contextsnippets) {
        // Collapse context snippets into a single string separated by ' ... '.
        $result = implode(' ... ', $contextsnippets);

        // Add ellipses at the beginning if the first context snippet does not start at 0.
        if ($contextsnippetindexes[0][0] > 0) {
            $result = '... ' . $result;
        }

        // Add ellipses at the end if the last context snippet does not end at the last word.
        if ($contextsnippetindexes[count($contextsnippetindexes) - 1][1] < count($contextwords) - 1) {
            $result = $result . ' ...';
        }

        return $result;
    }


    /**
     * Creates context snippets from context words and snippet indexes.
     * @param array $contextwords Array of combined words with starting indexes.
     * @param array $contextsnippetindexes Array of start and end indexes for context snippets.
     * @return array Array of context snippets as strings.
     */
    private static function create_context_snippets($contextwords, $contextsnippetindexes) {
        $result = [];

        for ($i = 0; $i < count($contextsnippetindexes); $i++) {
            list($start, $end) = $contextsnippetindexes[$i];
            $words = [];

            for ($index = $start; $index <= $end; $index++) {
                $words[] = $contextwords[$index][2]; // Get the word from the contextwords array.
            }

            $result[] = implode(' ', $words); // Collapse words into a single string.
        }

        return $result;
    }


    /**
     * This function returns the start and end indexes for the context for each search term occurrence.
     * @param array $contextwords Array of combined words with starting indexes.
     * @param int $contextlength Number of words before and after each occurrence to include in the context.
     * @return array Array of start and end indexes of occurrences.
     */
    private static function process_context_words($contextwords, $contextlength) {
        $occurrences = [];
        $currentoccurrencestart = -2 * $contextlength;
        $currentoccurrenceend = -2 * $contextlength;

        for ($i = 0; $i < count($contextwords); $i++) {
            list($isoccurrence, $index, $word) = $contextwords[$i];

            if (!$isoccurrence) {
                continue;
            }

            $start = max(0, $index - $contextlength);
            $end = min(count($contextwords) - 1, $index + $contextlength);

            // If the context of two occurrences touch, they get combined.
            if ($currentoccurrenceend >= $start - 1) {
                $currentoccurrenceend = $end;
                continue;
            }

            // The occurrences are too far apart and get separated.
            $occurrences[] = [$currentoccurrencestart, $currentoccurrenceend];
            $currentoccurrencestart = $start;
            $currentoccurrenceend = $end;
        }

        $occurrences[] = [$currentoccurrencestart, $currentoccurrenceend];
        array_shift($occurrences); // Remove the initial placeholder.

        return $occurrences;
    }


    /**
     * Combines words into one array element if they are in one occurrence.
     * @param array $words Array of pairs [starting index, word].
     * @param array $occurrences Array of pairs [start, end].
     * @return array Array of combined words with starting indexes.
     */
    private static function combine_words_in_occurrences($words, $occurrences) {
        $result = [];
        $currentoccurrenceindex = 0;
        if (count($occurrences) > 0) {
            list($currentoccurrencestart, $currentoccurrenceend) = $occurrences[0];
        } else {
            list($currentoccurrencestart, $currentoccurrenceend) = [-1, -1];
        }
        $currentoccurrencewords = [];
        $resultindexcounter = 0;

        // Iterate over all words.
        foreach ($words as $wordindex => $worddata) {
            list($wordstart, $word) = $worddata;
            $wordend = $wordstart + strlen($word);

            // No current occurrence, word added to result normally.
            if ($currentoccurrenceend === -1) {
                $result[] = [false, $resultindexcounter++, $word];
                continue;
            }

            // Current word is before the current occurrence, word added to result normally.
            if ($wordend < $currentoccurrencestart) {
                $result[] = [false, $resultindexcounter++, $word];
                continue;
            }

            // Current word is inside the current occurrence, word added to the combined word.
            if ($wordend >= $currentoccurrencestart && $wordstart <= $currentoccurrenceend) {
                $currentoccurrencewords[] = $word;
                continue;
            }

            // Current word is after the current occurrence, the current occurrence is completed, retry word.
            if ($wordstart > $currentoccurrenceend) {
                // Combine the words with occurrence into one and add to result.
                $result[] = [true, $resultindexcounter++, implode(' ', $currentoccurrencewords)];
                $currentoccurrencewords = [];

                // Move to the next occurrence.
                $currentoccurrenceindex++;
                if ($currentoccurrenceindex < count($occurrences)) {
                    list($currentoccurrencestart, $currentoccurrenceend) = $occurrences[$currentoccurrenceindex];
                } else {
                    list($currentoccurrencestart, $currentoccurrenceend) = [-1, -1];
                }

                // Recheck the same word with the new current occurrence.
                $wordindex--;
            }
        }

        // Add the last occurrence words if any.
        if (count($currentoccurrencewords) > 0) {
            $result[] = [true, $resultindexcounter++, implode(' ', $currentoccurrencewords)];
        }

        return $result;
    }


    /**
     * Splits the text into words and returns pairs of [starting index, word].
     * @param string $text The original text.
     * @return array Array of pairs [starting index, word].
     */
    private static function split_text_with_indexes($text) {
        $words = [];
        $regex = '/\S+/'; // Matches any non-whitespace sequence.

        if (preg_match_all($regex, $text, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $words[] = [$match[1], $match[0]]; // Here $match[1] is the starting index, $match[0] is the word.
            }
        }

        return $words;
    }


    /**
     * Transforms occurrences into pairs of start/end information.
     * @param array $positions Array of start positions.
     * @param int $length Length of each term.
     * @return array Array of pairs [start, end].
     */
    private static function expand_occurrence_data($positions, $length) {
        if (count($positions) === 0) {
            return [];
        }

        $result = [];
        $currentstart = $positions[0];
        $currentend = $currentstart + $length;

        // In this for loop, $positions[$i] describes the next occurrence.
        for ($i = 1; $i < count($positions); $i++) {
            if ($currentend >= $positions[$i]) {
                // If $currentend overlaps with the next start position, merge them.
                $currentend = $positions[$i] + $length;
            } else {
                // If no overlap, push the current start/end pair to the result.
                $result[] = [$currentstart, $currentend];
                // Update $currentstart and $currentend to the new positions.
                $currentstart = $positions[$i];
                $currentend = $currentstart + $length;
            }
        }

        // Push the last start/end pair to the result.
        $result[] = [$currentstart, $currentend];

        return $result;
    }


    /**
     * This function returns a list of starting indexes for all occurrences of the given term in the given content.
     * @param string $content The text content to search in.
     * @param string $term The search term to search for.
     * @return array An array of start indexes of the term occurrences in the content.
     */
    private static function get_term_occurrences($content, $term) {
        $occurrences = [];
        $startindex = 0;

        while (($startindex = strpos($content, $term, $startindex)) !== false) {
            $occurrences[] = $startindex;
            $startindex += 1;
        }

        return $occurrences;
    }
}
