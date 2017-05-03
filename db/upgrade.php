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

    if ($oldversion < 2014102000) {
        $table = new xmldb_table('mediagallery');
        $field = new xmldb_field('objectid');
        $field->set_attributes(XMLDB_TYPE_CHAR, '36', null, null, null, null, 'allowlikes');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('mediagallery_gallery');
        $field = new xmldb_field('objectid');
        $field->set_attributes(XMLDB_TYPE_CHAR, '36', null, null, null, null, 'thumbnail');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('mediagallery_item');
        $field = new xmldb_field('objectid');
        $field->set_attributes(XMLDB_TYPE_CHAR, '36', null, null, null, null, 'timecreated');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2014102000, 'mediagallery');
    }

    if ($oldversion < 2014111400) {
        $table = new xmldb_table('mediagallery');
        $field = new xmldb_field('galleryfocus');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '1', 'captionposition');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Migrate existing defaults into new focus.
        $field = new xmldb_field('gallerytype');
        if ($dbman->field_exists($table, $field)) {
            $rs = $DB->get_recordset('mediagallery');
            foreach ($rs as $record) {
                $types = explode(',', $record->gallerytype);
                $focus = !empty($types) ? $types[0] : \mod_mediagallery\collection::TYPE_IMAGE;
                if (empty($focus)) {
                    $focus = \mod_mediagallery\collection::TYPE_IMAGE;
                }
                $record->galleryfocus = $focus;
                $DB->update_record('mediagallery', $record);
            }

            $dbman->drop_field($table, $field);
        }

        // Just need to rename the field for this one.
        $table = new xmldb_table('mediagallery_gallery');
        $field = new xmldb_field('gallerytype');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '1', 'exportable');
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'galleryfocus');
        }

        upgrade_mod_savepoint(true, 2014111400, 'mediagallery');
    }

    if ($oldversion < 2014111801) {
        // Check these are added again - out of order version bumps means they
        // might've been missed.
        $table = new xmldb_table('mediagallery');
        $field = new xmldb_field('objectid');
        $field->set_attributes(XMLDB_TYPE_CHAR, '36', null, null, null, null, 'allowlikes');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('mediagallery_gallery');
        $field = new xmldb_field('objectid');
        $field->set_attributes(XMLDB_TYPE_CHAR, '36', null, null, null, null, 'thumbnail');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('mediagallery_item');
        $field = new xmldb_field('objectid');
        $field->set_attributes(XMLDB_TYPE_CHAR, '36', null, null, null, null, 'timecreated');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('mediagallery_gallery');
        $field = new xmldb_field('mode');
        $field->set_attributes(XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'standard', 'objectid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2014111801, 'mediagallery');
    }

    if ($oldversion < 2014112400) {
        $table = new xmldb_table('mediagallery');
        $field = new xmldb_field('colltype');
        $field->set_attributes(XMLDB_TYPE_CHAR, '12', null, XMLDB_NOTNULL, null, 'peerreviewed', 'timemodified');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2014112400, 'mediagallery');
    }

    if ($oldversion < 2014112401) {
        $table = new xmldb_table('mediagallery');
        $field = new xmldb_field('source');
        $field->set_attributes(XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'moodle', 'objectid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $table = new xmldb_table('mediagallery_gallery');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $table = new xmldb_table('mediagallery_item');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2014112401, 'mediagallery');
    }

    if ($oldversion < 2014112402) {
        $table = new xmldb_table('mediagallery_item');
        $field = new xmldb_field('collection');
        $field->set_attributes(XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'publisher');
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'reference');
        }

        upgrade_mod_savepoint(true, 2014112402, 'mediagallery');
    }

    if ($oldversion < 2014112500) {
        $table = new xmldb_table('mediagallery_item');
        $field = new xmldb_field('broadcaster');
        $field->set_attributes(XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'publisher');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2014112500, 'mediagallery');
    }

    if ($oldversion < 2015011600) {
        $table = new xmldb_table('mediagallery_item');
        $fields = array();
        $fields = array(
            new xmldb_field('extpath', XMLDB_TYPE_TEXT, null, null, null, null, null, 'objectid'),
            new xmldb_field('theme_id', XMLDB_TYPE_CHAR, '36', null, null, null, null, 'extpath'),
            new xmldb_field('copyright_video_id', XMLDB_TYPE_CHAR, '36', null, null, null, null, 'theme_id'),
        );

        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_mod_savepoint(true, 2015011600, 'mediagallery');
    }

    if ($oldversion < 2015020300) {
        $table = new xmldb_table('mediagallery');
        $field = new xmldb_field('userid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'name');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2015020300, 'mediagallery');
    }

    if ($oldversion < 2015020302) {
        $table = new xmldb_table('mediagallery');
        $field = new xmldb_field('creator', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'source');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2015020302, 'mediagallery');
    }

    if ($oldversion < 2015020400) {
        $table = new xmldb_table('mediagallery_gallery');
        $field = new xmldb_field('creator', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'source');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('mediagallery_item');
        $field = new xmldb_field('creator', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'source');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2015020400, 'mediagallery');
    }

    if ($oldversion < 2015020401) {
        $table = new xmldb_table('mediagallery_item');
        $field = new xmldb_field('processing_status', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'source');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2015020401, 'mediagallery');
    }

    if ($oldversion < 2015020601) {
        $table = new xmldb_table('mediagallery');
        $field = new xmldb_field('mode', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'standard', 'objectid');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Any collections that have an objectid are in theBox.
        $sql = "UPDATE {mediagallery}
                SET mode = 'thebox'
                WHERE objectid IS NOT NULL AND objectid != ''";
        $DB->execute($sql);

        upgrade_mod_savepoint(true, 2015020601, 'mediagallery');
    }

    if ($oldversion < 2015021000) {
        $tables = array(
            new xmldb_table('mediagallery'),
            new xmldb_table('mediagallery_gallery'),
            new xmldb_table('mediagallery_item'),
        );
        $field = new xmldb_field('agents', XMLDB_TYPE_TEXT, null, null, null, null, null, 'creator');

        foreach ($tables as $table) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_mod_savepoint(true, 2015021000, 'mediagallery');
    }

    if ($oldversion < 2015021101) {
        // Populate the userid field based on the original user that added the module.
        $rs = $DB->get_recordset('mediagallery');
        foreach ($rs as $record) {
            if (!empty($record->userid)) {
                continue;
            }
            $collection = new \mod_mediagallery\collection($record);
            if ($record->userid = $collection->get_userid_from_logs()) {
                $DB->update_record('mediagallery', $record);
            }
        }

        upgrade_mod_savepoint(true, 2015021101, 'mediagallery');
    }

    if ($oldversion < 2015021102) {
        // Rename the copyright field to match change in API.
        $table = new xmldb_table('mediagallery_item');
        $field = new xmldb_field('copyright_video_id', XMLDB_TYPE_CHAR, '36', null, null, null, null, 'theme_id');

        // Launch rename field summary.
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'copyright_id');
        }

        upgrade_mod_savepoint(true, 2015021102, 'mediagallery');
    }

    if ($oldversion < 2015082600) {
        // Rename the copyright field to match change in API.
        $table = new xmldb_table('mediagallery_gallery');
        $field = new xmldb_field('contributable', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'mode');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2015082600, 'mediagallery');
    }

    return true;
}
