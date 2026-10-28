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
 * Tests for the tag index callbacks.
 *
 * @package   mod_mediagallery
 * @copyright 2026 Open LMS
 * @author    Adam Olley <adam.olley@openlms.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_mediagallery;

/**
 * Tests that tag pages list only the tagged collections, galleries and items the user can see.
 *
 * @copyright 2026 Open LMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::mod_mediagallery_get_tagged_collections
 * @covers    ::mod_mediagallery_get_tagged_galleries
 * @covers    ::mod_mediagallery_get_tagged_items
 */
final class tag_test extends \advanced_testcase {

    /** @var \stdClass */
    private $course;

    /** @var \stdClass */
    private $student;

    /** @var \stdClass */
    private $classmate;

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/mod/mediagallery/locallib.php');

        $this->course = $this->getDataGenerator()->create_course();
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->classmate = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
    }

    public function test_hidden_collections_are_left_out(): void {
        $visible = $this->create_collection('Visible collection');
        $hidden = $this->create_collection('Hidden collection', ['visible' => 0]);
        foreach ([$visible, $hidden] as $collection) {
            \core_tag_tag::set_item_tags('mod_mediagallery', 'mediagallery', $collection->id,
                \context_module::instance($collection->cmid), ['shared']);
        }

        $this->setUser($this->student);
        $content = $this->get_content(mod_mediagallery_get_tagged_collections($this->get_tag()));

        $this->assertStringContainsString('Visible collection', $content);
        $this->assertStringNotContainsString('Hidden collection', $content);
    }

    public function test_other_students_galleries_are_left_out(): void {
        $collection = $this->create_collection('Assignment');
        $context = \context_module::instance($collection->cmid);
        $theirs = $this->create_gallery($collection, $this->classmate, 'Classmate gallery');
        $mine = $this->create_gallery($collection, $this->student, 'My gallery');
        foreach ([$theirs, $mine] as $gallery) {
            \core_tag_tag::set_item_tags('mod_mediagallery', 'mediagallery_gallery', $gallery->id, $context, ['shared']);
        }

        $this->setUser($this->student);
        $content = $this->get_content(mod_mediagallery_get_tagged_galleries($this->get_tag()));

        $this->assertStringContainsString('My gallery', $content);
        $this->assertStringNotContainsString('Classmate gallery', $content);
    }

    public function test_items_in_other_students_galleries_are_left_out(): void {
        $collection = $this->create_collection('Assignment');
        $context = \context_module::instance($collection->cmid);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_mediagallery');
        $theirs = $generator->create_item([
            'galleryid' => $this->create_gallery($collection, $this->classmate, 'Classmate gallery')->id,
            'userid' => $this->classmate->id,
            'caption' => 'Classmate item',
        ]);
        $mine = $generator->create_item([
            'galleryid' => $this->create_gallery($collection, $this->student, 'My gallery')->id,
            'userid' => $this->student->id,
            'caption' => 'My item',
        ]);
        foreach ([$theirs, $mine] as $item) {
            \core_tag_tag::set_item_tags('mod_mediagallery', 'mediagallery_item', $item->id, $context, ['shared']);
        }

        $this->setUser($this->student);
        $content = $this->get_content(mod_mediagallery_get_tagged_items($this->get_tag()));

        $this->assertStringContainsString('My item', $content);
        $this->assertStringNotContainsString('Classmate item', $content);
    }

    public function test_graders_see_items_in_submitted_galleries(): void {
        global $DB;
        if (!collection::is_assignsubmission_mediagallery_installed()) {
            $this->markTestSkipped('assignsubmission_mediagallery is not installed.');
        }
        $collection = $this->create_collection('Assignment');
        $context = \context_module::instance($collection->cmid);
        // An extra gallery, so the submitted gallery's ID differs from its item's.
        $this->create_gallery($collection, $this->classmate, 'Unsubmitted gallery');
        $gallery = $this->create_gallery($collection, $this->student, 'Submitted gallery');
        $item = $this->getDataGenerator()->get_plugin_generator('mod_mediagallery')->create_item([
            'galleryid' => $gallery->id,
            'userid' => $this->student->id,
            'caption' => 'Submitted item',
        ]);
        $this->assertNotEquals($gallery->id, $item->id);
        \core_tag_tag::set_item_tags('mod_mediagallery', 'mediagallery_item', $item->id, $context, ['shared']);

        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $this->course->id, 'duedate' => 0]);
        $DB->insert_record('assign_plugin_config', (object) [
            'assignment' => $assign->id,
            'plugin' => 'mediagallery',
            'subtype' => 'assignsubmission',
            'name' => 'mediagallery',
            'value' => $collection->id,
        ]);
        $submissionid = $DB->insert_record('assign_submission', (object) [
            'assignment' => $assign->id,
            'userid' => $this->student->id,
            'timecreated' => time(),
            'timemodified' => time(),
            'status' => 'submitted',
            'attemptnumber' => 0,
            'latest' => 1,
        ]);
        $DB->insert_record('assignsubmission_mg', (object) [
            'assignment' => $assign->id,
            'submission' => $submissionid,
            'galleryid' => $gallery->id,
        ]);

        // A non-editing teacher can't view all galleries, only those submitted for grading.
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'teacher'));
        $content = $this->get_content(mod_mediagallery_get_tagged_items($this->get_tag()));

        $this->assertStringContainsString('Submitted item', $content);
    }

    /**
     * Create an assignment-type collection, where students only see their own galleries.
     *
     * @param string $name
     * @param array $options Course module options.
     * @return \stdClass
     */
    private function create_collection(string $name, array $options = []): \stdClass {
        return $this->getDataGenerator()->create_module('mediagallery',
            ['course' => $this->course->id, 'name' => $name, 'colltype' => 'assignment'], $options);
    }

    /**
     * Create a gallery.
     *
     * @param \stdClass $collection
     * @param \stdClass $user The owner.
     * @param string $name
     * @return gallery
     */
    private function create_gallery(\stdClass $collection, \stdClass $user, string $name): gallery {
        return $this->getDataGenerator()->get_plugin_generator('mod_mediagallery')->create_gallery([
            'instanceid' => $collection->id,
            'name' => $name,
            'userid' => $user->id,
        ]);
    }

    /**
     * Get the tag the tests use.
     *
     * @return \core_tag_tag
     */
    private function get_tag(): \core_tag_tag {
        return \core_tag_tag::get_by_name(\core_tag_collection::get_default(), 'shared', '*', MUST_EXIST);
    }

    /**
     * Get the listed content of a tag index, which callbacks leave out when nothing is listed.
     *
     * @param \core_tag\output\tagindex|null $index
     * @return string
     */
    private function get_content(?\core_tag\output\tagindex $index): string {
        return $index ? $index->content : '';
    }
}
