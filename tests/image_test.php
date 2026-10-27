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
 * Tests for making low resolution and thumbnail images.
 *
 * @package   mod_mediagallery
 * @copyright 2026 Open LMS
 * @author    Adam Olley <adam.olley@openlms.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_mediagallery;

/**
 * Tests that images are resized correctly and clean up after themselves.
 *
 * @copyright 2026 Open LMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \mod_mediagallery\item
 * @covers    \mod_mediagallery\imagehelper
 */
final class image_test extends \advanced_testcase {

    /** @var \stdClass */
    private $collection;

    /** @var gallery */
    private $gallery;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);
        $this->collection = $this->getDataGenerator()->create_module('mediagallery',
            ['course' => $course->id, 'colltype' => 'contributed']);
        $this->gallery = $this->getDataGenerator()->get_plugin_generator('mod_mediagallery')->create_gallery([
            'instanceid' => $this->collection->id,
            'name' => 'Gallery',
            'userid' => $student->id,
        ]);
    }

    public function test_failed_resize_closes_its_output_buffer(): void {
        $item = $this->create_item('broken.jpg', 'not really an image');
        $level = ob_get_level();

        $this->assertFalse($item->generate_image_by_type('thumbnail', true));
        $this->assertEquals($level, ob_get_level());
    }

    public function test_resize_deletes_its_temp_file(): void {
        global $CFG;
        $item = $this->create_item('photo.png', $this->make_png(400, 300));
        $before = $this->count_temp_files();

        $this->assertNotFalse($item->generate_image_by_type('thumbnail', true));
        $this->assertNotFalse($item->generate_image_by_type('lowres', true));
        $this->assertEquals($before, $this->count_temp_files(), "Temp files were left in {$CFG->tempdir}/files.");
    }

    public function test_resize_restores_the_memory_limit(): void {
        $item = $this->create_item('broken.jpg', 'not really an image');
        $limit = ini_get('memory_limit');

        $item->generate_image_by_type('thumbnail', true);
        $this->assertEquals($limit, ini_get('memory_limit'));
    }

    public function test_lowres_fits_within_width_and_height(): void {
        $item = $this->create_item('wide.png', $this->make_png(2000, 1000));

        $lowres = $item->generate_image_by_type('lowres', true);

        $info = $lowres->get_imageinfo();
        $this->assertEquals([960, 480], [$info['width'], $info['height']]);
    }

    public function test_thumbnails_are_made_without_a_memory_limit(): void {
        $item = $this->create_item('photo.png', $this->make_png(400, 300));
        $limit = ini_get('memory_limit');
        ini_set('memory_limit', '-1');
        try {
            $thumbnail = $item->generate_image_by_type('thumbnail', true);
        } finally {
            ini_set('memory_limit', $limit);
        }

        $this->assertNotFalse($thumbnail);
        $this->assertEquals(250, $thumbnail->get_imageinfo()['width']);
    }

    public function test_page_view_makes_a_missing_thumbnail(): void {
        $item = $this->create_item('photo.png', $this->make_png(400, 300));

        $url = $this->reload($item)->get_image_url(true);

        $this->assertStringContainsString('/mod_mediagallery/thumbnail/', $url->out(false));
        $this->assertTrue($this->has_file($item, 'thumbnail'));
    }

    public function test_page_view_links_to_the_original_when_a_thumbnail_cannot_be_made(): void {
        $item = $this->create_item('broken.jpg', 'not really an image');

        $url = $this->reload($item)->get_image_url(true);

        $this->assertStringContainsString('/mod_mediagallery/item/', $url->out(false));
        $this->assertFalse($this->has_file($item, 'thumbnail'));
    }

    /**
     * Create an item in the gallery with a file attached.
     *
     * @param string $filename
     * @param string $content
     * @return item
     */
    private function create_item(string $filename, string $content): item {
        $item = $this->getDataGenerator()->get_plugin_generator('mod_mediagallery')->create_item([
            'galleryid' => $this->gallery->id,
        ]);
        get_file_storage()->create_file_from_string([
            'contextid' => \context_module::instance($this->collection->cmid)->id,
            'component' => 'mod_mediagallery',
            'filearea' => 'item',
            'itemid' => $item->id,
            'filepath' => '/',
            'filename' => $filename,
        ], $content);
        return $this->reload($item);
    }

    /**
     * Make a PNG image of a given size.
     *
     * @param int $width
     * @param int $height
     * @return string The image's contents.
     */
    private function make_png(int $width, int $height): string {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);
        return ob_get_clean();
    }

    /**
     * Does an item have a file in one of its file areas?
     *
     * @param item $item
     * @param string $filearea
     * @return bool
     */
    private function has_file(item $item, string $filearea): bool {
        return !get_file_storage()->is_area_empty(\context_module::instance($this->collection->cmid)->id,
            'mod_mediagallery', $filearea, $item->id);
    }

    /**
     * Count the files in the directory stored files are copied to for processing.
     *
     * @return int
     */
    private function count_temp_files(): int {
        return count(glob(make_temp_directory('files') . '/*'));
    }

    /**
     * Load a fresh copy of an item, without its files cached.
     *
     * @param item $item
     * @return item
     */
    private function reload(item $item): item {
        return new item($item->id);
    }
}
