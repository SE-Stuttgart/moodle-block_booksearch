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
 * Helper functions for the block_slidefinder Plugin
 *
 * @package    block_slidefinder
 * @copyright  2022 Universtity of Stuttgart <kasra.habib@iste.uni-stuttgart.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/pdfparser/alt_autoload.php-dist');

/**
 * Return a list of all eligable book-pdf matches in a given course.
 *
 * @param mixed $course course to search in
 *
 * @return array list of matches as objects containing pdf file information and book_id
 */
function block_lrf_get_all_book_pdf_matches_from_course($course)
{
    // Get all PDFs from course
    $fs = get_file_storage();
    $pdfs = array();
    foreach (get_all_instances_in_course('resource', $course) as $resource) {
        // Get all resources
        $cm = get_coursemodule_from_instance('resource', $resource->id, $resource->course, false, MUST_EXIST);
        $files = $fs->get_area_files(context_module::instance($cm->id)->id, 'mod_resource', 'content', 0, 'sortorder DESC, id ASC', false);
        if (count($files) < 1) {
            resource_print_filenotfound($resource, $cm, $course);
            die;
        } else {
            $file = reset($files);
            unset($files);
        }

        // Only allow PDFs
        if ($file->get_mimetype() != 'application/pdf') continue;

        $r = new stdClass();
        $r->pathnamehash = $file->get_pathnamehash();
        $r->filename = $file->get_filename();
        $r->section = $resource->section;
        $r->resourcename = trim(preg_replace('/\s*\[[^]]*\](?![^[]*\[)/', '', preg_replace('/\s*\([^)]*\)(?![^(]*\()/', '', $resource->name)));
        $pdfs[] = $r;
    }

    // Get all books from course
    $sectioned_books = array();
    $books = get_all_instances_in_course('book', $course);
    foreach ($books as $book) {
        $sectioned_books[$book->section][$book->id] = trim(preg_replace('/\s*\[[^]]*\](?![^[]*\[)/', '', preg_replace('/\s*\([^)]*\)(?![^(]*\()/', '', $book->name)));
    }

    // Get all book-PDF matches
    $matches = array();
    foreach ($pdfs as $pdf) {
        if (!isset($sectioned_books[$pdf->section])) continue;
        $pdf->bookid = array_search($pdf->resourcename, $sectioned_books[$pdf->section]);
        if ($pdf->bookid) $matches[] = $pdf;
    }

    return $matches;
}

/**
 * Return an array of objects each containing the content and some metadata of one PDF page of a given pdf-book match.
 *
 * @param mixed $match an object containing metadata of one pdf-book match.
 *
 * @return array list of objects containing the content and some metadata of one PDF page.
 */
function block_lrf_get_content_as_chapters($match)
{
    $fs = get_file_storage();

    $config = new \Smalot\PdfParser\Config();
    $config->setHorizontalOffset('');
    $pdf_parser = new \Smalot\PdfParser\Parser([], $config);

    $chapters = array();

    $file = $fs->get_file_by_hash($match->pathnamehash);
    if ($file->get_mimetype() != 'application/pdf') return $chapters;

    $pdf = $pdf_parser->parseContent($file->get_content());
    $pdf_details = $pdf->getDetails();
    $pages = $pdf->getPages();

    for ($i = 0; $i < $pdf_details['Pages']; $i++) {
        $chapter = new stdClass();
        $chapter->filename = $match->filename;
        $chapter->section = $match->section;
        $chapter->page = $i + 1;
        $chapter->content = $pages[$i]->getText();
        $chapter->book_url = block_lrf_get_book_chapter_url($match->bookid, $i + 1);
        $chapters[] = $chapter;
    }
    gc_collect_cycles();
    return $chapters;
}

/**
 * Create and return an url linking to a specific book chapter.
 *
 * @param int $book_id id of the book
 * @param int $pagenum chapter number / pdf page num
 *
 * @return string url linking to the book chapter
 */
function block_lrf_get_book_chapter_url($book_id, $pagenum)
{
    global $DB;

    $book_type_id = $DB->get_field('modules', 'id', ['name' => 'book'], MUST_EXIST);
    $cm_id = $DB->get_field('course_modules', 'id', ['module' => $book_type_id, 'instance' => $book_id], MUST_EXIST);
    $bc_id = $DB->get_field('book_chapters', 'id', ['bookid' => $book_id, 'pagenum' => $pagenum], MUST_EXIST);

    $url = new moodle_url('/mod/book/view.php', ['id' => $cm_id, 'chapterid' => $bc_id]);

    return $url->out(false);
}

/**
 * Returns a course with the given id, else selection of courses
 * @param int $id Given course id
 * @return array list of courses which user can choose from
 */
function block_lrf_select_course_options(int $selected_course_id, int $user_id)
{
    global $DB;

    // Array of possible courses
    $courses_shown = array();

    if ($user_id >= 0) {

        // get all course_id's for course user is enrolled in
        $params = array('userid' => $user_id);
        $sql = "SELECT e.courseid FROM  {enrol} e
            JOIN {user_enrolments} ue ON e.id = ue.enrolid
            WHERE ue.status = 0 AND ue.userid = :userid";
        $enrolled_courses = $DB->get_records_sql($sql, $params);

        // Create array of course_id's the user is enrolled in
        $eCourse_list = [];
        foreach ($enrolled_courses as $enrolled_course) {
            $eCourse_list[] = $enrolled_course->courseid;
        }

        // Create array of all courses the user is enrolled in
        foreach ($DB->get_records('course') as $course) {
            if (in_array($course->id, $eCourse_list)) $courses_shown[$course->id] = $course->fullname;
        }
    } else {
        // Create array for all courses independant if user is enrolled
        foreach ($DB->get_records('course') as $course) {
            $courses_shown[$course->id] = $course->fullname;
        }
    }

    // Array of course_id fullname pairs for displayal as a dropdown
    $courses_html = array();

    if ($selected_course_id) {
        $courses_html[0] = (object)['id' => $selected_course_id, 'value' => $courses_shown[$selected_course_id]];
        unset($courses_shown[$selected_course_id]);
    } else {
        $courses_html[0] = (object)['id' => 0, 'value' => get_string('select_course', 'block_slidefinder')];
    }

    foreach ($courses_shown as $key => $value) {
        $courses_html[] = (object)['id' => $key, 'value' => $value];
    }

    return $courses_html;
}

/**
 * Checks if a user is actively enrolled in a given course.
 * @param int $user_id ID of the user
 * @param int $course_id ID of the course
 */
function block_lrf_enrolled_in($user_id, $course_id)
{
    global $DB;

    $params = array('userid' => $user_id, 'courseid' => $course_id);
    $sql = "SELECT e.courseid FROM  {enrol} e
            JOIN {user_enrolments} ue ON e.id = ue.enrolid
            WHERE ue.status = 0 AND ue.userid = :userid AND e.courseid = :courseid";
    $enrolled_courses = $DB->get_records_sql($sql, $params);

    if (!$enrolled_courses) return false;
    return true;
}
