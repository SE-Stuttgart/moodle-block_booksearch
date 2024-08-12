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
 * @module     block_booksearch/display
 * @copyright  2024 University of Stuttgart <kasra.habib@iste.uni-stuttgart.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { render } from 'core/templates';

/**
 * Processes and Displays the search results inside the given element.
 * @param {HTMLElement} element The elemtent to display the results inside.
 * @param {Object} searchResults The search results to display as [filename[chapter{bookurl, context}]].
 * @param {String} chapterLabel The label meaning "Chapter" in the active language.
 */
export function displayResults(element, searchResults, chapterLabel) {
    const data = preprocessSearchResults(searchResults, chapterLabel);
    render('block_booksearch/display', data).then((html) => {
        element.innerHTML = html;
    }).catch(ex => {
        window.console.error('Template rendering failed: ', ex);
    });
}


/**
 * This function processes the searchResults, so they are usable for the template.
 * @param {Object} searchResults The search results to display as [filename[chapter{bookurl, context}]].
 * @param {String} chapterLabel The label meaning "Chapter" in the active language.
 * @returns Array of search results processed to be perfectly used by the template.
 */
function preprocessSearchResults(searchResults, chapterLabel) {
    const data = [];

    for (const pdfName in searchResults) {
        if (!searchResults.hasOwnProperty(pdfName)) {
            continue;
        }
        const chapters = [];

        for (const chapter in searchResults[pdfName]) {
            if (!searchResults[pdfName].hasOwnProperty(chapter)) {
                continue;
            }
            chapters.push({
                chapter: chapter,
                bookurl: searchResults[pdfName][chapter].bookurl,
                context: searchResults[pdfName][chapter].context,
                chapterLabel: chapterLabel
            });

        }

        data.push({
            filename: pdfName,
            chapters: chapters
        });

    }

    return { data: data };
}