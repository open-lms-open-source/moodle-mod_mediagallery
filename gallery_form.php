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
 * Form for creating/editing a gallery.
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
 */
class mod_mediagallery_gallery_form extends moodleform {

    /**
     * Defines forms elements
     */
    public function definition() {
        global $CFG;

        $mform = $this->_form;
        $mg = $this->_customdata['mediagallery'];
        $groupmode = $this->_customdata['groupmode'];
        $groups = $this->_customdata['groups'];

        // General settings.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('galleryname', 'mediagallery'), array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'mediagalleryname', 'mediagallery');

        if ($groupmode !== NOGROUPS && count($groups) > 1) {
            $opts = array();
            foreach ($groups as $group) {
                $opts[$group->id] = $group->name;
            }
            $mform->addElement('select', 'groupid', get_string('group'), $opts);
            $mform->addHelpButton('groupid', 'group', 'mediagallery');
        } else {
            $groupkeys = array_keys($groups);
            $groupid = !empty($groupkeys) ? $groupkeys[0] : 0;
            $mform->addElement('hidden', 'groupid', $groupid);
            $mform->setType('groupid', PARAM_INT);
        }

        // Gallery settings.
        $mform->addElement('header', 'display', get_string('settingsgallerydisplay', 'mediagallery'));

        $options = array(
            MEDIAGALLERY_TYPE_IMAGE => get_string('typeimage', 'mediagallery'),
            MEDIAGALLERY_TYPE_VIDEO => get_string('typevideo', 'mediagallery'),
            MEDIAGALLERY_TYPE_AUDIO => get_string('typeaudio', 'mediagallery'),
        );
        foreach ($options as $key => $val) {
            if (!in_array($key, $mg->gallerytypes)) {
                unset($options[$key]);
            }
        }
        $mform->addElement('select', 'gallerytype', get_string('gallerytype', 'mediagallery'), $options);
        $mform->addHelpButton('gallerytype', 'gallerytype', 'mediagallery');
        $mform->setDefault('gallerytype', MEDIAGALLERY_TYPE_IMAGE);

        $options = array();
        if ($mg->grid) {
            $options[MEDIAGALLERY_VIEW_GRID] = get_string('gridview', 'mediagallery');
        }
        if ($mg->carousel) {
            $options[MEDIAGALLERY_VIEW_CAROUSEL] = get_string('carousel', 'mediagallery');
        }

        if (!$mg->enforcedefaults || $mg->grid && $mg->carousel) {
            $mform->addElement('select', 'galleryview', get_string('galleryviewoptions', 'mediagallery'), $options);
        } else {
            $mform->addElement('static', 'galleryview', get_string('galleryviewoptions', 'mediagallery'), current($options));
        }

        if (!$mg->enforcedefaults) {
            if (isset($options[MEDIAGALLERY_VIEW_GRID])) {
                $coloptions = array(0 => get_string('automatic', 'mediagallery'), 1 => 1, 2 => 2, 3 => 3, 4 => 4);
                $mform->addElement('select', 'gridcolumns', get_string('gridviewcolumns', 'mediagallery'), $coloptions);
                $mform->disabledIf('gridcolumns', 'galleryview', 'ne', MEDIAGALLERY_VIEW_GRID);
                $mform->setDefault('gridcolumns', $mg->gridcolumns);

                $rowoptions = array(0 => get_string('automatic', 'mediagallery'), 1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5,
                    6 => 6, 7 => 7, 8 => 8, 9 => 9, 10 => 10);
                $mform->addElement('select', 'gridrows', get_string('gridviewrows', 'mediagallery'), $rowoptions);
                $mform->disabledIf('gridrows', 'galleryview', 'ne', MEDIAGALLERY_VIEW_GRID);
                $mform->setDefault('gridrows', $mg->gridrows);
            }
        } else {
            if ($mg->grid) {
                $mform->addElement('static', 'gridcolumns', get_string('gridviewcolumns', 'mediagallery'), $mg->gridcolumns);
                $mform->addElement('static', 'gridrows', get_string('gridviewrows', 'mediagallery'), $mg->gridrows);
            }
        }
        $mform->addHelpButton('galleryview', 'galleryviewoptions', 'mediagallery');
        if ($mg->grid) {
            $mform->addHelpButton('gridcolumns', 'gridviewcolumns', 'mediagallery');
            $mform->addHelpButton('gridrows', 'gridviewrows', 'mediagallery');
        }

        // Visibility settings.
        $mform->addElement('header', 'display', get_string('settingsvisibility', 'mediagallery'));

        $mform->addElement('date_time_selector', 'visibleinstructor',
            get_string('visibleinstructor', 'mediagallery'),
            array('optional' => true));
        $mform->addHelpButton('visibleinstructor', 'visibleinstructor', 'mediagallery');

        $mform->addElement('date_time_selector', 'visibleother',
            get_string('visibleother', 'mediagallery'),
            array('optional' => true));
        $mform->addHelpButton('visibleother', 'visibleother', 'mediagallery');
        $mform->setDefault('visibleother', time());

        $mform->addElement('hidden', 'm', $mg->id);
        $mform->setType('m', PARAM_INT);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons();
    }

    public function data_preprocessing(&$toform) {
        $toform['galleryviewoptions'] = array();
        $toform['galleryviewoptions']['carousel'] = $toform['carousel'];
        $toform['galleryviewoptions']['grid'] = $toform['grid'];
    }

    public function validation($data, $files) {
        $errors = array();
        $collection = new \mod_mediagallery\collection($data['m']);
        if (!isset($data['gallerytype'])) {
            $errors['gallerytype'] = get_string('gallerytypeinvalid', 'mediagallery');
        }
        return $errors;
    }
}
