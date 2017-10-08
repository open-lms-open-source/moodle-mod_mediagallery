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
 * Item editing page.
 *
 * @package    mod_mediagallery
 * @copyright  NetSpot Pty Ltd
 * @author     Adam Olley <adam.olley@netspot.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/item_form.php');
require_once(dirname(__FILE__).'/item_bulk_form.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once($CFG->dirroot.'/repository/lib.php');

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
    if (!$item->user_can_edit()) {
        print_error('nopermissions', 'error', null, 'edit item');
    }
}

$gallery = new \mod_mediagallery\gallery($g);
$mediagallery = $gallery->get_collection();
$course = $DB->get_record('course', array('id' => $mediagallery->course), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('mediagallery', $mediagallery->id, $course->id, false, MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
$pageurl = new moodle_url('/mod/mediagallery/item.php', array('g' => $gallery->id));
if (!$gallery->user_can_contribute()) {
    print_error('nopermissions', 'error', $pageurl, 'edit gallery');
}

$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($mediagallery->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->add_body_class('mediagallery-mode-'.$gallery->mode);

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
$tags = \mod_mediagallery\item::get_tags_possible();
$mform = new $formclass(null,
    array('gallery' => $gallery, 'firstitem' => !$gallery->has_items(), 'tags' => $tags, 'item' => $item));

$fs = get_file_storage();

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/mod/mediagallery/view.php', array('g' => $gallery->id, 'editing' => 1)));
} else if ($data = $mform->get_data()) {
    if ($bulk) {
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

        if (!empty($data->content)) {
            $info = file_get_draft_area_info($data->content);
            file_save_draft_area_files($data->content, $context->id, 'mod_mediagallery', 'item', $item->id, $fmoptions);

            $storedfile = null;
            $regenthumb = false;
            if ($gallery->galleryfocus != \mod_mediagallery\base::TYPE_IMAGE && $gallery->mode != 'thebox') {
                $draftid = file_get_submitted_draft_itemid('customthumbnail');
                if ($files = $fs->get_area_files(
                    context_user::instance($USER->id)->id, 'user', 'draft', $draftid, 'id DESC', false)) {
                    $storedfile = reset($files);
                    $regenthumb = true;
                }
            }
            if ($gallery->mode != 'thebox') {
                $item->generate_image_by_type('lowres', $regenthumb, $storedfile);
                $item->generate_image_by_type('thumbnail', $regenthumb, $storedfile);
            }
            $params = array(
                'context' => $context,
                'objectid' => $item->id,
                'other' => array(
                    'copyright_id' => $data->copyright_id,
                    'theme_id' => $data->theme_id,
                ),
            );
            $event = \mod_mediagallery\event\item_updated::create($params);
            $event->add_record_snapshot('mediagallery_item', $item->get_record());
            $event->trigger();
        }
    }

    redirect(new moodle_url('/mod/mediagallery/view.php', array('g' => $gallery->id, 'editing' => 1)));
} else if ($item) {
    $data = $item->get_record();

    $draftitemid = file_get_submitted_draft_itemid('content');
    file_prepare_draft_area($draftitemid, $context->id, 'mod_mediagallery', 'item', $data->id);

    if ($gallery->galleryfocus == \mod_mediagallery\base::TYPE_AUDIO) {
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

    $data->tags = $item->get_tags();
    $mform->set_data($data);
}

$maxitems = $mediagallery->maxitems;
if (!$item && $maxitems != 0 && count($gallery->get_items()) >= $maxitems) {
    print_error('errortoomanyitems', 'mediagallery', '', $maxitems);
}

echo $OUTPUT->header();

$mform->display();

echo $OUTPUT->footer();
