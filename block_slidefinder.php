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
 * @package    block_slidefinder
 * @copyright  University of Stuttgart
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

class block_slidefinder extends block_base
{
    /**
     * Set the initial properties for the block
     */
    function init()
    {
        $this->blockname = get_class($this);
        $this->title = get_string('pluginname', $this->blockname);
    }

    /**
     * Describe Block Content
     */
    public function get_content()
    {
        global $PAGE, $CFG, $DB, $USER;


        // Context
        $systemcontext = context_system::instance();
        $usercontext = context_user::instance($USER->id);

        // Params
        $cid = optional_param('id', 0, PARAM_INT);              // Do we have a set course id? Or are we on our dashboard (default).
        $lrf_cid = optional_param('lrf_cid', $cid, PARAM_INT);  // Selected course ID (by our course selection).
        $search = optional_param('search', '', PARAM_TEXT);     // Searched pattern (search hook).

        $course = null;     // Course to search in.
        $course_id = 0;     // Course ID of searched course.

        $view_course_selection = false;     // Are we displaying the course selection?
        $view_selected_course = false;      // Are we displaying the search field? Did we select a course? Are we allowed to search said course?

        // Get Course and CourseID by parameter
        if ($course = $DB->get_record('course', array('id' => $lrf_cid))) {
            $course_id = $course->id;
            $coursecontext = context_course::instance($course->id);
        }

        // Renderer needed to use templates
        $renderer = $PAGE->get_renderer($this->blockname);

        $view_course_selection = !$cid;
        $view_selected_course = $course_id ? block_lrf_enrolled_in($USER->id, $course_id) : false;

        // Main Content (text) and Footer of the block
        $text = '';
        $footer = '';


        if ($view_course_selection) {
            $text .= $renderer->render_from_template('block_slidefinder/lrf_drop_down', [
                'action' => $PAGE->url,
                'course_selector_param_name' => 'lrf_cid',
                'course_selector_options' => block_lrf_select_course_options($course_id, $USER->id),
            ]);
        }
        if ($view_selected_course) {
            $text .= $renderer->render_from_template('block_slidefinder/lrf_search', [
                'action' => $PAGE->url,
                'cid' => $cid,
                'lrf_cid' => $lrf_cid,
                'course_selector_param_name' => 'lrf_cid',
                'search_term_param_name' => 'search',
                'search_term_placeholder' => get_string('search', $this->blockname),
                'search_term_label' => get_string('search_term', $this->blockname),
                'search_term' => $search,
                'chapter_label' => get_string('chapter', $this->blockname),
                'content' => base64_encode(json_encode($this->get_pdfs_content_from_course($course)))
            ]);
        }

        $this->content = new stdClass();
        $this->content->text = $text;
        $this->content->footer = $footer;
        return $this->content;
    }

    /**
     * Returns the PDFs and their content (splitted in pages) for all eligable PDFs in the given course.
     *
     * @param mixed $course course to search in.
     *
     * @return array array of objects each holding one pdf page on content and some metadata
     */
    function get_pdfs_content_from_course($course): array
    {
        if ($course == null) return [];

        $chapters = array();

        $matches = block_lrf_get_all_book_pdf_matches_from_course($course);

        foreach ($matches as $match) {
            $chapters = array_merge($chapters, block_lrf_get_content_as_chapters($match));
        }

        return $chapters;
    }
}
