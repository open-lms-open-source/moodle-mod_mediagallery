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
 * Data provider tests.
 *
 * @package    mod_mediagallery
 * @category   test
 * @author     Adam Olley <adam.olley@blackboard.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_mediagallery;

defined('MOODLE_INTERNAL') || die();
global $CFG;

use core_privacy\tests\provider_testcase;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;
use mod_mediagallery\privacy\provider;

require_once($CFG->dirroot . '/mod/mediagallery/lib.php');

/**
 * Data provider testcase class.
 *
 * @package    mod_mediagallery
 * @category   test
 * @author     Adam Olley <adam.olley@blackboard.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_mediagallery\privacy\provider
 */
class privacy_test extends provider_testcase {

    use \mod_mediagallery\privacy\subcontext_info;

    public function setUp(): void {
        $this->resetAfterTest();
    }

    private function create_users($count) {
        $users = [];
        for ($i = 0; $i < $count; $i++) {
            $users[] = $this->getDataGenerator()->create_user();
        }
        return $users;
    }

    private function create_gallery($cm, $user) {
        $record = array(
            'name' => 'Test gallery '.$cm->id.'_'.$user->id,
            'instanceid' => $cm->id,
            'contributable' => 1,
            'userid' => $user->id,
        );
        return self::getDataGenerator()
            ->get_plugin_generator('mod_mediagallery')->create_gallery($record);
    }

    private function create_item($gallery, $user) {
        $record = array(
            'galleryid' => $gallery->id,
            'userid' => $user->id,
        );
        return self::getDataGenerator()
            ->get_plugin_generator('mod_mediagallery')->create_item($record);
    }

    public function test_get_contexts_for_userid() {
        $dg = $this->getDataGenerator();

        $c1 = $dg->create_course();
        $c2 = $dg->create_course();
        $cm1a = $dg->create_module('mediagallery', ['course' => $c1]);
        $cm1b = $dg->create_module('mediagallery', ['course' => $c1]);
        $cm2a = $dg->create_module('mediagallery', ['course' => $c2]);
        $cm2b = $dg->create_module('mediagallery', ['course' => $c2]);
        $users = $this->create_users(4);

        $this->create_gallery($cm1a, $users[0]);
        $this->create_gallery($cm1b, $users[0]);
        $this->create_gallery($cm2a, $users[0]);

        $this->create_gallery($cm2a, $users[1]);
        $gallery = $this->create_gallery($cm2b, $users[1]);

        // Check a user with only items get the context back.
        $item = $this->create_item($gallery, $users[2]);

        // Check a user with only feedback gets the context back.
        $this->create_userfeedback($item->id, $users[3]->id);

        $contextids = provider::get_contexts_for_userid($users[0]->id)->get_contextids();
        $this->assertCount(3, $contextids);
        $this->assertTrue(in_array(\context_module::instance($cm1a->cmid)->id, $contextids));
        $this->assertTrue(in_array(\context_module::instance($cm1b->cmid)->id, $contextids));
        $this->assertTrue(in_array(\context_module::instance($cm2a->cmid)->id, $contextids));

        $contextids = provider::get_contexts_for_userid($users[1]->id)->get_contextids();
        $this->assertCount(2, $contextids);
        $this->assertTrue(in_array(\context_module::instance($cm2a->cmid)->id, $contextids));
        $this->assertTrue(in_array(\context_module::instance($cm2b->cmid)->id, $contextids));

        $contextids = provider::get_contexts_for_userid($users[2]->id)->get_contextids();
        $this->assertCount(1, $contextids);
        $this->assertTrue(in_array(\context_module::instance($cm2b->cmid)->id, $contextids));

        $contextids = provider::get_contexts_for_userid($users[3]->id)->get_contextids();
        $this->assertCount(1, $contextids);
        $this->assertTrue(in_array(\context_module::instance($cm2b->cmid)->id, $contextids));
    }

    public function test_delete_data_for_all_users_in_context() {
        global $DB;
        $dg = $this->getDataGenerator();

        $c1 = $dg->create_course();
        $cm1a = $dg->create_module('mediagallery', ['course' => $c1]);
        $cm1b = $dg->create_module('mediagallery', ['course' => $c1]);
        $users = $this->create_users(2);

        $this->create_gallery($cm1a, $users[0]);
        $this->create_gallery($cm1b, $users[1]);

        provider::delete_data_for_all_users_in_context(\context_module::instance($cm1b->cmid));
        $this->assertTrue($DB->record_exists('mediagallery_gallery', ['userid' => $users[0]->id, 'instanceid' => $cm1a->id]));
        $this->assertFalse($DB->record_exists('mediagallery_gallery', ['userid' => $users[1]->id, 'instanceid' => $cm1b->id]));
    }

    public function test_delete_data_for_user() {
        global $DB;
        $dg = $this->getDataGenerator();
        $fs = get_file_storage();

        $c1 = $dg->create_course();
        $cm1a = $dg->create_module('mediagallery', ['course' => $c1]);
        $cm1b = $dg->create_module('mediagallery', ['course' => $c1]);
        $users = $this->create_users(2);

        $g1 = $this->create_gallery($cm1a, $users[0]);
        $g2 = $this->create_gallery($cm1a, $users[1]);
        $g3 = $this->create_gallery($cm1b, $users[1]);

        $item = $this->create_item($g1, $users[0]);

        $cm1actx = \context_module::instance($cm1a->cmid);
        $fs->create_file_from_string([
            'contextid' => $cm1actx->id,
            'component' => 'mod_mediagallery',
            'filearea' => 'item',
            'itemid' => $item->id,
            'filepath' => '/',
            'filename' => 'example.jpg',
        ], 'image contents (not really)');

        provider::delete_data_for_user(new approved_contextlist($users[0], 'mod_lightboxgallery', [
            \context_course::instance($c1->id)->id,
            \context_module::instance($cm1a->cmid)->id,
            \context_module::instance($cm1b->cmid)->id,
        ]));

        $this->assertFalse($DB->record_exists('mediagallery_gallery', ['userid' => $users[0]->id, 'instanceid' => $cm1a->id]));
        $this->assertEmpty($fs->get_area_files($cm1actx->id, 'mod_mediagallery', 'item', $item->id));
        $this->assertTrue($DB->record_exists('mediagallery_gallery', ['userid' => $users[1]->id, 'instanceid' => $cm1a->id]));
        $this->assertTrue($DB->record_exists('mediagallery_gallery', ['userid' => $users[1]->id, 'instanceid' => $cm1b->id]));
    }

    public function test_export_data_for_user() {
        global $DB;
        $dg = $this->getDataGenerator();
        $fs = get_file_storage();

        $c1 = $dg->create_course();
        $cm1a = $dg->create_module('mediagallery', ['course' => $c1]);
        $cm1b = $dg->create_module('mediagallery', ['course' => $c1]);
        $cm1c = $dg->create_module('mediagallery', ['course' => $c1]);
        $users = $this->create_users(2);

        $cm1actx = \context_module::instance($cm1a->cmid);
        $cm1bctx = \context_module::instance($cm1b->cmid);
        $cm1cctx = \context_module::instance($cm1c->cmid);

        $gallery1a = $this->create_gallery($cm1a, $users[0]);
        $gallery1a2 = $this->create_gallery($cm1a, $users[1]);
        $gallery1b = $this->create_gallery($cm1b, $users[1]);
        $gallery1c = $this->create_gallery($cm1c, $users[0]);

        provider::export_user_data(new approved_contextlist($users[0], 'mod_mediagallery',
                                                            [$cm1actx->id, $cm1bctx->id, $cm1cctx->id]));

        $subcontext = static::get_subcontext($cm1a, (object)['id' => $gallery1a->id, 'name' => $gallery1a->name]);
        $data = writer::with_context($cm1actx)->get_data($subcontext);
        $this->assertNotEmpty($data);
        $this->assertEquals($gallery1a->name, $data->name);

        $data = writer::with_context($cm1bctx)->get_data([]);
        $this->assertEmpty($data);

        $subcontext = static::get_subcontext($cm1a, (object)['id' => $gallery1c->id, 'name' => $gallery1c->name]);
        $data = writer::with_context($cm1cctx)->get_data($subcontext);
        $this->assertNotEmpty($data);
        $this->assertEquals($gallery1c->name, $data->name);

        writer::reset();
        provider::export_user_data(new approved_contextlist($users[1], 'mod_mediagallery',
                                                            [$cm1actx->id, $cm1bctx->id, $cm1cctx->id]));

        $subcontext = static::get_subcontext($cm1a, (object)['id' => $gallery1a2->id, 'name' => $gallery1a2->name]);
        $data = writer::with_context($cm1actx)->get_data($subcontext);
        $this->assertNotEmpty($data);
        $this->assertEquals($gallery1a2->name, $data->name);

        $subcontext = static::get_subcontext($cm1a, (object)['id' => $gallery1b->id, 'name' => $gallery1b->name]);
        $data = writer::with_context($cm1bctx)->get_data($subcontext);
        $this->assertNotEmpty($data);
        $this->assertEquals($gallery1b->name, $data->name);

        $data = writer::with_context($cm1cctx)->get_data([]);
        $this->assertEmpty($data);
    }

    public function test_get_contexts_for_userid_finds_commenters() {
        $data = $this->create_shared_gallery();

        $contextids = provider::get_contexts_for_userid($data->commenter->id)->get_contextids();
        $this->assertEquals([$data->context->id], array_values($contextids));
    }

    public function test_get_users_in_context_finds_everyone_with_data() {
        $data = $this->create_shared_gallery();

        $userlist = new \core_privacy\local\request\userlist($data->context, 'mod_mediagallery');
        provider::get_users_in_context($userlist);

        $expected = [$data->teacher->id, $data->owner->id, $data->contributor->id, $data->liker->id, $data->commenter->id];
        sort($expected);
        $actual = $userlist->get_userids();
        sort($actual);
        $this->assertEquals($expected, $actual);
    }

    public function test_delete_data_for_user_removes_contributions_likes_and_tags() {
        global $DB;
        $data = $this->create_shared_gallery();

        provider::delete_data_for_user(new approved_contextlist($data->contributor, 'mod_mediagallery',
            [$data->context->id]));

        // The contributor's item, its file, its tags and the contributor's like are gone.
        $this->assertFalse($DB->record_exists('mediagallery_item', ['id' => $data->contributoritem->id]));
        $this->assertEmpty($this->get_item_files($data, $data->contributoritem->id));
        $this->assertFalse($DB->record_exists('tag_instance', ['itemtype' => 'mediagallery_item',
            'itemid' => $data->contributoritem->id]));
        $this->assertFalse($DB->record_exists('mediagallery_userfeedback', ['userid' => $data->contributor->id]));

        // The owner's gallery and item, and other users' likes and comments, stay.
        $this->assertTrue($DB->record_exists('mediagallery_gallery', ['id' => $data->gallery->id]));
        $this->assertTrue($DB->record_exists('mediagallery_item', ['id' => $data->owneritem->id]));
        $this->assertNotEmpty($this->get_item_files($data, $data->owneritem->id));
        $this->assertTrue($DB->record_exists('mediagallery_userfeedback', ['userid' => $data->liker->id]));
        $this->assertTrue($DB->record_exists('comments', ['userid' => $data->commenter->id]));
    }

    public function test_delete_data_for_user_removes_owned_gallery_and_its_contents() {
        global $DB;
        $data = $this->create_shared_gallery();

        provider::delete_data_for_user(new approved_contextlist($data->owner, 'mod_mediagallery', [$data->context->id]));

        $this->assertFalse($DB->record_exists('mediagallery_gallery', ['id' => $data->gallery->id]));
        foreach ([$data->owneritem->id, $data->contributoritem->id] as $itemid) {
            $this->assertFalse($DB->record_exists('mediagallery_item', ['id' => $itemid]));
            $this->assertEmpty($this->get_item_files($data, $itemid));
            $this->assertFalse($DB->record_exists('mediagallery_userfeedback', ['itemid' => $itemid]));
        }
        // Comments on the deleted items go too.
        $this->assertFalse($DB->record_exists('comments', ['contextid' => $data->context->id]));
        // The other collection is untouched.
        $this->assertTrue($DB->record_exists('mediagallery_gallery', ['id' => $data->othergallery->id]));
    }

    public function test_delete_data_for_users_removes_likes_and_comments() {
        global $DB;
        $data = $this->create_shared_gallery();

        provider::delete_data_for_users(new \core_privacy\local\request\approved_userlist($data->context, 'mod_mediagallery',
            [$data->liker->id, $data->commenter->id]));

        $this->assertFalse($DB->record_exists('mediagallery_userfeedback', ['userid' => $data->liker->id]));
        $this->assertFalse($DB->record_exists('comments', ['userid' => $data->commenter->id]));
        $this->assertTrue($DB->record_exists('mediagallery_userfeedback', ['userid' => $data->contributor->id]));
        $this->assertTrue($DB->record_exists('mediagallery_item', ['id' => $data->owneritem->id]));
    }

    public function test_collection_creator_is_exported_and_removed() {
        global $DB;
        $data = $this->create_shared_gallery();

        $this->setUser($data->teacher);
        provider::export_user_data(new approved_contextlist($data->teacher, 'mod_mediagallery', [$data->context->id]));
        $this->assertEquals(transform::yesno(true), writer::with_context($data->context)->get_data([])->creator_was_you);

        provider::delete_data_for_user(new approved_contextlist($data->teacher, 'mod_mediagallery', [$data->context->id]));
        $this->assertEquals(0, $DB->get_field('mediagallery', 'userid', ['id' => $data->collection->id]));
        $this->assertTrue($DB->record_exists('mediagallery_gallery', ['id' => $data->gallery->id]));
    }

    public function test_export_includes_likes_and_comments() {
        $data = $this->create_shared_gallery();
        $itemarea = array_merge(static::get_gallery_area($data->gallery->get_record()), static::get_item_area());

        $this->setUser($data->liker);
        provider::export_user_data(new approved_contextlist($data->liker, 'mod_mediagallery', [$data->context->id]));
        $items = (array) writer::with_context($data->context)->get_data($itemarea);
        $this->assertCount(1, $items);
        $this->assertEquals($data->owneritem->id, $items[0]->id);
        $this->assertEquals(transform::yesno(true), $items[0]->liked_by_you);
        // The liker only gets their like, not the owner's content.
        $this->assertFalse(property_exists($items[0], 'description'));

        writer::reset();
        $this->setUser($data->commenter);
        provider::export_user_data(new approved_contextlist($data->commenter, 'mod_mediagallery', [$data->context->id]));
        $commentarea = array_merge($itemarea, [$data->owneritem->id, get_string('commentsubcontext', 'core_comment')]);
        $comments = writer::with_context($data->context)->get_data($commentarea)->comments;
        $this->assertCount(1, $comments);
        $this->assertEquals('Nice photo', $comments[0]->content);
    }

    /**
     * Create a gallery that several users hold data in.
     *
     * A teacher creates the collection. The owner's gallery holds an item from the owner and one from a contributor.
     * The contributor and a liker like the owner's item, and a commenter comments on it. Another user owns a gallery
     * in a second collection. Page activities are created first, so course module ids differ from collection ids.
     *
     * @return \stdClass
     */
    private function create_shared_gallery(): \stdClass {
        global $DB;
        $dg = $this->getDataGenerator();
        $data = new \stdClass();

        $course = $dg->create_course();
        $dg->create_module('page', ['course' => $course->id]);
        $dg->create_module('page', ['course' => $course->id]);

        $data->teacher = $dg->create_user();
        $this->setUser($data->teacher);
        $data->collection = $dg->create_module('mediagallery', ['course' => $course->id]);
        $othercollection = $dg->create_module('mediagallery', ['course' => $course->id]);
        $this->setUser(null);
        $data->context = \context_module::instance($data->collection->cmid);
        $this->assertNotEquals($data->collection->id, $data->collection->cmid);

        [$data->owner, $data->contributor, $data->liker, $data->commenter, $other] = $this->create_users(5);
        $data->gallery = $this->create_gallery($data->collection, $data->owner);
        $data->othergallery = $this->create_gallery($othercollection, $other);
        $data->owneritem = $this->create_item($data->gallery, $data->owner);
        $data->contributoritem = $this->create_item($data->gallery, $data->contributor);

        $fs = get_file_storage();
        foreach ([$data->owneritem, $data->contributoritem] as $item) {
            $fs->create_file_from_string([
                'contextid' => $data->context->id,
                'component' => 'mod_mediagallery',
                'filearea' => 'item',
                'itemid' => $item->id,
                'filepath' => '/',
                'filename' => 'photo.jpg',
            ], 'image contents (not really)');
        }
        \core_tag_tag::set_item_tags('mod_mediagallery', 'mediagallery_item', $data->contributoritem->id, $data->context,
            ['contributed']);

        $this->create_userfeedback($data->owneritem->id, $data->contributor->id, true);
        $this->create_userfeedback($data->owneritem->id, $data->liker->id, true);
        $DB->insert_record('comments', [
            'contextid' => $data->context->id,
            'component' => 'mod_mediagallery',
            'commentarea' => 'item',
            'itemid' => $data->owneritem->id,
            'content' => 'Nice photo',
            'format' => FORMAT_PLAIN,
            'userid' => $data->commenter->id,
            'timecreated' => time(),
        ]);

        return $data;
    }

    /**
     * Get the stored files for an item.
     *
     * @param \stdClass $data The data from create_shared_gallery().
     * @param int $itemid
     * @return \stored_file[]
     */
    private function get_item_files(\stdClass $data, int $itemid): array {
        return get_file_storage()->get_area_files($data->context->id, 'mod_mediagallery', 'item', $itemid, 'id', false);
    }

    /**
     * Create userfeedback.
     *
     * @param int $itemid The item ID.
     * @param int $userid The user ID.
     * @param bool $liked If the user liked the item.
     * @return stdClass
     */
    protected function create_userfeedback($itemid, $userid, $liked = false) {
        global $DB;
        $record = (object) [
            'itemid' => $itemid,
            'userid' => $userid,
            'liked' => $liked ? 1 : 0,
        ];
        $record->id = $DB->insert_record('mediagallery_userfeedback', $record);
        return $record;
    }

}
