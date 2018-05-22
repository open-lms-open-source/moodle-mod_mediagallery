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
 * Privacy Subsystem implementation for mod_mediagallery.
 *
 * @package    mod_mediagallery
 * @author     Adam Olley <adam.olley@blackboard.com>
 * @copyright  2018 Blackboard Inc.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_mediagallery\privacy;

use \core_privacy\request\approved_contextlist;
use \core_privacy\request\writer;
use \core_privacy\metadata\item_collection;

defined('MOODLE_INTERNAL') || die();

/**
 * Subcontext helper trait.
 *
 * @copyright  2018 Blackboard Inc.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait subcontext_info {
    /**
     * Get the gallery part of the subcontext.
     *
     * @param   \stdClass   $gallery The gallery
     * @return  array
     */
    protected static function get_gallery_area(\stdClass $gallery) : Array {
        $pathparts = [];
        if (!empty($discussion->groupname)) {
            $pathparts[] = get_string('groups');
            $pathparts[] = $discussion->groupname;
        }

        $parts = [
            $gallery->id,
            $gallery->name,
        ];

        $galleryname = implode('-', $parts);

        $pathparts[] = get_string('areagallery', 'mod_mediagallery');
        $pathparts[] = $galleryname;

        return $pathparts;
    }

    /**
     * Get the item part of the subcontext.
     *
     * @param   \stdClass   $gallery The gallery
     * @return  array
     */
    protected static function get_item_area() : Array {
        $pathparts = [
            get_string('areaitem', 'mod_mediagallery'),
        ];

        return $pathparts;
    }

    /**
     * Get the subcontext for the supplied collection, gallery, and item combination.
     *
     * @param   \stdClass   $collection The mediagallery.
     * @param   \stdClass   $gallery The gallery.
     * @param   \stdClass   $item The item.
     * @return  array
     */
    protected static function get_subcontext($collection, $gallery = null, $item = null) {
        $subcontext = [];
        if (null !== $gallery) {
            $subcontext += self::get_gallery_area($gallery);

            if (null !== $item) {
                $subcontext += self::get_item_area();
            }
        }

        return $subcontext;
    }

}
