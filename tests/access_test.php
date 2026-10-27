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
 * Tests for gallery visibility in file serving and contribution.
 *
 * @package   mod_mediagallery
 * @copyright 2026 Open LMS
 * @author    Adam Olley <adam.olley@openlms.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_mediagallery;

/**
 * Tests that files and contributable galleries are only open to users who can see the gallery.
 *
 * @copyright 2026 Open LMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::mediagallery_pluginfile
 * @covers    ::mediagallery_user_can_access_item
 * @covers    \mod_mediagallery\gallery
 */
final class access_test extends \advanced_testcase {

    /** @var \stdClass */
    private $course;

    /** @var \stdClass A teacher in the course. */
    private $teacher;

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/mod/mediagallery/lib.php');

        $this->course = $this->getDataGenerator()->create_course();
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
    }

    public function test_pluginfile_hides_files_from_other_groups(): void {
        $collection = $this->create_collection('contributed', SEPARATEGROUPS);
        [$owner, $groupmate, $outsider] = $this->create_group_members();
        $gallery = $this->create_gallery($collection, $owner, $this->get_group_id($owner));
        $item = $this->create_item_with_file($collection, $gallery, $owner);

        $this->assertTrue($this->can_fetch($collection, $item, $owner));
        $this->assertTrue($this->can_fetch($collection, $item, $groupmate));
        $this->assertTrue($this->can_fetch($collection, $item, $this->teacher));
        $this->assertFalse($this->can_fetch($collection, $item, $outsider));
    }

    public function test_pluginfile_hides_files_in_assignment_collections(): void {
        $collection = $this->create_collection('assignment');
        $owner = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $classmate = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $gallery = $this->create_gallery($collection, $owner);
        $item = $this->create_item_with_file($collection, $gallery, $owner);

        $this->assertTrue($this->can_fetch($collection, $item, $owner));
        $this->assertTrue($this->can_fetch($collection, $item, $this->teacher));
        $this->assertFalse($this->can_fetch($collection, $item, $classmate));
    }

    public function test_pluginfile_refuses_files_the_user_cannot_see(): void {
        $collection = $this->create_collection('assignment');
        $owner = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $classmate = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $gallery = $this->create_gallery($collection, $owner);
        $item = $this->create_item_with_file($collection, $gallery, $owner);

        $this->setUser($classmate);
        $cm = get_coursemodule_from_instance('mediagallery', $collection->id, $this->course->id, false, MUST_EXIST);
        $result = mediagallery_pluginfile($this->course, $cm, \context_module::instance($cm->id), 'item',
            [$item->id, 'photo.jpg'], false, ['dontdie' => true]);

        $this->assertFalse($result);
        $this->expectOutputString('');
    }

    public function test_pluginfile_serves_contributors_their_own_items(): void {
        $collection = $this->create_collection('assignment');
        $owner = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $contributor = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $gallery = $this->create_gallery($collection, $owner);
        $item = $this->create_item_with_file($collection, $gallery, $contributor);

        $this->assertTrue($this->can_fetch($collection, $item, $contributor));
    }

    public function test_pluginfile_rejects_items_from_another_collection(): void {
        $collection = $this->create_collection('contributed');
        $othercollection = $this->create_collection('contributed');
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $othergallery = $this->create_gallery($othercollection, $student);
        // A file stored under this collection's context, but named after an item in another collection.
        $item = $this->create_item_with_file($collection, $othergallery, $student);

        $this->assertFalse($this->can_fetch($collection, $item, $this->teacher));
    }

    public function test_contributable_gallery_is_closed_to_other_groups(): void {
        $collection = $this->create_collection('contributed', SEPARATEGROUPS);
        [$owner, $groupmate, $outsider] = $this->create_group_members();
        $gallery = $this->create_gallery($collection, $owner, $this->get_group_id($owner), true);

        $this->setUser($groupmate);
        $this->assertTrue($this->reload($gallery)->user_can_contribute());
        $this->setUser($outsider);
        $this->assertFalse($this->reload($gallery)->user_can_contribute());
    }

    public function test_user_can_view_checks_the_given_users_capabilities(): void {
        $collection = $this->create_collection('assignment');
        $owner = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $classmate = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $gallery = $this->create_gallery($collection, $owner);

        // The teacher can view all galleries, but the classmate can't, whoever is logged in.
        $this->setUser($this->teacher);
        $gallery = $this->reload($gallery);
        $this->assertTrue($gallery->user_can_view());
        $this->assertFalse($gallery->user_can_view($classmate->id));
    }

    /**
     * Check whether a user may see an item's files.
     *
     * @param \stdClass $collection
     * @param item $item
     * @param \stdClass $user
     * @return bool
     */
    private function can_fetch(\stdClass $collection, item $item, \stdClass $user): bool {
        $this->setUser($user);
        $cm = get_coursemodule_from_instance('mediagallery', $collection->id, $this->course->id, false, MUST_EXIST);
        return mediagallery_user_can_access_item($cm, $item->id);
    }

    /**
     * Create a collection in the course.
     *
     * @param string $colltype
     * @param int $groupmode
     * @return \stdClass The collection's activity record.
     */
    private function create_collection(string $colltype, int $groupmode = NOGROUPS): \stdClass {
        return $this->getDataGenerator()->create_module('mediagallery',
            ['course' => $this->course->id, 'colltype' => $colltype], ['groupmode' => $groupmode]);
    }

    /**
     * Create two groups and three students: two in the first group and one in the second.
     *
     * @return \stdClass[] The owner and groupmate in the first group, and the outsider in the second.
     */
    private function create_group_members(): array {
        $generator = $this->getDataGenerator();
        $group1 = $generator->create_group(['courseid' => $this->course->id]);
        $group2 = $generator->create_group(['courseid' => $this->course->id]);
        $users = [];
        foreach ([$group1, $group1, $group2] as $group) {
            $user = $generator->create_and_enrol($this->course, 'student');
            $generator->create_group_member(['groupid' => $group->id, 'userid' => $user->id]);
            $users[] = $user;
        }
        return $users;
    }

    /**
     * Get the id of a user's only group in the course.
     *
     * @param \stdClass $user
     * @return int
     */
    private function get_group_id(\stdClass $user): int {
        $groups = groups_get_all_groups($this->course->id, $user->id);
        return (int) reset($groups)->id;
    }

    /**
     * Create a gallery.
     *
     * @param \stdClass $collection
     * @param \stdClass $user The owner.
     * @param int $groupid
     * @param bool $contributable
     * @return gallery
     */
    private function create_gallery(\stdClass $collection, \stdClass $user, int $groupid = 0,
            bool $contributable = false): gallery {
        return $this->getDataGenerator()->get_plugin_generator('mod_mediagallery')->create_gallery([
            'instanceid' => $collection->id,
            'name' => 'Gallery',
            'userid' => $user->id,
            'groupid' => $groupid,
            'contributable' => (int) $contributable,
        ]);
    }

    /**
     * Create an item with a file stored in a collection's context.
     *
     * @param \stdClass $collection The collection whose context holds the file.
     * @param gallery $gallery
     * @param \stdClass $user The item's owner.
     * @return item
     */
    private function create_item_with_file(\stdClass $collection, gallery $gallery, \stdClass $user): item {
        $item = $this->getDataGenerator()->get_plugin_generator('mod_mediagallery')->create_item([
            'galleryid' => $gallery->id,
            'userid' => $user->id,
        ]);
        get_file_storage()->create_file_from_string([
            'contextid' => \context_module::instance($collection->cmid)->id,
            'component' => 'mod_mediagallery',
            'filearea' => 'item',
            'itemid' => $item->id,
            'filepath' => '/',
            'filename' => 'photo.jpg',
        ], 'image contents (not really)');
        return $item;
    }

    /**
     * Load a fresh copy of a gallery, so its collection reads the current user's groups.
     *
     * @param gallery $gallery
     * @return gallery
     */
    private function reload(gallery $gallery): gallery {
        return new gallery($gallery->id);
    }
}
