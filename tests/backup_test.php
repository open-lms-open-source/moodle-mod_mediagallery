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
 * Tests for backing up and restoring collections.
 *
 * @package   mod_mediagallery
 * @copyright 2026 Open LMS
 * @author    Adam Olley <adam.olley@openlms.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_mediagallery;

/**
 * Tests that a course backup brings a collection's fields, intro files and comments with it.
 *
 * @copyright 2026 Open LMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \backup_mediagallery_activity_structure_step
 * @covers    \restore_mediagallery_activity_task
 */
final class backup_test extends \advanced_testcase {

    public function test_backup_and_restore(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $CFG->usecomments = 1;

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $collection = $this->getDataGenerator()->create_module('mediagallery',
            ['course' => $course->id, 'colltype' => 'contributed', 'agents' => 'collection-agent']);
        $context = \context_module::instance($collection->cmid);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_mediagallery');
        $gallery = $generator->create_gallery([
            'instanceid' => $collection->id,
            'name' => 'Gallery',
            'userid' => $student->id,
            'agents' => 'gallery-agent',
        ]);
        $item = $generator->create_item([
            'galleryid' => $gallery->id,
            'userid' => $student->id,
            'agents' => 'item-agent',
            'extpath' => '/external/path',
            'theme_id' => 'theme-1',
            'copyright_id' => 'copyright-1',
        ]);
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_mediagallery',
            'filearea' => 'intro',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'intro.png',
        ], 'intro image');
        $this->add_comment($context, 'gallery', $gallery->id, $student, 'About the gallery');
        $this->add_comment($context, 'item', $item->id, $student, 'About the item');

        $newcourseid = $this->backup_and_restore($course);

        $newcollection = $DB->get_record('mediagallery', ['course' => $newcourseid], '*', MUST_EXIST);
        $newcontext = \context_module::instance(get_coursemodule_from_instance('mediagallery', $newcollection->id)->id);
        $newgallery = $DB->get_record('mediagallery_gallery', ['instanceid' => $newcollection->id], '*', MUST_EXIST);
        $newitem = $DB->get_record('mediagallery_item', ['galleryid' => $newgallery->id], '*', MUST_EXIST);

        $this->assertEquals('collection-agent', $newcollection->agents);
        $this->assertEquals('gallery-agent', $newgallery->agents);
        $this->assertEquals(['item-agent', '/external/path', 'theme-1', 'copyright-1'],
            [$newitem->agents, $newitem->extpath, $newitem->theme_id, $newitem->copyright_id]);

        $this->assertTrue(get_file_storage()->file_exists($newcontext->id, 'mod_mediagallery', 'intro', 0, '/', 'intro.png'));

        $comments = $DB->get_records_menu('comments', ['contextid' => $newcontext->id, 'component' => 'mod_mediagallery'],
            'commentarea', 'commentarea, itemid');
        $this->assertEquals(['gallery' => $newgallery->id, 'item' => $newitem->id], $comments);
    }

    /**
     * Add a comment directly, so it doesn't depend on the comment API's permission checks.
     *
     * @param \context $context
     * @param string $commentarea
     * @param int $itemid
     * @param \stdClass $user
     * @param string $content
     */
    private function add_comment(\context $context, string $commentarea, int $itemid, \stdClass $user, string $content): void {
        global $DB;
        $DB->insert_record('comments', [
            'contextid' => $context->id,
            'component' => 'mod_mediagallery',
            'commentarea' => $commentarea,
            'itemid' => $itemid,
            'content' => $content,
            'format' => FORMAT_MOODLE,
            'userid' => $user->id,
            // Core skips restoring a comment if another in the context has the same user and time.
            'timecreated' => time() - ($commentarea == 'gallery' ? 10 : 0),
        ]);
    }

    /**
     * Back up a course with user data and restore it into a new course.
     *
     * @param \stdClass $course
     * @return int The new course's ID.
     */
    private function backup_and_restore(\stdClass $course): int {
        global $CFG, $USER;

        $bc = new \backup_controller(\backup::TYPE_1COURSE, $course->id, \backup::FORMAT_MOODLE, \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL, $USER->id);
        $bc->get_plan()->get_setting('users')->set_value(true);
        $bc->execute_plan();
        $file = $bc->get_results()['backup_destination'];
        $bc->destroy();

        $folder = 'mediagallery_backup_test';
        $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), "{$CFG->tempdir}/backup/{$folder}");

        $newcourseid = \restore_dbops::create_new_course('Restored', 'restored', $course->category);
        $rc = new \restore_controller($folder, $newcourseid, \backup::INTERACTIVE_NO, \backup::MODE_GENERAL, $USER->id,
            \backup::TARGET_NEW_COURSE);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        return $newcourseid;
    }
}
