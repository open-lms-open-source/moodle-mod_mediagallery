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

namespace mod_mediagallery\output\collection;

defined('MOODLE_INTERNAL') || die();

class renderable implements \renderable {

    public $collection;
    public $galleries = array();
    public $maxreached = true;
    public $normallycanadd = true;
    public $readonly = false;
    public $tags = '';
    public $timestamp = 0;

    // Assignment related.
    public $hassubmitted = false;
    public $isassessable = false;
    public $linkedassigncmid = false;
    public $submissionsopen = false;
    public $userorgrouphasgallery = false;

    // Properties from collection object.
    public $id;
    public $mode = 'standard';
    public $thumbnailsperpage = 0;
    public $thumbnailsperrow = 0;

    public function __construct(\mod_mediagallery\collection $collection, array $galleries) {
        global $CFG, $DB;

        $this->collection = $collection;
        $this->galleries = $galleries;

        if ($collection->user_can_add_children()) {
            $this->maxreached = false;
        }

        if ($this->isassessable = $collection->is_assessable()) {
            $this->hassubmitted = $collection->has_submitted();
            if ($this->linkedassigncmid = $collection->get_linked_assignid()) {
                require_once($CFG->dirroot.'/mod/assign/locallib.php');
                $context = \context_module::instance($this->linkedassigncmid);
                $cm = get_coursemodule_from_id('assign', $this->linkedassigncmid, 0, false, MUST_EXIST);
                $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
                $assign = new \assign($context, $cm, $course);

                $this->submissionsopen = $assign->submissions_open();
            }
        }
        $this->readonly = $collection->is_read_only();
        $this->tags = $collection->get_tags();
        $mygalleries = $collection->get_my_galleries();
        $this->userorgrouphasgallery = !empty($mygalleries);

        foreach (array('id', 'mode', 'thumbnailsperrow', 'thumbnailsperpage') as $opt) {
            if (isset($collection->$opt)) {
                $this->$opt = $collection->$opt;
            }
        }
    }
}
