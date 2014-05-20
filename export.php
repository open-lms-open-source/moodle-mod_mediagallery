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
 * @package    mod_mediagallery
 * @copyright  NetSpot Pty Ltd
 * @author     Adam Olley <adam.olley@netspot.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/gallery_form.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/classes/export_form.php');

$g = required_param('g', PARAM_INT); // A gallery id.

$gallery = new \mod_mediagallery\gallery($g);
$m = $gallery->instanceid;

$mediagallery = $DB->get_record('mediagallery', array('id' => $m), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id' => $mediagallery->course), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('mediagallery', $mediagallery->id, $course->id, false, MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

$galleryurl = new moodle_url('/mod/mediagallery/view.php', array('g' => $g, 'editing' => 1));
$pageurl = new moodle_url('/mod/mediagallery/export.php', array('g' => $g));
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($mediagallery->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$navnode = $PAGE->navigation->find($cm->id, navigation_node::TYPE_ACTIVITY);
if (empty($navnode)) {
    $navnode = $PAGE->navbar;
}
$node = $navnode->add(format_string($gallery->name), $galleryurl);
$node = $node->add(format_string(get_string('exportgallery', 'mediagallery')), $pageurl);
$node->make_active();

$mform = new \mod_mediagallery\export_form(null, array('gallery' => $gallery));
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/mod/mediagallery/view.php', array('g' => $gallery->id, 'editing' => 1)));
} else if ($data = $mform->get_data()) {
    $list = array();

    if (empty($data->completegallery)) {
        foreach (array_keys((array)$data) as $key) {
            if (substr($key, 0, 5) == 'item_') {
                $list[] = substr($key, 5);
            }
        }
    }

    if (!empty($list) || isset($data->completegallery)) {
        $gallery->download_items($list);
    }
    // Above should exit, if we got here there were no files to download.
    redirect($galleryurl, get_string('noitemsselected', 'mediagallery'));
}

echo $OUTPUT->header();

$output = $PAGE->get_renderer('mod_mediagallery');
echo $output->gallery_heading($gallery);

$mform->display();

echo $OUTPUT->footer();
