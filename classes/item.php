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

class item extends base {

    protected $context;
    protected $gallery;
    static protected $table = 'mediagallery_item';

    /**
     * Related stored files.
     */
    protected $file = null;
    protected $lowres = null;
    protected $thumbnail = null;
    protected static $defaultvalues = array(
        'sortorder' => 1000000,
        'display' => 1,
        'moralrights' => 1,
        'productiondate' => '',
    );

    public function __construct($recordorid, $options = array()) {
        if (!empty($options['gallery'])) {
            $this->gallery = $options['gallery'];
            unset($options['gallery']);
        }

        if (!empty($options['files']['item'])) {
            $this->file = $options['files']['item'];
        }
        if (!empty($options['files']['lowres'])) {
            $this->lowres = $options['files']['lowres'];
        }
        if (!empty($options['files']['thumbnail'])) {
            $this->thumbnail = $options['files']['thumbnail'];
        }

        parent::__construct($recordorid, $options);
        if (empty($this->gallery) && empty($options['nogallery'])) {
            $this->gallery = new gallery($this->galleryid);
        }
    }

    public function copy($targetid) {
        $newitemrecord = clone $this->record;
        unset($newitemrecord->id);
        $newitemrecord->galleryid = $targetid;

        $newitem = self::create($newitemrecord);

        $fs = get_file_storage();
        if ($file = $this->get_file()) { // Item.
            $fileinfo = array(
                'contextid'     => $newitem->get_context()->id,
                'itemid'        => $newitem->id,
            );
            $fs->create_file_from_storedfile($fileinfo, $file);
        }
        if ($file = $this->get_file(true)) { // Thumbnail.
            $fileinfo = array(
                'contextid'     => $newitem->get_context()->id,
                'itemid'        => $newitem->id,
            );
            $fs->create_file_from_storedfile($fileinfo, $file);
        }
        if ($file = $this->get_stored_file_by_type('lowres')) { // Low res version of full image.
            $fileinfo = array(
                'contextid'     => $newitem->get_context()->id,
                'itemid'        => $newitem->id,
            );
            $fs->create_file_from_storedfile($fileinfo, $file);
        }
        return $newitem;
    }

    public static function create(\stdClass $data) {
        global $DB, $USER;
        foreach (static::$defaultvalues as $key => $val) {
            if (!isset($data->$key)) {
                $data->$key = $val;
            }
        }
        $data->timecreated = time();
        if (!isset($data->userid)) {
            $data->userid = $USER->id;
        }
        $result = parent::create($data);
        if (!empty($data->thumbnail)) {
            $sql = "UPDATE {mediagallery_gallery}
                    SET thumbnail = :item
                    WHERE id = :galleryid";
            $DB->execute($sql, array('item' => $result->id, 'galleryid' => $result->galleryid));
        }

        $params = array(
            'context' => $result->get_context(),
            'objectid' => $result->id,
        );
        if (!empty($data->nosync)) {
            $params['other']['nosync'] = true;
        }
        $event = \mod_mediagallery\event\item_created::create($params);
        $event->add_record_snapshot('mediagallery_item', $result->get_record());
        $event->trigger();

        return $result;
    }

    public static function create_from_archive(gallery $gallery, \stored_file $storedfile, $formdata = array()) {
        global $DB;
        $context = $gallery->get_collection()->context;

        $maxitems = $gallery->get_collection()->maxitems;
        $count = $DB->count_records('mediagallery_item', array('galleryid' => $gallery->id));
        if ($maxitems != 0 && $count >= $maxitems) {
            return;
        }

        $fs = get_file_storage();
        $packer = get_file_packer('application/zip');
        $fs->delete_area_files($context->id, 'mod_mediagallery', 'unpacktemp', 0);
        $storedfile->extract_to_storage($packer, $context->id, 'mod_mediagallery', 'unpacktemp', 0, '/');
        $itemfiles = $fs->get_area_files($context->id, 'mod_mediagallery', 'unpacktemp', 0);
        $storedfile->delete();

        foreach ($itemfiles as $storedfile) {
            if ($storedfile->get_filesize() == 0 || preg_match('#^/.DS_Store|__MACOSX/#', $storedfile->get_filepath())) {
                continue;
            }
            if ($maxitems != 0 && $count >= $maxitems) {
                break;
            }
            $filename = $storedfile->get_filename();

            // Create an item.
            $data = new \stdClass();
            $data->caption = $filename;
            $data->description = '';
            $data->display = 1;

            $metafields = array(
                'moralrights' => 1,
                'originalauthor' => '',
                'productiondate' => 0,
                'medium' => '',
                'publisher' => '',
                'broadcaster' => '',
                'reference' => ''
            );
            foreach ($metafields as $field => $default) {
                $data->$field = isset($formdata->$field) ? $formdata->$field : $default;
            }

            $data->galleryid = $gallery->id;
            if (!$count) {
                $data->thumbnail = 1;
                $count = 0;
            }
            $item = self::create($data);

            // Copy the file into the correct area.
            $fileinfo = array(
                'contextid'     => $context->id,
                'component'     => 'mod_mediagallery',
                'filearea'      => 'item',
                'itemid'        => $item->id,
                'filepath'      => '/',
                'filename'      => $filename
            );
            if (!$fs->get_file($context->id, 'mod_mediagallery', 'item', $item->id, '/', $filename)) {
                $storedfile = $fs->create_file_from_storedfile($fileinfo, $storedfile);
            }
            $item->generate_image_by_type('lowres');
            $item->generate_image_by_type('thumbnail');
            $count++;
        }
        $fs->delete_area_files($context->id, 'mod_mediagallery', 'unpacktemp', 0);
    }

    /**
     * Delete the item and everything related to it.
     */
    public function delete($options = array()) {
        global $DB;

        $params = array(
            'context' => $this->get_context(),
            'objectid' => $this->id,
        );
        if (!empty($options['nosync'])) {
            $params['other']['nosync'] = true;
        }
        $event = \mod_mediagallery\event\item_deleted::create($params);
        $event->add_record_snapshot('mediagallery_item', $this->record);
        $event->trigger();

        $fs = get_file_storage();
        $fs->delete_area_files($this->get_context()->id, 'mod_mediagallery', 'item', $this->record->id);
        $fs->delete_area_files($this->get_context()->id, 'mod_mediagallery', 'lowres', $this->record->id);
        $fs->delete_area_files($this->get_context()->id, 'mod_mediagallery', 'thumbnail', $this->record->id);

        $DB->delete_records('mediagallery_userfeedback', array('itemid' => $this->record->id));
        \comment::delete_comments(array('commentarea' => 'item', 'itemid' => $this->record->id));

        return parent::delete();
    }

    public static function delete_all_by_gallery($galleryid) {
        global $DB;

        // Bulk delete files.
        if ($itemids = $DB->get_records('mediagallery_item', array('galleryid' => $galleryid), '', 'id')) {
            $fs = get_file_storage();

            list($insql, $params) = $DB->get_in_or_equal(array_keys($itemids), SQL_PARAMS_NAMED, 'mgi');
            $gallery = new gallery($galleryid);
            $cm = get_coursemodule_from_instance('mediagallery', $gallery->instanceid);
            $context = \context_module::instance($cm->id);
            $fs->delete_area_files_select($context->id, 'mod_mediagallery', 'item', $insql, $params);
            $fs->delete_area_files_select($context->id, 'mod_mediagallery', 'lowres', $insql, $params);
            $fs->delete_area_files_select($context->id, 'mod_mediagallery', 'thumbnail', $insql, $params);
        }

        // Bulk delete userfeedback.
        $sql = "DELETE FROM {mediagallery_userfeedback}
                WHERE itemid IN (
                    SELECT id
                    FROM {mediagallery_item}
                    WHERE galleryid = :galleryid
                )";
        $DB->execute($sql, array('galleryid' => $galleryid));
        $DB->delete_records('mediagallery_item', array('galleryid' => $galleryid));
        return true;
    }

    public function generate_thumbnail(\stored_file $storedfile = null) {
        return $this->generate_image_by_type('thumbnail');
    }

    public function generate_image_by_type($type = 'thumbnail', $force = false, \stored_file $file = null) {
        global $CFG;
        $originalfile = $this->get_stored_file_by_type();
        if ($type != 'item' && !is_null($file)) {
            $originalfile = $file;
        } else if (!$originalfile || !file_mimetype_in_typegroup($originalfile->get_mimetype(), 'web_image')
            || $originalfile->get_mimetype() == 'image/svg+xml') {
            return false;
        }
        if ($type == 'item') {
            return $originalfile;
        }
        if (!$force && ($newfile = $this->get_stored_file_by_type($type))) {
            return $newfile;
        }

        $fileinfo = array(
            'contextid' => $this->get_context()->id,
            'component' => 'mod_mediagallery',
            'filearea' => $type,
            'itemid' => $this->record->id,
            'userid' => $originalfile->get_userid(),
            'filepath' => $originalfile->get_filepath(),
            'filename' => $originalfile->get_filename());

        if ($type == 'thumbnail') {
            $w = 250;
            $h = 250;
        } else {
            $w = 960;
            $h = 940;
        }

        ob_start();
        if (!$resized = $this->get_image_resized($originalfile, $w, $h, 0, 0, $type != 'lowres')) {
            // File is smaller than lowres, so don't bother.'
            return false;
        }
        imagepng($resized);
        $newfiledata = ob_get_clean();
        $fs = get_file_storage();
        $fs->delete_area_files($this->get_context()->id, 'mod_mediagallery', $type, $this->record->id);
        return $fs->create_file_from_string($fileinfo, $newfiledata);
    }

    private function get_image_resized(\stored_file $file = null, $height = 250, $width = 250, $offsetx = 0, $offsety = 0,
        $crop = true) {
        global $CFG;

        if (is_null($file) && !$file = $this->get_stored_file_by_type('item')) {
            return false;
        }

        require_once($CFG->libdir.'/gdlib.php');

        $oldmemlimit = @ini_get('memory_limit');
        raise_memory_limit(MEMORY_EXTRA);

        $tempfile = $file->copy_content_to_temp();

        if (!imagehelper::memory_check($tempfile)) {
            return false;
        }

        $image = imagecreatefromstring(file_get_contents($tempfile));

        // Func exif_read_data is only supported for jpeg/tiff images.
        $mimetype = $file->get_mimetype();
        $isjpegortiff = $mimetype == 'image/jpeg' || $mimetype == 'image/tiff';

        if ($isjpegortiff && function_exists('exif_read_data') && ($exif = exif_read_data($tempfile))) {
            $ort = 1;
            if (isset($exif['IFD0']['Orientation'])) {
                $ort = $exif['IFD0']['Orientation'];
            } else if (isset($exif['Orientation'])) {
                $ort = $exif['Orientation'];
            }
            $mirror = false;
            $degree = 0;
            switch ($ort) {
                case 2: // Horizontal flip.
                    $mirror = true;
                break;

                case 3: // 180 Rotate left.
                    $degree = 180;
                break;

                case 4: // Vertical flip.
                    $degree = 180;
                    $mirror = true;
                break;

                case 5: // Vertical flip + 90 rotate right.
                    $degree = 270;
                    $mirror = true;
                break;

                case 6: // 90 rotate right.
                    $degree = 270;
                break;

                case 7: // Horizontal flip + 90 rotate right.
                    $degree = 90;
                    $mirror = true;
                break;

                case 8: // 90 rotate left.
                    $degree = 90;
                break;

                default: // Do nothing.
                break;
            }

            if ($degree) {
                $image = imagerotate($image, $degree, 0);
            }
            if ($mirror) {
                $image = imagehelper::mirror($image);
            }
        }

        $info = array(
            'width' => imagesx($image),
            'height' => imagesy($image),
        );

        $cx = $info['width'] / 2;
        $cy = $info['height'] / 2;

        $ratiow = $width / $info['width'];
        $ratioh = $height / $info['height'];

        if ($info['width'] <= $width && $info['height'] <= $height) {
            // Images containing EXIF orientation data don't display correctly in browsers.
            // So even though we're not making a smaller version of the original here, we still
            // want to have it displayed right-side up.
            $width = $info['width'];
            $height = $info['height'];
            $ratiow = $width / $info['width'];
            $ratioh = $height / $info['height'];
        }
        if (!$crop) {
            if ($ratiow < $ratioh) {
                $height = floor($info['height'] * $ratiow);
                $width = floor($info['width'] * $ratiow);
            } else {
                $height = floor($info['height'] * $ratioh);
                $width = floor($info['width'] * $ratioh);
            }
            $srcw = $info['width'];
            $srch = $info['height'];
            $srcx = 0;
            $srcy = 0;
        } else if ($ratiow < $ratioh) {
            $srcw = floor($width / $ratioh);
            $srch = $info['height'];
            $srcx = floor($cx - ($srcw / 2)) + $offsetx;
            $srcy = $offsety;
        } else {
            $srcw = $info['width'];
            $srch = floor($height / $ratiow);
            $srcx = $offsetx;
            $srcy = floor($cy - ($srch / 2)) + $offsety;
        }

        $resized = imagecreatetruecolor($width, $height);
        imagecopybicubic($resized, $image, 0, 0, $srcx, $srcy, $width, $height, $srcw, $srch);

        unset($image);
        reduce_memory_limit($oldmemlimit);

        return $resized;
    }


    public function get_context() {
        global $DB;
        if (!empty($this->context)) {
            return $this->context;
        }
        if (empty($this->gallery)) {
            $this->gallery = new gallery($this->record->galleryid);
        }

        $this->context = $this->gallery->get_collection()->context;
        return $this->context;
    }

    public function get_embed_url() {
        global $CFG;
        $embed = '';
        if ($id = $this->get_youtube_videoid()) {
            $embed = "https://www.youtube.com/embed/{$id}";
        } else if ($this->type() == self::TYPE_IMAGE) {
            $embed = $this->get_image_url_by_type();
        } else {
            if (!empty($this->objectid)) {
                $embed = $this->get_box_url();
            } else if ($file = $this->get_file()) {
                $embed = \moodle_url::make_pluginfile_url($this->get_context()->id, 'mod_mediagallery', 'item', $this->record->id, '/', $file->get_filename());
            }
        }
        return $embed;
    }

    public function get_file($thumbnail = false) {
        $type = $thumbnail ? 'thumbnail' : 'file';
        if (!is_null($this->$type)) {
            return $this->$type;
        }
        $fs = get_file_storage();
        $filearea = $thumbnail ? 'thumbnail' : 'item';
        $files = $fs->get_area_files($this->get_context()->id, 'mod_mediagallery', $filearea, $this->record->id, 'id', false);

        if (empty($files)) {
            return $this->$type = false;
        }
        return $this->$type = current($files);
    }

    private function get_stored_file_by_type($type = 'item') {
        $property = $type == 'item' ? 'file' : $type;
        if (!is_null($this->$property)) {
            return $this->$property;
        }
        $fs = get_file_storage();
        $files = $fs->get_area_files($this->get_context()->id, 'mod_mediagallery', $type, $this->record->id, 'id', false);

        if (empty($files)) {
            $file = null;
            if ($type != 'item') {
                $file = $this->generate_image_by_type($type, true);
            }
            return $this->$property = $file;
        }
        return $this->$property = current($files);
    }

    public function get_metainfo() {
        $info = clone $this->record;
        $info->timecreatedformatted = '';
        $info->productiondateformatted = '';
        $info->copyrightformatted = '';
        if ($info->timecreated > 0) {
            $info->timecreatedformatted = userdate($info->timecreated, get_string('strftimedaydatetime', 'langconfig'));
        }
        if ($info->productiondate > 0) {
            $info->productiondateformatted = userdate($info->productiondate, get_string('strftimedaydate', 'langconfig'));
        }
        if (!has_capability('moodle/user:viewhiddendetails', $this->get_context())) {
            $info->username = null;
        }
        $info->tags = $this->get_tags();
        return $info;
    }

    public function get_structured_metainfo() {
        global $DB;

        $displayfields = array(
            'caption' => get_string('caption', 'mod_mediagallery'),
            'description' => get_string('description'),
            'originalauthor' => get_string('originalauthor', 'mod_mediagallery'),
            'productiondateformatted' => get_string('productiondate', 'mod_mediagallery'),
            'medium' => get_string('medium', 'mod_mediagallery'),
            'publisher' => get_string('publisher', 'mod_mediagallery'),
            'broadcaster' => get_string('broadcaster', 'mod_mediagallery'),
            'reference' => get_string('reference', 'mod_mediagallery'),
            'moralrightsformatted' => get_string('moralrights', 'mod_mediagallery'),
            'copyrightformatted' => get_string('copyright', 'mod_mediagallery'),
            'tags' => get_string('tags', 'mod_mediagallery'),
        );

        $info = $this->get_socialinfo();
        $info->fields = array();

        $data = clone $this->record;
        $data->timecreatedformatted = '';
        $data->productiondateformatted = '';
        $data->copyrightformatted = '';
        $data->tags = $this->get_tags();
        if ($data->timecreated > 0) {
            $data->timecreatedformatted = userdate($data->timecreated, get_string('strftimedaydatetime', 'langconfig'));
        }
        if ($data->productiondate > 0) {
            $data->productiondateformatted = userdate($data->productiondate, get_string('strftimedaydate', 'langconfig'));
        }
        $data->moralrightsformatted = $data->moralrights ? get_string('yes') : get_string('no');
        foreach ($displayfields as $key => $displayname) {
            $info->fields[] = array(
                'displayname' => $displayname,
                'name' => $key,
                'value' => $data->$key,
            );
        }

        if ($user = $DB->get_record('user', array('id' => $this->record->userid), 'id, firstname, lastname')) {
            $linkurl = new \moodle_url('/user/profile.php', array('id' => $this->record->userid));
            $info->fields[] = array(
                'displayname' => get_string('uploader', 'mod_mediagallery'),
                'name' => 'owner',
                'link' => $linkurl->out(),
                'value' => "{$user->firstname} {$user->lastname}",
            );
        }

        return $info;
    }

    private function get_youtube_videoid() {
        $id = null;
        if ($this->record->externalurl) {
            $url = $this->record->externalurl;
            if (strpos($this->record->externalurl, '#') !== false) {
                $url = substr($this->record->externalurl, 0, strpos($this->record->externalurl, '#'));
            }
            preg_match('/(youtu\.be\/|youtube\.com\/(watch\?(.*&)?v=|(embed|v)\/))([^\?&"\'>]+)/', $url, $matches);
            if (isset($matches[5])) {
                $id = $matches[5];
            }
        }
        return $id;
    }

    public function get_source() {
        if (empty($this->record->externalurl) && empty($this->record->objectid)) {
            return 'internal';
        }
        if ($this->get_youtube_videoid()) {
            return 'youtube';
        }
        return 'external';
    }

    public function get_image_url($preview = false) {
        global $CFG;

        $context = $this->get_context();

        if ($this->record->externalurl) {
            $url = $this->record->externalurl;
            if (strpos($this->record->externalurl, '#') !== false) {
                $url = substr($this->record->externalurl, 0, strpos($this->record->externalurl, '#'));
            }
            preg_match('/(youtu\.be\/|youtube\.com\/(watch\?(.*&)?v=|(embed|v)\/))([^\?&"\'>]+)/', $url, $matches);
            if (isset($matches[5])) {
                return new \moodle_url('https://img.youtube.com/vi/'.$matches[5].'/0.jpg');
            }
        }

        $type = $preview ? 'thumbnail' : 'item';
        if (!empty($this->objectid) && ($this->type() !== self::TYPE_AUDIO && $this->type() !== null || $type == 'item')) {
            return $this->get_box_url($type);
        }

        if (!$file = $this->get_stored_file_by_type('thumbnail')) {
            if (!$file = $this->get_stored_file_by_type()) {
                return null;
            }
        }
        if (!file_mimetype_in_typegroup($file->get_mimetype(), 'web_image')) {
            // If its not an image, we want to display a moodle filetype icon, so we need to use the item path.
            $type = 'item';
        }
        $path = \moodle_url::make_pluginfile_url($this->get_context()->id, 'mod_mediagallery', $type, $this->record->id, '/', $file->get_filename());
        if ($preview && $type == 'item') {
            $path->param('preview', 'bigthumb');
        }

        if (!$preview && $this->type() != self::TYPE_IMAGE) {
            $path = $this->get_image_url(true);
        }

        return $path;
    }

    protected function get_box_url($type = 'item') {
        return '';
    }

    public function get_image_url_by_type($type = 'item') {
        global $CFG;

        $context = $this->get_context();

        if ($this->record->externalurl) {
            $url = $this->record->externalurl;
            if (strpos($this->record->externalurl, '#') !== false) {
                $url = substr($this->record->externalurl, 0, strpos($this->record->externalurl, '#'));
            }
            preg_match('/(youtu\.be\/|youtube\.com\/(watch\?(.*&)?v=|(embed|v)\/))([^\?&"\'>]+)/', $url, $matches);
            if (isset($matches[5])) {
                return new \moodle_url('https://img.youtube.com/vi/'.$matches[5].'/0.jpg');
            }

            // Handle FILE_EXTERNAL repository content.
            if (preg_match('#^'.$CFG->wwwroot.'/repository/([a-z][a-z0-9]*)/#', $url, $matches) && !empty($matches)) {
                require_once("{$CFG->dirroot}/repository/{$matches[1]}/lib.php");
                $class = "repository_{$matches[1]}";
                if (method_exists($class, "get_mediagallery_link")) {
                    return $class::get_mediagallery_link($this->record->externalurl, $type);
                }
                return $this->record->externalurl;
            }
        }

        // Fetch the box url for thumbnails of images and video. Documents and audio don't need it.
        if (!empty($this->objectid) && ($this->type() !== self::TYPE_AUDIO && $this->type() !== null || $type == 'item')) {
            return $this->get_box_url($type);
        }

        $urltype = $type;
        if (!$file = $this->get_stored_file_by_type($type)) {
            if (!$file = $this->get_stored_file_by_type()) {
                return null;
            }
            $urltype = 'item';
        }
        $isimagetype = file_mimetype_in_typegroup($file->get_mimetype(), 'web_image');
        if (!$isimagetype) {
            // If its not an image, we want to display a moodle filetype icon, so we need to use the item path.
            $urltype = 'item';
        }
        $path = \moodle_url::make_pluginfile_url($this->get_context()->id, 'mod_mediagallery', $urltype, $this->record->id, '/', $file->get_filename());

        // For audio/video files, this has moodle display a filetype icon.
        if ($type == 'thumbnail' && $urltype == 'item' && !$isimagetype) {
            $path->param('preview', 'bigthumb');
        }

        if ($type != 'thumbnail' && $this->type() != self::TYPE_IMAGE) {
            $path = $this->get_image_url_by_type('thumbnail');
        }

        return $path;
    }

    public function get_like_count() {
        global $DB;
        $select = 'liked = 1 AND itemid = :itemid';
        $count = $DB->count_records_select('mediagallery_userfeedback', $select, array('itemid' => $this->record->id));
        $count = is_null($count) ? 0 : $count;
        return $count;
    }


    public function get_socialinfo() {
        global $CFG, $DB;

        $info = new \stdClass();
        $info->ratings = null;
        $info->contextid = $this->get_context()->id;
        if ($this->gallery->can_like()) {
            $info->likes = $DB->count_records('mediagallery_userfeedback', array('itemid' => $this->record->id, 'liked' => 1));
            $info->likedbyme = false;

            if ($fb = $this->get_userfeedback()) {
                if ($fb->liked) {
                    $info->likedbyme = true;
                }
            }
        }

        if ($this->gallery->can_comment()) {
            $cmtopt = new \stdClass();
            $cmtopt->area = 'item';
            $cmtopt->context = $this->get_context();
            $cmtopt->itemid = $this->record->id;
            $cmtopt->showcount = true;
            $cmtopt->component = 'mod_mediagallery';
            $cmtopt->autostart = true;
            $comment = new \comment($cmtopt);
            $info->commentcontrol = $comment->output(true);
            preg_match('#comment-link-([\w]+)#', $info->commentcontrol, $matches);
            $info->client_id = isset($matches[1]) ? $matches[1] : null;
        }

        // Creator name.
        $info->extradetails = '';

        return $info;
    }

    public function get_userfeedback() {
        global $DB, $USER;
        return $DB->get_record('mediagallery_userfeedback', array('itemid' => $this->record->id, 'userid' => $USER->id));
    }

    public function file_icon() {
        if ($file = $this->get_file()) {
            return file_file_icon($file);
        } else if (!empty($this->record->externalurl)) {
            if ($this->get_youtube_videoid()) {
                return file_mimetype_icon('video/mpeg');
            }
            return file_mimetype_icon('image/jpeg');
        }
        return null;
    }

    public function like() {
        global $DB, $USER;

        if (!has_capability('mod/mediagallery:like', $this->get_context())) {
            return false;
        }

        if ($fb = $this->get_userfeedback()) {
            $fb->liked = 1;
            $DB->update_record('mediagallery_userfeedback', $fb);
        } else {
            $fb = (object) array(
                'itemid' => $this->record->id,
                'userid' => $USER->id,
                'liked' => 1,
            );
            $DB->insert_record('mediagallery_userfeedback', $fb);
        }
        return $this->get_like_count();
    }

    /**
     * Returns what gallery type this falls into (image, audio, video).
     * @param bool $text If the function returns the internal or text representation of the item type.
     * @return int|null Returns the self::TYPE_* that most closely matches the content. Otherwise null.
     */
    public function type($text = false) {
        if ($this->record->externalurl != '') {
            // External URLs are either youtube videos or images.
            if ($this->get_youtube_videoid()) {
                return $text ? 'video' : self::TYPE_VIDEO;
            }
            return $text ? 'image' : self::TYPE_IMAGE;
        }

        if (!$file = $this->get_file()) {
            return null;
        }

        $videogroups = array('web_video');
        if (!empty($this->objectid)) {
            $videogroups[] = 'video';
        }

        $mimetype = $this->file->get_mimetype();
        if (file_mimetype_in_typegroup($mimetype, 'web_image')) {
            return $text ? 'image' : self::TYPE_IMAGE;
        } else if (file_mimetype_in_typegroup($mimetype, 'web_audio')) {
            return $text ? 'audio' : self::TYPE_AUDIO;
        } else if (file_mimetype_in_typegroup($mimetype, $videogroups)) {
            return $text ? 'video' : self::TYPE_VIDEO;
        }

        $texttotype = array(
            'audio' => self::TYPE_AUDIO,
            'image' => self::TYPE_IMAGE,
            'video' => self::TYPE_VIDEO,
        );

        if ($mimetype == 'document/unknown' && !empty($this->objectid)) {
            $ref = $this->file->get_reference_details();
            if (isset($ref->type) && in_array($ref->type, array('audio', 'image', 'video'))) {
                return $text ? $ref->type : $texttotype[$ref->type];
            }
        }

        return null;
    }

    public function unlike() {
        global $DB;

        if (!has_capability('mod/mediagallery:like', $this->get_context())) {
            return false;
        }

        if (!$fb = $this->get_userfeedback()) {
            // No record is the same as not liking it.
            return;
        }

        $fb->liked = 0;
        $DB->update_record('mediagallery_userfeedback', $fb);
        return $this->get_like_count();
    }

    public function update($data) {
        global $DB;
        if (!$data->display && empty($this->record->sortorder)) {
            $data->sortorder = 1000000;
        }
        if ($data->thumbnail) {
            $sql = "UPDATE {mediagallery_gallery}
                    SET thumbnail = :item
                    WHERE id = :galleryid";
            $DB->execute($sql, array('item' => $this->record->id, 'galleryid' => $this->record->galleryid));
        }
        if (!$this->is_thebox_creator_or_agent()) {
            $data->nosync = true;
        }
        return parent::update($data);
    }

    public function user_can_edit($userid = null) {
        global $USER;

        if (is_null($userid)) {
            $userid = $USER->id;
        }

        if ($userid == $this->record->userid || has_capability('mod/mediagallery:manage', $this->get_context(), $userid)) {
            return true;
        }

        $coll = $this->gallery->get_collection();
        if ($this->gallery->record->groupid != 0 && $coll->options['groupmode'] != NOGROUPS) {
            if ($userid != $USER->id) {
                $groups = groups_get_all_groups($coll->cm->course, $USER->id, $coll->cm->groupingid);
            } else {
                $groups = $coll->options['groups'];
            }
            if (isset($groups[$this->gallery->record->groupid])) {
                return true;
            }
        }

        return $this->gallery->mode == 'thebox' && $this->is_thebox_creator_or_agent();
    }

    public function user_can_remove($userid = null) {
        global $USER;

        if (is_null($userid)) {
            $userid = $USER->id;
        }

        if ($this->user_can_edit($userid)) {
            return true;
        }
        return has_capability('mod/mediagallery:manage', $this->get_context(), $userid);
    }

    public function thebox_processed() {
        if (!empty($this->objectid) && $this->processing_status == 'complete') {
            return true;
        }
        return false;
    }
}
