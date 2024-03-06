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
 * Restore steps for mod_mediagallery
 *
 * @package    mod_mediagallery
 * @copyright  2014 NetSpot Pty Ltd
 * @author     Adam Olley <adam.olley@netspot.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the restore steps that will be used by the restore_mediagallery_activity_task
 */

/**
 * Structure step to restore one mediagallery activity
 */
class restore_mediagallery_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');
        $includecomments = $this->get_setting_value('comments');

        $mediagallery = new restore_path_element('mediagallery', '/activity/mediagallery');
        $paths[] = $mediagallery;

        $gallery = new restore_path_element('mediagallery_gallery', '/activity/mediagallery/gallerys/gallery');
        $paths[] = $gallery;

        $item = new restore_path_element('mediagallery_item', '/activity/mediagallery/gallerys/gallery/items/item');
        $paths[] = $item;

        if ($userinfo) {
            $userfeedback = new restore_path_element('mediagallery_userfeedback',
                '/activity/mediagallery/gallerys/gallery/items/item/userfeedback/feedback');
            $paths[] = $userfeedback;

            if ($includecomments) {
                $paths[] = new restore_path_element('mediagallery_gcomment',
                    '/activity/mediagallery/gallerys/gallery/gallerycomments/gallerycomment');
                $paths[] = new restore_path_element('mediagallery_icomment',
                    '/activity/mediagallery/gallerys/gallery/items/item/itemcomments/itemcomment');
            }

            $paths[] = new restore_path_element('mediagallery_ctag', '/activity/mediagallery/collectiontags/collectiontag');
            $paths[] = new restore_path_element('mediagallery_gtag',
                                                '/activity/mediagallery/gallerys/gallery/gallerytags/gallerytag');
            $paths[] = new restore_path_element('mediagallery_itag',
                                                '/activity/mediagallery/gallerys/gallery/items/item/itemtags/itemtag');
        }

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    protected function process_mediagallery($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        if (isset($data->gallerytype)) {
            $types = explode(',', $data->gallerytype);
            $focus = !empty($types) ? $types[0] : \mod_mediagallery\collection::TYPE_IMAGE;
            if (empty($focus)) {
                $focus = \mod_mediagallery\collection::TYPE_IMAGE;
            }
            $data->galleryfocus = $focus;
        }
        // Insert the mediagallery record.
        $newitemid = $DB->insert_record('mediagallery', $data);
        // Immediately after inserting "activity" record, call this.
        $this->apply_activity_instance($newitemid);
    }

    protected function process_mediagallery_userfeedback($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->itemid = $this->get_new_parentid('mediagallery_item');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $newfeedbackid = $DB->insert_record('mediagallery_userfeedback', $data);
    }

    protected function process_mediagallery_gallery($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->instanceid = $this->get_new_parentid('mediagallery');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->groupid = $this->get_mappingid('group', $data->groupid);
        if (isset($data->gallerytype)) {
            $data->galleryfocus = $data->gallerytype;
        }
        $newitemid = $DB->insert_record('mediagallery_gallery', $data);
        $this->set_mapping('mediagallery_gallery', $oldid, $newitemid);
    }

    protected function process_mediagallery_item($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->galleryid = $this->get_new_parentid('mediagallery_gallery');
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->userid = $this->get_mappingid('user', $data->userid);
        if (isset($data->collection)) {
            $data->reference = $data->collection;
        }
        $newitemid = $DB->insert_record('mediagallery_item', $data);
        $this->set_mapping('mediagallery_item', $oldid, $newitemid, true);
    }

    /**
     * Process a gallery comment.
     *
     * @param object|array $data
     * @return void
     */
    protected function process_mediagallery_gcomment($data): void {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->itemid = $this->get_new_parentid('mediagallery_gallery');
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->contextid = $this->get_mappingid('context', $data->contextid);
        $newcommentid = $DB->insert_record('comments', $data);
        $this->set_mapping('comments', $oldid, $newcommentid, true);
    }

    /**
     * Process an item comment.
     *
     * @param object|array $data
     * @return void
     */
    protected function process_mediagallery_icomment($data): void {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->itemid = $this->get_new_parentid('mediagallery_item');
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->contextid = $this->get_mappingid('context', $data->contextid);
        $newcommentid = $DB->insert_record('comments', $data);
        $this->set_mapping('comments', $oldid, $newcommentid, true);
    }

    protected function process_mediagallery_ctag($data) {
        $data = (object)$data;
        if (!core_tag_tag::is_enabled('mod_mediagallery', 'mediagallery')) {
            return;
        }

        $tag = $data->rawname;
        if (!$itemid = $this->get_mappingid('mediagallery', $data->itemid)) {
            return;
        }

        $context = context_module::instance($this->task->get_moduleid());
        core_tag_tag::add_item_tag('mod_mediagallery', 'mediagallery', $itemid, $context, $tag);
    }

    protected function process_mediagallery_gtag($data) {
        $data = (object)$data;
        if (!core_tag_tag::is_enabled('mod_mediagallery', 'mediagallery_gallery')) {
            return;
        }

        $tag = $data->rawname;
        if (!$itemid = $this->get_mappingid('mediagallery_gallery', $data->itemid)) {
            return;
        }

        $context = context_module::instance($this->task->get_moduleid());
        core_tag_tag::add_item_tag('mod_mediagallery', 'mediagallery_gallery', $itemid, $context, $tag);

    }

    protected function process_mediagallery_itag($data) {
        $data = (object)$data;
        if (!core_tag_tag::is_enabled('mod_mediagallery', 'mediagallery_item')) {
            return;
        }

        $tag = $data->rawname;
        if (!$itemid = $this->get_mappingid('mediagallery_item', $data->itemid)) {
            return;
        }

        $context = context_module::instance($this->task->get_moduleid());
        core_tag_tag::add_item_tag('mod_mediagallery', 'mediagallery_item', $itemid, $context, $tag);

    }

    protected function after_execute() {
        global $DB;

        // Can't do thumbnail mapping before the item is restored, so we do it here.
        $mgid = $this->task->get_activityid();
        if ($records = $DB->get_records('mediagallery_gallery', array('instanceid' => $mgid))) {
            foreach ($records as $record) {
                if ($record->thumbnail) {
                    $record->thumbnail = $this->get_mappingid('mediagallery_item', $record->thumbnail);
                    $DB->update_record('mediagallery_gallery', $record);
                }
            }
        }
        $this->add_related_files('mod_mediagallery', 'intro', null);
        $this->add_related_files('mod_mediagallery', 'item', 'mediagallery_item');
        $this->add_related_files('mod_mediagallery', 'lowres', 'mediagallery_item');
        $this->add_related_files('mod_mediagallery', 'thumbnail', 'mediagallery_item');
    }
}
