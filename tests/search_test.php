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
 * Tests for the course-wide item search.
 *
 * @package   mod_mediagallery
 * @copyright 2026 Open LMS
 * @author    Adam Olley <adam.olley@openlms.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_mediagallery;

/**
 * Tests that search only returns items from galleries the user can see, and pages through those correctly.
 *
 * @copyright 2026 Open LMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::mediagallery_search_items
 */
final class search_test extends \advanced_testcase {

    /** @var \stdClass */
    private $course;

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/mod/mediagallery/locallib.php');

        $this->course = $this->getDataGenerator()->create_course();
    }

    public function test_search_hides_items_from_other_groups(): void {
        $generator = $this->getDataGenerator();
        $collection = $generator->create_module('mediagallery', ['course' => $this->course->id, 'colltype' => 'contributed'],
            ['groupmode' => SEPARATEGROUPS]);
        $group1 = $generator->create_group(['courseid' => $this->course->id]);
        $group2 = $generator->create_group(['courseid' => $this->course->id]);
        $owner = $this->create_group_member($group1);
        $groupmate = $this->create_group_member($group1);
        $outsider = $this->create_group_member($group2);
        $teacher = $generator->create_and_enrol($this->course, 'editingteacher');

        $gallery = $this->create_gallery($collection, $owner, $group1->id);
        $this->create_item($gallery, $owner, 'Sunset');

        $this->assertSame(1, $this->search_count('Sunset', $owner));
        $this->assertSame(1, $this->search_count('Sunset', $groupmate));
        $this->assertSame(1, $this->search_count('Sunset', $teacher));
        $this->assertSame(0, $this->search_count('Sunset', $outsider));
    }

    public function test_search_hides_classmates_items_in_assignment_collections(): void {
        $generator = $this->getDataGenerator();
        $collection = $generator->create_module('mediagallery', ['course' => $this->course->id, 'colltype' => 'assignment']);
        $owner = $generator->create_and_enrol($this->course, 'student');
        $classmate = $generator->create_and_enrol($this->course, 'student');

        $gallery = $this->create_gallery($collection, $owner);
        $this->create_item($gallery, $owner, 'Sunset');

        $this->assertSame(1, $this->search_count('Sunset', $owner));
        $this->assertSame(0, $this->search_count('Sunset', $classmate));
    }

    public function test_search_pages_through_visible_items_only(): void {
        $generator = $this->getDataGenerator();
        $contributed = $generator->create_module('mediagallery', ['course' => $this->course->id, 'colltype' => 'contributed']);
        $assignment = $generator->create_module('mediagallery', ['course' => $this->course->id, 'colltype' => 'assignment']);
        $student = $generator->create_and_enrol($this->course, 'student');
        $classmate = $generator->create_and_enrol($this->course, 'student');

        // Three items the student can see, mixed in with two they can't.
        $visible = $this->create_gallery($contributed, $classmate);
        $hidden = $this->create_gallery($assignment, $classmate);
        $expected = [];
        foreach ([$visible, $hidden, $visible, $hidden, $visible] as $gallery) {
            $item = $this->create_item($gallery, $classmate, 'Sunset');
            if ($gallery === $visible) {
                $expected[] = $item->id;
            }
        }
        rsort($expected);

        $this->setUser($student);
        [$firstpage, $total] = mediagallery_search_items(['Sunset'], [$this->course->id => $this->course], 0, 2);
        $this->assertSame(3, $total);
        $this->assertEquals(array_slice($expected, 0, 2), array_keys($firstpage));

        [$secondpage, $total] = mediagallery_search_items(['Sunset'], [$this->course->id => $this->course], 2, 2);
        $this->assertSame(3, $total);
        $this->assertEquals(array_slice($expected, 2), array_keys($secondpage));
    }

    /**
     * Search the course as a user and count the matches.
     *
     * @param string $term
     * @param \stdClass $user
     * @return int
     */
    private function search_count(string $term, \stdClass $user): int {
        $this->setUser($user);
        [$items, $total] = mediagallery_search_items([$term], [$this->course->id => $this->course]);
        $this->assertCount($total, $items ?: []);
        return $total;
    }

    /**
     * Create a student in a group.
     *
     * @param \stdClass $group
     * @return \stdClass
     */
    private function create_group_member(\stdClass $group): \stdClass {
        $user = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $user->id]);
        return $user;
    }

    /**
     * Create a gallery.
     *
     * @param \stdClass $collection
     * @param \stdClass $user The owner.
     * @param int $groupid
     * @return gallery
     */
    private function create_gallery(\stdClass $collection, \stdClass $user, int $groupid = 0): gallery {
        return $this->getDataGenerator()->get_plugin_generator('mod_mediagallery')->create_gallery([
            'instanceid' => $collection->id,
            'name' => 'Gallery',
            'userid' => $user->id,
            'groupid' => $groupid,
        ]);
    }

    /**
     * Create an item.
     *
     * @param gallery $gallery
     * @param \stdClass $user The owner.
     * @param string $caption
     * @return item
     */
    private function create_item(gallery $gallery, \stdClass $user, string $caption): item {
        return $this->getDataGenerator()->get_plugin_generator('mod_mediagallery')->create_item([
            'galleryid' => $gallery->id,
            'userid' => $user->id,
            'caption' => $caption,
        ]);
    }
}
