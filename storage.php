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


require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$title = get_string('storagereport', 'mediagallery');
$url = new moodle_url('/mod/mediagallery/storage.php');
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_title(format_string($title));
$PAGE->set_heading(format_string($title));
$PAGE->set_pagelayout('incourse');

$usagedata = mediagallery_get_file_storage_usage();
$output = $PAGE->get_renderer('mod_mediagallery');

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
echo $output->storage_report($usagedata);
echo $OUTPUT->footer();
