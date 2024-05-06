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
 * @copyright  2022 Universtity of Stuttgart <kasra.habib@iste.uni-stuttgart.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/locallib.php');

define('BLOCK_BOOKSEARCH_BOOKSEARCH_PARAM', 'booksearchid');

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

        // Params.
        $cid = optional_param('id', 0, PARAM_INT);          // Do we have a set course id? Or are we on our dashboard (default).
        $booksearchid = optional_param(BLOCK_BOOKSEARCH_BOOKSEARCH_PARAM, 0, PARAM_INT); // Selected course ID.
        $search = optional_param('search', '', PARAM_TEXT); // Searched pattern (search hook).

        // Main Content (text) and Footer of the block.
        $text = '';
        $footer = '';

        try {
            if ($cid == 0) { // My Moodle Page.
                if ($booksearchid != 0) {
                    // Course.
                    if (!$course = $DB->get_record('course', ['id' => $booksearchid])) {
                        throw new moodle_exception(get_string('error_course_not_found', 'block_booksearch'));
                    }
                    // Does the user have access to the course?
                    if (!can_access_course($course)) {
                        throw new moodle_exception(get_string('error_course_access_denied', 'block_booksearch'));
                    }
                } else {
                    $course = null;
                }
                $text .= $OUTPUT->render_from_template('block_booksearch/block_booksearch_drop_down', [
                    'action' => $this->page->url,
                    'course_selector_param_name' => BLOCK_BOOKSEARCH_BOOKSEARCH_PARAM,
                    'course_selector_options' => block_booksearch_select_course_options($booksearchid),
                ]);
            } else { // Course Page.
                // Course.
                if (!$course = $DB->get_record('course', ['id' => $cid])) {
                    throw new moodle_exception(get_string('error_course_not_found', 'block_booksearch'));
                }
                // Does the user have access to the course?
                if (!can_access_course($course)) {
                    throw new moodle_exception(get_string('error_course_access_denied', 'block_booksearch'));
                }
            }

            // Info: data[0] has the attributes section, filename, page, bookurl, size, content.
            $data = [[], []];
            if (!is_null($course)) {
                $data = block_booksearch_get_all_content_of_course_as_sections_with_metadata($course->id, $USER->id);
                if (!empty($data[1])) {
                    $footer .= get_string('misconfigured_info', get_class($this));
                    foreach ($data[1] as $key => $value) {
                        $footer .= '<br>';
                        $footer .= $value;
                    }
                }
            }

            $text .= $OUTPUT->render_from_template('block_booksearch/block_booksearch_search', [
                'action' => $this->page->url,
                'cid' => $booksearchid,
                'course_selector_param_name' => BLOCK_BOOKSEARCH_BOOKSEARCH_PARAM,
                'search_term_param_name' => 'search',
                'search_term_placeholder' => get_string('search', get_class($this)),
                'search_term_label' => get_string('search_term', get_class($this)),
                'search_term' => $search,
                'chapter_label' => get_string('chapter', get_class($this)),
                'content' => base64_encode(json_encode($data[0])),
            ]);
        } catch (\Throwable $th) {
            debugging($th);
            $text .= get_string('error_message', get_class($this));
            $footer .= $th;
        }

        $this->content = new stdClass();
        $this->content->text = $text;
        $this->content->footer = $footer;
        return $this->content;
    }
}
