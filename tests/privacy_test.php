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
 */
class mod_mediagallery_privacy_testcase extends provider_testcase {

    use \mod_mediagallery\privacy\subcontext_info;

    public function setUp() {
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
        $this->assertTrue(in_array(context_module::instance($cm1a->cmid)->id, $contextids));
        $this->assertTrue(in_array(context_module::instance($cm1b->cmid)->id, $contextids));
        $this->assertTrue(in_array(context_module::instance($cm2a->cmid)->id, $contextids));

        $contextids = provider::get_contexts_for_userid($users[1]->id)->get_contextids();
        $this->assertCount(2, $contextids);
        $this->assertTrue(in_array(context_module::instance($cm2a->cmid)->id, $contextids));
        $this->assertTrue(in_array(context_module::instance($cm2b->cmid)->id, $contextids));

        $contextids = provider::get_contexts_for_userid($users[2]->id)->get_contextids();
        $this->assertCount(1, $contextids);
        $this->assertTrue(in_array(context_module::instance($cm2b->cmid)->id, $contextids));

        $contextids = provider::get_contexts_for_userid($users[3]->id)->get_contextids();
        $this->assertCount(1, $contextids);
        $this->assertTrue(in_array(context_module::instance($cm2b->cmid)->id, $contextids));
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

        provider::delete_data_for_all_users_in_context(context_module::instance($cm1b->cmid));
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
            'filearea'  => 'item',
            'itemid'    => $item->id,
            'filepath'  => '/',
            'filename'  => 'example.jpg',
        ], 'image contents (not really)');

        provider::delete_data_for_user(new approved_contextlist($users[0], 'mod_lightboxgallery', [
            context_course::instance($c1->id)->id,
            context_module::instance($cm1a->cmid)->id,
            context_module::instance($cm1b->cmid)->id,
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

        $cm1actx = context_module::instance($cm1a->cmid);
        $cm1bctx = context_module::instance($cm1b->cmid);
        $cm1cctx = context_module::instance($cm1c->cmid);

        $gallery1a = $this->create_gallery($cm1a, $users[0]);
        $gallery1a2 = $this->create_gallery($cm1a, $users[1]);
        $gallery1b = $this->create_gallery($cm1b, $users[1]);
        $gallery1c = $this->create_gallery($cm1c, $users[0]);

        provider::export_user_data(new approved_contextlist($users[0], 'mod_mediagallery', [$cm1actx->id, $cm1bctx->id, $cm1cctx->id]));

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
        provider::export_user_data(new approved_contextlist($users[1], 'mod_mediagallery', [$cm1actx->id, $cm1bctx->id, $cm1cctx->id]));

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

    /**
     * Create userfeedback.
     *
     * @param int $itemid The item ID.
     * @param int $userid The user ID.
     * @param bool If the user liked the item.
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
