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
require_once(dirname(__FILE__).'/item_form.php');
require_once(dirname(__FILE__).'/item_bulk_form.php');
require_once(dirname(__FILE__).'/locallib.php');

$g = optional_param('g', 0, PARAM_INT); // The gallery id.
$i = optional_param('i', 0, PARAM_INT); // An item id.
$bulk = optional_param('bulk', false, PARAM_BOOL);

if (!$g && !$i) {
    print_error('missingparameter');
}


$item = false;
if ($i) {
    $item = new \mod_mediagallery\item($i);
    $g = $item->galleryid;
}

$gallery = new \mod_mediagallery\gallery($g);
$mediagallery = $gallery->get_collection();
$course = $DB->get_record('course', array('id' => $mediagallery->course), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('mediagallery', $mediagallery->id, $course->id, false, MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
$pageurl = new moodle_url('/mod/mediagallery/item.php', array('g' => $gallery->id));
if (!$gallery->user_can_edit()) {
    print_error('nopermissions', 'error', $pageurl, 'edit gallery');
}

$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($mediagallery->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

if ($gallery) {
    $pageurl = new moodle_url('/mod/mediagallery/view.php', array('g' => $g));

    $navnode = $PAGE->navigation->find($cm->id, navigation_node::TYPE_ACTIVITY);
    if (empty($navnode)) {
        $navnode = $PAGE->navbar;
    }
    $node = $navnode->add(format_string($gallery->name), $pageurl);
    $node->make_active();
}

$fmoptions = mediagallery_filepicker_options($gallery);

$formclass = $bulk ? 'mod_mediagallery_item_bulk_form' : 'mod_mediagallery_item_form';
$mform = new $formclass(null, array('gallery' => $gallery, 'firstitem' => !$gallery->has_items()));
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/mod/mediagallery/view.php', array('g' => $gallery->id, 'editing' => 1)));
} else if ($data = $mform->get_data()) {
    if ($bulk) {
        $fs = get_file_storage();
        $draftid = file_get_submitted_draft_itemid('content');
        $files = $fs->get_area_files(context_user::instance($USER->id)->id, 'user', 'draft', $draftid, 'id DESC', false);
        $storedfile = reset($files);
        \mod_mediagallery\item::create_from_archive($gallery, $storedfile, $data);
    } else {
        $data->description = $data->description['text'];
        $data->galleryid = $gallery->id;

        if (!empty($data->id)) {
            $item = new \mod_mediagallery\item($data->id);
            $item->update($data);
        } else {
            $item = \mod_mediagallery\item::create($data);
        }

        $info = file_get_draft_area_info($data->content);
        file_save_draft_area_files($data->content, $context->id, 'mod_mediagallery', 'item', $item->id, $fmoptions);

        $storedfile = null;
        if ($gallery->gallerytype != MEDIAGALLERY_TYPE_IMAGE) {
            $draftid = file_get_submitted_draft_itemid('customthumbnail');
            $fs = get_file_storage();
            if ($files = $fs->get_area_files(
                context_user::instance($USER->id)->id, 'user', 'draft', $draftid, 'id DESC', false)) {
                $storedfile = reset($files);
            }
        }
        $item->generate_image_by_type('lowres', false, $storedfile);
        $item->generate_image_by_type('thumbnail', false, $storedfile);
    }

    redirect(new moodle_url('/mod/mediagallery/view.php', array('g' => $gallery->id, 'editing' => 1)));
} else if ($item) {
    $data = $item->get_record();

    $draftitemid = file_get_submitted_draft_itemid('content');
    file_prepare_draft_area($draftitemid, $context->id, 'mod_mediagallery', 'item', $data->id);

    if ($gallery->gallerytype == MEDIAGALLERY_TYPE_AUDIO) {
        $draftitemidthumb = file_get_submitted_draft_itemid('customthumbnail');
        $data->customthumbnail = $draftitemidthumb;
    }

    $draftideditor = file_get_submitted_draft_itemid('description');
    $currenttext = file_prepare_draft_area($draftideditor, $context->id, 'mod_mediagallery',
            'description', empty($data->id) ? null : $data->id,
            array('subdirs' => 0), empty($data->description) ? '' : $data->description);

    $data->content = $draftitemid;
    $data->description = array('text' => $currenttext,
                           'format' => editors_get_preferred_format(),
                           'itemid' => $draftideditor);

    $mform->set_data($data);
}

$maxitems = $mediagallery->maxitems;
if (!$item && $maxitems != 0 && count($gallery->get_items()) >= $maxitems) {
    print_error('errortoomanyitems', 'mediagallery', '', $maxitems);
}

echo $OUTPUT->header();

$mform->display();

echo $OUTPUT->footer();
