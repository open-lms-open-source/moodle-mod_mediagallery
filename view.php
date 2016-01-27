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

$id = optional_param('id', 0, PARAM_INT); // A course_module id.
$m  = optional_param('m', 0, PARAM_INT); // A mediagallery id.
$g = optional_param('g', 0, PARAM_INT); // A mediagallery_gallery id.
$action = optional_param('action', 'viewcollection', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);
$focus = optional_param('focus', null, PARAM_INT);
$editing = optional_param('editing', false, PARAM_BOOL);
$forcesync = optional_param('sync', false, PARAM_BOOL);
$viewcontrols = 'gallery';
$gallery = false;

$mediasize = get_user_preferences('mod_mediagallery_mediasize', \mod_mediagallery\output\gallery\renderable::MEDIASIZE_MD);
user_preference_allow_ajax_update('mod_mediagallery_mediasize', PARAM_INT);

$options = array(
    'focus' => $focus,
    'mediasize' => $mediasize,
    'editing' => $editing,
    'page' => $page,
    'action' => $action,
    'viewcontrols' => $viewcontrols,
);
if ($g) {
    $gallery = new \mod_mediagallery\gallery($g, $options);
    $gallery->sync($forcesync);
    $options['action'] = 'viewgallery';
    $m = $gallery->instanceid;
    $options['viewcontrols'] = 'item';
    $mediagallery = $gallery->get_collection();
    $course     = $DB->get_record('course', array('id' => $mediagallery->course), '*', MUST_EXIST);
    $cm = $mediagallery->cm;
} else if ($id) {
    $cm         = get_coursemodule_from_id('mediagallery', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $mediagallery = new \mod_mediagallery\collection($cm->instance);
} else if ($m) {
    $mediagallery = new \mod_mediagallery\collection($m);
    $course     = $DB->get_record('course', array('id' => $mediagallery->course), '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance('mediagallery', $mediagallery->id, $course->id, false, MUST_EXIST);
} else {
    print_error('missingparameter');
}

// The single collection type was contributed by github user eSrem.
// It is not officially supported by UNSW.
if ($mediagallery->colltype == "single") {
    switch($mediagallery->count_galleries()) {
        case 0:
            // Redirect to adding a gallery.
            redirect(new moodle_url('/mod/mediagallery/gallery.php', array('m' => $mediagallery->id)));
            break;
        case 1:
            $galleries = $mediagallery->get_visible_galleries();
            $gallery = reset($galleries);
            $options['action'] = 'viewgallery';
            $options['viewcontrols'] = 'item';
            break;
        default: // More than one, delete others.
            print_error('toomany', 'mod_mediagallery');
            break;
    }
}

$context = context_module::instance($cm->id);

// Request update from theBox (does nothing if synced within the past hour).
if (!$gallery) {
    $mediagallery->sync($forcesync);
}
if ($mediagallery->was_deleted()) {
    $coursecontext = $context->get_course_context();
    $pageurl = new moodle_url('/mod/mediagallery/view.php');
    $PAGE->set_context($coursecontext);
    $PAGE->set_pagelayout('incourse');
    $PAGE->set_url($pageurl);
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('collectionwasdeleted', 'mediagallery'));
    echo $OUTPUT->footer();
    exit;
}

$canedit = $gallery && $gallery->user_can_contribute();
if ($mediagallery->is_read_only() || !$canedit) {
    $options['editing'] = false;
}


if ($gallery) {
    $pageurl = new moodle_url('/mod/mediagallery/view.php', array('g' => $g, 'page' => $page));

    if ($options['editing']) {
        $pageurl->param('editing', true);
    }
} else {
    $pageurl = new moodle_url('/mod/mediagallery/view.php', array('id' => $cm->id));
}

$PAGE->set_cm($cm, $course);
$PAGE->set_url($pageurl);
require_login($course, true, $cm);

if ($gallery) {
    $navnode = $PAGE->navigation->find($cm->id, navigation_node::TYPE_ACTIVITY);
    if (empty($navnode)) {
        $navnode = $PAGE->navbar;
    }
    $navurl = clone $pageurl;
    $navurl->remove_params('editing');
    $node = $navnode->add(format_string($gallery->name), $navurl);
    $node->make_active();

}

$controller = new \mod_mediagallery\viewcontroller($context, $cm, $course, $mediagallery, $gallery, $pageurl, $options);
echo $controller->display_action($options['action']);
