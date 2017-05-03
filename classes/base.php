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

abstract class base {

    const POS_BOTTOM = 0;
    const POS_TOP = 1;
    const TYPE_AUDIO = 0;
    const TYPE_IMAGE = 1;
    const TYPE_VIDEO = 2;
    const TYPE_ALL = 3;

    protected $options;
    protected $record;
    static protected $table;

    public function __construct($recordorid, $options = array()) {
        global $DB;

        if (empty(static::$table)) {
            throw new \coding_exception(get_called_class().' must define a table');
        }

        if (is_object($recordorid)) {
            if (empty($recordorid->id)) {
                throw new \coding_exception('must provide a '.static::$table.' record!');
            }
            $this->record = $recordorid;
        } else {
            $this->record = $DB->get_record(static::$table, array('id' => $recordorid), '*', MUST_EXIST);
        }

        $this->options = $options;
    }

    public function __get($name) {
        if (isset($this->record->$name)) {
            return $this->record->$name;
        }
        return null;
    }

    public function __isset($name) {
        return isset($this->record->$name);
    }

    /**
     * Create a new object in the db.
     */
    public static function create(\stdClass $data) {
        global $DB, $USER;

        $class = get_called_class();

        $data->id = $DB->insert_record(static::$table, $data);
        $record = $DB->get_record(static::$table, array('id' => $data->id));

        // Return an instance of the gallery class.
        $object = new $class($record);
        $tags = !empty($data->tags) ? $data->tags : '';
        $object->set_tags($tags);
        return $object;
    }

    public function delete($options = array()) {
        global $DB;

        $DB->delete_records(static::$table, array('id' => $this->record->id));

        return true;
    }

    abstract public function get_context();
    abstract public function user_can_remove();

    /**
     * Get a copy of the record as it appears in the db.
     *
     * @returns stdClass The item record.
     */
    public function get_record() {
        return clone $this->record;
    }

    /**
     * Retrieve the current set of tags for this object.
     *
     * @access public
     * @return string CSV list of tags.
     */
    public function get_tags() {
        return implode(', ', \core_tag_tag::get_item_tags_array('mod_mediagallery', static::$table, $this->id, null, 0, false));
    }

    public static function get_tags_possible() {
        global $DB;
        $sql = "SELECT tg.name
                FROM {tag_instance} ti
                JOIN {tag} tg ON tg.id = ti.tagid
                WHERE ti.itemtype = :recordtype
                GROUP BY tg.name";
        $params = array();
        $params['recordtype'] = static::$table;
        $records = $DB->get_records_sql($sql, $params);

        $result = array();
        foreach ($records as $record) {
            $result[] = $record->name;
        }
        return $result;
    }

    public function set_tags($tags = null) {
        if (is_null($tags)) {
            $tags = $this->tags;
        }

        // Support both an array list, or a csv list (CSV comes from forms, array from theBox).
        if (!is_array($tags)) {
            $list = explode(',', $tags);
        } else {
            $list = $tags;
        }
        $list = array_filter($list);

        $ctx = $this->get_context();
        $tagcontext = !empty($ctx) ? $ctx : \context_system::instance();
        \core_tag_tag::set_item_tags('mod_mediagallery', static::$table, $this->id, $tagcontext, $list);
    }

    public function set_option($key, $value) {
        $this->options[$key] = $value;
    }

    /**
     * Update the item based on object record.
     *
     * @param $data stdClass Object with all the details on the item (likely from the item_form.php form).
     */
    public function update($data) {
        global $DB;

        if ($DB->update_record(static::$table, $data)) {
            $this->record = (object)array_replace((array)$this->record, (array)$data);

            $this->set_tags();

            if (empty($data->noevent)) {
                $params = array(
                    'context' => $this->get_context(),
                    'objectid' => $this->id,
                );
                if (isset($data->nosync) && $data->nosync) {
                    $params['other']['nosync'] = true;
                }
                $class = get_called_class();
                $eventclass = str_replace(__NAMESPACE__, __NAMESPACE__.'\event', $class).'_updated';
                $event = $eventclass::create($params);
                $event->add_record_snapshot(static::$table, $this->get_record());
                $event->trigger();
            }

            return true;
        }
        return false;
    }

    public function is_thebox_creator_or_agent($userid = null) {
        global $DB, $USER;

        $username = $USER->username;
        if (is_null($userid)) {
            $userid = $USER->id;
        } else if ($userid != $USER->id) {
            $username = $DB->get_field('user', 'username', array('id' => $userid));
        }

        if ($username == $this->creator || $this->creator == 'z9999999') {
            return true;
        }

        if (empty($this->agents)) {
            return false;
        }

        // We're not the creator, but the item has agents assigned. Are we one of them?
        $agents = explode(',', $this->agents);
        return in_array($username, $agents);
    }

    /**
     * Checks for the assignsubmission_mediagallery plugin.
     *
     * @static
     * @access public
     * @return bool
     */
    public static function is_assignsubmission_mediagallery_installed() {
        global $CFG;
        $installed = get_config('assignsubmission_mediagallery', 'version');
        $path = $CFG->dirroot.'/mod/assign/submission/mediagallery/locallib.php';
        return $installed && file_exists($path);
    }
}
