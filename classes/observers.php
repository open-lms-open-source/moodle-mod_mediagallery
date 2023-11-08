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
 * Event observers.
 *
 * @package    mod_mediagallery
 * @copyright  2023 Te Pūkenga – New Zealand Institute of Skills and Technology
 * @author     James Calder
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_mediagallery;

/**
 * Event observers.
 *
 * @package    mod_mediagallery
 * @copyright  2023 Te Pūkenga – New Zealand Institute of Skills and Technology
 * @author     James Calder
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observers {

    // TODO: Ignore if course reset in progress?

    /**
     * A comment was created.
     *
     * @param \core\event\base $event The event.
     * @return void
     */
    public static function comment_created($event): void {
        // Update completion state.
        if ($event->userid) {
            $cm = get_coursemodule_from_id('mediagallery', $event->contextinstanceid);
            $completion = new \completion_info(get_course($cm->course));
            if ($completion->is_enabled($cm) == COMPLETION_TRACKING_AUTOMATIC) {
                $completion->update_state($cm, COMPLETION_COMPLETE, $event->userid);
            }
        }
    }

    /**
     * A comment was deleted.
     *
     * @param \core\event\base $event The event.
     * @return void
     */
    public static function comment_deleted($event): void {
        // Update completion state.
        if ($event->userid) {
            $cm = get_coursemodule_from_id('mediagallery', $event->contextinstanceid);
            $completion = new \completion_info(get_course($cm->course));
            if ($completion->is_enabled($cm) == COMPLETION_TRACKING_AUTOMATIC) {
                $completion->update_state($cm, COMPLETION_INCOMPLETE, $event->userid);
            }
        }
    }

}
