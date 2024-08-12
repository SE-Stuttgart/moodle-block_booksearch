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
 * Block core and UI
 *
 * @module     block_booksearch/search_and_display
 * @copyright  2024 University of Stuttgart <kasra.habib@iste.uni-stuttgart.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * String label 'Chapter' in the current language.
 */
let chapterLabel = 'Chapter';

/**
 * Sets up the event listener for search input changes.
 * @param {string} label String label 'Chapter' in the current language.
 */
export function init(label) {
    chapterLabel = label;
    const inputElement = document.getElementById('bs-search-input');
    inputElement.addEventListener('input', handleSearchInputChange);
}

/**
 * Function to handle search term input event
 * @param {*} event The input event catched by the listener.
 */
function handleSearchInputChange(event) {
    const searchTerm = event.target.value;
    const searchTermLabel = document.getElementById('bs-search-term-label').value;
    const courseContent = JSON.parse(atob(document.getElementById('bs-json-course-content').value));
    const contextLength = 5;

    let searchResults = [];
    // We search the content if we have a search term.
    if (searchTerm) {
        searchResults = getSearchResults(courseContent, searchTerm, contextLength);
    }

    // Update the inner HTML of the element with ID 'bs-search-term' to display the current search Term.
    document.getElementById("bs-search-term").innerHTML = searchTermLabel + searchTerm;

    // Update the inner HTML of the element with ID 'bs-content' to display the results.
    document.getElementById("bs-content").innerHTML = getResultsUI(searchResults);
}


/**
 * Generates an HTML string to display search results for PDFs and their chapters.
 * @param {Object} searchResults - An object where keys are PDF names and values are objects of chapters.
 * @returns {string} An HTML string with headings for each PDF name and an unordered list of chapters, each with link and context.
 */
function getResultsUI(searchResults) {
    // Initialize an empty string to build the HTML display
    let display = '';

    // Iterate over each PDF name in the search results
    for (var pdfName in searchResults) {
        // Add the PDF name as a heading
        display += '<h4>' + pdfName + '</h4>';
        // Start an unordered list for the chapters
        display += '<ul class="bs-content-element">';
        // Iterate over each chapter in the current PDF
        for (var chapter in searchResults[pdfName]) {
            // Add each chapter as a list item with a link and context
            display += '<li>' +
                '<a href="' + searchResults[pdfName][chapter].bookurl + '">' +
                chapterLabel + '-' + chapter +
                '</a>: ' + searchResults[pdfName][chapter].context +
                '</li>';
        }
        // Close the unordered list
        display += '</ul>';
    }

    return display;
}


/**
 * Processes an array of content sections to extract and format search results based on a search term and context length.
 * @param {Array} courseContent Array of content sections, where each section is an object
 * with keys 'content', 'filename', and 'page'.
 * @param {string} searchTerm The term to search for within the content sections.
 * @param {number} contextLength The number of words to include before and after each occurrence of the search term.
 * @return {Object} The search results, with filenames as keys and sections as values.
 * [filename: {page: {section}}]
 */
function getSearchResults(courseContent, searchTerm, contextLength) {
    const results = {};

    courseContent.forEach(section => {
        // Get any search results with context from the section.
        const context = getSectionSearchResultContext(section.content, searchTerm, contextLength);

        // Add result to the section object.
        section.context = context;

        // Skip this section if there's no context (no result).
        if (context.length < 1) {
            return;
        }

        // Create new file entry in results if it does not exist.
        if (!results.hasOwnProperty(section.filename)) {
            results[section.filename] = {};
        }

        // Set chapter entry as section or add section context to existing chapter entry.
        if (!results[section.filename].hasOwnProperty(section.page)) {
            results[section.filename][section.page] = {
                filename: section.filename,
                url: section.url,
                bookUrl: section.bookUrl,
                context: section.context
            };
        } else {
            // Append to existing context if the section already exists.
            results[section.filename][section.page].context += section.context;
        }
    });

    return results;
}


/**
 * Get a combined string of any found search term occurrences in the content with the surrounding words as context.
 * @param {string} content The text content to search in.
 * @param {string} searchTerm The term to search for in this content.
 * @param {number} contextLength The number of words on each side surrounding the found occurrence to be returned as context.
 * @return {string} Text snippets for each term occurrence with their context, combined as one.
 */
function getSectionSearchResultContext(content, searchTerm, contextLength) {
    const searchContent = content.toLowerCase();
    searchTerm = searchTerm.toLowerCase();

    // Check if the search term is present in the content.
    if (searchContent.indexOf(searchTerm) === -1) {
        return "";
    }

    // Get the text indexes of the term occurrences. Array of objects with 'start' and 'end' properties.
    const occurrenceIndexes = findOccurrences(searchContent, searchTerm);

    // Get the text as words and word starting indexes.
    const [words, wordIndexes] = splitTextIntoWords(content);

    // Get the word number positions of the context we want to return. Objects with 'start' and 'end' properties.
    const contextPositions = getContextPositions(occurrenceIndexes, wordIndexes, contextLength);

    // Get the combined string context.
    const context = getContext(words, contextPositions);

    return context;
}


/**
 * Searches for occurrences of a term in a given text and returns an array of occurrence objects.
 * Each occurrence object contains the start index (position in text) and the end index.
 * @param {string} text The text in which to search for the term.
 * @param {string} term The term to search for within the text.
 * @return {Array} An array of objects, each with 'start' and 'end' properties.
 */
function findOccurrences(text, term) {
    const occurrences = [];
    const termLength = term.length;

    // Use indexOf to find the occurrences of the term in the text.
    let offset = 0;
    let index;

    while ((index = text.indexOf(term, offset)) !== -1) {
        const occurrence = {
            start: index,
            end: index + termLength
        };
        occurrences.push(occurrence);
        // Update the offset to search for the next occurrence.
        offset = index + 1;
    }

    return occurrences;
}


/**
 * Splits the text into words and returns an array of word strings and an array of word starting indexes.
 * @param {string} text The original text.
 * @return {Array} A pair of arrays [array of string words, array of word starting indexes].
 */
function splitTextIntoWords(text) {
    const words = [];
    const wordIndexes = [];

    const regex = /\S+/g; // Matches any non-whitespace sequence.

    let match;
    while ((match = regex.exec(text)) !== null) {
        words.push(match[0]); // Gather the word string.
        wordIndexes.push(match.index); // Gather the word starting index.
    }

    return [words, wordIndexes];
}


/**
 * Returns an array of positional data for each occurrence, including starting word number and ending word number.
 * @param {Array} occurrences Array of search term occurrence objects, each with 'start' and 'end' properties.
 * @param {Array} wordIndexes Array of word indexes that indicate the start position of each word in the text.
 * @param {number} contextLength The number of words to include as context on each side of the search term occurrence.
 * @return {Array} An array of occurrence position objects, each with 'start' (first word number of context)
 * and 'end' (last word number of context or null if at end of text) properties.
 */
function getEachOccurrenceContextPosition(occurrences, wordIndexes, contextLength) {
    const results = [];
    let currentOccurrenceIndex = 0;

    // Iterate through each word index
    for (let wordNumber = 0; wordNumber < wordIndexes.length; wordNumber++) {
        // If there are no more occurrences to check, exit the loop
        if (currentOccurrenceIndex >= occurrences.length) {
            break;
        }

        // Check if this is the last word
        if (wordNumber + 1 >= wordIndexes.length) {
            const start = Math.max(0, wordNumber - contextLength);
            const length = null;
            results.push({
                start: start,
                end: length
            });
            continue;
        }

        // The current occurrence to check against
        const currentOccurrence = occurrences[currentOccurrenceIndex];

        // If this word is not (yet) part of the context
        if (wordIndexes[wordNumber + 1] <= currentOccurrence.start) {
            continue;
        }

        // This word begins an occurrence
        const start = Math.max(0, wordNumber - contextLength);
        const end = getContextEnd(wordIndexes, wordNumber, currentOccurrence.end, contextLength);

        const position = {
            start: start,
            end: end
        };

        results.push(position);

        currentOccurrenceIndex++;
    }

    return results;
}


/**
 * Returns the number of the last word still in the context. Returns null if the context is the rest of all words.
 * @param {Array} wordIndexes An array that has the text starting index for each word.
 * @param {number} startNumber The word number where the occurrence starts.
 * @param {number} endIndex The text index where the occurrence ends.
 * @param {number} contextLength The amount of words that get returned on each side of the occurrence as context.
 * @return {?number} The word number of the last word in the context or null if it ends with the text.
 */
function getContextEnd(wordIndexes, startNumber, endIndex, contextLength) {
    for (let i = startNumber; i < wordIndexes.length; i++) {
        // Check if the context reaches the last word
        if (i + contextLength + 1 >= wordIndexes.length) {
            return null;
        }

        // Check if the occurrence is part of the next word
        if (wordIndexes[i + 1] <= endIndex) {
            continue;
        }

        // Calculate the last word number in context
        const lastWordInContext = i + contextLength;

        return lastWordInContext;
    }
    return null;
}


/**
 * Returns an array of positional data for each occurrence set, including starting word number, ending word number, and word count.
 * @param {Array} occurrences Array of search term occurrence objects, each with 'start' and 'end' properties.
 * @param {Array} wordIndexes Array of word indexes that indicate the start position of each word in the text.
 * @param {number} contextLength The number of words to include as context on each side of the search term occurrence.
 * @return {Array} An array of occurrence position objects, each with 'start' (first word number of context)
 * and 'end' (first word number outside the context or undefined if at end of text).
 */
function getContextPositions(occurrences, wordIndexes, contextLength) {
    let occurrenceContextPositions = getEachOccurrenceContextPosition(occurrences, wordIndexes, contextLength);
    occurrenceContextPositions = mergeOccurrenceContextPositions(occurrenceContextPositions);
    return occurrenceContextPositions;
}


/**
 * Merge overlapping occurrence positions together.
 * @param {Array} contextPositions Array of position objects with 'start' (first word in context)
 * and 'end' (last word in context or null if at end of text) properties.
 * @return {Array} An array of occurrence position objects, each with 'start' (first word number of context)
 * and 'end' (first word number outside the context or undefined if at end of text).
 */
function mergeOccurrenceContextPositions(contextPositions) {
    const results = [];

    for (let i = 0; i < contextPositions.length; i++) {
        // First position.
        let position = contextPositions[i];
        let start = position.start;
        let end = position.end;

        // Further positions.
        while (
            i + 1 < contextPositions.length && // Check if there is a next position.
            contextPositions[i].end && // Check if 'end' is null. We can then ignore all upcoming positions.
            contextPositions[i].end >= contextPositions[i + 1].start // Check if this and the next positions overlap.
        ) {
            end = contextPositions[i + 1].end; // The positions overlap so the end gets set to ht next positions end.
            i++;
        }

        const mergedPosition = {
            start: start,
            end: end !== null ? end + 1 : undefined // If end is not null we set end to the next element.
        };

        results.push(mergedPosition);

        if (!end) { // We can ignore all later positions as we already are at the end of possible context.
            break;
        }
    }

    return results;
}


/**
 * Based on given context positions, return a combined string from a list of words.
 * @param {Array} words List of all words.
 * @param {Array} contextPositions An array of occurrence position objects, each with 'start' (first word number of context),
 * and 'end' (first word number outside the context or undefinded if at end of text)
 * @return {string} A combined string of all given positions.
 */
function getContext(words, contextPositions) {
    let context = "... ";

    contextPositions.forEach(position => {
        const subcontextWords = words.slice(position.start, position.end);
        context += subcontextWords.join(" ");
        context += " ... ";
    });

    return context;
}