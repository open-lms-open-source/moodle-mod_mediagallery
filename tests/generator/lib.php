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
 * Generator for mod_mediagallery activities.
 *
 * @package   mod_mediagallery
 * @copyright Copyright (c) 2017 Blackboard Inc.
 * @author    Adam Olley <adam.olley@netspot.com.au>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * mediagallery module data generator class
 *
 * @package mod_mediagallery
 * @category test
 * @copyright 2014 NetSpot Pty Ltd
 * @author Adam Olley <adam.olley@netspot.com.au>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_mediagallery_generator extends testing_module_generator {

    /**
     * Create new mediagallery module instance
     * @param array|stdClass $record
     * @param array $options (mostly course_module properties)
     * @return stdClass activity record with extra cmid field
     */
    public function create_instance($record = null, array $options = null) {
        global $CFG;
        require_once("$CFG->dirroot/mod/mediagallery/lib.php");
        require_once("$CFG->dirroot/mod/mediagallery/locallib.php");

        $this->instancecount++;
        $i = $this->instancecount;

        $record = (object)(array)$record;
        $options = (array)$options;

        if (empty($record->course)) {
            throw new coding_exception('module generator requires $record->course');
        }

        $defaultsettings = array(
            'name'               => get_string('pluginname', 'mediagallery').' '.$i,
            'intro'              => 'Test mediagallery ' . $i,
            'introformat'        => FORMAT_MOODLE,
            'userid'             => 0,
            'thumbnailsperpage'  => 0,
            'thumbnailsperrow'   => 2,
            'displayfullcaption' => 0,
            'captionposition'    => 0,
            'colltype'           => 'contributed',
            'galleryfocus'       => 1,
            'galleryviewoptions' => array('carousel' => 1),
            'gridrows'           => 2,
            'gridcolumns'        => 3,
            'enforcedefauls'     => 0,
            'readonlyfrom'       => 0,
            'readonlyto'         => 0,
            'mode'               => 'standard',
            'contributable'      => 0,
            'maxgalleries'       => 0,
        );
        $defaultsettings['gallerytypeoptions']['focus'] = 1;

        foreach ($defaultsettings as $name => $value) {
            if (!isset($record->{$name})) {
                $record->{$name} = $value;
            }
        }

        return parent::create_instance($record, $options);
    }

    /**
     * Create a gallery object.
     *
     * @param mixed $record An array or object defining a gallery.
     * @return \mod_mediagallery\gallery
     */
    public function create_gallery($record = null) {
        global $CFG, $USER;

        $record = (object)(array)$record;
        $defaults = array(
            'groupid' => 0,
            'galleryfocus' => \mod_mediagallery\base::TYPE_IMAGE,
            'galleryview' => \mod_mediagallery\gallery::VIEW_GRID,
            'userid' => $USER->id,
            'mode' => 'standard',
            'tags' => '',
        );

        if (!isset($record->instanceid)) {
            throw new coding_exception('instanceid must be present in $record');
        }

        if (!isset($record->name)) {
            throw new coding_exception('name must be present in $record');
        }

        foreach ($defaults as $key => $value) {
            if (!isset($record->$key)) {
                $record->$key = $value;
            }
        }
        return \mod_mediagallery\gallery::create($record);
    }

    /**
     * Create an item.
     *
     * @param mixed $record An array or object defining a item.
     * @return \mod_mediagallery\item
     */
    public function create_item($record = null) {
        global $CFG, $USER;

        $record = (object)(array)$record;
        $defaults = array(
            'caption' => '',
            'userid' => $USER->id,
            'tags' => '',
        );

        if (!isset($record->galleryid)) {
            throw new coding_exception('galleryid must be present in $record');
        }

        foreach ($defaults as $key => $value) {
            if (!isset($record->$key)) {
                $record->$key = $value;
            }
        }
        return \mod_mediagallery\item::create($record);
    }
}
