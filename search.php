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
 * Prints a particular instance of mediagallery
 *
 * @package    mod_mediagallery
 * @copyright  NetSpot Pty Ltd
 * @author     Adam Olley <adam.olley@netspot.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/classes/search_form.php');

$courseid = required_param('id', PARAM_INT); // A course_module id.
$search = trim(optional_param('search', '', PARAM_NOTAGS)); // A mediagallery id.
$caption = trim(optional_param('caption', '', PARAM_NOTAGS));
$moralrights = optional_param('moralrights', '', PARAM_NOTAGS);
$originalauthor = trim(optional_param('originalauthor', '', PARAM_NOTAGS));
$medium = trim(optional_param('medium', '', PARAM_NOTAGS));
$publisher = trim(optional_param('publisher', '', PARAM_NOTAGS));
$collection = trim(optional_param('collection', '', PARAM_NOTAGS));
$searchstring = trim(optional_param('searchstring', '', PARAM_TEXT));
$courseonly = optional_param('courseonly', false, PARAM_BOOL);

$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 10, PARAM_INT);
$showform = optional_param('showform', 0, PARAM_INT);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

require_login($course);
$context = context_course::instance($courseid);
$PAGE->set_context($context);

$pageurl = new moodle_url('/mod/mediagallery/search.php', array('id' => $course->id));
$pageurl->param('courseonly', $courseonly);

$data = array();

if (empty($searchstring)) {
    if (!empty($search)) {
        $searchstring .= ' '.$search;
        $data['search'] = $search;
    }
    if (!empty($caption)) {
        $searchstring .= ' caption:'.$caption;
        $data['caption'] = $caption;
    }
    if (!empty($moralrights) || $moralrights === '0') {
        $searchstring .= ' moralrights:'.$moralrights;
        $data['moralrights'] = $moralrights;
    }
    if (!empty($originalauthor)) {
        $searchstring .= ' originalauthor:'.$originalauthor;
        $data['originalauthor'] = $originalauthor;
    }
    if (!empty($medium)) {
        $searchstring .= ' medium:'.$medium;
        $data['medium'] = $medium;
    }
    if (!empty($publisher)) {
        $searchstring .= ' publisher:'.$publisher;
        $data['publisher'] = $publisher;
    }
    if (!empty($collection)) {
        $searchstring .= ' collection:'.$collection;
        $data['collection'] = $collection;
    }
}
foreach ($data as $k => $v) {
    $pageurl->param($k, $v);
}

$strsearch = get_string("search", "mediagallery");
$strsearchresults = get_string("searchresults", "mediagallery");
$strpage = get_string("page");

$PAGE->set_url($pageurl);
$title = format_string($course->fullname).": ".get_string('searchtitle', 'mediagallery');
$PAGE->set_title($title);
$PAGE->set_heading(format_string($course->fullname));

$navnode = $PAGE->navigation->find($course->id, navigation_node::TYPE_COURSE);
if (empty($navnode)) {
    $navnode = $PAGE->navbar;
}
$node = $navnode->add(get_string('pluginname', 'mediagallery'));
$node = $node->add(get_string('search'), $pageurl);
$node->make_active();

$results = false;
$mform = new search_form(null, array('course' => $course));
$mform->set_data(array('search' => $search, 'moralrights' => $moralrights));

$searchterms = explode(' ', $searchstring);

echo $OUTPUT->header();
$output = $PAGE->get_renderer('mod_mediagallery');
$mform->display();

if (is_array($results)) {
    echo 'test';
}
if (empty($searchstring)) {
    $items = false;
} else {
    $courses = array($course->id => $course);
    list($items, $totalcount) = mediagallery_search_items($searchterms, $courses, $page * $perpage, $perpage);
}
if (!$items) {
    echo $OUTPUT->heading(get_string("noitemsfound", "mediagallery"));
} else {
    echo $OUTPUT->heading(get_string("searchresults", "mediagallery"));
    echo $output->search_results($items, $totalcount, $page, $perpage);
}

echo $OUTPUT->footer();
