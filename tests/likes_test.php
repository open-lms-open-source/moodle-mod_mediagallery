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
 * Tests for liking items.
 *
 * @package   mod_mediagallery
 * @copyright 2026 Open LMS
 * @author    Adam Olley <adam.olley@openlms.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_mediagallery;

/**
 * Tests that likes follow the collection's allowlikes setting.
 *
 * @copyright 2026 Open LMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \mod_mediagallery\item::like
 * @covers    \mod_mediagallery\item::unlike
 */
final class likes_test extends \advanced_testcase {

    /** @var \stdClass */
    private $course;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'student'));
    }

    public function test_items_can_be_liked_when_likes_are_allowed(): void {
        $item = $this->create_item(true);

        $this->assertEquals(1, $item->like());
        $this->assertEquals(0, $item->unlike());
    }

    public function test_items_cannot_be_liked_when_likes_are_off(): void {
        global $DB;
        $item = $this->create_item(false);

        $this->assertFalse($item->like());
        $this->assertFalse($item->unlike());
        $this->assertFalse($DB->record_exists('mediagallery_userfeedback', ['itemid' => $item->id]));
    }

    /**
     * Create an item in a new collection, and load it without its gallery.
     *
     * @param bool $allowlikes
     * @return item
     */
    private function create_item(bool $allowlikes): item {
        $collection = $this->getDataGenerator()->create_module('mediagallery',
            ['course' => $this->course->id, 'colltype' => 'contributed', 'allowlikes' => (int) $allowlikes]);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_mediagallery');
        $gallery = $generator->create_gallery(['instanceid' => $collection->id, 'name' => 'Gallery']);
        $item = $generator->create_item(['galleryid' => $gallery->id]);
        return new item($item->id);
    }
}
