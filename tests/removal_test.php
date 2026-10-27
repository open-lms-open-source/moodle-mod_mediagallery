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
 * Tests for editing and removing galleries and items while they are locked.
 *
 * @package   mod_mediagallery
 * @copyright 2026 Open LMS
 * @author    Adam Olley <adam.olley@openlms.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_mediagallery;

/**
 * Tests that read-only collections and submitted galleries can't be changed by students.
 *
 * @copyright 2026 Open LMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \mod_mediagallery\gallery
 * @covers    \mod_mediagallery\item
 * @covers    \mod_mediagallery\collection::is_read_only
 */
final class removal_test extends \advanced_testcase {

    /** @var \stdClass */
    private $course;

    /** @var \stdClass A teacher in the course. */
    private $teacher;

    /** @var \stdClass A student in the course. */
    private $student;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
    }

    public function test_owner_can_remove_when_unlocked(): void {
        $collection = $this->create_collection();
        [$gallery, $item] = $this->create_gallery_with_item($collection, $this->student);

        $this->setUser($this->student);
        $this->assertTrue($this->reload($gallery)->user_can_remove());
        $this->assertTrue($this->reload_item($item)->user_can_edit());
        $this->assertTrue($this->reload_item($item)->user_can_remove());
    }

    public function test_read_only_collection_blocks_removal(): void {
        $collection = $this->create_collection(['readonlyfrom' => time() - DAYSECS]);
        [$gallery, $item] = $this->create_gallery_with_item($collection, $this->student);

        $this->setUser($this->student);
        $this->assertFalse($this->reload($gallery)->user_can_remove());
        $this->assertFalse($this->reload_item($item)->user_can_edit());
        $this->assertFalse($this->reload_item($item)->user_can_remove());

        $this->setUser($this->teacher);
        $this->assertTrue($this->reload($gallery)->user_can_remove());
        $this->assertTrue($this->reload_item($item)->user_can_remove());
    }

    public function test_read_only_is_checked_for_the_given_user(): void {
        $collection = $this->create_collection(['readonlyfrom' => time() - DAYSECS]);
        [$gallery, $item] = $this->create_gallery_with_item($collection, $this->student);

        // The teacher is logged in, but it's the student who is asked about.
        $this->setUser($this->teacher);
        $this->assertFalse($this->reload($gallery)->user_can_remove($this->student->id));
        $this->assertFalse($this->reload_item($item)->user_can_remove($this->student->id));
    }

    public function test_read_only_collection_blocks_group_members(): void {
        $collection = $this->create_collection(['readonlyfrom' => time() - DAYSECS], SEPARATEGROUPS);
        $group = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $groupmate = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        foreach ([$this->student, $groupmate] as $user) {
            $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $user->id]);
        }
        [$gallery, $item] = $this->create_gallery_with_item($collection, $this->student, $group->id);

        $this->setUser($groupmate);
        $this->assertFalse($this->reload($gallery)->user_can_remove());
        $this->assertFalse($this->reload_item($item)->user_can_remove());
    }

    public function test_submitted_gallery_blocks_removal(): void {
        if (!collection::is_assignsubmission_mediagallery_installed()) {
            $this->markTestSkipped('assignsubmission_mediagallery is not installed.');
        }
        $collection = $this->create_collection();
        [$gallery, $item] = $this->create_gallery_with_item($collection, $this->student);
        $this->submit_gallery($collection, $gallery, $this->student);

        $this->setUser($this->student);
        $this->assertFalse($this->reload($gallery)->user_can_remove());
        $this->assertFalse($this->reload_item($item)->user_can_edit());
        $this->assertFalse($this->reload_item($item)->user_can_remove());

        $this->setUser($this->teacher);
        $this->assertTrue($this->reload($gallery)->user_can_remove());
        $this->assertTrue($this->reload_item($item)->user_can_remove());
    }

    /**
     * Create a contributed collection in the course.
     *
     * @param array $settings Extra collection settings.
     * @param int $groupmode
     * @return \stdClass The collection's activity record.
     */
    private function create_collection(array $settings = [], int $groupmode = NOGROUPS): \stdClass {
        $settings += ['course' => $this->course->id, 'colltype' => 'contributed'];
        return $this->getDataGenerator()->create_module('mediagallery', $settings, ['groupmode' => $groupmode]);
    }

    /**
     * Create a gallery with one item, both owned by a user.
     *
     * @param \stdClass $collection
     * @param \stdClass $user
     * @param int $groupid
     * @return array The gallery and the item.
     */
    private function create_gallery_with_item(\stdClass $collection, \stdClass $user, int $groupid = 0): array {
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_mediagallery');
        $gallery = $generator->create_gallery([
            'instanceid' => $collection->id,
            'name' => 'Gallery',
            'userid' => $user->id,
            'groupid' => $groupid,
        ]);
        $item = $generator->create_item(['galleryid' => $gallery->id, 'userid' => $user->id]);
        return [$gallery, $item];
    }

    /**
     * Submit a gallery to an assignment that grades the collection.
     *
     * @param \stdClass $collection
     * @param gallery $gallery
     * @param \stdClass $user
     */
    private function submit_gallery(\stdClass $collection, gallery $gallery, \stdClass $user): void {
        global $DB;

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
            'userid' => $user->id,
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
    }

    /**
     * Load a fresh copy of a gallery, so its collection reads the current user's groups and submissions.
     *
     * @param gallery $gallery
     * @return gallery
     */
    private function reload(gallery $gallery): gallery {
        return new gallery($gallery->id);
    }

    /**
     * Load a fresh copy of an item.
     *
     * @param item $item
     * @return item
     */
    private function reload_item(item $item): item {
        return new item($item->id);
    }
}
