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
 * AJAX endpoint to get the list of possible tags.
 *
 * @package   mod_mediagallery
 * @copyright Copyright (c) 2017 Blackboard Inc.
 * @author    Adam Olley <adam.olley@netspot.com.au>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot.'/mod/mediagallery/locallib.php');

$insttype = required_param('insttype', PARAM_ALPHA);

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/mod/mediagallery/tagajax.php', array('insttype' => $insttype));

require_login();
require_sesskey();
echo $OUTPUT->header();
$classname = "\\mod_mediagallery\\{$insttype}";
$result = $classname::get_tags_possible();
echo json_encode($result);
echo $OUTPUT->footer();
