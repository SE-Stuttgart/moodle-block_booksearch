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
 * Language settings english.
 *
 * @package    block_booksearch
 * @copyright  2022 University of Stuttgart <kasra.habib@iste.uni-stuttgart.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Config.
$string['pluginname'] = 'Book Search';
$string['privacy:metadata'] = 'The Book Search block only displays/returns and links to existing book/pdf data.';

// Block.
$string['search_term'] = 'Search term: ';
$string['chapter'] = 'Chapter';
$string['misconfigured_info'] = "The following files are flagged as matching but have not been set up correctly.
Maybe there is a chapter count mismatch between book and pdf?";
$string['pdf_replace'] = ' (Book)';

// Search Field.
$string['search'] = 'Search keyword...';
$string['select_course'] = 'Select course...';

// Capabilities.
$string['booksearch:myaddinstance'] = 'Add a new Book Search block to my moodle Dashboard.';
$string['booksearch:addinstance'] = 'Add a new Book Search block to this page.';
$string['booksearch:searchservice'] = 'Use the Book Search webservice.';

// Error.
$string['error_message'] = 'There was a problem. Please contact the Plugin creator and send him the error. This is the error:';
$string['error_user_not_found'] = 'User does not exist.';
$string['error_course_not_found'] = 'Course not found.';
$string['error_course_access_denied'] = 'Access to course denied.';
$string['error_book_pdf_mismatch'] = 'There exists an mismatch of book and pdf.';

// External Parameter Description.
$string['parameter_course_id'] = 'Id of the course the user wants to access.';
$string['parameter_search_string'] = 'String to search for in the course.';
$string['parameter_context_length'] = "A positive integer indicating the number of words on each side of the search result
 that get returned as additional context.";

// External Parameter Error.
$string['invalid_context_length'] = 'The parameter contextlength needs to be a positive integer.';
$string['invalid_course'] = 'The parameter courseid needs to be a valid course id, you have access to.';

// External Return Parameter Description.
$string['parameter_file_name'] = 'Name of the PDF file that has a matching book.';
$string['parameter_page_number'] = 'Page number where this searched occurrence happens in the filename book.';
$string['parameter_book_chapter_url'] = 'URL to the book chapter with the page number.';
$string['parameter_context_snippet'] = 'Text snippet around the occurrence.';
