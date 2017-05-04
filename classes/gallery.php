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

namespace mod_mediagallery;

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__).'/../locallib.php');

class gallery extends base {

    const VIEW_CAROUSEL = 0;
    const VIEW_GRID = 1;

    protected $collection = null;
    protected $context;
    protected $items = null;
    static protected $table = 'mediagallery_gallery';

    public function __construct($recordorid, $options = array()) {
        if (!empty($options['collection'])) {
            $this->collection = $options['collection'];
            unset($options['collection']);
        }
        parent::__construct($recordorid, $options);
    }

    public function can_comment() {
        global $CFG;
        $can = true;
        if (!$CFG->usecomments || !$this->get_collection()->allowcomments
            || !has_capability('mod/mediagallery:comment', $this->get_context())) {
            $can = false;
        }
        return $can;
    }

    public function can_like() {
        $can = true;
        if (!$this->get_collection()->allowlikes || !has_capability('mod/mediagallery:like', $this->get_context())) {
            $can = false;
        }
        return $can;
    }

    public function copy($targetid) {
        global $DB, $USER;
        if (!$DB->record_exists('mediagallery', array('id' => $targetid))) {
            return false;
        }
        // Create a gallery matching this one in the target collection.
        $newgalleryrecord = clone $this->record;
        unset($newgalleryrecord->id);
        $newgalleryrecord->instanceid = $targetid;
        $newgalleryrecord->userid = $USER->id;
        $newgalleryrecord->groupid = 0;

        $newgallery = self::create($newgalleryrecord);

        $thumbnail = 0;
        foreach ($this->get_items() as $item) {
            $newitem = $item->copy($newgallery->id);
            if ($item->id == $newgallery->thumbnail) {
                $thumbnail = $newitem->id;
            }
        }

        // Update thumbnail.
        $DB->set_field('mediagallery_gallery', 'thumbnail', $thumbnail, array('id' => $newgallery->id));

        return true;
    }

    public static function create(\stdClass $data) {
        if ($data->mode == 'youtube') {
            $data->galleryfocus = self::TYPE_VIDEO;
        }
        if (empty($data->groupid)) {
            $data->groupid = 0;
        }
        $result = parent::create($data);

        $params = array(
            'context' => $result->get_collection()->context,
            'objectid' => $result->id,
        );
        if (!empty($data->nosync)) {
            $params['other']['nosync'] = true;
        }
        if (empty($data->noevent)) {
            $event = \mod_mediagallery\event\gallery_created::create($params);
            $event->add_record_snapshot('mediagallery_gallery', $result->get_record());
            $event->trigger();
        }

        return $result;
    }

    public function delete($options = array()) {
        global $DB;

        $coll = $this->get_collection();

        $params = array(
            'context' => $coll->context,
            'objectid' => $this->id,
        );
        if (!empty($options['nosync'])) {
            $params['other']['nosync'] = true;
        }
        $event = \mod_mediagallery\event\gallery_deleted::create($params);
        $event->add_record_snapshot('mediagallery_gallery', $this->record);
        $event->trigger();

        // Delete all items and then the gallery.
        item::delete_all_by_gallery($this->record->id);
        \comment::delete_comments(array('commentarea' => 'gallery', 'itemid' => $this->record->id));

        parent::delete();

        return true;
    }

    /**
     * @param $list array List of item id's to download. Empty array means all files.
     * @return void
     */
    public function download_items(array $list = array()) {
        global $CFG, $DB;

        // More efficient to load this here.
        require_once($CFG->libdir.'/filelib.php');

        $filesforzipping = array();

        $fs = get_file_storage();
        $filename = clean_filename('mediagallery-export-'.$this->record->name.'.zip');

        $files = $fs->get_area_files($this->get_collection()->context->id, 'mod_mediagallery', 'item', false, 'id', false);
        $items = $this->get_items();
        $keyed = array_flip($list);
        foreach ($files as $file) {
            $selected = isset($keyed[$file->get_itemid()]) || empty($list);
            if ($selected && isset($items[$file->get_itemid()])) {
                $filesforzipping[$file->get_filename()] = $file;
            }
        }

        if (empty($filesforzipping)) {
            return;
        }

        $tempzip = tempnam($CFG->tempdir . '/', 'mediagallery_');
        $zipper = new \zip_packer();
        $zipper->archive_to_pathname($filesforzipping, $tempzip);

        // Send file and delete after sending.
        send_temp_file($tempzip, $filename);
    }

    public function get_collection() {
        if (is_null($this->collection)) {
            $this->collection = new collection($this->record->instanceid);
        }
        return $this->collection;
    }

    public function get_context() {
        return $this->get_collection()->context;
    }

    /**
     * If the parent collection forces settings, return those. Otherwise instance settings.
     *
     * @return object
     */
    public function get_display_settings() {
        $settings = new \stdClass();
        $settings->galleryfocus = $this->galleryfocus;
        $settings->gridcolumns = $this->gridcolumns;
        $settings->gridrows = $this->gridrows;
        $settings->galleryview = $this->galleryview;

        $coll = $this->get_collection();
        if ($coll->enforcedefaults) {
            $settings->galleryfocus = $coll->galleryfocus;
            $settings->gridcolumns = $coll->gridcolumns;
            $settings->gridrows = $coll->gridrows;
            if ($coll->grid && !$coll->carousel) {
                $settings->galleryview = self::VIEW_GRID;
            } else if (!$coll->grid && $coll->carousel) {
                $settings->galleryview = self::VIEW_CAROUSEL;
            }
        }
        return $settings;
    }

    public function get_items() {
        global $DB;

        if (!is_null($this->items)) {
            return $this->items;
        }

        $sql = "SELECT i.*, u.firstname, u.lastname, u.username, gr.name as groupname
                FROM {mediagallery_item} i
                JOIN {mediagallery_gallery} g ON g.id = i.galleryid
                LEFT JOIN {user} u ON u.id = i.userid
                LEFT JOIN {groups} gr ON gr.id = g.groupid
                WHERE i.galleryid = :galleryid
                ORDER BY i.sortorder ASC";

        $fs = get_file_storage();
        $filelist = array();

        $files = $fs->get_area_files($this->get_collection()->context->id, 'mod_mediagallery', 'item', false, 'id', false);
        foreach ($files as $file) {
            $filelist[$file->get_itemid()]['item'] = $file;
        }
        $files = $fs->get_area_files($this->get_collection()->context->id, 'mod_mediagallery', 'thumbnail', false, 'id', false);
        foreach ($files as $file) {
            $filelist[$file->get_itemid()]['thumbnail'] = $file;
        }
        $files = $fs->get_area_files($this->get_collection()->context->id, 'mod_mediagallery', 'lowres', false, 'id', false);
        foreach ($files as $file) {
            $filelist[$file->get_itemid()]['lowres'] = $file;
        }

        $items = array();
        if ($records = $DB->get_records_sql($sql, array('galleryid' => $this->record->id))) {
            foreach ($records as $record) {
                $files = !empty($filelist[$record->id]) ? $filelist[$record->id] : false;
                $options = array(
                    'files' => $files,
                    'gallery' => $this,
                );

                // Replacing empty caption with image filename/video url for
                // all items in gallery on mouseover for better user experience.
                if (empty($record->caption)) {
                    if (!empty($filelist[$record->id])) {
                        $record->caption = $filelist[$record->id]['item']->get_filename();
                    } else if (!empty($record->externalurl)) {
                        $record->caption = $record->externalurl;
                    }
                }
                $items[$record->id] = new item($record, $options);
            }
        }
        $this->items = $items;
        return $this->items;
    }

    /**
     * Return items that [dont]match the collections gallery type.
     * @param bool $matching Return items that match if true, all others if false.
     * @return array List of items requested.
     */
    public function get_items_by_type($matching = true) {
        $currentfocus = $this->type();
        if (isset($this->options['focus']) && !is_null($this->options['focus'])) {
            $currentfocus = $this->options['focus'];
        }

        $list = array();
        foreach ($this->get_items() as $item) {
            $matches = false;
            $type = $item->type();
            $allandmediatype = $currentfocus == self::TYPE_ALL && !is_null($type);
            $matchesfocus = $type === $currentfocus;
            if ($allandmediatype || $matchesfocus) {
                $matches = true;
            }
            if (!($matching xor $matches)) {
                $list[] = $item;
            }
        }
        return $list;
    }

    public function get_metainfo() {
        $info = clone $this->record;
        return $info;
    }

    public function has_items() {
        global $DB;
        if (!is_null($this->items)) {
            return !empty($this->items);
        }
        $result = $DB->count_records('mediagallery_item', array('galleryid' => $this->record->id));
        return !empty($result);
    }

    /**
     *  Gets the thumbnail src path for this gallery, if any.
     */
    public function get_thumbnail() {
        global $DB, $OUTPUT;
        $record = false;
        if (empty($this->record->thumbnail) ||
            (!$record = $DB->get_record('mediagallery_item', array('id' => $this->record->thumbnail)))) {
            // The thumbnail item got deleted, pick the first item as the new thumbnail.
            $items = $this->get_items();
            if (empty($items)) {
                $thumbid = 0;
            } else {
                $thumbnail = current($items);
                $record = $thumbnail->get_record();
                $thumbid = $record->id;
            }
            if ($this->record->thumbnail != $thumbid) {
                $DB->set_field('mediagallery_gallery', 'thumbnail', $thumbid, array('id' => $this->id));
            }
        }
        if (!$record) {
            return $OUTPUT->image_url('galleryicon', 'mediagallery')->out(false);
            return null;
        }
        $item = new item($record, array('gallery' => $this));
        return $item->get_image_url(true);
    }

    public function moral_rights_asserted() {
        global $DB;

        $count = $DB->count_records('mediagallery_item', array('galleryid' => $this->record->id, 'moralrights' => 0));
        return $count == 0;
    }

    /**
     * Checks if this gallery has been submitted for grading in the
     * assignsubmission_mediagallery plugin.
     * @return bool true if a submission exists for this gallery in the submitted state. false otherwise.
     */
    public function submitted_for_grading() {
        $submitted = $this->get_collection()->get_submitted_galleries();
        return !empty($submitted[$this->record->id]);
    }

    public function sync($forcesync = false) {
        return;
    }

    public function update_sortorder($data) {
        global $DB;
        $flipped = array_flip($data);
        $items = $DB->get_records('mediagallery_item', array('galleryid' => $this->record->id), '', 'id, sortorder');
        foreach ($items as $item) {
            if (isset($flipped[$item->id]) && $item->sortorder == $flipped[$item->id]) {
                unset($flipped[$item->id]);
            }
        }

        // TODO: Optimize this.
        foreach ($flipped as $id => $order) {
            $DB->set_field('mediagallery_item', 'sortorder', $order, array('id' => $id));
        }
        return true;
    }

    /**
     * Returns what the focus of this gallery this falls into (image, audio, video).
     * @param bool $text If the function returns the internal or text representation of the item type.
     * @return int|null Returns the self::TYPE_* that most closely matches the content. Otherwise null.
     */
    public function type($text = false) {
        $view = $this->get_display_settings();
        if ($this->mode == 'youtube' || $view->galleryfocus == self::TYPE_VIDEO) {
            return $text ? 'video' : self::TYPE_VIDEO;
        } else if ($view->galleryfocus == self::TYPE_IMAGE) {
            return $text ? 'image' : self::TYPE_IMAGE;
        } else if ($view->galleryfocus == self::TYPE_AUDIO) {
            return $text ? 'audio' : self::TYPE_AUDIO;
        } else if ($view->galleryfocus == self::TYPE_ALL) {
            return $text ? 'all' : self::TYPE_ALL;
        }

        return null;
    }

    public function user_can_contribute($userid = null) {
        global $USER;
        if (is_null($userid)) {
            $userid = $USER->id;
        }

        if ($this->get_collection()->is_read_only()) {
            return false;
        }

        $submitted = $this->submitted_for_grading();
        if ($this->contributable && !$submitted) {
            return true;
        }
        return $this->user_can_edit($userid);
    }

    /**
     * Determines if a given user can edit this gallery.
     * @param $userid int The user to check can edit. If null then the current user is checked.
     * @param $ownercheck bool Used to exclude readonly and submission checks to see if user is owner of the gallery.
     */
    public function user_can_edit($userid = null, $ownercheck = false) {
        global $USER;

        $coll = $this->get_collection();

        if (!$ownercheck && ($coll->is_read_only() || $this->submitted_for_grading())) {
            return false;
        }

        if (is_null($userid)) {
            $userid = $USER->id;
        }

        if ($userid == $this->record->userid || has_capability('mod/mediagallery:manage', $this->get_context(), $userid)) {
            return true;
        }

        if ($this->record->groupid != 0 && $coll->options['groupmode'] != NOGROUPS) {
            if ($userid != $USER->id) {
                $groups = groups_get_all_groups($coll->cm->course, $USER->id, $coll->cm->groupingid);
            } else {
                $groups = $coll->options['groups'];
            }
            if (isset($groups[$this->record->groupid])) {
                return true;
            }
        }

        if ($this->mode == 'thebox' && $this->is_thebox_creator_or_agent($userid)) {
            return true;
        }

        return false;
    }

    public function user_can_remove($userid = null) {
        global $USER;

        if (is_null($userid)) {
            $userid = $USER->id;
        }

        $theboxowner = $this->get_collection()->mode == 'thebox' && $this->get_collection()->is_thebox_creator_or_agent($userid);
        if ($this->user_can_edit($userid, true) || $theboxowner) {
            return true;
        }
        return has_capability('mod/mediagallery:manage', $this->get_context(), $userid);
    }

    public function user_can_view($userid = null) {
        global $USER;

        $coll = $this->get_collection();

        if (is_null($userid)) {
            $userid = $USER->id;
        }

        if ($this->record->userid == $userid) {
            return true;
        }

        $accessallgroups = has_capability('moodle/site:accessallgroups', $coll->context);
        if ($userid != $USER->id) {
            $groups = groups_get_all_groups($coll->cm->course, $userid, $coll->cm->groupingid);
        } else {
            $groups = $coll->options['groups'];
        }
        $groupmode = $coll->options['groupmode'];
        if ($groupmode == SEPARATEGROUPS && !$accessallgroups) {
            if (!isset($groups[$this->record->groupid])) {
                return false;
            }
            return true;
        }

        // Graders need to be able to see the gallery once its submitted no matter what.
        if (has_capability('mod/mediagallery:grade', $coll->context) && $this->submitted_for_grading()) {
            return true;
        }

        if (!empty($this->record->visibleinstructor) && has_capability('mod/mediagallery:manage', $coll->context)) {
            if (time() < $this->record->visibleinstructor) {
                return false;
            }
            return true;
        }

        if (has_capability('mod/mediagallery:viewall', $coll->context)) {
            return true;
        }

        // If in assignment mode, and above checks show this belongs to another user/group, then no access.
        if ($coll->colltype == 'assignment') {
            return false;
        }

        if (!empty($this->record->visibleother) && !has_capability('mod/mediagallery:viewall', $coll->context)) {
            if (time() < $this->record->visibleother) {
                return false;
            }
            return true;
        }

        // Now that specific vis checks are done and colltype isn't assignment...
        if ($coll->colltype != 'assignment') {
            return true;
        }

        if ($groupmode == VISIBLEGROUPS) {
            return true;
        }

        return false;
    }
}
