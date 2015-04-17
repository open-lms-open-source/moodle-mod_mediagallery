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
 * Settings for mediagallery
 *
 * @package    mod_mediagallery
 * @copyright  2014 NetSpot Pty Ltd
 * @author     Adam Olley <adam.olley@netspot.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    if (isset($CFG->maxbytes)) {
        $maxbytes = get_config('mediagallery', 'maxbytes');
        $options = get_max_upload_sizes($CFG->maxbytes, 0, 0, $maxbytes);
        $settings->add(new admin_setting_configselect('mediagallery/maxbytes', new lang_string('maxbytes', 'mediagallery'),
                            new lang_string('configmaxbytes', 'mediagallery'), 0, $options));
    }

    $settings->add(new admin_setting_configcheckbox('mediagallery/disablestandardgallery',
                        new lang_string('disablestandardgallery', 'mediagallery'),
                        new lang_string('configdisablestandardgallery', 'mediagallery'), 0));

}
if ($hassiteconfig) {
    $ADMIN->add('reports', new admin_externalpage('modmediagallerystorage',
        new lang_string('storagereport', 'mediagallery'), "$CFG->wwwroot/mod/mediagallery/storage.php", 'moodle/site:config'));
}
