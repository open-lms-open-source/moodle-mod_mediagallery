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

namespace mod_mediagallery\output\searchresults;

defined('MOODLE_INTERNAL') || die();

class renderable implements \renderable {

    public $results = array();
    public $pageurl;
    public $totalcount;
    public $page;
    public $perpage;

    public function __construct($results, $pageurl, $totalcount = 0, $page = 1, $perpage = 0) {
        $this->results = $results;
        $this->pageurl = $pageurl;
        $this->totalcount = $totalcount;
        $this->page = $page;
        $this->perpage = $perpage;
    }
}
