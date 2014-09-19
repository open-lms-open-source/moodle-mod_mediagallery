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

abstract class base {

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

    /**
     * Create a new object in the db.
     */
    public static function create(\stdClass $data) {
        global $DB, $USER;

        $class = get_called_class();

        $data->id = $DB->insert_record(static::$table, $data);

        // Return an instance of the gallery class.
        return new $class($data);
    }

    public function delete() {
        global $DB;

        $DB->delete_records(static::$table, array('id' => $this->record->id));

        return true;
    }

    /**
     * Get a copy of the record as it appears in the db.
     *
     * @returns stdClass The item record.
     */
    public function get_record() {
        return clone $this->record;
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

            $params = array(
                'context' => $this->get_context(),
                'objectid' => $this->id,
            );
            $class = get_called_class();
            $eventclass = str_replace(__NAMESPACE__, __NAMESPACE__.'\event', $class).'_updated';
            $event = $eventclass::create($params);
            $event->add_record_snapshot(static::$table, $this->get_record());
            $event->trigger();

            return true;
        }
        return false;
    }

}
