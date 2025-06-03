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
 * Tests
 *
 * @package    mod_mediagallery
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

require_admin();

// TODO: Delete this file.

$action = optional_param('action', '', PARAM_ALPHANUMEXT);
$id = optional_param('id', 0, PARAM_INT); // A course_module id.
$user1id = optional_param('user1id', 0, PARAM_INT);
$user2id = optional_param('user2id', 0, PARAM_INT);

if ($id) {
    $cm         = get_coursemodule_from_id('mediagallery', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $mediagallery = new \mod_mediagallery\collection($cm->instance);
    $context = \context_module::instance($cm->id);
}

if ($user1id) {
    $user1 = $DB->get_record('user', ['id' => $user1id]);
    $userlist = new \core_privacy\local\request\approved_userlist($context, 'mod_mediagallery', [$user1id]);
    $contextlist = new \core_privacy\local\request\approved_contextlist($user1, 'mod_mediagallery', [$context->id]);
}

if ($user2id) {
    $userlist = new \core_privacy\local\request\approved_userlist($context, 'mod_mediagallery', [$user1id, $user2id]);
}

echo "<p>Action: {$action}</p>\n";

if ($action == 'delete_data_for_all_users_in_context') {
    \mod_mediagallery\privacy\provider::delete_data_for_all_users_in_context($context);
} else if ($action == 'delete_data_for_user') {
    \mod_mediagallery\privacy\provider::delete_data_for_user($contextlist);
} else if ($action == 'delete_data_for_users') {
    \mod_mediagallery\privacy\provider::delete_data_for_users($userlist);
} else {
    echo "<p>Unknown action</p>\n";
}

echo "<p>Done</p>\n";
