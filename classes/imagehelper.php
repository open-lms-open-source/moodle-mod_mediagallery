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

class imagehelper {

    /**
     * Rough estimate to check if an image will fit in memory for resizing.
     * We need to check before loading it, otherwise we get a fatal error.
     *
     * @param string $path Path to the file.
     * @static
     * @access public
     * @return bool true if there's enough memory to load the image, false otherwise.
     */
    public static function memory_check($path) {
        $limit = @ini_get('memory_limit');
        $limit = get_real_size($limit);
        $current = memory_get_usage();

        list($x, $y) = @getimagesize($path);
        $need = $x * $y * 3 * 1.7; // Bytes needed when uncompressed.

        if ($need < $limit - $current) {
            return true;
        }

        return false;
    }

    public static function mirror($image) {
        $width = imagesx($image);
        $height = imagesy($image);

        $srcx = $width - 1;
        $srcy = 0;
        $srcwidth = -$width;
        $srcheight = $height;

        $imgdest = imagecreatetruecolor ( $width, $height );

        if (imagecopyresampled ($imgdest, $image, 0, 0, $srcx, $srcy, $width, $height, $srcwidth, $srcheight)) {
            return $imgdest;
        }

        return $image;
    }
}
