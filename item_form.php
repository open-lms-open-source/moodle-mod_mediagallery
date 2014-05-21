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
 * Form for creating/editing an item.
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
class mod_mediagallery_item_form extends moodleform {

    /**
     * Defines forms elements
     */
    public function definition() {
        global $CFG;

        $mform = $this->_form;
        $gallery = $this->_customdata['gallery'];

        // General settings.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'caption', get_string('caption', 'mediagallery'), array('size' => '64'));
        $mform->setType('caption', PARAM_TEXT);
        $mform->addRule('caption', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('caption', 'caption', 'mediagallery');

        $options = array(
            'collapsed' => true,
            'maxfiles' => 0,
            'return_types' => null,
        );
        $mform->addElement('editor', 'description', get_string('description'), null, $options);

        $mform->addElement('selectyesno', 'display', get_string('itemdisplay', 'mediagallery'));
        $mform->setDefault('display', 1);
        $mform->addHelpButton('display', 'itemdisplay', 'mediagallery');

        $mform->addElement('selectyesno', 'thumbnail', get_string('gallerythumbnail', 'mediagallery'));
        $default = $this->_customdata['firstitem'] ? 1 : 0;
        $mform->setDefault('thumbnail', $default);
        $mform->addHelpButton('thumbnail', 'gallerythumbnail', 'mediagallery');

        $mform->addElement('static', 'filecheck', '', get_string('choosecontent', 'mediagallery'));

        $mform->addElement('filepicker', 'content', get_string('content', 'mediagallery'), '0',
            mediagallery_filepicker_options($gallery));
        $mform->addHelpButton('content', 'content', 'mediagallery');

        $mform->addElement('url', 'externalurl', get_string('externalurl', 'mediagallery'), array('size' => '60'),
            array('usefilepicker' => true));
        $mform->setType('externalurl', PARAM_TEXT);
        $mform->addHelpButton('externalurl', 'externalurl', 'mediagallery');

        if ($gallery->gallerytype != MEDIAGALLERY_TYPE_IMAGE) {
            $fpoptions = mediagallery_filepicker_options($gallery);
            $fpoptions['accepted_types'] = array('web_image');
            $fpoptions['return_types'] = FILE_INTERNAL;
            $mform->addElement('filepicker', 'customthumbnail', get_string('thumbnail', 'mediagallery'), '0', $fpoptions);
            $mform->addHelpButton('customthumbnail', 'thumbnail', 'mediagallery');
        }

        // Advanced settings.
        $mform->addElement('header', 'advanced', get_string('advanced'));
        mediagallery_add_metainfo_fields($mform);

        $mform->addElement('hidden', 'g', $gallery->id);
        $mform->setType('g', PARAM_INT);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons();
    }

    public function validation($data, $files) {
        global $CFG;
        $errors = parent::validation($data, $files);
        $info = file_get_draft_area_info($data['content']);
        $url = trim($data['externalurl']);

        if (empty($data['externalurl']) && $info['filecount'] == 0) {
            $errors['filecheck'] = get_string('required');
        } else if (!empty($url)) {
            if (preg_match('|^/|', $url)) {
                // Links relative to server root are ok - no validation necessary.
            } else if (preg_match('|^[a-z]+://|i', $url) or preg_match('|^https?:|i', $url) or preg_match('|^ftp:|i', $url)) {
                // Normal URL.
                if (!mediagallery_appears_valid_url($url)) {
                    $errors['externalurl'] = get_string('invalidurl', 'url');
                }

            } else if (preg_match('|^[a-z]+:|i', $url)) {
                // General URI such as teamspeak, mailto, etc. - it may or may not work in all browsers.
                // We do not validate these at all, sorry.
            } else {
                // Invalid URI, we try to fix it by adding 'http://' prefix.
                // Relative links are NOT allowed because we display the link on different pages!
                require_once($CFG->dirroot."/mod/url/locallib.php");
                if (!url_appears_valid_url('http://'.$url)) {
                    $errors['externalurl'] = get_string('invalidurl', 'url');
                }
            }
        }
        return $errors;
    }

}
