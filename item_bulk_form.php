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
 * Form for adding items in bulk.
 *
 * @package    mod_mediagallery
 * @copyright  NetSpot Pty Ltd
 * @author     Adam Olley <adam.olley@netspot.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');
require_once($CFG->dirroot.'/mod/mediagallery/locallib.php');

/**
 * Module instance settings form
 *
 * @copyright Copyright (c) 2017 Blackboard Inc.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_mediagallery_item_bulk_form extends moodleform {

    /**
     * Defines forms elements
     */
    public function definition() {
        global $CFG;

        $mform = $this->_form;
        $gallery = $this->_customdata['gallery'];

        // General settings.
        $mform->addElement('header', 'general', get_string('addbulkitems', 'mediagallery'));

        $mform->addElement('static', 'filecheck', '', get_string('contentbulkheader', 'mediagallery'));
        $options = array('maxbytes' => $gallery->get_collection()->maxbytes, 'accepted_types' => array('application/zip'));
        $mform->addElement('filepicker', 'content', get_string('content', 'mediagallery'), '0', $options);
        $mform->addHelpButton('content', 'contentbulk', 'mediagallery');
        $mform->addRule('content', null, 'required', null, 'client');

        $mform->addElement('header', 'advanced', get_string('advanced'));
        $mform->addElement('static', 'metadatainfo', '', get_string('metainfobulkheader', 'mediagallery'));
        mediagallery_add_metainfo_fields($mform);

        $mform->addElement('hidden', 'g', $gallery->id);
        $mform->setType('g', PARAM_INT);

        $mform->addElement('hidden', 'bulk', 1);
        $mform->setType('bulk', PARAM_INT);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons(false, get_string('submit'));
    }

    /**
     * Validate the user input.
     *
     * @param mixed $data The submitted data.
     * @param mixed $files The submitted files.
     * @return array A list of errors, if any.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $info = file_get_draft_area_info($data['content']);

        if ($info['filecount'] == 0) {
            $errors['content'] = get_string('required');
        }
        return $errors;
    }

}
