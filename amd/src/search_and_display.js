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
        courseContent.forEach(section => getSectionResults(section, searchTerm, contextLength, searchResults));
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
 * Processes a section of content to extract and format search results based on a search term and context length.
 * @param {Object} section - An object representing a section of content with properties `content` (text) and `filename`.
 * @param {string} searchTerm - The term to search for within the section content.
 * @param {number} contextLength - The number of words to include before and after each occurrence of the search term.
 * @param {Object} searchResults - An object to store the search results, with filenames as keys and sections as values.
 */
function getSectionResults(section, searchTerm, contextLength, searchResults) {
    // Get the section content.
    const content = section.content.toLowerCase();
    const term = searchTerm.toLowerCase();

    // Check if the search term is present in the content.
    if (!content.includes(term)) {
        return;
    }

    // Get all term occurances in the content with index.
    const occuranceIndexes = getTermOccurances(content, term);

    // Expannd and combine the occurances into ranges with start and end index.
    const occuranceRanges = expandOccuranceData(occuranceIndexes, term.length);

    // Get all words in content with starting index.
    const words = splitTextWithIndices(content);

    // Combine all words belonging to the same search result occurance to have the best context.
    const contextWords = combineWordsInOccurrences(words, occuranceRanges);

    // Get all occurance indexes for the contextWords array.
    const contextSnippetIndexes = processContextWords(contextWords, contextLength);

    // Combine all words within context size range of one search term occurance into one result. Discard unnecessary words.
    const contextSnippets = createContextSnippets(contextWords, contextSnippetIndexes);

    // Combine all Occurances into a single string.
    const result = generateFinalResult(contextWords, contextSnippetIndexes, contextSnippets);

    // Add result to the section object.
    section.context = result;

    // Create new file entry in results if it does not exist.
    if (!(section.filename in searchResults)) {
        searchResults[section.filename] = [];
    }

    // Set chapter entry as section or add section context to existing chapter entry.
    if (!(section.page in searchResults[section.filename])) {
        searchResults[section.filename][section.page] = section;
    } else {
        searchResults[section.filename][section.page].context += " ... " + section.context;
    }
}


/**
 * Generates the final result string with context snippets and ellipses.
 * @param {Array<[boolean, number, string]>} contextWords - Array of combined words with starting indices.
 * @param {Array<[number, number]>} contextSnippetIndexes - Array of start and end indices for context snippets.
 * @param {Array<string>} contextSnippets - Array of context snippets as strings.
 * @returns {string} - Final result string with ellipses.
 */
function generateFinalResult(contextWords, contextSnippetIndexes, contextSnippets) {
    // Collapse context snippets into a single string separated by ' ... '.
    let result = contextSnippets.join(' ... ');

    // Add ellipses at the beginning if the first context snippet does not start at 0.
    if (contextSnippetIndexes[0][0] > 0) {
        result = '... ' + result;
    }

    // Add ellipses at the end if the last context snippet does not end at the last word.
    if (contextSnippetIndexes[contextSnippetIndexes.length - 1][1] < contextWords.length - 1) {
        result = result + ' ...';
    }

    return result;
}


/**
 * Creates context snippets from context words and snippet indexes.
 * @param {Array<[boolean, number, string]>} contextWords - Array of combined words with starting indices.
 * @param {Array<[number, number]>} contextSnippetIndexes - Array of start and end indices for context snippets.
 * @returns {Array<string>} - Array of context snippets as strings.
 */
function createContextSnippets(contextWords, contextSnippetIndexes) {
    const result = [];

    for (let i = 0; i < contextSnippetIndexes.length; i++) {
        const [start, end] = contextSnippetIndexes[i];
        const words = [];

        for (let index = start; index <= end; index++) {
            words.push(contextWords[index][2]); // Get the word from the contextWords array.
        }

        result.push(words.join(' ')); // Collapse words into a single string.
    }

    return result;
}


/**
 * This function returns the start and end indexes for the context for each search term occurance.
 * @param {Array<[boolean, number, string]>} contextWords - Array of combined words with starting indices.
 * @param {number} contextLength - Number of words before and after each occurrence to include in the context.
 * @returns {Array<[number, number]>} - Array of start and end indices of occurrences.
 */
function processContextWords(contextWords, contextLength) {
    const occurrences = [];
    let currentOccurrenceStart = -2 * contextLength;
    let currentOccurrenceEnd = -2 * contextLength;

    for (let i = 0; i < contextWords.length; i++) {
        const [isOccurrence, index] = contextWords[i];

        if (!isOccurrence) {
            continue;
        }

        const start = Math.max(0, index - contextLength);
        const end = Math.min(contextWords.length - 1, index + contextLength);

        // IF the context of two occurances touch, they get combined.
        if (currentOccurrenceEnd >= start - 1) {
            currentOccurrenceEnd = end;
            continue;
        }

        // The occurances are too far apart and get seperated.
        occurrences.push([currentOccurrenceStart, currentOccurrenceEnd]);
        currentOccurrenceStart = start;
        currentOccurrenceEnd = end;
    }

    occurrences.push([currentOccurrenceStart, currentOccurrenceEnd]);
    occurrences.shift(); // Remove the initial placeholder.

    return occurrences;
}


/**
 * Combines words into one array element if they are in one occurrence.
 * @param {Array<[number, string]>} words - Array of pairs [starting index, word].
 * @param {Array<[number, number]>} occurrences - Array of pairs [start, end].
 * @returns {Array<[boolean, number, string]>} - Array of combined words with starting indices.
 */
function combineWordsInOccurrences(words, occurrences) {
    const result = [];
    let currentOccurrenceIndex = 0;
    let [currentOccurrenceStart, currentOccurrenceEnd] = occurrences.length > 0 ? occurrences[0] : [-1, -1];
    let currentOccurrenceWords = [];
    let resultIndexCounter = 0;

    // Iterate over all words.
    for (let wordIndex = 0; wordIndex < words.length; wordIndex++) {
        const [wordStart, word] = words[wordIndex];
        const wordEnd = wordStart + word.length;

        // No current occurrence, word added to result normally.
        if (currentOccurrenceEnd === -1) {
            result.push([false, resultIndexCounter++, word]);
            continue;
        }

        // Current word is before the current occurrence, word added to result normally.
        if (wordEnd < currentOccurrenceStart) {
            result.push([false, resultIndexCounter++, word]);
            continue;
        }

        // Current word is inside the current occurrence, word added to the combined word.
        if (wordEnd >= currentOccurrenceStart && wordStart <= currentOccurrenceEnd) {
            currentOccurrenceWords.push(word);
            continue;
        }

        // Current word is after the current occurrence, the current occurance is completed, retry word.
        if (wordStart > currentOccurrenceEnd) {
            // Combine the words with occurance into one and add to result.
            result.push([true, resultIndexCounter++, currentOccurrenceWords.join(' ')]);
            currentOccurrenceWords = [];

            // Move to the next occurrence.
            currentOccurrenceIndex++;
            if (currentOccurrenceIndex < occurrences.length) {
                [currentOccurrenceStart, currentOccurrenceEnd] = occurrences[currentOccurrenceIndex];
            } else {
                [currentOccurrenceStart, currentOccurrenceEnd] = [-1, -1];
            }

            // Recheck the same word with the new current occurrence.
            wordIndex--;
        }
    }

    // Add the last occurrence words if any.
    if (currentOccurrenceWords.length > 0) {
        result.push([true, resultIndexCounter++, currentOccurrenceWords.join(' ')]);
    }

    return result;
}


/**
 * Splits the text into words and returns pairs of [starting index, word].
 * @param {string} text - The original text.
 * @returns {Array<[number, string]>} - Array of pairs [starting index, word].
 */
function splitTextWithIndices(text) {
    const words = [];
    const regex = /\S+/g; // Matches any non-whitespace sequence.

    let match;
    while ((match = regex.exec(text)) !== null) {
        words.push([match.index, match[0]]);
    }

    return words;
}


/**
 * Transforms occurrences into pairs of start/end information.
 * @param {number[]} positions - Array of start positions.
 * @param {number} length - Length of each term.
 * @returns {number[][]} Array of pairs [start, end].
 */
function expandOccuranceData(positions, length) {
    if (positions.length === 0) {
        return [];
    }

    const result = [];
    let currentStart = positions[0];
    let currentEnd = currentStart + length;

    // In this for loop, positions[i] describes the next occurance.
    for (let i = 1; i < positions.length; i++) {
        if (currentEnd >= positions[i]) {
            // If currentEnd overlaps with the next start position, merge them.
            currentEnd = positions[i] + length;
        } else {
            // If no overlap, push the current start/end pair to the result.
            result.push([currentStart, currentEnd]);
            // Update currentStart and currentEnd to the new positions.
            currentStart = positions[i];
            currentEnd = currentStart + length;
        }
    }

    // Push the last start/end pair to the result.
    result.push([currentStart, currentEnd]);

    return result;
}


/**
 * This Function returns a list of starting indexes for all occurances of the given term in the given content.
 * @param {string} content The text content to search in.
 * @param {string} term The search term to search for.
 * @returns Array of start indexes of the term occurances in the content.
 */
function getTermOccurances(content, term) {
    const occurances = [];
    let startIndex = 0;
    while ((startIndex = content.indexOf(term, startIndex)) !== -1) {
        occurances.push(startIndex);
        startIndex += 1;
    }
    return occurances;
}