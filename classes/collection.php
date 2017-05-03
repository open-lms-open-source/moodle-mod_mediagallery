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

namespace mod_mediagallery;

defined('MOODLE_INTERNAL') || die();

/**
 * This class contains functions for manipulating and getting information for
 * the galleries that belong to a specific mediagallery activity.
 */
class collection extends base {

    public $cm;
    public $context;
    public $options = array(
        'groups' => array(),
    );
    protected $record;
    protected $submitted = null;
    static protected $table = 'mediagallery';
    private $deleted = false;

    public function __construct($recordorid, $options = array()) {
        global $USER;

        parent::__construct($recordorid, $options);
        $this->cm = get_coursemodule_from_instance('mediagallery', $this->record->id);

        if (!empty($this->cm)) {
            $this->context = \context_module::instance($this->cm->id);
            $this->options['currentgroup'] = groups_get_activity_group($this->cm, true);
            $this->options['groupmode'] = groups_get_activity_groupmode($this->cm);
            $this->options['groups'] = groups_get_all_groups($this->cm->course, $USER->id, $this->cm->groupingid);
        }
    }

    /**
     * Here for completeness, but new collections should be created via course mod_form interface.
     */
    public static function create(\stdClass $data) {
        global $DB, $USER;

        if (!isset($data->userid)) {
            $data->userid = $USER->id;
        }

        $data->id = mediagallery_add_instance($data);
        return new collection($data);
    }

    public function delete($options = array()) {
        global $DB;

        $params = array(
            'context' => $this->get_context(),
            'objectid' => $this->id,
            'other' => array(
                'modulename' => 'mediagallery',
                'instanceid' => $this->id,
            ),
        );
        if (!empty($options['nosync'])) {
            $params['other']['nosync'] = true;
        }

        $sql = "DELETE FROM {mediagallery_userfeedback}
                WHERE itemid IN (
                    SELECT i.id
                    FROM {mediagallery_item} i
                    JOIN {mediagallery_gallery} g ON g.id = i.galleryid
                    WHERE g.instanceid = ?
                )";
        $DB->execute($sql, array($this->id));

        // We trigger this early so observers can handle external deletion.
        if (!empty($params['context'])) {
            $event = \mod_mediagallery\event\collection_deleted::create($params);
            $event->add_record_snapshot(static::$table, $this->get_record());
            $event->trigger();
        }

        $sql = "DELETE FROM {mediagallery_item}
                WHERE galleryid IN (
                    SELECT id
                    FROM {mediagallery_gallery}
                    WHERE instanceid = ?
                )";
        $DB->execute($sql, array($this->id));
        $DB->delete_records('mediagallery_gallery', array('instanceid' => $this->id));
        $DB->delete_records('mediagallery', array('id' => $this->id));

        return true;
    }

    public function get_context() {
        return $this->context;
    }

    public function get_my_galleries($userid = null) {
        global $DB, $USER;

        if (is_null($userid)) {
            $userid = $USER->id;
        }

        $galleries = array();

        $select = "instanceid = :instanceid AND (userid = :userid";
        $params = array();
        if ($this->options['groupmode'] != NOGROUPS) {
            if (!empty($this->options['groups'])) {
                list($insql, $params) = $DB->get_in_or_equal(array_keys($this->options['groups']), SQL_PARAMS_NAMED);
                $select .= " OR groupid $insql";
            }
        }
        $select .= ")";
        $params['instanceid'] = $this->record->id;
        $params['userid'] = $userid;
        if ($records = $DB->get_records_select('mediagallery_gallery', $select, $params)) {
            foreach ($records as $record) {
                $galleries[$record->id] = new gallery($record, array('collection' => $this));
            }
        }

        return $galleries;
    }

    /**
     * Bulk load all the galleries that are submitted. Saves us checking for each gallery individually.
     * @return array A list of gallery id's and the number of submissions they have.
     */
    public function get_submitted_galleries() {
        global $CFG, $DB;

        if (!is_null($this->submitted)) {
            return $this->submitted;
        }

        if (!self::is_assignsubmission_mediagallery_installed()) {
            return false;
        }
        require_once($CFG->dirroot.'/mod/assign/locallib.php');

        $sql = "SELECT asub.id, asm.galleryid, asub.status, asub.assignment, asub.userid
                FROM {assign_submission} asub
                LEFT JOIN {assignsubmission_mg} asm ON asm.submission = asub.id AND asm.assignment = asub.assignment
                JOIN {assign_plugin_config} apc ON apc.assignment = asub.assignment
                JOIN {assign} a ON a.id = asub.assignment
                LEFT JOIN {assign_user_flags} uf ON a.id = uf.assignment AND uf.userid = asub.userid
                WHERE apc.plugin = 'mediagallery' AND apc.subtype = 'assignsubmission' AND apc.name = 'mediagallery'
                    AND apc.value = :collection
                    AND asub.status IN ('".ASSIGN_SUBMISSION_STATUS_SUBMITTED."', '".ASSIGN_SUBMISSION_STATUS_REOPENED."')
                    AND (a.duedate = 0 OR a.duedate < :time OR uf.locked = 1)
                GROUP BY asub.id, asm.galleryid, asub.assignment, asub.status, asub.attemptnumber, asub.userid
                ORDER BY asub.assignment ASC, asub.userid, asub.attemptnumber ASC";
        $params = array('collection' => $this->record->id, 'time' => time());
        if ($results = $DB->get_records_sql($sql, $params)) {
            $this->submitted = array();
            $submissions = array();
            foreach ($results as $result) {
                if ($result->status == ASSIGN_SUBMISSION_STATUS_REOPENED) {
                    unset($this->submitted[$submissions[$result->assignment][$result->userid]]);
                } else {
                    if (!empty($result->galleryid)) {
                        $this->submitted[$result->galleryid] = $result;
                        $submissions[$result->assignment][$result->userid] = $result->galleryid;
                    }
                }
            }
        }
        return $this->submitted;
    }

    /**
     * Get all the galleries in this collection visible to the current user.
     *
     * Check various visibility requirements.
     *  - availability. TODO.
     *  - ownership (user or group).
     *  - view caps.
     *
     * @return array    List of gallery objects.
     */
    public function get_visible_galleries() {
        global $DB, $USER;
        $galleries = array();

        $accessallgroups = has_capability('moodle/site:accessallgroups', $this->context);
        $viewall = has_capability('mod/mediagallery:viewall', $this->context);

        $select = "instanceid = :instanceid";
        $params = array();
        if ($this->options['groupmode'] == SEPARATEGROUPS && !$accessallgroups && !$viewall) {
            if (!empty($this->options['groups'])) {
                list($insql, $params) = $DB->get_in_or_equal(array_keys($this->options['groups']), SQL_PARAMS_NAMED);
                $select .= " AND groupid $insql";
            }
        } else if (!empty($this->options['currentgroup'])) {
            $select .= " AND groupid = :groupid";
            $params['groupid'] = $this->options['currentgroup'];
        }
        $params['instanceid'] = $this->record->id;
        $sql = "SELECT gg.*, u.firstname, u.lastname, g.name as groupname
                FROM {mediagallery_gallery} gg
                LEFT JOIN {user} u ON (gg.userid = u.id)
                LEFT JOIN {groups} g ON (gg.groupid = g.id)
                WHERE $select
                ORDER BY gg.id ASC";
        if ($records = $DB->get_records_sql($sql, $params)) {
            foreach ($records as $record) {
                $gallery = new gallery($record, array('collection' => $this));
                if ($viewall || $gallery->user_can_view()) {
                    $galleries[$record->id] = $gallery;
                }
            }
        }

        return $galleries;
    }

    public function count_galleries() {
        global $DB;
        return $DB->count_records('mediagallery_gallery', array('instanceid' => $this->record->id));
    }

    /**
     * Get a list of all the gallery's associated with this collection.
     *
     * @access public
     * @param $filterbymode mixed If this is a non-empty string, only galleries of the specified mode are returned.
     * @return array of gallery objects
     */
    public function get_galleries($filterbymode = false) {
        global $DB;

        $list = array();
        $params = array('instanceid' => $this->record->id);
        if ($filterbymode) {
            $params['mode'] = $filterbymode;
        }
        $records = $DB->get_records('mediagallery_gallery', $params);
        foreach ($records as $record) {
            $list[$record->id] = new gallery($record, array('collection' => $this));
        }
        return $list;
    }

    public static function get_public_galleries_by_contextid($contextid, $prefix = true) {
        global $DB;

        $context = \context::instance_by_id($contextid);
        if (!$coursecontext = $context->get_course_context(false)) {
            return array();
        }
        $course = $DB->get_record('course', array('id' => $coursecontext->instanceid), '*', MUST_EXIST);

        $collections = get_all_instances_in_course('mediagallery', $course);

        $collids = array();
        foreach ($collections as $collection) {
            $collids[] = $collection->id;
        }

        if (empty($collids)) {
            return array();
        }

        $concat = $prefix ? $DB->sql_concat('mg.name', "' > '", 'g.name') : 'g.name';
        list($insql, $params) = $DB->get_in_or_equal($collids, SQL_PARAMS_NAMED);
        $sql = "SELECT g.*,
                $concat AS label
                FROM {mediagallery_gallery} g
                JOIN {mediagallery} mg on (mg.id = g.instanceid)
                WHERE instanceid $insql";
        $list = array();
        foreach ($DB->get_records_sql($sql, $params) as $record) {
            $gallery = new gallery($record);
            if ($gallery->user_can_view()) {
                $list[$gallery->id] = $record->label;
            }
        }
        return $list;
    }

    public static function get_my_galleries_by_contextid($contextid) {
        global $DB;

        $context = \context::instance_by_id($contextid);
        if (!$coursecontext = $context->get_course_context(false)) {
            return array();
        }
        $course = $DB->get_record('course', array('id' => $coursecontext->instanceid), '*', MUST_EXIST);

        $collections = get_all_instances_in_course('mediagallery', $course);

        $collids = array();
        $theboxenabled = false;
        foreach ($collections as $collection) {
            if (!$theboxenabled && $collection->mode == 'thebox') {
                continue;
            }
            $collids[] = $collection->id;
        }

        if (empty($collids)) {
            return array();
        }

        list($insql, $params) = $DB->get_in_or_equal($collids, SQL_PARAMS_NAMED);
        // If theBox is removed, or set to hidden, don't display theBox content.
        $select = '';
        $repos = \core\plugininfo\repository::get_enabled_plugins();
        if (!isset($repos['thebox']) || !$theboxenabled) {
            $select .= " AND g.mode != 'thebox'";
        }
        $sql = "SELECT g.*,
                ".$DB->sql_concat('mg.name', "' > '", 'g.name')." AS label
                FROM {mediagallery_gallery} g
                JOIN {mediagallery} mg on (mg.id = g.instanceid)
                WHERE instanceid $insql $select";
        $list = array();
        foreach ($DB->get_records_sql($sql, $params) as $record) {
            $gallery = new gallery($record);
            if ($gallery->user_can_edit(null, true)) {
                $list[$gallery->id] = $record->label;
            }
        }
        return $list;
    }

    /**
     * Has the current user submitted this to the linked assignment?
     *
     * @access public
     * @return bool
     */
    public function has_submitted() {
        global $DB, $USER;

        if (!$this->is_assessable() || !self::is_assignsubmission_mediagallery_installed()) {
            return false;
        }

        $groupselect = '';
        $params = array('collection' => $this->record->id, 'userid' => $USER->id);
        if ($this->options['groupmode'] != NOGROUPS) {
            if (!empty($this->options['groups'])) {
                list($insql, $gparams) = $DB->get_in_or_equal(array_keys($this->options['groups']), SQL_PARAMS_NAMED);
                $params = array_merge($params, $gparams);
                $groupselect = " OR asub.groupid $insql";
            }
        }

        $sql = "SELECT asub.id
                FROM {assign_submission} asub
                JOIN {assign_plugin_config} apc ON apc.assignment = asub.assignment
                JOIN {assign} a ON a.id = asub.assignment
                LEFT JOIN {assign_user_flags} uf ON a.id = uf.assignment AND uf.userid = asub.userid
                WHERE apc.plugin = 'mediagallery' AND apc.subtype = 'assignsubmission' AND apc.name = 'mediagallery'
                    AND apc.value = :collection AND (asub.userid = :userid $groupselect)";
        return $DB->record_exists_sql($sql, $params);
    }

    /**
     * Get the ID of the linked assign coursemodule if one exists.
     *
     * @access public
     * @return int|bool Returns the cmid of the linked assign, false otherwise.
     */
    public function get_linked_assignid() {
        global $DB;

        $sql = "SELECT cm.id
                FROM {course_modules} cm
                JOIN {modules} m ON m.id = cm.module
                JOIN {assign_plugin_config} apc ON apc.assignment = cm.instance
                WHERE apc.plugin = 'mediagallery' AND apc.name = 'mediagallery'
                    AND m.name = 'assign' AND ".$DB->sql_compare_text('apc.value')." = ?";
        $params = array($this->record->id);
        return $DB->get_field_sql($sql, $params, IGNORE_MULTIPLE);
    }

    /**
     * Is this collection able to be used as part of an assignment.
     *
     * @access public
     * @return bool
     */
    public function is_assessable() {
        return $this->colltype == 'assignment' || $this->colltype == 'peerreviewed';
    }

    /**
     * is_read_only
     *
     * Returns true if this collection is readonly for the current user.
     * Users with the mod/mediagallery:manage cap can always edit the collection.
     *
     * @access public
     * @return bool true if read only for this user.
     */
    public function is_read_only() {
        if (has_capability('mod/mediagallery:manage', $this->context)) {
            return false;
        }

        // If the user doesn't have the manage cap, then instructor mode means they can't edit.
        if ($this->record->colltype == 'instructor') {
            return true;
        }

        $from = $this->record->readonlyfrom;
        $till = $this->record->readonlyto;
        $now = time();
        if ($from > 0 && $from < $now) {
            if ($till == 0 || $till > $now) {
                return true;
            }
            return false;
        }
        if ($till > $now && $from == 0) {
            return true;
        }
        return false;
    }

    public function was_deleted() {
        return $this->deleted;
    }

    public function sync($forcesync = false) {
        return;
    }

    /**
     * Retrieve the userid of the collection creator from the log tables
     *
     * This function is used to update the new userid field during module
     * upgrade.
     *
     * @param int $collectionid
     * @param int $courseid
     * @access public
     * @return mixed bool|int   Returns the userid of the creator, or false if
     * not found.
     */
    public function get_userid_from_logs() {
        global $DB;
        // Look in logs for the course module creation event.
        // Odds are creation was before 2.7. So we check there first.

        if (empty($this->cm)) {
            return false;
        }

        $params = array(
            'course' => $this->course,
            'module' => 'mediagallery',
            'action' => 'add',
            'cmid' => $this->cm->id,
        );
        if ($legacylog = $DB->get_record('log', $params, 'id, userid', IGNORE_MULTIPLE)) {
            return $legacylog->userid;
        }

        // It wasn't in the legacy log table, check the new logstore.
        $params = array(
            'courseid' => $this->course,
            'eventname' => '\core\event\course_module_created',
            'contextinstanceid' => $this->cm->id,
            'contextlevel' => CONTEXT_MODULE,
        );
        if ($standardlog = $DB->get_record('logstore_standard_log', $params, 'id, userid', IGNORE_MULTIPLE)) {
            return $standardlog->userid;
        }

        return false;
    }

    public function user_can_remove($userid = null) {
        global $USER;

        if (is_null($userid)) {
            $userid = $USER->id;
        }

        $theboxowner = $this->mode == 'thebox' && $this->is_thebox_creator_or_agent($userid);
        return $theboxowner || has_capability('mod/mediagallery:manage', $this->get_context(), $userid);
    }

    /**
     * Can a given user add a gallery?
     *
     * Accounts for the max gallery limit and how many they or their group already has.
     *
     * @param int $userid
     * @access public
     * @return bool
     */
    public function user_can_add_children($userid = null) {
        global $USER;

        if (is_null($userid)) {
            $userid = $USER->id;
        }

        $max = $this->record->maxgalleries;
        $manager = has_capability('mod/mediagallery:manage', $this->context, $userid);
        if ($max != 0 && !$manager) {
            if ($this->options['groupmode'] != NOGROUPS) {
                // Compare user group count to galleries for those groups.
                $groupings = groups_get_user_groups($this->record->course, $userid);
                $groups = $groupings[0];
                if (empty($groups)) {
                    return false;
                }
                $groupkeyed = array_flip($groups);

                $galleries = $this->get_group_gallery_counts();
                foreach ($galleries as $count) {
                    if ($count->count >= $max) {
                        unset($groupkeyed[$count->groupid]);
                    }
                }
                return !empty($groupkeyed);
            }

            return count($this->get_my_galleries($userid)) < $max;
        }
        return true;
    }

    public function get_group_gallery_counts() {
        global $DB;

        $sql = "SELECT groupid, count(1) AS count
                FROM {mediagallery_gallery}
                WHERE instanceid = ?
                GROUP BY groupid";
        $params[] = $this->record->id;
        $galleries = $DB->get_records_sql($sql, $params);
        return $galleries;
    }
}
