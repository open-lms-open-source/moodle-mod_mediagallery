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

namespace mod_mediagallery\output\gallery;

defined('MOODLE_INTERNAL') || die();

class renderable implements \renderable {
    const MEDIASIZE_SM = 0;
    const MEDIASIZE_MD = 1;
    const MEDIASIZE_LG = 2;

    public $gallery = null;

    public $editing = false;
    public $page = 0;
    public $mediasize = self::MEDIASIZE_MD;
    public $mediasizeclass = '';
    public $comments = null;
    public $syncstamp = null;
    public $options = array();
    public $nosample = false;
    public $focus = null;
    public $galleryview;

    public function __construct(\mod_mediagallery\gallery $gallery, $editing = false, $options = array()) {
        $this->gallery = $gallery;
        $this->options = $options;
        $this->editing = $editing;
        $this->galleryview = $gallery->get_display_settings()->galleryview;

        foreach (array('page', 'comments', 'mediasize', 'syncstamp', 'nosample', 'focus') as $opt) {
            if (isset($options[$opt])) {
                $this->$opt = $options[$opt];
            }
        }

        if ($this->mediasize == self::MEDIASIZE_SM) {
            $this->mediasizeclass = ' small';
        } else if ($this->mediasize == self::MEDIASIZE_LG) {
            $this->mediasizeclass = ' large';
        }
    }
}
