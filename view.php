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
$editing = optional_param('editing', false, PARAM_BOOL);
$viewcontrols = 'gallery';
$gallery = false;

if ($g) {
    $gallery = new \mod_mediagallery\gallery($g);
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

$canedit = $gallery && $gallery->user_can_edit();

if ($mediagallery->is_read_only() || !$canedit) {
    $editing = false;
}

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
$PAGE->set_context($context);

if ($gallery) {
    $pageurl = new moodle_url('/mod/mediagallery/view.php', array('g' => $g, 'page' => $page));

    $navnode = $PAGE->navigation->find($cm->id, navigation_node::TYPE_ACTIVITY);
    if (empty($navnode)) {
        $navnode = $PAGE->navbar;
    }
    $node = $navnode->add(format_string($gallery->name), $pageurl);
    $node->make_active();
} else {
    $pageurl = new moodle_url('/mod/mediagallery/view.php', array('id' => $cm->id));
}

$jsoptions = new stdClass();
if ($gallery) {
    $jsoptions->enablecomments = $gallery->can_comment();
    $jsoptions->enablelikes = $gallery->can_like();
}

$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($mediagallery->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->js('/mod/mediagallery/js/screenfull.min.js');
$PAGE->requires->yui_module('moodle-mod_mediagallery-base', 'M.mod_mediagallery.base.init',
    array($mediagallery->id, $viewcontrols, $editing, $g, $jsoptions));

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
    'viewfullsize', 'you', 'togglesidebar', 'close', 'togglefullscreen');
$PAGE->requires->strings_for_js($jsstrs, 'mod_mediagallery');
$PAGE->requires->strings_for_js(array(
    'move', 'add', 'description', 'no', 'yes', 'group', 'fullnameuser', 'username', 'next', 'previous'
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
        if ($mediagallery->maxgalleries == 0 || count($mediagallery->get_my_galleries()) < $mediagallery->maxgalleries) {
            echo html_writer::link(new moodle_url('/mod/mediagallery/gallery.php', array('m' => $mediagallery->id)),
                get_string('addagallery', 'mediagallery'));
        } else {
            echo html_writer::span(get_string('maxgalleriesreached', 'mediagallery'));
        }
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
        echo $output->gallery_page($gallery, $editing, $options);
    } else {
        print_error('nopermissions', 'error', $pageurl, 'view gallery');
    }
}

echo $OUTPUT->footer();
