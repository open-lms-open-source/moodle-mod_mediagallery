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
 * @package    mod_mediagallery
 * @copyright  NetSpot Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/mediagallery/locallib.php');

/**
 * Module instance settings form
 *
 * @copyright  NetSpot Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_mediagallery_mod_form extends moodleform_mod {

    /**
     * @var stdClass The course record.
     */
    protected $course = null;

    /**
     * Initialise activity form.
     *
     * @param mixed $current
     * @param mixed $section
     * @param mixed $cm
     * @param mixed $course
     * @return void
     */
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

        $this->standard_intro_elements();
        $opts = array(
            'standard' => get_string('modestandard', 'mod_mediagallery'),
        );
        if (!empty($config->disablestandardgallery) && (empty($this->_instance) || $this->current->mode == 'thebox') && count($opts) > 1) {
            unset($opts['standard']);
        }

        if (count($opts) == 1) {
            $key = key($opts);
            $mform->addElement('hidden', 'mode', $key);
            $mform->setType('mode', PARAM_ALPHA);
            $mform->hardFreeze('mode');
        } else {
            $mform->addElement('select', 'mode', get_string('collmode', 'mod_mediagallery'), $opts);
            $mform->addHelpButton('mode', 'collmode', 'mediagallery');
            $mform->disabledIf('mode', 'instance', 'neq', '' );
        }

        $options = array(
            'instructor' => get_string('colltypeinstructor', 'mediagallery'),
            'single' => get_string('colltypesingle', 'mediagallery'),
            'contributed' => get_string('colltypecontributed', 'mediagallery'),
            'assignment' => get_string('colltypeassignment', 'mediagallery'),
            'peerreviewed' => get_string('colltypepeerreviewed', 'mediagallery'),
        );
        $mform->addElement('select', 'colltype', get_string('colltype', 'mediagallery'), $options);
        $mform->addHelpButton('colltype', 'colltype', 'mediagallery');

        mediagallery_add_tag_field($mform, array(), true, true, 'mctags');

        $options = get_max_upload_sizes($CFG->maxbytes, $this->course->maxbytes, 0, $config->maxbytes);
        $mform->addElement('select', 'maxbytes', get_string('maxbytes', 'mediagallery'), $options);
        $mform->setDefault('maxbytes', $config->maxbytes);

        $numbers = array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 20, 30, 40, 50, 100);
        $options = array_merge(array(0 => get_string('unlimited')), $numbers);
        $mform->addElement('select', 'maxitems', get_string('maxitems', 'mediagallery'), $options);
        $mform->setType('maxitems', PARAM_INT);
        $mform->setDefault('maxitems', 0);
        $mform->addHelpButton('maxitems', 'maxitems', 'mediagallery');
        $mform->disabledIf('maxitems', 'colltype', 'eq', 'instructor');
        $mform->disabledIf('maxitems', 'colltype', 'eq', 'single');

        $options = array(
            0 => get_string('unlimited'),
            '-1' => 0,
            1 => 1,
            2 => 2,
            3 => 3,
            4 => 4,
            5 => 5,
            6 => 6,
            7 => 7,
            8 => 8,
            9 => 9,
            10 => 10,
            20 => 20,
            30 => 30,
            40 => 40,
            50 => 50,
            100 => 100,
        );
        $mform->addElement('select', 'maxgalleries', get_string('maxgalleries', 'mediagallery'), $options);
        $mform->setType('maxgalleries', PARAM_INT);
        $mform->setDefault('maxgalleries', 1);
        $mform->addHelpButton('maxgalleries', 'maxgalleries', 'mediagallery');
        $mform->disabledIf('maxgalleries', 'colltype', 'eq', 'instructor');
        $mform->disabledIf('maxgalleries', 'colltype', 'eq', 'single');

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
            \mod_mediagallery\base::POS_BOTTOM => get_string('bottom', 'mediagallery'),
            \mod_mediagallery\base::POS_TOP => get_string('top', 'mediagallery'),
        );
        $mform->addElement('select', 'captionposition', get_string('captionposition', 'mediagallery'), $options);

        // Gallery settings.
        $mform->addElement('header', 'display', get_string('settingsgallery', 'mediagallery'));

        $typeoptgroup = array();
        $typeoptgroup[] = $mform->createElement('radio', 'focus', '',
            get_string('typeall', 'mediagallery'), \mod_mediagallery\base::TYPE_ALL);
        $typeoptgroup[] = $mform->createElement('radio', 'focus', '',
            get_string('typeimage', 'mediagallery'), \mod_mediagallery\base::TYPE_IMAGE);
        $typeoptgroup[] = $mform->createElement('radio', 'focus', '',
            get_string('typevideo', 'mediagallery'), \mod_mediagallery\base::TYPE_VIDEO);
        $typeoptgroup[] = $mform->createElement('radio', 'focus', '',
            get_string('typeaudio', 'mediagallery'), \mod_mediagallery\base::TYPE_AUDIO);
        $mform->addGroup($typeoptgroup, 'gallerytypeoptions', get_string('galleryfocus', 'mediagallery'));
        $mform->addHelpButton('gallerytypeoptions', 'galleryfocus', 'mediagallery');
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

        $mform->addElement('hidden', 'source', 'moodle');
        $mform->setType('source', PARAM_ALPHA);
        $mform->hardFreeze('source');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Preprocess form data. Some of our data needs to be structured to match what a moodleform expects.
     *
     * @param array $toform
     * @return void
     */
    public function data_preprocessing(&$toform) {
        $toform['galleryviewoptions'] = array();
        $toform['galleryviewoptions']['carousel'] = isset($toform['carousel']) ? $toform['carousel'] : 1;
        $toform['galleryviewoptions']['grid'] = isset($toform['grid']) ? $toform['grid'] : '';

        $toform['gallerytypeoptions'] = array();
        $toform['gallerytypeoptions']['focus'] = \mod_mediagallery\base::TYPE_IMAGE;
        if (isset($toform['galleryfocus'])) {
            $toform['gallerytypeoptions']['focus'] = $toform['galleryfocus'];
        }
    }

    /**
     * Set the form data.
     *
     * @param mixed $data Set the form data.
     * @return void
     */
    public function set_data($data) {
        if (!empty($data->id)) {
            $collection = new \mod_mediagallery\collection($data);
            $data->mctags = $collection->get_tags();
            if ($collection->count_galleries() && $collection->is_assessable()) {
                $this->_form->hardFreeze('colltype');
            }
        }
        parent::set_data($data);
    }

}
