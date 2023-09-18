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
 * @package    block_slidefinder
 * @copyright  2022 Universtity of Stuttgart <kasra.habib@iste.uni-stuttgart.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Config.
$string['pluginname'] = 'Slide finder';

// Block.
$string['search_term'] = 'Search term: ';
$string['chapter'] = 'Chapter';
$string['misconfigured_info'] = 'The following files are flagged as matching but have not been set up correctly. '
    . 'Maybe there is a chapter count mismatch between book and pdf?';

// Search Field.
$string['search'] = 'Search keyword...';
$string['select_course'] = 'Select course...';

// Capabilities.
$string['block_slidefinder:myaddinstance'] = 'Add a new slide finder block to my moodle Dashboard.';
$string['block_slidefinder:addinstance'] = 'Add a new slide finder block to this page.';

// Error.
$string['error_message'] = 'There was a problem. Please contact the Plugin creator and send him the error. This is the error:';
$string['error_user_not_found'] = 'User does not exist.';
$string['error_course_not_found'] = 'Course is misconfigured';
$string['error_course_access_denied'] = 'Access to course denied.';
$string['error_book_pdf_mismatch'] = 'There exists an mismatch of book and pdf.';
