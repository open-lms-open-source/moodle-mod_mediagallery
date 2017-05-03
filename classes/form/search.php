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

namespace mod_mediagallery\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');

class search extends \moodleform {
    protected function definition() {
        global $CFG, $DB;

        $collection = $this->_customdata['collection'];
        $context = $this->_customdata['context'];

        $mform = $this->_form;

        // Text search box.
        $mform->addElement('text', 'search', get_string('search'));
        $mform->setType('search', PARAM_TEXT);

        $options = array(
            \mod_mediagallery\base::TYPE_ALL => get_string('typeall', 'mediagallery'),
            \mod_mediagallery\base::TYPE_IMAGE => get_string('typeimage', 'mediagallery'),
            \mod_mediagallery\base::TYPE_VIDEO => get_string('typevideo', 'mediagallery'),
            \mod_mediagallery\base::TYPE_AUDIO => get_string('typeaudio', 'mediagallery'),
        );
        $mform->addElement('select', 'type', get_string('mediatype', 'mediagallery'), $options);

        // Role select dropdown includes all roles, but using course-specific
        // names if applied. The reason for not restricting to roles that can
        // be assigned at course level is that upper-level roles display in the
        // enrolments table so it makes sense to let users filter by them.

        $rolenames = role_fix_names(get_profile_roles($context), $context, ROLENAME_ALIAS, true);
        if (!empty($CFG->showenrolledusersrolesonly)) {
            $rolenames = role_fix_names(get_roles_used_in_context($context));
        }
        $mform->addElement('select', 'role', get_string('role'),
                array(0 => get_string('all')) + $rolenames);

        // Filter by group.
        $allgroups = groups_get_all_groups($collection->course);
        $groupsmenu[0] = get_string('allparticipants');
        foreach ($allgroups as $gid => $unused) {
            $groupsmenu[$gid] = $allgroups[$gid]->name;
        }
        if (count($groupsmenu) > 1) {
            $mform->addElement('select', 'group', get_string('group'), $groupsmenu);
        }

        // Submit button does not use add_action_buttons because that adds
        // another fieldset which causes the CSS style to break in an unfixable
        // way due to fieldset quirks.
        $group = array();
        $group[] = $mform->createElement('submit', 'submitbutton', get_string('filter'));
        $group[] = $mform->createElement('submit', 'resetbutton', get_string('reset'));
        $group[] = $mform->createElement('submit', 'exportbutton', get_string('exportascsv', 'mediagallery'));
        $mform->addGroup($group, 'buttons', '', ' ', false);

        $mform->addElement('hidden', 'id', $context->instanceid);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'action', 'search');
        $mform->setType('action', PARAM_ALPHA);

    }
}
