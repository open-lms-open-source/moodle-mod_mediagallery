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

    public function __construct($recordorid, $options = array()) {
        global $USER;

        parent::__construct($recordorid, $options);
        $this->cm = get_coursemodule_from_instance('mediagallery', $this->record->id);
        $this->context = \context_module::instance($this->cm->id);
        $this->gallerytypes = explode(',', $this->gallerytype);

        $this->options['currentgroup'] = groups_get_activity_group($this->cm, true);
        $this->options['groupmode'] = groups_get_activity_groupmode($this->cm);
        $this->options['groups'] = groups_get_all_groups($this->cm->course, $USER->id, $this->cm->groupingid);
    }

    /**
     * Here for completeness, but new collections should be created via course mod_form interface.
     */
    public static function create(\stdClass $data) {
        global $DB, $USER;

        $data->id = mediagallery_add_instance($data);
        return new collection($data);
    }

    public function get_context() {
        return $this->context;
    }

    public function get_my_galleries() {
        global $DB, $USER;
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
        $params['userid'] = $USER->id;
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

        $installed = get_config('assignsubmission_mediagallery', 'version');
        $path = $CFG->dirroot.'/mod/assign/submission/mediagallery/locallib.php';
        if (!$installed || !file_exists($path)) {
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

    public static function get_public_galleries_by_contextid($contextid, $prefix = true) {
        global $DB;

        $context = \context::instance_by_id($contextid);
        $coursecontext = $context->get_course_context();
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
        $coursecontext = $context->get_course_context();
        $course = $DB->get_record('course', array('id' => $coursecontext->instanceid), '*', MUST_EXIST);

        $collections = get_all_instances_in_course('mediagallery', $course);

        $collids = array();
        foreach ($collections as $collection) {
            $collids[] = $collection->id;
        }

        if (empty($collids)) {
            return array();
        }

        list($insql, $params) = $DB->get_in_or_equal($collids, SQL_PARAMS_NAMED);
        $sql = "SELECT g.*,
                ".$DB->sql_concat('mg.name', "' > '", 'g.name')." AS label
                FROM {mediagallery_gallery} g
                JOIN {mediagallery} mg on (mg.id = g.instanceid)
                WHERE instanceid $insql";
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
}
