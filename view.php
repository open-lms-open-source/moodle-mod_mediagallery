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
$page = optional_param('page', 0, PARAM_INT);
$focus = optional_param('focus', null, PARAM_INT);
$editing = optional_param('editing', false, PARAM_BOOL);
$forcesync = optional_param('sync', false, PARAM_BOOL);
$viewcontrols = 'gallery';
$gallery = false;

if ($g) {
    $options = array('focus' => $focus);
    $gallery = new \mod_mediagallery\gallery($g, $options);
    $gallery->sync($forcesync);
    $m = $gallery->instanceid;
    $viewcontrols = 'item';
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

$canedit = $gallery && $gallery->user_can_edit();

if ($mediagallery->is_read_only() || !$canedit) {
    $editing = false;
}

require_login($course, true, $cm);

if ($gallery) {
    $pageurl = new moodle_url('/mod/mediagallery/view.php', array('g' => $g, 'page' => $page));

    $navnode = $PAGE->navigation->find($cm->id, navigation_node::TYPE_ACTIVITY);
    if (empty($navnode)) {
        $navnode = $PAGE->navbar;
    }
    $navurl = clone $pageurl;
    $node = $navnode->add(format_string($gallery->name), $navurl);
    $node->make_active();

    if ($editing) {
        $pageurl->param('editing', true);
    }
} else {
    $pageurl = new moodle_url('/mod/mediagallery/view.php', array('id' => $cm->id));
}

$collmode = !empty($mediagallery->objectid) ? 'thebox' : 'standard';
$jsoptions = new stdClass();
$jsoptions->mode = $collmode;
if ($gallery) {
    $jsoptions->enablecomments = $gallery->can_comment();
    $jsoptions->enablelikes = $gallery->can_like();
    $jsoptions->mode = $gallery->mode;
}

$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($mediagallery->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->js('/mod/mediagallery/js/screenfull.min.js');
$PAGE->requires->yui_module('moodle-mod_mediagallery-base', 'M.mod_mediagallery.base.init',
    array($course->id, $mediagallery->id, $viewcontrols, $editing, $g, $jsoptions));

$mediaboxparams = array(
    'metainfouri' => $CFG->wwwroot.'/mod/mediagallery/rest.php',
    'metainfodata' => array(
        'sesskey' => sesskey(),
        'm' => $mediagallery->id,
        'class' => 'item',
    ),
);
if ($gallery) {
    $mediaboxparams['enablecomments'] = $gallery->can_comment();
    $mediaboxparams['enablelikes'] = $gallery->can_like();
}
$PAGE->requires->yui_module('moodle-mod_mediagallery-mediabox', 'M.mod_mediagallery.init_mediabox', array($mediaboxparams));
$PAGE->requires->jquery();

$jsstrs = array('confirmgallerydelete', 'confirmitemdelete', 'deletegallery',
    'deleteitem', 'like', 'likedby', 'comments', 'unlike', 'others', 'other',
    'addsamplegallery', 'mediagallery', 'information', 'caption',
    'moralrights', 'originalauthor', 'productiondate', 'medium', 'collection',
    'publisher', 'galleryname', 'creator', 'filename', 'filesize', 'datecreated',
    'viewfullsize', 'you', 'togglesidebar', 'close', 'togglefullscreen', 'tags',
    'reference', 'broadcaster', 'confirmcollectiondelete',
    'deleteorremovecollection', 'deleteorremovecollectionwarn',
    'deleteorremovegallery', 'deleteorremovegallerywarn',
    'deleteorremoveitem', 'deleteorremoveitemwarn',
    'removecollectionconfirm', 'removegalleryconfirm', 'removeitemconfirm',
    'youmusttypedelete', 'copyright');
$PAGE->requires->strings_for_js($jsstrs, 'mod_mediagallery');
$PAGE->requires->strings_for_js(array(
    'move', 'add', 'description', 'no', 'yes', 'group', 'fullnameuser', 'username', 'next', 'previous', 'submit',
), 'moodle');

if ($gallery && $canedit) {
    if (!$editing) {
        $url = new moodle_url('/mod/mediagallery/view.php', array('g' => $gallery->id, 'editing' => 1));
        $PAGE->set_button($OUTPUT->single_button($url, get_string('editthisgallery', 'mediagallery', 'get')));
    } else {
        $PAGE->requires->yui_module('moodle-mod_mediagallery-dragdrop', 'M.mod_mediagallery.dragdrop.init');
    }
}

$output = $PAGE->get_renderer('mod_mediagallery');

echo $OUTPUT->header();

if (!$gallery) {
    $params = array(
        'context' => $context,
        'objectid' => $mediagallery->id,
    );
    $event = \mod_mediagallery\event\course_module_viewed::create($params);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->trigger();

    groups_print_activity_menu($cm, $pageurl);
    if ($mediagallery->intro) {
        echo $OUTPUT->box(format_module_intro('mediagallery', $mediagallery, $cm->id),
            'generalbox mod_introbox', 'mediagalleryintro');
    }

    $galleries = $mediagallery->get_visible_galleries();
    echo $output->gallery_list_page($mediagallery, $galleries);

    if (!$mediagallery->is_read_only()) {
        $maxreached = false;
        if ($mediagallery->maxgalleries == 0 || count($mediagallery->get_my_galleries()) < $mediagallery->maxgalleries) {
            $maxreached = true;
        }
        echo $output->collection_editing_actions($mediagallery, $maxreached);
    }
} else {
    $params = array(
        'context' => $context,
        'objectid' => $gallery->id,
    );
    $event = \mod_mediagallery\event\gallery_viewed::create($params);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->trigger();
    if ($canedit || $gallery->user_can_view()) {
        $options = array();
        if ($gallery->can_comment()) {
            $cmtopt = new stdClass();
            $cmtopt->area = 'gallery';
            $cmtopt->context = $context;
            $cmtopt->itemid = $gallery->id;
            $cmtopt->showcount = true;
            $cmtopt->component = 'mod_mediagallery';
            $cmtopt->cm = $cm;
            $cmtopt->course = $course;
            $options['comments'] = new comment($cmtopt);
            comment::init();
        }
        $options['page'] = $page;
        $options['focus'] = $focus;
        echo $output->gallery_page($gallery, $editing, $options);
    } else {
        print_error('nopermissions', 'error', $pageurl, 'view gallery');
    }
}

echo $OUTPUT->footer();
