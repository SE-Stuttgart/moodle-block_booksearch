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
 * Helper functions for the block_booksearch Plugin
 *
 * @package    block_booksearch
 * @copyright  2022 Universtity of Stuttgart <kasra.habib@iste.uni-stuttgart.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_reportbuilder\external\reports\retrieve;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/pdfparser/alt_autoload.php-dist');

/**
 * Return the content for all elligable Book to Pdf matches.
 *
 * Return[0]:
 * The content is returned in sections.
 * A section is a sentece or part of the Pdf that fits together.
 * Each section contains the content as text and some metadata.
 * The metadata is:
 *  - section: The moodle course section this pdf/book match appears on.
 *  - filename: The name of the Pdf this section appears on.
 *  - page: The page number this section appears on.
 *  - bookurl: The url linking to the matching book-chapter this section appears on.
 *  - text: The text content of this section.
 *
 * Return[1]:
 * Additionally returns a list of filenames that are intended to match to a book but have an error in the setup.
 *
 * @param int $courseid ID of the course to be searched
 * @param int $userid ID of the user initiating the search
 *
 * @return array [0] list of logical sections of content (section, filename, page, bookurl, text).
 * @return array [1] list of filenames of intended eligable pairs that have a problem
 */
function block_booksearch_get_all_content_of_course_as_sections_with_metadata($courseid, $userid) {
    global $DB;

    // Array of pdf_chapter metadata and content of all book to pdf matches in the given course.
    $sections = [];
    // Array of pdf_chapter metadata of all book to pdf matches with some misconfigurations in the given course.
    $misconfiguredmatches = [];

    try {
        // Course.
        if (!$course = $DB->get_record('course', ['id' => $courseid])) {
            throw new moodle_exception(get_string('error_course_not_found', 'block_booksearch'));
        }
        // Does the user have access to the course?
        if (!can_access_course($course, $userid)) {
            throw new moodle_exception(get_string('error_course_access_denied', 'block_booksearch'));
        }
    } catch (\Throwable $th) {
        debugging($th);
        return [$sections, $misconfiguredmatches];
    }

    try {
        // Get the Book to Pdf matches that exist. Array of metadata for each match.
        $matches = block_booksearch_get_all_book_pdf_matches_from_course($course);
    } catch (\Throwable $th) {
        debugging($th);
        gc_collect_cycles();
        return [$sections, $misconfiguredmatches];
    }

    foreach ($matches as $match) {
        try {
            // Split each pdf content into logical sections containing text and metadata.
            $pagesections = block_booksearch_get_content_as_sections($match);
            if (!is_null($pagesections) && !empty($pagesections)) {
                $sections = array_merge($sections, $pagesections);
            } else {
                $misconfiguredmatches[] = $match->filename;
            }
        } catch (\Throwable $th) {
            debugging($th);
            $misconfiguredmatches[] = $match->filename;
            gc_collect_cycles();
        }
    }

    return [$sections, $misconfiguredmatches];
}

/**
 * Return a list of all eligable book-pdf matches in a given course.
 *
 * @param mixed $course course to search in
 *
 * @return array list of matches as objects containing pdf file information and bookid
 */
function block_booksearch_get_all_book_pdf_matches_from_course($course) {
    // Get all PDFs from course.
    $fs = get_file_storage();
    $pdfs = [];
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
    $sectionedbooks = [];
    $books = get_all_instances_in_course('book', $course);
    foreach ($books as $book) {
        $sectionedbooks[$book->section][$book->id] =
            trim(preg_replace('/\s*\[[^]]*\](?![^[]*\[)/', '', preg_replace('/\s*\([^)]*\)(?![^(]*\()/', '', $book->name)));
    }

    // Get all book-PDF matches.
    $matches = [];
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
 * Return an array of logical sections based on each page of the given pdf/book match.
 * A section is a sentece or part of the Pdf page that fits together.
 * Each section contains the content as text and some metadata.
 * The metadata is:
 *  - section: The moodle course section this pdf/book match appears on.
 *  - filename: The name of the Pdf this section appears on.
 *  - page: The page number this section appears on.
 *  - bookurl: The url linking to the matching book-chapter this section appears on.
 *  - text: The text content of this section.
 *
 * @param mixed $match an object containing metadata of one pdf-book match.
 *
 * @return array list of logical sections of content (section, filename, page, bookurl, text).
 */
function block_booksearch_get_content_as_sections($match) {
    $sections = [];

    $fs = get_file_storage();

    $config = new \Smalot\PdfParser\Config();
    $config->setRetainImageContent(false);
    $config->setHorizontalOffset('');
    $config->setFontSpaceLimit(-600);
    $pdfparser = new \Smalot\PdfParser\Parser([], $config);

    $file = $fs->get_file_by_hash($match->pathnamehash);
    if ($file->get_mimetype() != 'application/pdf') {
        return $sections;
    }

    $pdf = $pdfparser->parseContent($file->get_content());
    gc_collect_cycles();

    // Create a list of pages, where each page is a combination of match and pdf metadata for one pdf page.
    $pages = block_booksearch_get_pdf_metadata_as_pages($pdf, $match);

    // Split the list of pages (with metadata) into smaller logical sections containing metadata and text content.
    foreach ($pages as $page) {
        $sections = array_merge($sections, block_booksearch_get_page_as_sections_with_content($page));
    }

    gc_collect_cycles();
    return $sections;
}

/**
 * Create a list of pages with metadata from a given match and parsed pdf.
 *
 * @param mixed $pdf object containing the parsed information (content and metadata) of the pdf.
 * @param mixed $match object containing metadata of the book/pdf match.
 *
 * @return array of pages, each with metadata combined from match and parsed pdf and representing one pdf page.
 */
function block_booksearch_get_pdf_metadata_as_pages($pdf, $match) {
    $pages = [];
    $pdfdetails = $pdf->getDetails();

    for ($i = 0; $i < $pdfdetails['Pages']; $i++) {
        $page = new stdClass();
        $page->section = $match->section;
        $page->filename = str_replace('.pdf', get_string('pdf_replace', 'block_booksearch'), $match->filename);
        $page->page = $i + 1;
        $page->bookurl = block_booksearch_get_book_chapter_url($match->bookid, $i + 1);
        $page->content = $pdf->getPages()[$i];
        $pages[] = $page;
    }

    return $pages;
}

/**
 * Split a page (with metadata) into smaller logical sections containing metadata and text content.
 *
 * @param mixed $page The page to be split.
 *
 * @return array The given page split into smaller sections (with metadata).
 */
function block_booksearch_get_page_as_sections_with_content($page) {
    $sections = [];

    // List of subsections/subsentences of text with metadata like size.
    $subsections = block_booksearch_get_sub_sections_from_page($page);
    gc_collect_cycles();

    $currentsection = null;

    foreach ($subsections as $subsection) {
        $isseperator = block_booksearch_text_is_seperator($subsection->content);
        if (is_null($currentsection)) {
            if (!$isseperator) {
                $currentsection = $subsection;
            }
            continue;
        }
        if ($isseperator) {
            $sections[] = $currentsection;
            $currentsection = null;
            continue;
        }
        if ($currentsection->size !== $subsection->size) {
            $sections[] = $currentsection;
            $currentsection = $subsection;
            continue;
        }
        // The current section and current subsection belong together.
        $currentsection->content .= " " . $subsection->content;
    }
    if (!is_null($currentsection)) {
        $sections[] = $currentsection;
        $currentsection = null;
    }

    gc_collect_cycles();
    return $sections;
}

/**
 * For a given parsed pdf document. Create a list of subsections/subsentences of text with metadata like size.
 *
 * @param mixed $page document of a parsed pdf.
 *
 * @return array list of subsections/subsentences of text with metadata like size for the given page.
 */
function block_booksearch_get_sub_sections_from_page($page) {
    // Subsections do no longer contain the end of a sentence inside the text.
    $subsections = [];
    $endofsentencepattern = '/(?<=[.?!;:])\s+/';

    // Get pdf content as lines with metadata: [0]: Metadata, [1]: Text.
    $lines = $page->content->getDataTm();

    foreach ($lines as $line) {
        $subsection = new stdClass();
        $subsection->section = $page->section;
        $subsection->filename = $page->filename;
        $subsection->page = $page->page;
        $subsection->bookurl = $page->bookurl;
        $subsection->size = array_slice($line[0], 0, 4);

        // Split the line into subsections.
        $subtexts = preg_split($endofsentencepattern, $line[1], -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

        foreach ($subtexts as $text) {
            $subsection->content = $text;
            $subsections[] = $subsection;
        }
    }

    gc_collect_cycles();
    return $subsections;
}

/**
 * Create and return an url linking to a specific book chapter.
 *
 * @param int $bookid id of the book
 * @param int $pagenum chapter number / pdf page num
 *
 * @return string url linking to the book chapter
 */
function block_booksearch_get_book_chapter_url($bookid, $pagenum) {
    global $DB;

    $booktypeid = $DB->get_field('modules', 'id', ['name' => 'book'], MUST_EXIST);
    $cmid = $DB->get_field('course_modules', 'id', ['module' => $booktypeid, 'instance' => $bookid], MUST_EXIST);
    $chapterid = $DB->get_field('book_chapters', 'id', ['bookid' => $bookid, 'pagenum' => $pagenum], MUST_EXIST);

    $url = new moodle_url('/mod/book/view.php', ['id' => $cmid, 'chapterid' => $chapterid]);

    return $url->out(false);
}

/**
 * Checks if the given text counts as a seperator.
 *
 * @param string $text given text to check.
 *
 * @return bool true if it is a seperator.
 */
function block_booksearch_text_is_seperator($text) {
    return strlen($text) <= 2;
}

/**
 * Create and Return an (id => fullname) array for all courses the current user can access.
 * @param int $cid ID of a course. The selected course is at the beginning of the array, else a selection method.
 * @return array Array of courses the current user has access to. Position 1 is either selected course or selection message.
 */
function block_booksearch_select_course_options(int $cid = 0) {
    $courses = [];

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
            return [];
        }
    } else {
        array_unshift($courses, (object)['id' => 0, 'value' => get_string('select_course', 'block_booksearch')]);
    }

    return $courses;
}
