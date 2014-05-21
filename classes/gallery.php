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

class gallery extends base {

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

    public function delete() {
        global $DB;

        $coll = $this->get_collection();

        add_to_log($coll->cm->course, 'mediagallery', 'delete gallery',
            "view.php?id={$coll->cm->id}", $this->record->name, $coll->cm->id);

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
        $list = array();
        foreach ($this->get_items() as $item) {
            $matches = false;
            if ($item->type() == $this->gallerytype) {
                $matches = true;
            }
            if (($matching && $matches) || (!$matching && !$matches)) {
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
        global $DB;
        if (empty($this->record->thumbnail)) {
            return null;
        }
        if (!$record = $DB->get_record('mediagallery_item', array('id' => $this->record->thumbnail))) {
            return null;
        }
        $item = new item($record, array('gallery' => $this));
        return $item->get_image_url(true);
    }

    public function moral_rights_asserted() {
        global $DB;

        $count = $DB->count_records('mediagallery_item', array('galleryid' => $this->record->id, 'moralrights' => 1));
        return $count > 0;
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
     * Returns what gallery type this falls into (image, audio, video).
     * @param bool $text If the function returns the internal or text representation of the item type.
     * @return int|null Returns the MEDIAGALLERY_TYPE_* that most closely matches the content. Otherwise null.
     */
    public function type($text = false) {
        if ($this->gallerytype == MEDIAGALLERY_TYPE_IMAGE) {
            return $text ? 'image' : MEDIAGALLERY_TYPE_IMAGE;
        } else if ($this->gallerytype == MEDIAGALLERY_TYPE_AUDIO) {
            return $text ? 'audio' : MEDIAGALLERY_TYPE_AUDIO;
        } else if ($this->gallerytype == MEDIAGALLERY_TYPE_VIDEO) {
            return $text ? 'video' : MEDIAGALLERY_TYPE_VIDEO;
        }

        return null;
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

        if ($userid == $this->record->userid) {
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

        return false;
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
        if (!empty($this->record->visibleother) && !has_capability('mod/mediagallery:viewall', $coll->context)) {
            if (time() < $this->record->visibleother) {
                return false;
            }
            return true;
        }

        if (has_capability('mod/mediagallery:viewall', $coll->context)) {
            return true;
        }

        if ($groupmode == VISIBLEGROUPS) {
            return true;
        }

        return false;
    }
}
