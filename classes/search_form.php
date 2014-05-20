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
 * Form for searching a gallerys items.
 *
 * @package    mod_mediagallery
 * @copyright  NetSpot Pty Ltd
 * @author     Adam Olley <adam.olley@netspot.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');

class search_form extends \moodleform {

    /**
     * Defines forms elements
     */
    public function definition() {
        global $CFG;

        $mform = $this->_form;
        $course = $this->_customdata['course'];

        // General settings.
        $mform->addElement('header', 'general', get_string('search'));

        $mform->addElement('text', 'search', get_string('search'));
        $mform->addRule('search', null, 'required', null, 'client');
        $mform->addHelpButton('search', 'search', 'mediagallery');
        $mform->setType('search', PARAM_TEXT);

        $mform->addElement('checkbox', 'courseonly', get_string('searchcourseonly', 'mediagallery'));
        $mform->addHelpButton('courseonly', 'searchcourseonly', 'mediagallery');
        $mform->setDefault('courseonly', true);

        $mform->addElement('header', 'advancedsearch', get_string('advancedfilter'));
        $mform->setExpanded('advancedsearch', false);

        $mform->addElement('text', 'caption', get_string('caption', 'mediagallery'));
        $mform->setType('caption', PARAM_TEXT);

        $options = array(
            '' => get_string('choosedots'),
            0 => get_string('no'),
            1 => get_string('yes'),
        );
        $mform->addElement('select', 'moralrights', get_string('moralrights', 'mediagallery'), $options);

        $mform->addElement('text', 'originalauthor', get_string('originalauthor', 'mediagallery'));
        $mform->setType('originalauthor', PARAM_TEXT);
        $mform->addRule('originalauthor', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('originalauthor', 'originalauthor', 'mediagallery');

        $mform->addElement('text', 'medium', get_string('medium', 'mediagallery'));
        $mform->setType('medium', PARAM_TEXT);
        $mform->addRule('medium', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('medium', 'medium', 'mediagallery');

        $mform->addElement('text', 'publisher', get_string('publisher', 'mediagallery'));
        $mform->setType('publisher', PARAM_TEXT);
        $mform->addRule('publisher', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('publisher', 'publisher', 'mediagallery');

        $mform->addElement('text', 'collection', get_string('collection', 'mediagallery'));
        $mform->setType('collection', PARAM_TEXT);
        $mform->addRule('collection', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('collection', 'collection', 'mediagallery');

        $mform->addElement('hidden', 'id', $course->id);
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons(false, get_string('submit'));
    }

}
