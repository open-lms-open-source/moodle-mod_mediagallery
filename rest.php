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
 * Provide interface for AJAX mod_mediagallery requests.
 *
 * @package    mod_mediagallery
 * @copyright  NetSpot Pty Ltd
 * @author     Adam Olley <adam.olley@netspot.com.au>
 */

if (!defined('AJAX_SCRIPT')) {
    define('AJAX_SCRIPT', true);
}
require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot.'/mod/mediagallery/locallib.php');

$class = required_param('class', PARAM_ALPHA);
$m = required_param('m', PARAM_INT);
$id = optional_param('id', 0, PARAM_INT);
$action = optional_param('action', null, PARAM_ALPHAEXT);
$data = optional_param_array('data', null, PARAM_RAW);

$PAGE->set_url('/mod/mediagallery/rest.php', array('id' => $id, 'class' => $class, 'm' => $m));

$mediagallery = $DB->get_record('mediagallery', array('id' => $m), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id' => $mediagallery->course), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('mediagallery', $mediagallery->id, $course->id, false, MUST_EXIST);

require_login($course, false, $cm);
require_sesskey();

$requestmethod = $_SERVER['REQUEST_METHOD'];

echo $OUTPUT->header();

$classname = "\\mod_mediagallery\\{$class}";

if (!empty($id)) {
    $object = new $classname($id);
}

switch($requestmethod) {
    case 'GET':
        if ($action == 'socialinfo') {
            $info = $object->get_socialinfo();
            echo json_encode($info);
        } else if ($action == 'get_sample_targets') {
            $info = mediagallery_get_sample_targets($course);
            echo json_encode($info);
        } else if ($action == 'embed') {
            $output = $PAGE->get_renderer('mod_mediagallery')->embed_html($object);
            preg_match('#(<span.*</span>)#s', $output, $matches);
            $data = new stdClass();
            $data->html = isset($matches[1]) ? $matches[1] : null;

            preg_match('#M.util.add_(audio|video)_player\("(\w*)",[\s\n]?"(.*)"#s', $output, $matches);
            $data->id = isset($matches[2]) ? $matches[2] : null;
            $data->url = isset($matches[3]) ? str_replace('\/', '/', $matches[3]) : null;
            $data->type = isset($matches[1]) ? $matches[1] : $object->type(true);
            $data->flow = !empty($matches[1]);
            echo json_encode($data);
        } else if ($action == 'metainfo') {
            $info = $object->get_structured_metainfo();
            echo json_encode($info);
        }
    break;

    case 'POST':
        if ($action == 'sortorder') {
            $object->update_sortorder($data);
        } else if ($action == 'like' || $action == 'unlike') {
            $count = $object->$action();
            $info = new stdClass();
            $info->likes = $count;
            echo json_encode($info);
        } else if ($action == 'sample') {
            $info = $object->copy($data[0]);
            echo json_encode($info);
        }
    break;

    case 'DELETE':
        if ($object->delete()) {
            echo json_encode('success');
        } else {
            throw new moodle_exception("failed to delete $class $id");
        }
    break;
}
