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
 * Tests for reordering a gallery's items.
 *
 * @package   mod_mediagallery
 * @copyright 2026 Open LMS
 * @author    Adam Olley <adam.olley@openlms.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_mediagallery;

/**
 * Tests that reordering only changes the gallery being reordered.
 *
 * @copyright 2026 Open LMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \mod_mediagallery\gallery::update_sortorder
 */
final class sortorder_test extends \advanced_testcase {

    /** @var \stdClass */
    private $collection;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $this->collection = $this->getDataGenerator()->create_module('mediagallery',
            ['course' => $course->id, 'colltype' => 'contributed']);
    }

    public function test_items_are_reordered(): void {
        $gallery = $this->create_gallery();
        [$first, $second, $third] = $this->create_items($gallery, 3);

        $gallery->update_sortorder([$third->id, $first->id, $second->id]);

        $this->assertEquals([$third->id => 0, $first->id => 1, $second->id => 2], $this->get_sortorders($gallery));
    }

    public function test_items_in_other_galleries_are_not_changed(): void {
        $gallery = $this->create_gallery();
        [$mine] = $this->create_items($gallery, 1);
        $othergallery = $this->create_gallery();
        $theirs = $this->create_items($othergallery, 2);
        $before = $this->get_sortorders($othergallery);

        $gallery->update_sortorder([$theirs[1]->id, $mine->id, $theirs[0]->id]);

        $this->assertEquals($before, $this->get_sortorders($othergallery));
        $this->assertEquals([$mine->id => 1], $this->get_sortorders($gallery));
    }

    /**
     * Create a gallery in the collection.
     *
     * @return gallery
     */
    private function create_gallery(): gallery {
        return $this->getDataGenerator()->get_plugin_generator('mod_mediagallery')->create_gallery([
            'instanceid' => $this->collection->id,
            'name' => 'Gallery',
        ]);
    }

    /**
     * Create items in a gallery.
     *
     * @param gallery $gallery
     * @param int $count
     * @return item[]
     */
    private function create_items(gallery $gallery, int $count): array {
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $items[] = $this->getDataGenerator()->get_plugin_generator('mod_mediagallery')->create_item([
                'galleryid' => $gallery->id,
            ]);
        }
        return $items;
    }

    /**
     * Get the sort order of each item in a gallery.
     *
     * @param gallery $gallery
     * @return int[] Sort orders, keyed by item ID.
     */
    private function get_sortorders(gallery $gallery): array {
        global $DB;
        return array_map('intval', $DB->get_records_menu('mediagallery_item', ['galleryid' => $gallery->id], 'sortorder',
            'id, sortorder'));
    }
}
