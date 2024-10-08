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
 * Describes WebServices
 *
 * @package    block_booksearch
 * @copyright  2022 University of Stuttgart <kasra.habib@iste.uni-stuttgart.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$functions = [
    // Info: local_PLUGINNAME_FUNCTIONNAME is the name of the web service function that the client will call.
    'block_booksearch_search_course_book_content' => [

        // Class containing the external function.
        'classname'     => 'block_booksearch\external\search_book_content',

        // This documentation will be displayed in the generated API documentation.
        // Administration > Plugins > Webservices > API documentation.
        'description'   => 'Search valid (see README.md) books in a course you can access for a given term.',

        // The value is 'write' if your function does any database change, otherwise it is 'read'.
        'type'          => 'read',

        // True/False if you allow this web service function to be callable via ajax.
        'ajax'          => false,

        // List the capabilities required by the function (those in a require_capability() call).
        // Missing capabilities are displayed for authorised users.
        // And also for manually created tokens in the web interface, this is just informative.
        'capabilities'  => 'block/booksearch:searchservice',

        // Optional, only available for Moodle 3.1 onwards.
        // List of built-in services (by shortname) where the function will be included.
        // Services created manually via the Moodle interface are not supported.
        'services'      => [],
    ],
];
