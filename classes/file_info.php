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

/**
 * File browsing support class.
 *
 * @package    mod_mediagallery
 * @copyright  2014 NetSpot Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_mediagallery_file_info extends file_info {

    /** @var stdClass Course object */
    protected $course;
    /** @var stdClass Course module object */
    protected $cm;
    /** @var array Available file areas */
    protected $areas;
    /** @var string File area to browse */
    protected $filearea;

    /**
     * Constructor
     *
     * @param file_browser $browser file_browser instance
     * @param stdClass $course course object
     * @param stdClass $cm course module object
     * @param stdClass $context module context
     * @param array $areas available file areas
     * @param string $filearea file area to browse
     */
    public function __construct($browser, $course, $cm, $context, $areas, $filearea) {
        parent::__construct($browser, $context);
        $this->course = $course;
        $this->cm = $cm;
        $this->areas = $areas;
        $this->filearea = $filearea;
    }

    /**
     * Returns list of standard virtual file/directory identification.
     * The difference from stored_file parameters is that null values
     * are allowed in all fields
     * @return array with keys contextid, filearea, itemid, filepath and filename
     */
    public function get_params() {
        return array('contextid' => $this->context->id,
                     'component' => 'mod_mediagallery',
                     'filearea' => $this->filearea,
                     'itemid' => null,
                     'filepath' => null,
                     'filename' => null);
    }

    /**
     * Returns localised visible name.
     * @return string
     */
    public function get_visible_name() {
        return $this->areas[$this->filearea];
    }

    /**
     * Can I add new files or directories?
     * @return bool
     */
    public function is_writable() {
        return false;
    }

    /**
     * Is directory?
     * @return bool
     */
    public function is_directory() {
        return true;
    }

    /**
     * Returns list of children.
     * @return array of file_info instances
     */
    public function get_children() {
        return $this->get_filtered_children('*', false, true);
    }

    /**
     * Help function to return files matching extensions or their count
     *
     * @param string|array $extensions either '*' or array of lowercase extensions, i.e. array('.gif','.jpg')
     * @param bool|int $countonly if false returns the children, if an int returns just the
     *    count of children but stops counting when $countonly number of children is reached
     * @param bool $returnemptyfolders if true returns items that don't have matching files inside
     * @return array|int array of file_info instances or the count
     */
    private function get_filtered_children($extensions = '*', $countonly = false, $returnemptyfolders = false) {
        global $CFG;
        $galleries = \mod_mediagallery\collection::get_public_galleries_by_contextid($this->context->id, false);

        $children = array();
        $urlbase = $CFG->wwwroot.'/pluginfile.php';
        foreach ($galleries as $id => $name) {
            $storedfile = new virtual_root_file($this->context->id, 'mod_mediagallery', 'gallery', $id);
            $child = new mod_mediagallery_file_info_area_gallery($this->browser, $this->context, $storedfile, $urlbase, $name,
                                                                 true, true, true, false);

            $children[] = $child;
            if ($countonly !== false && count($children) >= $countonly) {
                break;
            }
        }
        if ($countonly !== false) {
            return count($children);
        }

        return $children;
    }

    /**
     * Returns list of children which are either files matching the specified extensions
     * or folders that contain at least one such file.
     *
     * @param string|array $extensions either '*' or array of lowercase extensions, i.e. array('.gif','.jpg')
     * @return array of file_info instances
     */
    public function get_non_empty_children($extensions = '*') {
        return $this->get_filtered_children($extensions, false);
    }

    /**
     * Returns the number of children which are either files matching the specified extensions
     * or folders containing at least one such file.
     *
     * @param string|array $extensions for example '*' or array('.gif','.jpg')
     * @param int $limit stop counting after at least $limit non-empty children are found
     * @return int
     */
    public function count_non_empty_children($extensions = '*', $limit = 1) {
        return $this->get_filtered_children($extensions, $limit);
    }

    /**
     * Returns parent file_info instance
     * @return file_info or null for root
     */
    public function get_parent() {
        return $this->browser->get_file_info($this->context);
    }
}
