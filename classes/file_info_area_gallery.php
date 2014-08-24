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
 * File browsing support.
 *
 * @package    mod_mediagallery
 * @copyright  2014 NetSpot Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * File browsing support class.
 *
 * @package    mod_mediagallery
 * @copyright  2014 NetSpot Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_mediagallery_file_info_area_gallery extends file_info_stored {

    private $gallery;

    public function __construct(file_browser $browser, $context, $storedfile, $urlbase, $topvisiblename, $itemidused, $readaccess,
                                $writeaccess, $areaonly) {
        if ($storedfile->get_filearea() == 'gallery') {
            $this->gallery = new \mod_mediagallery\gallery($storedfile->get_itemid());
            $topvisiblename = $this->gallery->name;
        }
        parent::__construct($browser, $context, $storedfile, $urlbase, $topvisiblename, $itemidused, $readaccess, $writeaccess,
                            $areaonly);
    }

    /**
     * Returns list of children.
     *
     * @return array of file_info instances
     */
    public function get_children() {
        $result = array();

        $items = $this->gallery->get_items();
        foreach ($items as $item) {
            $result[] = new file_info_stored($this->browser, $this->context, $item->get_file());
        }

        return $result;
    }

    /**
     * Returns list of children which are either files matching the specified extensions
     * or folders that contain at least one such file.
     *
     * @param string|array $extensions, either '*' or array of lowercase extensions, i.e. array('.gif','.jpg')
     * @return array of file_info instances
     */
    public function get_non_empty_children($extensions = '*') {
        $items = $this->gallery->get_items();
        $result = array();

        foreach ($items as $item) {
            $file = $item->get_file();
            $extension = core_text::strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
            if ($file->is_directory() || $extensions === '*' || (!empty($extension) && in_array('.'.$extension, $extensions))) {
                $fileinfo = new file_info_stored($this->browser, $this->context, $file, $this->urlbase, $this->topvisiblename,
                                                 $this->itemidused, $this->readaccess, $this->writeaccess, false);
                if (!$file->is_directory() || $fileinfo->count_non_empty_children($extensions)) {
                    $result[] = $fileinfo;
                }
            }
        }

        return $result;
    }
}
