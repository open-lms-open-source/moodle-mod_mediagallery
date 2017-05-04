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
 * The collection search class.
 *
 * @package   mod_mediagallery
 * @copyright Copyright (c) 2017 Blackboard Inc.
 * @author    Adam Olley <adam.olley@blackboard.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_mediagallery;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/csvlib.class.php');

/**
 * Media collection search implementation.
 *
 * @copyright Copyright (c) 2017 Blackboard Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mcsearch {
    public $params;
    public $results;

    public function __construct($params) {
        $this->params = $params;
    }

    public function get_results() {
        global $DB;
        $results = array();

        if (is_null($this->params['search'])) {
            return $this->results = $results;
        }

        $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');
        $search = array();
        $searchsql = "";
        if ($this->params['search']) {
             $search = array(
                $DB->sql_like('i.caption', '?', false, false),
                $DB->sql_like($fullname, '?', false, false),
                $DB->sql_like('u.username', '?', false, false),
                $DB->sql_like('f.filename', '?', false, false),
            );
            $searchsql = 'AND ('.implode(' OR ', $search).')';
        }

        $params = array(
            $this->params['context']->id,
            $this->params['collection']->id,
        );

        $groupswhere = '';
        if ($this->params['group']) {
            $groupswhere = "AND i.userid IN (
                SELECT DISTINCT gm.userid
                FROM {groups_members} gm
                JOIN {groups} g ON g.id = gm.groupid
                WHERE g.id = ?
            )";
            $params[] = $this->params['group'];
        }

        $roleswhere = '';
        if ($this->params['role']) {
            $roleswhere = " AND i.userid IN (
                SELECT ra.userid
                FROM {role_assignments} ra
                WHERE ra.contextid = ? AND ra.roleid = ?
            )";
            $params[] = $this->params['context']->get_course_context()->id;
            $params[] = $this->params['role'];
        }

        if (count($search) > 0) {
            $params += array_fill(count($params), count($search), '%'.$this->params['search'].'%');
        }

        $select = "i.*, i.id AS itemid, i.caption AS itemcaption, g.id AS galleryid, g.name AS galleryname,
                    $fullname as creator, i.userid";

        $sql = "SELECT g.*
                FROM {mediagallery_item} i
                JOIN {mediagallery_gallery} g ON i.galleryid = g.id
                JOIN {mediagallery} m ON g.instanceid = m.id
                JOIN {user} u ON i.userid = u.id
                LEFT JOIN {files} f ON f.itemid = i.id AND f.contextid = ? AND f.component = 'mod_mediagallery'
                    AND f.filearea = 'item' AND f.filename != '.'
                WHERE m.id = ? $groupswhere $roleswhere $searchsql";
        $grs = $DB->get_recordset_sql($sql, $params);

        $gallerys = array();
        foreach ($grs as $galleryrecord) {
            $gallerys[$galleryrecord->id] = new gallery($galleryrecord, array('collection' => $this->params['collection']));
        }

        $sql = str_replace('g.*', $select, $sql);
        $rs = $DB->get_recordset_sql($sql, $params);

        $userids = array();
        foreach ($rs as $record) {
            $item = new item($record, array('nogallery' => true));
            if ($this->params['type'] != base::TYPE_ALL && $item->type() != $this->params['type']) {
                continue;
            }
            if (!$gallerys[$record->galleryid]->user_can_view()) {
                continue;
            }

            $userids[$record->userid] = true;
            $results[$record->id] = $record;
        }
        $userids = array_keys($userids);

        $groups = $this->get_groups_for_users($userids);
        $roles = $this->get_roles_for_users($userids);

        // Now with the list of userids, lookup the groups and roles for display.
        foreach ($results as $record) {
            if (isset($groups[$record->userid])) {
                asort($groups[$record->userid]);
            }
            if (isset($roles[$record->userid])) {
                asort($roles[$record->userid]);
            }
            $record->groups = isset($groups[$record->userid]) ? $groups[$record->userid] : array();
            $record->roles = isset($roles[$record->userid]) ? $roles[$record->userid] : array();
        }

        return $this->results = $results;
    }

    /**
     * Get the group names of all groups the users shown are in.
     *
     * @param array $userids
     * @access public
     * @return array
     */
    public function get_groups_for_users($userids) {
        global $DB;
        if (empty($userids)) {
            return array();
        }

        $groups = array();

        list ($insql, $params) = $DB->get_in_or_equal($userids);
        $sql = "SELECT gm.id, g.name, gm.userid, g.id as groupid
                FROM {groups_members} gm
                JOIN {groups} g ON g.id = gm.groupid
                WHERE gm.userid $insql AND g.courseid = ?";
        $params[] = $this->params['courseid'];
        $grouprs = $DB->get_recordset_sql($sql, $params);
        foreach ($grouprs as $record) {
            $groups[$record->userid][$record->groupid] = $record->name;
        }
        return $groups;
    }

    /**
     * Get the role names of all roles the users shown have.
     *
     * @param array $userids
     * @access public
     * @return array
     */
    public function get_roles_for_users($userids) {
        global $DB;
        if (empty($userids)) {
            return array();
        }

        $roles = array();

        $context = $this->params['context'];
        list ($insql, $params) = $DB->get_in_or_equal($userids);
        $rolenames = role_fix_names(get_profile_roles($context), $context, ROLENAME_ALIAS, true);

        $sql = "SELECT ra.id, ra.userid, ra.roleid
                FROM {role_assignments} ra
                WHERE ra.userid $insql AND ra.contextid = ?";
        $params[] = $context->get_course_context()->id;
        $rolers = $DB->get_recordset_sql($sql, $params);
        foreach ($rolers as $record) {
            if (isset($rolenames[$record->roleid])) {
                $roles[$record->userid][$record->roleid] = $rolenames[$record->roleid];
            }
        }
        return $roles;
    }

    /**
     * Trigger a download of a csv export of the search results.
     *
     * @access public
     * @return void
     */
    public function download_csv() {
        $csv = new \csv_export_writer();
        $csv->set_filename('mediacollection_search');
        $csv->add_data(array(
            get_string('caption', 'mod_mediagallery'),
            get_string('gallery', 'mod_mediagallery'),
            get_string('creator', 'mod_mediagallery'),
            get_string('groups'),
            get_string('roles'),
        ));
        foreach ($this->results as $row) {
            $csv->add_data(array(
                $row->itemcaption,
                $row->galleryname,
                $row->creator,
                implode(', ', $row->groups),
                implode(', ', $row->roles),
            ));
        }
        $csv->download_file();
        exit;
    }

}
