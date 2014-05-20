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
 * @package    mod_mediagallery
 * @copyright  NetSpot Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute mediagallery upgrade from the given old version
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_mediagallery_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2014010300) {
        $dbman->install_one_table_from_xmldb_file($CFG->dirroot.'/mod/mediagallery/db/install.xml', 'mediagallery_userfeedback');
        upgrade_mod_savepoint(true, 2014010300, 'mediagallery');
    }

    if ($oldversion < 2014010400) {
        $table = new xmldb_table('mediagallery_gallery');
        $field = new xmldb_field('groupid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'userid');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2014010400, 'mediagallery');
    }

    if ($oldversion < 2014010800) {
        require_once("{$CFG->dirroot}/mod/mediagallery/locallib.php");
        if ($records = $DB->get_recordset('mediagallery_item')) {
            foreach ($records as $record) {
                $item = new \mod_mediagallery\item($record);
                $item->generate_thumbnail();
            }
        }

        upgrade_mod_savepoint(true, 2014010800, 'mediagallery');
    }

    if ($oldversion < 2014011400) {
        $table = new xmldb_table('mediagallery_gallery');
        $field = new xmldb_field('visibleinstructor', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, false, 0, 0);
        $dbman->change_field_precision($table, $field);
        $field = new xmldb_field('visibleother', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, false, 0, 0);
        $dbman->change_field_precision($table, $field);

        upgrade_mod_savepoint(true, 2014011400, 'mediagallery');
    }

    if ($oldversion < 2014021800) {
        $table = new xmldb_table('mediagallery_item');
        $field = new xmldb_field('timecreated');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'externalurl');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('userid');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'galleryid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2014021800, 'mediagallery');
    }

    if ($oldversion < 2014022500) {
        $table = new xmldb_table('mediagallery');
        $field = new xmldb_field('maxbytes');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'readonlyto');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('maxitems');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'maxbytes');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('maxgalleries');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '1', 'maxitems');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2014022500, 'mediagallery');
    }

    if ($oldversion < 2014022701) {
        $table = new xmldb_table('mediagallery');
        $field = new xmldb_field('allowcomments');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '1', 'maxgalleries');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('allowlikes');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '1', 'allowcomments');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2014022701, 'mediagallery');
    }

    if ($oldversion < 2014042801) {
        $table = new xmldb_table('mediagallery');
        $field = new xmldb_field('gallerytype', XMLDB_TYPE_CHAR, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, '');
        $dbman->change_field_precision($table, $field);

        $table = new xmldb_table('mediagallery_gallery');
        $field = new xmldb_field('gallerytype');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '1', 'exportable');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Set type based on collection type.
        $sql = "SELECT mg.id, mc.gallerytype
                FROM {mediagallery_gallery} mg
                JOIN {mediagallery} mc ON (mc.id = mg.instanceid)";
        $galleries = $DB->get_recordset_sql($sql);
        foreach ($galleries as $gallery) {
            $DB->update_record('mediagallery_gallery', $gallery);
        }

        upgrade_mod_savepoint(true, 2014042801, 'mediagallery');
    }

    return true;
}
