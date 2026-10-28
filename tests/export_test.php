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
 * Tests for downloading a gallery's files.
 *
 * @package   mod_mediagallery
 * @copyright 2026 Open LMS
 * @author    Adam Olley <adam.olley@openlms.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_mediagallery;

/**
 * Tests that a gallery download includes every chosen file.
 *
 * @copyright 2026 Open LMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \mod_mediagallery\gallery::get_files_for_download
 */
final class export_test extends \advanced_testcase {

    /** @var \stdClass */
    private $collection;

    /** @var gallery */
    private $gallery;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $this->collection = $this->getDataGenerator()->create_module('mediagallery',
            ['course' => $course->id, 'colltype' => 'contributed']);
        $this->gallery = $this->getDataGenerator()->get_plugin_generator('mod_mediagallery')->create_gallery([
            'instanceid' => $this->collection->id,
            'name' => 'Gallery',
        ]);
    }

    public function test_files_with_the_same_name_are_all_included(): void {
        $first = $this->create_item('photo.jpg', 'first');
        $second = $this->create_item('photo.jpg', 'second');
        $other = $this->create_item('other.jpg', 'other');

        $files = $this->reload()->get_files_for_download();

        $this->assertEquals([
            'photo.jpg' => 'first',
            "{$second->id}-photo.jpg" => 'second',
            'other.jpg' => 'other',
        ], array_map(fn($file) => $file->get_content(), $files));
        $this->assertNotEquals($first->id, $second->id);
    }

    public function test_only_chosen_items_are_included(): void {
        $this->create_item('one.jpg', 'one');
        $two = $this->create_item('two.jpg', 'two');

        $files = $this->reload()->get_files_for_download([$two->id]);

        $this->assertEquals(['two.jpg'], array_keys($files));
    }

    public function test_items_without_files_and_other_galleries_are_left_out(): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_mediagallery');
        $generator->create_item(['galleryid' => $this->gallery->id, 'externalurl' => 'https://www.youtube.com/watch?v=abc']);
        $othergallery = $generator->create_gallery(['instanceid' => $this->collection->id, 'name' => 'Other']);
        $this->create_item('elsewhere.jpg', 'elsewhere', $othergallery);
        $this->create_item('mine.jpg', 'mine');

        $files = $this->reload()->get_files_for_download();

        $this->assertEquals(['mine.jpg'], array_keys($files));
    }

    /**
     * Create an item with a file.
     *
     * @param string $filename
     * @param string $content
     * @param gallery|null $gallery Defaults to the test's gallery.
     * @return item
     */
    private function create_item(string $filename, string $content, ?gallery $gallery = null): item {
        $item = $this->getDataGenerator()->get_plugin_generator('mod_mediagallery')->create_item([
            'galleryid' => ($gallery ?? $this->gallery)->id,
        ]);
        get_file_storage()->create_file_from_string([
            'contextid' => \context_module::instance($this->collection->cmid)->id,
            'component' => 'mod_mediagallery',
            'filearea' => 'item',
            'itemid' => $item->id,
            'filepath' => '/',
            'filename' => $filename,
        ], $content);
        return $item;
    }

    /**
     * Load a fresh copy of the gallery, so its items are read again.
     *
     * @return gallery
     */
    private function reload(): gallery {
        return new gallery($this->gallery->id);
    }
}
