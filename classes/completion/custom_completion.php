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

declare(strict_types=1);

namespace mod_mediagallery\completion;

use core_completion\activity_custom_completion;

/**
 * Activity custom completion subclass for the Media collection activity.
 *
 * Class for defining mod_mediagallery's custom completion rules and fetching the completion statuses
 * of the custom completion rules for a given Media collection instance and a user.
 *
 * @package   mod_mediagallery
 * @copyright 2023 Te Pūkenga – New Zealand Institute of Skills and Technology
 * @author    James Calder
 * @copyright based on work by Simey Lameze <simey@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {

    /**
     * Fetches the completion state for a given completion rule.
     *
     * @param string $rule The completion rule.
     * @return int The completion state.
     */
    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);

        $userid = $this->userid;
        $collectionid = $this->cm->instance;

        if (!$collection = $DB->get_record('mediagallery', ['id' => $collectionid])) {
            throw new \moodle_exception('Unable to find Media collection with id ' . $collectionid);
        }

        $itemcountparams = ['collectionid' => $collectionid, 'userid' => $userid];
        $itemcountsql =
           "SELECT COUNT(*)
            FROM {mediagallery_item} mgi
            JOIN {mediagallery_gallery} mgg ON mgg.id = mgi.galleryid
            WHERE mgg.instanceid = :collectionid AND mgi.userid = :userid";

        $commentcountparams = ['collectionid' => $collectionid, 'userid' => $userid];
        $commentcountsql =
           "SELECT COUNT(*)
            FROM {comments} c
            LEFT JOIN {mediagallery_item} mgi
                ON c.component = 'mod_mediagallery' AND c.commentarea = 'item' AND mgi.id = c.itemid
            JOIN {mediagallery_gallery} mgg ON c.component = 'mod_mediagallery'
                AND (c.commentarea = 'gallery' AND mgg.id = c.itemid
                    OR c.commentarea = 'item' AND mgg.id = mgi.galleryid)
            WHERE c.component = 'mod_mediagallery' AND mgg.instanceid = :collectionid AND c.userid = :userid";

        if ($rule == 'completiongalleries') {
            $status = $collection->completiongalleries <=
                $DB->count_records('mediagallery_gallery', ['instanceid' => $collectionid, 'userid' => $userid]);
        } else if ($rule == 'completionitems') {
            $status = $collection->completionitems <= $DB->get_field_sql($itemcountsql, $itemcountparams);
        } else if ($rule == 'completioncomments') {
            $status = $collection->completioncomments <= $DB->get_field_sql($commentcountsql, $commentcountparams);
        }

        return $status ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * Fetch the list of custom completion rules that this module defines.
     *
     * @return string[]
     */
    public static function get_defined_custom_rules(): array {
        return [
            'completiongalleries',
            'completionitems',
            'completioncomments',
        ];
    }

    /**
     * Returns an associative array of the descriptions of custom completion rules.
     *
     * @return string[]
     */
    public function get_custom_rule_descriptions(): array {
        $completiongalleries = $this->cm->customdata['customcompletionrules']['completiongalleries'] ?? 0;
        $completionitems = $this->cm->customdata['customcompletionrules']['completionitems'] ?? 0;
        $completioncomments = $this->cm->customdata['customcompletionrules']['completioncomments'] ?? 0;

        return [
            'completiongalleries' => get_string('completiondetail:galleries', 'mediagallery', $completiongalleries),
            'completionitems' => get_string('completiondetail:items', 'mediagallery', $completionitems),
            'completioncomments' => get_string('completiondetail:comments', 'mediagallery', $completioncomments),
        ];
    }

    /**
     * Returns an array of all completion rules, in the order they should be displayed to users.
     *
     * @return string[]
     */
    public function get_sort_order(): array {
        return [
            'completionview',
            'completiongalleries',
            'completionitems',
            'completioncomments',
        ];
    }
}
