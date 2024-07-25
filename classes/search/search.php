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
     * @param string $searchTerm The term to search for within the content sections.
     * @param int $contextLength The number of words to include before and after each occurrence of the search term.
     * @return array The search results, with filenames as keys and sections as values.
     * [filename[page[section => filename, url, bookurl, context]]]
     */
    public static function get_search_results(array $content, string $searchTerm, int $contextLength = 1): array {
        $results = [];

        foreach ($content as $section) {
            self::getSectionResults($section, $searchTerm, $contextLength, $results);
        }

        return $results;
    }


    /**
     * Processes a section of content to extract and format search results based on a search term and context length.
     * @param stdClass $section An array representing a section of content with keys 'content' (text) and 'filename' (file name).
     * @param string $searchTerm The term to search for within the section content.
     * @param int $contextLength The number of words to include before and after each occurrence of the search term.
     * @param array &$searchResults An array to store the search results, with filenames as keys and sections as values.
     * [filename[page[section => filename, url, bookurl, context]]]
     */
    private static function getSectionResults($section, $searchTerm, $contextLength, &$searchResults) {
        // Get the section content.
        $content = strtolower($section->content);
        $term = strtolower($searchTerm);

        // Check if the search term is present in the content.
        if (strpos($content, $term) === false) {
            return;
        }

        // Get all term occurrences in the content with index.
        $occurrenceIndexes = self::getTermOccurrences($content, $term);

        // Expand and combine the occurrences into ranges with start and end index.
        $occurrenceRanges = self::expandOccurrenceData($occurrenceIndexes, strlen($term));

        // Get all words in content with starting index.
        $words = self::splitTextWithIndices($content);

        // Combine all words belonging to the same search result occurrence to have the best context.
        $contextWords = self::combineWordsInOccurrences($words, $occurrenceRanges);

        // Get all occurrence indexes for the contextWords array.
        $contextSnippetIndexes = self::processContextWords($contextWords, $contextLength);

        // Combine all words within context size range of one search term occurrence into one result. Discard unnecessary words.
        $contextSnippets = self::createContextSnippets($contextWords, $contextSnippetIndexes);

        // Combine all occurrences into a single string.
        $result = self::generateFinalResult($contextWords, $contextSnippetIndexes, $contextSnippets);

        // Add result to the section object.
        $section->context = $result;

        // Create new file entry in results if it does not exist.
        if (!array_key_exists($section->filename, $searchResults)) {
            $searchResults[$section->filename] = [];
        }

        // Set chapter entry as section or add section context to existing chapter entry.
        if (!array_key_exists($section->page, $searchResults[$section->filename])) {
            $searchResults[$section->filename][$section->page] = $section;
        } else {
            // Append to existing context if the section already exists.
            $searchResults[$section->filename][$section->page]->context .= " ... " . $section->context;
        }
    }


    /**
     * Generates the final result string with context snippets and ellipses.
     * @param array $contextWords Array of combined words with starting indices.
     * @param array $contextSnippetIndexes Array of start and end indices for context snippets.
     * @param array $contextSnippets Array of context snippets as strings.
     * @return string Final result string with ellipses.
     */
    private static function generateFinalResult($contextWords, $contextSnippetIndexes, $contextSnippets) {
        // Collapse context snippets into a single string separated by ' ... '.
        $result = implode(' ... ', $contextSnippets);

        // Add ellipses at the beginning if the first context snippet does not start at 0.
        if ($contextSnippetIndexes[0][0] > 0) {
            $result = '... ' . $result;
        }

        // Add ellipses at the end if the last context snippet does not end at the last word.
        if ($contextSnippetIndexes[count($contextSnippetIndexes) - 1][1] < count($contextWords) - 1) {
            $result = $result . ' ...';
        }

        return $result;
    }


    /**
     * Creates context snippets from context words and snippet indexes.
     * @param array $contextWords Array of combined words with starting indices.
     * @param array $contextSnippetIndexes Array of start and end indices for context snippets.
     * @return array Array of context snippets as strings.
     */
    private static function createContextSnippets($contextWords, $contextSnippetIndexes) {
        $result = [];

        for ($i = 0; $i < count($contextSnippetIndexes); $i++) {
            list($start, $end) = $contextSnippetIndexes[$i];
            $words = [];

            for ($index = $start; $index <= $end; $index++) {
                $words[] = $contextWords[$index][2]; // Get the word from the contextWords array.
            }

            $result[] = implode(' ', $words); // Collapse words into a single string.
        }

        return $result;
    }


    /**
     * This function returns the start and end indexes for the context for each search term occurrence.
     * @param array $contextWords Array of combined words with starting indices.
     * @param int $contextLength Number of words before and after each occurrence to include in the context.
     * @return array Array of start and end indices of occurrences.
     */
    private static function processContextWords($contextWords, $contextLength) {
        $occurrences = [];
        $currentOccurrenceStart = -2 * $contextLength;
        $currentOccurrenceEnd = -2 * $contextLength;

        for ($i = 0; $i < count($contextWords); $i++) {
            list($isOccurrence, $index, $word) = $contextWords[$i];

            if (!$isOccurrence) {
                continue;
            }

            $start = max(0, $index - $contextLength);
            $end = min(count($contextWords) - 1, $index + $contextLength);

            // If the context of two occurrences touch, they get combined.
            if ($currentOccurrenceEnd >= $start - 1) {
                $currentOccurrenceEnd = $end;
                continue;
            }

            // The occurrences are too far apart and get separated.
            $occurrences[] = [$currentOccurrenceStart, $currentOccurrenceEnd];
            $currentOccurrenceStart = $start;
            $currentOccurrenceEnd = $end;
        }

        $occurrences[] = [$currentOccurrenceStart, $currentOccurrenceEnd];
        array_shift($occurrences); // Remove the initial placeholder.

        return $occurrences;
    }


    /**
     * Combines words into one array element if they are in one occurrence.
     * @param array $words Array of pairs [starting index, word].
     * @param array $occurrences Array of pairs [start, end].
     * @return array Array of combined words with starting indices.
     */
    private static function combineWordsInOccurrences($words, $occurrences) {
        $result = [];
        $currentOccurrenceIndex = 0;
        if (count($occurrences) > 0) {
            list($currentOccurrenceStart, $currentOccurrenceEnd) = $occurrences[0];
        } else {
            list($currentOccurrenceStart, $currentOccurrenceEnd) = [-1, -1];
        }
        $currentOccurrenceWords = [];
        $resultIndexCounter = 0;

        // Iterate over all words.
        foreach ($words as $wordIndex => $wordData) {
            list($wordStart, $word) = $wordData;
            $wordEnd = $wordStart + strlen($word);

            // No current occurrence, word added to result normally.
            if ($currentOccurrenceEnd === -1) {
                $result[] = [false, $resultIndexCounter++, $word];
                continue;
            }

            // Current word is before the current occurrence, word added to result normally.
            if ($wordEnd < $currentOccurrenceStart) {
                $result[] = [false, $resultIndexCounter++, $word];
                continue;
            }

            // Current word is inside the current occurrence, word added to the combined word.
            if ($wordEnd >= $currentOccurrenceStart && $wordStart <= $currentOccurrenceEnd) {
                $currentOccurrenceWords[] = $word;
                continue;
            }

            // Current word is after the current occurrence, the current occurrence is completed, retry word.
            if ($wordStart > $currentOccurrenceEnd) {
                // Combine the words with occurrence into one and add to result.
                $result[] = [true, $resultIndexCounter++, implode(' ', $currentOccurrenceWords)];
                $currentOccurrenceWords = [];

                // Move to the next occurrence.
                $currentOccurrenceIndex++;
                if ($currentOccurrenceIndex < count($occurrences)) {
                    list($currentOccurrenceStart, $currentOccurrenceEnd) = $occurrences[$currentOccurrenceIndex];
                } else {
                    list($currentOccurrenceStart, $currentOccurrenceEnd) = [-1, -1];
                }

                // Recheck the same word with the new current occurrence.
                $wordIndex--;
            }
        }

        // Add the last occurrence words if any.
        if (count($currentOccurrenceWords) > 0) {
            $result[] = [true, $resultIndexCounter++, implode(' ', $currentOccurrenceWords)];
        }

        return $result;
    }


    /**
     * Splits the text into words and returns pairs of [starting index, word].
     * @param string $text The original text.
     * @return array Array of pairs [starting index, word].
     */
    private static function splitTextWithIndices($text) {
        $words = [];
        $regex = '/\S+/'; // Matches any non-whitespace sequence.

        if (preg_match_all($regex, $text, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $words[] = [$match[1], $match[0]]; // $match[1] is the starting index, $match[0] is the word.
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
    private static function expandOccurrenceData($positions, $length) {
        if (count($positions) === 0) {
            return [];
        }

        $result = [];
        $currentStart = $positions[0];
        $currentEnd = $currentStart + $length;

        // In this for loop, $positions[$i] describes the next occurrence.
        for ($i = 1; $i < count($positions); $i++) {
            if ($currentEnd >= $positions[$i]) {
                // If $currentEnd overlaps with the next start position, merge them.
                $currentEnd = $positions[$i] + $length;
            } else {
                // If no overlap, push the current start/end pair to the result.
                $result[] = [$currentStart, $currentEnd];
                // Update $currentStart and $currentEnd to the new positions.
                $currentStart = $positions[$i];
                $currentEnd = $currentStart + $length;
            }
        }

        // Push the last start/end pair to the result.
        $result[] = [$currentStart, $currentEnd];

        return $result;
    }


    /**
     * This function returns a list of starting indexes for all occurrences of the given term in the given content.
     * @param string $content The text content to search in.
     * @param string $term The search term to search for.
     * @return array An array of start indexes of the term occurrences in the content.
     */
    private static function getTermOccurrences($content, $term) {
        $occurrences = [];
        $startIndex = 0;

        while (($startIndex = strpos($content, $term, $startIndex)) !== false) {
            $occurrences[] = $startIndex;
            $startIndex += 1;
        }

        return $occurrences;
    }
}
