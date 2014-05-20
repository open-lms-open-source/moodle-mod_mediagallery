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
 * The main mediagallery configuration form
 *
 * It uses the standard core Moodle formslib. For more info about them, please
 * visit: http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * @package    mod
 * @subpackage mediagallery
 * @copyright  NetSpot Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/mediagallery/locallib.php');

/**
 * Module instance settings form
 */
class mod_mediagallery_mod_form extends moodleform_mod {

    protected $course = null;

    public function __construct($current, $section, $cm, $course) {
        $this->course = $course;
        parent::__construct($current, $section, $cm, $course);
    }

    /**
     * Defines forms elements
     */
    public function definition() {
        global $CFG;

        $mform = $this->_form;
        $config = get_config('mediagallery');

        // General settings.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('mediagalleryname', 'mediagallery'), array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'mediagalleryname', 'mediagallery');

        $this->add_intro_editor();

        $options = get_max_upload_sizes($CFG->maxbytes, $this->course->maxbytes, 0, $config->maxbytes);
        $mform->addElement('select', 'maxbytes', get_string('maxbytes', 'mediagallery'), $options);
        $mform->setDefault('maxbytes', $config->maxbytes);

        $numbers = array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 20, 30, 40, 50, 100);
        $options = array_merge(array(0 => get_string('unlimited')), $numbers);
        $mform->addElement('select', 'maxitems', get_string('maxitems', 'mediagallery'), $options);
        $mform->setType('maxitems', PARAM_INT);
        $mform->setDefault('maxitems', 0);
        $mform->addHelpButton('maxitems', 'maxitems', 'mediagallery');

        $mform->addElement('select', 'maxgalleries', get_string('maxgalleries', 'mediagallery'), $options);
        $mform->setType('maxgalleries', PARAM_INT);
        $mform->setDefault('maxgalleries', 1);
        $mform->addHelpButton('maxgalleries', 'maxgalleries', 'mediagallery');

        if ($CFG->usecomments) {
            $mform->addElement('selectyesno', 'allowcomments', get_string('allowcomments', 'mediagallery'));
            $mform->setDefault('allowcomments', 1);
            $mform->addHelpButton('allowcomments', 'allowcomments', 'mediagallery');
        }

        $mform->addElement('selectyesno', 'allowlikes', get_string('allowlikes', 'mediagallery'));
        $mform->setDefault('allowlikes', 1);
        $mform->addHelpButton('allowlikes', 'allowlikes', 'mediagallery');

        // Display settings.
        $mform->addElement('header', 'display', get_string('settingsdisplay', 'mediagallery'));

        $options = array(
            0 => get_string('showall', 'mediagallery'),
            10 => 10,
            25 => 25,
            50 => 50,
            100 => 100,
            200 => 200,
        );
        $mform->addElement('select', 'thumbnailsperpage', get_string('thumbnailsperpage', 'mediagallery'), $options);

        $options = array(2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6);
        $mform->addElement('select', 'thumbnailsperrow', get_string('thumbnailsperrow', 'mediagallery'), $options);

        $mform->addElement('selectyesno', 'displayfullcaption', get_string('displayfullcaption', 'mediagallery'));

        $options = array(
            MEDIAGALLERY_POS_BOTTOM => get_string('bottom', 'mediagallery'),
            MEDIAGALLERY_POS_TOP => get_string('top', 'mediagallery'),
        );
        $mform->addElement('select', 'captionposition', get_string('captionposition', 'mediagallery'), $options);

        // Gallery settings.
        $mform->addElement('header', 'display', get_string('settingsgallery', 'mediagallery'));

        $typeoptgroup = array();
        $typeoptgroup[] = $mform->createElement('checkbox', MEDIAGALLERY_TYPE_IMAGE, '', get_string('typeimage', 'mediagallery'));
        $typeoptgroup[] = $mform->createElement('checkbox', MEDIAGALLERY_TYPE_VIDEO, '', get_string('typevideo', 'mediagallery'));
        $typeoptgroup[] = $mform->createElement('checkbox', MEDIAGALLERY_TYPE_AUDIO, '', get_string('typeaudio', 'mediagallery'));
        $mform->addGroup($typeoptgroup, 'gallerytypeoptions', get_string('gallerytypes', 'mediagallery'));
        $mform->addHelpButton('gallerytypeoptions', 'gallerytypes', 'mediagallery');
        $mform->setDefault('gallerytypeoptions', MEDIAGALLERY_TYPE_IMAGE);
        $mform->addRule('gallerytypeoptions', null, 'required', null, 'client');

        $viewoptgroup = array();
        $viewoptgroup[] = $mform->createElement('checkbox', 'carousel', '', get_string('carousel', 'mediagallery'));
        $viewoptgroup[] = $mform->createElement('checkbox', 'grid', '', get_string('gridview', 'mediagallery'));
        $mform->addGroup($viewoptgroup, 'galleryviewoptions', get_string('galleryviewoptions', 'mediagallery'));
        $mform->addHelpButton('galleryviewoptions', 'galleryviewoptions', 'mediagallery');
        $mform->addRule('galleryviewoptions', null, 'required', null, 'client');

        $options = array(0 => get_string('automatic', 'mediagallery'), 1 => 1, 2 => 2, 3 => 3, 4 => 4);
        $mform->addElement('select', 'gridcolumns', get_string('gridviewcolumns', 'mediagallery'), $options);
        $mform->addHelpButton('gridcolumns', 'gridviewcolumns', 'mediagallery');
        $mform->disabledIf('gridcolumns', 'galleryviewoptions[grid]', 'notchecked');
        $mform->setDefault('gridcolumns', 0);

        $options = array(0 => get_string('automatic', 'mediagallery'), 1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5,
            6 => 6, 7 => 7, 8 => 8, 9 => 9, 10 => 10);
        $mform->addElement('select', 'gridrows', get_string('gridviewrows', 'mediagallery'), $options);
        $mform->addHelpButton('gridrows', 'gridviewrows', 'mediagallery');
        $mform->disabledIf('gridrows', 'galleryviewoptions[grid]', 'notchecked');
        $mform->setDefault('gridrows', 0);

        $mform->addElement('checkbox', 'enforcedefaults', get_string('enforcedefaults', 'mediagallery'));
        $mform->addHelpButton('enforcedefaults', 'enforcedefaults', 'mediagallery');

        // Availability settings.
        $mform->addElement('header', 'display', get_string('settingsavailability', 'mediagallery'));

        $mform->addElement('date_time_selector', 'readonlyfrom', get_string('readonlyfrom', 'mediagallery'),
            array('optional' => true));
        $mform->addElement('date_time_selector', 'readonlyto', get_string('readonlyto', 'mediagallery'),
            array('optional' => true));
        if ($CFG->enableavailability) {
            $mform->addElement('static', 'restrictavailableinfo', '', get_string('restrictavailableinfo', 'mediagallery'));
        }

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    public function data_preprocessing(&$toform) {
        $toform['galleryviewoptions'] = array();
        $toform['galleryviewoptions']['carousel'] = isset($toform['carousel']) ? $toform['carousel'] : 1;
        $toform['galleryviewoptions']['grid'] = isset($toform['grid']) ? $toform['grid'] : '';

        $toform['gallerytypeoptions'] = array();
        if (isset($toform['gallerytype'])) {
            $values = explode(',', $toform['gallerytype']);
        } else {
            $values = array();
        }

        foreach ($values as $value) {
            $toform['gallerytypeoptions'][$value] = 1;
        }
        if (empty($values)) {
            $toform['gallerytypeoptions'][MEDIAGALLERY_TYPE_IMAGE] = 1;
        }
    }
}
