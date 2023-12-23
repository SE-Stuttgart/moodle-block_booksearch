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
 * Return the content & link of all chapters that are part of an eliganble book-pdf match in the given course.
 *
 * @param int $courseid ID of the course to be searched
 * @param int $userid ID of the user initiating the search
 *
 * @return array [0] list of chapters (content, link, other metadata). One chapter for each eligable book chaper in course.
 * @return array [1] list of filenames of intended eligable pairs that have a problem
 */
function block_slidefinder_get_content_as_chapters_for_all_book_pdf_matches_from_course($courseid, $userid) {
    global $DB;

    $coursechapters = array();
    $misconfiguredcoursechapters = array();

    try {
        // Course.
        if (!$course = $DB->get_record('course', array('id' => $courseid))) {
            throw new moodle_exception(get_string('error_course_not_found', 'block_slidefinder'));
        }
        // Does the user have access to the course?
        if (!can_access_course($course, $userid)) {
            throw new moodle_exception(get_string('error_course_access_denied', 'block_slidefinder'));
        }
    } catch (\Throwable $th) {
        debugging($th);
        return [$coursechapters, $misconfiguredcoursechapters];
    }

    $matches = block_slidefinder_get_all_book_pdf_matches_from_course($course);

    foreach ($matches as $match) {
        $matchchapters = block_slidefinder_get_content_as_chapters($match);
        if (!is_null($matchchapters) && !empty($matchchapters)) {
            $coursechapters = array_merge($coursechapters, $matchchapters);
        } else {
            $misconfiguredcoursechapters[] = $match->filename;
        }
    }

    return [$coursechapters, $misconfiguredcoursechapters];
}

/**
 * Return a list of all eligable book-pdf matches in a given course.
 *
 * @param mixed $course course to search in
 *
 * @return array list of matches as objects containing pdf file information and bookid
 */
function block_slidefinder_get_all_book_pdf_matches_from_course($course) {
    // Get all PDFs from course.
    $fs = get_file_storage();
    $pdfs = array();
    foreach (get_all_instances_in_course('resource', $course) as $resource) {
        // Get all resources.
        $cm = get_coursemodule_from_instance('resource', $resource->id, $resource->course, false, MUST_EXIST);
        $files = $fs->get_area_files(
            context_module::instance($cm->id)->id,
            'mod_resource',
            'content',
            0,
            'sortorder DESC, id ASC',
            false
        );
        if (count($files) < 1) {
            resource_print_filenotfound($resource, $cm, $course);
            die;
        } else {
            $file = reset($files);
            unset($files);
        }

        // Only allow PDFs.
        if ($file->get_mimetype() != 'application/pdf') {
            continue;
        }

        $r = new stdClass();
        $r->pathnamehash = $file->get_pathnamehash();
        $r->filename = $file->get_filename();
        $r->section = $resource->section;
        $r->resourcename =
            trim(preg_replace('/\s*\[[^]]*\](?![^[]*\[)/', '', preg_replace('/\s*\([^)]*\)(?![^(]*\()/', '', $resource->name)));
        $pdfs[] = $r;
    }

    // Get all books from course.
    $sectionedbooks = array();
    $books = get_all_instances_in_course('book', $course);
    foreach ($books as $book) {
        $sectionedbooks[$book->section][$book->id] =
            trim(preg_replace('/\s*\[[^]]*\](?![^[]*\[)/', '', preg_replace('/\s*\([^)]*\)(?![^(]*\()/', '', $book->name)));
    }

    // Get all book-PDF matches.
    $matches = array();
    foreach ($pdfs as $pdf) {
        if (!isset($sectionedbooks[$pdf->section])) {
            continue;
        }
        $pdf->bookid = array_search($pdf->resourcename, $sectionedbooks[$pdf->section]);
        if ($pdf->bookid) {
            $matches[] = $pdf;
        }
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
function block_slidefinder_get_content_as_chapters($match) {
    $chapters = array();

    try {
        $fs = get_file_storage();

        $config = new \Smalot\PdfParser\Config();
        $config->setHorizontalOffset('');
        $pdfparser = new \Smalot\PdfParser\Parser([], $config);

        $file = $fs->get_file_by_hash($match->pathnamehash);
        if ($file->get_mimetype() != 'application/pdf') {
            return $chapters;
        }

        $pdf = $pdfparser->parseContent($file->get_content());
        $pdfdetails = $pdf->getDetails();
        $pages = $pdf->getPages();

        for ($i = 0; $i < $pdfdetails['Pages']; $i++) {
            $chapter = new stdClass();
            $chapter->filename = str_replace('.pdf', get_string('pdf_replace', 'block_slidefinder'), $match->filename);
            $chapter->section = $match->section;
            $chapter->page = $i + 1;
            $chapter->content = $pages[$i]->getText();
            $chapter->bookurl = block_slidefinder_get_book_chapter_url($match->bookid, $i + 1);
            $chapters[] = $chapter;
        }
    } catch (\Throwable $th) {
        gc_collect_cycles();
        debugging($th);
        return null;
    }

    gc_collect_cycles();
    return $chapters;
}

/**
 * Create and return an url linking to a specific book chapter.
 *
 * @param int $bookid id of the book
 * @param int $pagenum chapter number / pdf page num
 *
 * @return string url linking to the book chapter
 */
function block_slidefinder_get_book_chapter_url($bookid, $pagenum) {
    global $DB;

    $booktypeid = $DB->get_field('modules', 'id', ['name' => 'book'], MUST_EXIST);
    $cmid = $DB->get_field('course_modules', 'id', ['module' => $booktypeid, 'instance' => $bookid], MUST_EXIST);
    $chapterid = $DB->get_field('book_chapters', 'id', ['bookid' => $bookid, 'pagenum' => $pagenum], MUST_EXIST);

    $url = new moodle_url('/mod/book/view.php', ['id' => $cmid, 'chapterid' => $chapterid]);

    return $url->out(false);
}

/**
 * Create and Return an (id => fullname) array for all courses the current user can access.
 * @param int $cid ID of a course. The selected course is at the beginning of the array, else a selection method.
 * @return array Array of courses the current user has access to. Position 1 is either selected course or selection message.
 */
function block_slidefinder_select_course_options(int $cid = 0) {
    $courses = array();

    foreach (get_courses() as $course) {
        if (can_access_course($course)) {
            $courses[$course->id] = (object)['id' => $course->id, 'value' => $course->fullname];
        }
    }

    if ($cid > 0) {
        try {
            if (can_access_course($course = get_course($cid))) {
                unset($courses[$cid]);
                array_unshift($courses, (object)['id' => $course->id, 'value' => $course->fullname]);
            }
        } catch (\Throwable $th) {
            throw $th;
            return array();
        }
    } else {
        array_unshift($courses, (object)['id' => 0, 'value' => get_string('select_course', 'block_slidefinder')]);
    }

    return $courses;
}
