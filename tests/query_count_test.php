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
 * Tests that loading galleries and searching don't run queries for every row.
 *
 * @package   mod_mediagallery
 * @copyright 2026 Open LMS
 * @author    Adam Olley <adam.olley@openlms.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_mediagallery;

/**
 * Tests that query counts stay the same as galleries and items are added.
 *
 * @copyright 2026 Open LMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \mod_mediagallery\gallery::get_items
 * @covers    \mod_mediagallery\mcsearch
 * @covers    \mod_mediagallery\collection
 */
final class query_count_test extends \advanced_testcase {

    /** @var \stdClass */
    private $course;

    /** @var \stdClass */
    private $collection;

    /** @var \stdClass */
    private $student;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->collection = $this->getDataGenerator()->create_module('mediagallery',
            ['course' => $this->course->id, 'colltype' => 'contributed']);
        $this->setUser($teacher);
    }

    public function test_get_items_loads_each_items_files(): void {
        global $DB;
        $gallery = $this->create_gallery();
        $this->create_item($gallery, 'one.jpg', ['thumbnail', 'lowres']);
        $this->create_item($gallery, 'two.jpg', ['thumbnail', 'lowres']);
        $othergallery = $this->create_gallery();
        $this->create_item($othergallery, 'other.jpg', ['thumbnail', 'lowres']);

        $items = (new gallery($gallery->id))->get_items();

        $reads = $DB->perf_get_reads();
        $names = [];
        foreach ($items as $item) {
            $names[] = $item->get_file()->get_filename();
            $this->assertNotFalse($item->get_file(true));
        }
        $this->assertEquals(['one.jpg', 'two.jpg'], $names);
        $this->assertEquals($reads, $DB->perf_get_reads(), 'Item files were not loaded with the items.');
    }

    public function test_search_queries_do_not_grow_with_results(): void {
        $this->add_galleries_with_items(2);
        $this->search(base::TYPE_IMAGE);

        $reads = $this->count_reads(fn() => $this->search(base::TYPE_IMAGE));
        $this->add_galleries_with_items(4);
        $results = [];
        $morereads = $this->count_reads(function() use (&$results) {
            $results = $this->search(base::TYPE_IMAGE);
        });

        $this->assertCount(6, $results);
        $this->assertEquals($reads, $morereads);
    }

    public function test_search_type_filter_still_applies(): void {
        $gallery = $this->create_gallery();
        $this->create_item($gallery, 'photo.jpg');
        $this->create_item($gallery, 'song.mp3');

        $this->assertEquals(['photo.jpg'], array_column($this->search(base::TYPE_IMAGE), 'itemcaption'));
        $this->assertEquals(['song.mp3'], array_column($this->search(base::TYPE_AUDIO), 'itemcaption'));
        $this->assertCount(2, $this->search(base::TYPE_ALL));
    }

    public function test_public_gallery_list_queries_do_not_grow_with_galleries(): void {
        $contextid = \context_module::instance($this->collection->cmid)->id;
        $this->add_galleries_with_items(2);
        collection::get_public_galleries_by_contextid($contextid);

        $reads = $this->count_reads(fn() => collection::get_public_galleries_by_contextid($contextid));
        $this->add_galleries_with_items(4);
        $list = [];
        $morereads = $this->count_reads(function() use (&$list, $contextid) {
            $list = collection::get_public_galleries_by_contextid($contextid);
        });

        $this->assertCount(6, $list);
        $this->assertEquals($reads, $morereads);
    }

    public function test_my_gallery_list_queries_do_not_grow_with_galleries(): void {
        $contextid = \context_module::instance($this->collection->cmid)->id;
        $this->add_galleries_with_items(2);
        collection::get_my_galleries_by_contextid($contextid);

        $reads = $this->count_reads(fn() => collection::get_my_galleries_by_contextid($contextid));
        $this->add_galleries_with_items(4);
        $list = [];
        $morereads = $this->count_reads(function() use (&$list, $contextid) {
            $list = collection::get_my_galleries_by_contextid($contextid);
        });

        $this->assertCount(6, $list);
        $this->assertEquals($reads, $morereads);
    }

    /**
     * Count the database reads a function makes.
     *
     * @param callable $function
     * @return int
     */
    private function count_reads(callable $function): int {
        global $DB;
        $reads = $DB->perf_get_reads();
        $function();
        return $DB->perf_get_reads() - $reads;
    }

    /**
     * Search the collection for everything of a type.
     *
     * @param int $type One of the base::TYPE_* constants.
     * @return \stdClass[]
     */
    private function search(int $type): array {
        $search = new mcsearch([
            'search' => '',
            'group' => 0,
            'page' => 1,
            'role' => 0,
            'type' => $type,
            'collection' => new collection($this->collection->id),
            'courseid' => $this->course->id,
            'context' => \context_module::instance($this->collection->cmid),
        ]);
        return $search->get_results();
    }

    /**
     * Add galleries owned by the student, each with one image.
     *
     * @param int $count
     */
    private function add_galleries_with_items(int $count): void {
        for ($i = 0; $i < $count; $i++) {
            $this->create_item($this->create_gallery(), "photo{$i}.jpg");
        }
    }

    /**
     * Create a gallery owned by the student.
     *
     * @return gallery
     */
    private function create_gallery(): gallery {
        return $this->getDataGenerator()->get_plugin_generator('mod_mediagallery')->create_gallery([
            'instanceid' => $this->collection->id,
            'name' => 'Gallery',
            'userid' => $this->student->id,
        ]);
    }

    /**
     * Create an item owned by the student, with a file in the item area and optionally others.
     *
     * @param gallery $gallery
     * @param string $filename Also used as the caption.
     * @param string[] $otherareas Other file areas to put a copy of the file in.
     * @return item
     */
    private function create_item(gallery $gallery, string $filename, array $otherareas = []): item {
        $item = $this->getDataGenerator()->get_plugin_generator('mod_mediagallery')->create_item([
            'galleryid' => $gallery->id,
            'userid' => $this->student->id,
            'caption' => $filename,
        ]);
        foreach (array_merge(['item'], $otherareas) as $filearea) {
            get_file_storage()->create_file_from_string([
                'contextid' => \context_module::instance($this->collection->cmid)->id,
                'component' => 'mod_mediagallery',
                'filearea' => $filearea,
                'itemid' => $item->id,
                'filepath' => '/',
                'filename' => $filename,
            ], 'contents');
        }
        return $item;
    }
}
