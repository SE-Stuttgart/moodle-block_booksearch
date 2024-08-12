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
     * Describe Block Content.
     */
    public function get_content() {
        global $OUTPUT, $DB, $USER;

        if ($this->content !== null) {
            return $this->content;
        }

        // Register the stylesheet.
        $this->page->requires->css('/blocks/booksearch/styles.css');

        // Main Content (text) and Footer of the block.
        $text = '';
        $footer = '';

        // Add the course target selection to the Block ui content if we are in the dashboard view.
        if (!self::in_course_view()) {
            list($text, $footer) = self::add_course_selection_ui($text, $footer);
        }

        // Add the booksearch ui to the Block ui content if we have a target course selected to search in.
        if (self::is_course_selected()) {
            list($isvalid, $course, $error) = block_booksearch_validate_course_access(self::get_selected_course_id(), $USER->id);

            if ($isvalid) {
                list($text, $footer) = self::add_search_and_results_ui($text, $footer);
            } else {
                $text .= get_string('error_message', get_class($this));
                $footer .= $error;
            }
        }

        $this->content = new stdClass();
        $this->content->text = $text;
        $this->content->footer = $footer;
        return $this->content;
    }


    /**
     * This function checks if we currently are in course view or on our dashboard.
     * It checks if the Paramater 'id' is set, which is the case for course view but not for dashboard.
     * @return bool True if we are in course view.
     */
    private function in_course_view(): bool {
        $currentcourseid = optional_param('id', 0, PARAM_INT);
        return $currentcourseid != 0;
    }


    /**
     * This function checks if there is a current selected target course to search.
     * @return bool True, if there is a target course selected.
     */
    private function is_course_selected(): bool {
        $currentcourseid = optional_param('id', 0, PARAM_INT);
        $searchcourseid = optional_param(BLOCK_BOOKSEARCH_TARGET_ID_PARAM, 0, PARAM_INT);
        // If at least one of the two parameters is not zero, there is a course selected.
        return 0 < $currentcourseid + $searchcourseid;
    }


    /**
     * This function returns the course id of the current booksearch target, 0 if there is no target.
     * @return int Target course id.
     */
    private function get_selected_course_id(): int {
        $searchcourseid = optional_param(BLOCK_BOOKSEARCH_TARGET_ID_PARAM, 0, PARAM_INT);
        // If $currentcourseid is not set (which means we are not in a course), we use our custom $searchcourseid.
        $currentcourseid = optional_param('id', $searchcourseid, PARAM_INT);
        return $currentcourseid;
    }


    /**
     * This function adds the ui elements regarding the course selection to the given strings and returns them.
     * @param string $text This string has the main Block content UI.
     * @param string $footer This string has the Blocks footer UI.
     * @return array [$text, $footer] - The updated $text and $footer with the course selection elements.
     */
    private function add_course_selection_ui(string $text, string $footer): array {
        global $OUTPUT;

        // Display the drop down course selector.
        $text .= $OUTPUT->render_from_template('block_booksearch/course_selector', [
            'action' => $this->page->url,
            'course_selector_param_name' => BLOCK_BOOKSEARCH_TARGET_ID_PARAM,
            'course_selector_options' => self::get_select_course_options(self::get_selected_course_id()),
        ]);

        return [$text, $footer];
    }


    /**
     * This function adds the ui elements regarding the book search input and results to the given strings and returns them.
     * @param string $text This string has the main Block content UI.
     * @param string $footer This string has the Blocks footer UI.
     * @return array [$text, $footer] - The updated $text and $footer with the search input and result elements.
     */
    private function add_search_and_results_ui(string $text, string $footer): array {
        global $OUTPUT;

        // Info: $content has the attributes section, filename, page, bookurl, size, content.
        list($content, $misconfiguredcontentinfo) = data::get_course_content(self::get_selected_course_id());

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

        return [$text, $footer];
    }

    /**
     * Create and Return an (id => fullname) array for all courses the current user can access.
     * @param int $courseid ID of a course. The selected course is at the beginning of the array, else a selection method.
     * @return array Array of courses the current user has access to. Position 1 is either selected course or selection message.
     */
    private function get_select_course_options(int $courseid = 0) {
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
