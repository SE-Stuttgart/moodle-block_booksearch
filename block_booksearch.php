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
 * Block core and UI
 *
 * @package    block_booksearch
 * @copyright  2022 University of Stuttgart <kasra.habib@iste.uni-stuttgart.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_booksearch\data\data;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/locallib.php');

define('BLOCK_BOOKSEARCH_TARGET_ID_PARAM', 'booksearchcourseid');

/**
 * The booksearch block class.
 *
 * Used to create the booksearch block. Base of all booksearch block functionality & UI.
 */
class block_booksearch extends block_base {
    /**
     * Set the initial properties for the block
     */
    public function init() {
        $this->title = get_string('pluginname', get_class($this));
    }


    /**
     * Allow multiple instances of this block
     * @return bool Returns false
     */
    public function instance_allow_multiple() {
        return false;
    }


    /**
     * Where can this Block be placed
     * @return array Context level where this block can be placed
     */
    public function applicable_formats() {
        return [
            'admin' => false,
            'site-index' => false,
            'course-view' => true,
            'mod' => true,
            'my' => true,
        ];
    }


    /**
     * Describe Block Content.
     */
    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }

        // Register the stylesheet.
        $this->page->requires->css('/blocks/booksearch/styles.css');

        // Main Content (text) and Footer of the block.
        $text = '';
        $footer = '';

        switch ($this->page->context->contextlevel) {
            case CONTEXT_USER:
                self::handle_user_context($text, $footer);
                break;
            case CONTEXT_COURSE:
                self::handle_course_context($text, $footer);
                break;
            case CONTEXT_MODULE:
                self::handle_module_context($text, $footer);
                break;
            default:
                break;
        }

        $this->content = new stdClass();
        $this->content->text = $text;
        $this->content->footer = $footer;
        return $this->content;
    }


    /**
     * Behavior of this block when on the dashboard (user context).
     * @param string $text This is the main block ui.
     * @param string $footer This is the footer of the Block ui.
     */
    private function handle_user_context(&$text, &$footer) {
        global $USER;

        // As this context does not have a fixed course, display a course selection.
        self::add_course_selection_ui($text);

        // Get a selected courseid if one was selected, -1 if not.
        $courseid = self::get_selected_course_id();

        // If there is no active selected course, we do not display anything else.
        if ($courseid < 0) {
            return;
        }

        // Check if we have access to the selected course.
        list($isvalid, $course, $error) = block_booksearch_validate_course_access($courseid, $USER->id);

        // We do not have access, so we display an error message.
        if (!$isvalid) {
            $text .= get_string('error_message', get_class($this));
            $footer .= $error;
            return;
        }

        // We have valid access, so we display a search field and the results.
        self::add_search_and_results_ui($text, $footer, $course->id);
    }


    /**
     * Behavior of this block when on the main course view (course context).
     * @param string $text This is the main block ui.
     * @param string $footer This is the footer of the Block ui.
     */
    private function handle_course_context(&$text, &$footer) {
        // Get the course id.
        $courseid = $this->page->context->instanceid;

        // Add the search field and results to the final ui.
        self::add_search_and_results_ui($text, $footer, $courseid);
    }


    /**
     * Behavior of this block when viewing a course module (module context).
     * @param string $text This is the main block ui.
     * @param string $footer This is the footer of the Block ui.
     */
    private function handle_module_context(&$text, &$footer) {
        global $DB;

        // Get the module id.
        $moduleid = $this->page->context->instanceid;

        // Fetch the course module record from the database.
        $cm = $DB->get_record('course_modules', ['id' => $moduleid], 'course');

        // The course module for this module id could not be found, so we display an error message.
        if (!$cm) {
            $text .= get_string('database_error', get_class($this));
            return;
        }

        // Get courseid from coursemodule.
        $courseid = $cm->course;

        // Add the search field and results to the final ui.
        self::add_search_and_results_ui($text, $footer, $courseid);
    }


    /**
     * This function returns the course id of the current booksearch target, -1 if there is no target.
     * @return int Selected courseid or -1 if none selected.
     */
    private function get_selected_course_id(): int {
        $courseid = optional_param(BLOCK_BOOKSEARCH_TARGET_ID_PARAM, -1, PARAM_INT);
        return $courseid;
    }


    /**
     * This function adds the ui elements regarding the course selection to the given strings and returns them.
     * @param string $text This string has the main Block content UI.
     * @param string $footer This string has the Blocks footer UI.
     */
    private function add_course_selection_ui(string &$text) {
        global $OUTPUT;

        // Display the drop down course selector.
        $text .= $OUTPUT->render_from_template('block_booksearch/course_selector', [
            'action' => $this->page->url,
            'course_selector_param_name' => BLOCK_BOOKSEARCH_TARGET_ID_PARAM,
            'course_selector_options' => self::get_select_course_options(self::get_selected_course_id()),
        ]);
    }


    /**
     * This function adds the ui elements regarding the book search input and results to the given strings and returns them.
     * @param string $text This string has the main Block content UI.
     * @param string $footer This string has the Blocks footer UI.
     * @param int $courseid This is the id of the course we want to search in.
     */
    private function add_search_and_results_ui(string &$text, string &$footer, int $courseid) {
        global $OUTPUT;

        // Info: $content has the attributes section, filename, page, bookurl, size, content.
        list($content, $misconfiguredcontentinfo) = data::get_course_content($courseid);

        // Add a list of names of the misconfigured chapters to the block footer.
        if (!empty($misconfiguredcontentinfo)) {
            $footer .= get_string('misconfigured_info', get_class($this));
            foreach ($misconfiguredcontentinfo as $key => $value) {
                $footer .= '<br>';
                $footer .= $value;
            }
        }

        // Display the search input and results.
        $text .= $OUTPUT->render_from_template('block_booksearch/search', [
            'search_term_placeholder' => get_string('search', get_class($this)),
            'search_term_label' => get_string('search_term', get_class($this)),
            'chapter_label' => get_string('chapter', get_class($this)),
            'course_content' => base64_encode(json_encode($content)),
        ]);
    }

    /**
     * Create and Return an (id => fullname) array for all courses the current user can access.
     * @param int $courseid ID of a course. The selected course is at the beginning of the array, else a selection method.
     * @return array Array of courses the current user has access to. Position 1 is either selected course or selection message.
     */
    private function get_select_course_options(int $courseid = -1) {
        $courses = [];

        foreach (get_courses() as $course) {
            if (can_access_course($course)) {
                $courses[$course->id] = (object)['id' => $course->id, 'value' => $course->fullname];
            }
        }

        if ($courseid > 0) {
            try {
                if (can_access_course($course = get_course($courseid))) {
                    unset($courses[$courseid]);
                    array_unshift($courses, (object)['id' => $course->id, 'value' => $course->fullname]);
                }
            } catch (\Throwable $th) {
                throw $th;
                return [];
            }
        } else {
            array_unshift($courses, (object)['id' => 0, 'value' => get_string('select_course', 'block_booksearch')]);
        }

        return $courses;
    }
}
