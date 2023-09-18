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
 * Privacy Subsystem implementation for mod_mediagallery.
 *
 * @package    mod_mediagallery
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_mediagallery\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\deletion_criteria;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Implementation of the privacy subsystem plugin provider for the mediagallery activity module.
 *
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    // This plugin stores personal data.
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    // This plugin is a core_user_data_provider.
    \core_privacy\local\request\plugin\provider,
    // This plugin has some sitewide user preferences to export.
    \core_privacy\local\request\user_preference_provider {

    use subcontext_info;

    /**
     * Return the fields which contain personal data.
     *
     * @param collection $items a reference to the collection to use to store the metadata.
     * @return collection the updated collection of metadata items.
     */
    public static function get_metadata(collection $items) : collection {

        $items->add_database_table(
            'mediagallery',
            [
                'id' => 'privacy:metadata:mediagallery:id',
                'name' => 'privacy:metadata:mediagallery:name',
                'userid' => 'privacy:metadata:mediagallery:userid',
            ],
            'privacy:metadata:mediagallery'
        );

        $items->add_database_table(
            'mediagallery_gallery',
            [
                'instanceid' => 'privacy:metadata:mediagallery_gallery:instanceid',
                'name' => 'privacy:metadata:mediagallery_gallery:name',
                'userid' => 'privacy:metadata:mediagallery_gallery:userid',
                'groupid' => 'privacy:metadata:mediagallery_gallery:groupid',
            ],
            'privacy:metadata:mediagallery_gallery'
        );

        $items->add_database_table(
            'mediagallery_item',
            [
                'galleryid' => 'privacy:metadata:mediagallery_item:galleryid',
                'userid' => 'privacy:metadata:mediagallery_item:userid',
                'caption' => 'privacy:metadata:mediagallery_item:caption',
                'description' => 'privacy:metadata:mediagallery_item:description',
                'moralrights' => 'privacy:metadata:mediagallery_item:moralrights',
                'originalauthor' => 'privacy:metadata:mediagallery_item:originalauthor',
                'productiondate' => 'privacy:metadata:mediagallery_item:productiondate',
                'medium' => 'privacy:metadata:mediagallery_item:medium',
                'publisher' => 'privacy:metadata:mediagallery_item:publisher',
                'broadcaster' => 'privacy:metadata:mediagallery_item:broadcaster',
                'reference' => 'privacy:metadata:mediagallery_item:reference',
                'externalurl' => 'privacy:metadata:mediagallery_item:externalurl',
                'timecreated' => 'privacy:metadata:mediagallery_item:timecreated',
            ],
            'privacy:metadata:mediagallery_item'
        );

        $items->add_database_table(
            'mediagallery_userfeedback',
            [
                'itemid' => 'privacy:metadata:mediagallery_userfeedback:itemid',
                'userid' => 'privacy:metadata:mediagallery_userfeedback:userid',
                'liked' => 'privacy:metadata:mediagallery_userfeedback:liked',
                'rating' => 'privacy:metadata:mediagallery_userfeedback:rating',
            ],
            'privacy:metadata:mediagallery_userfeedback'
        );

        $items->add_subsystem_link('core_files', [], 'privacy:metadata:core_files');
        $items->add_subsystem_link('core_comment', [], 'privacy:metadata:core_comments');
        $items->add_subsystem_link('core_tag', [], 'privacy:metadata:core_tag');

        $items->add_user_preference('mod_mediagallery_mediasize', 'privacy:metadata:preference:mediasize');

        return $items;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid the userid.
     * @return contextlist the list of contexts containing user info for the user.
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {
        // Fetch all mediagallery comments.
        $sql = "SELECT c.id
                FROM {context} c
                JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                JOIN {modules} m ON m.id = cm.module AND m.name = 'mediagallery'
                JOIN {mediagallery} mg ON mg.id = cm.instance
                LEFT JOIN {mediagallery_gallery} mgg ON mgg.instanceid = mg.id
                LEFT JOIN {mediagallery_item} mgi ON mgi.galleryid = mgg.id
                LEFT JOIN {mediagallery_userfeedback} mgu ON mgu.itemid = mgi.id
                LEFT JOIN {comments} comg ON comg.commentarea = 'gallery' AND comg.itemid = mgg.id
                LEFT JOIN {comments} comi ON comi.commentarea = 'item' AND comi.itemid = mgi.id
                WHERE mgg.userid = :userid1 OR mgi.userid = :userid2 OR mgu.userid = :userid3";

        $params = [
            'contextlevel' => CONTEXT_MODULE,
            'userid1' => $userid,
            'userid2' => $userid,
            'userid3' => $userid,
        ];
        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Export personal data for the given approved_contextlist. User and context information is contained within the contextlist.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for export.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        $sql = "SELECT c.id AS contextid,
                       mg.id,
                       cm.id AS cmid
                FROM {context} c
                JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                JOIN {modules} m ON m.id = cm.module AND m.name = 'mediagallery'
                JOIN {mediagallery} mg ON mg.id = cm.instance
                JOIN {mediagallery_gallery} g ON g.instanceid = mg.id
                LEFT JOIN {mediagallery_item} i ON i.galleryid = g.id
                LEFT JOIN {mediagallery_userfeedback} u ON u.itemid = i.id AND u.userid = :userid1
                WHERE c.id {$contextsql} AND (
                    g.userid = :userid2 OR i.userid = :userid3 OR u.id IS NOT NULL
                )
                GROUP BY c.id, mg.id, cm.id";

        $params = [
            'userid1' => $user->id,
            'userid2' => $user->id,
            'userid3' => $user->id,
            'contextlevel' => CONTEXT_MODULE
        ] + $contextparams;
        $collections = $DB->get_recordset_sql($sql, $params);

        $mappings = [];

        foreach ($collections as $collection) {
            $mappings[$collection->id] = $collection->contextid;
            $context = \context_module::instance($collection->cmid);

            $data = helper::get_context_data($context, $user);
            writer::with_context($context)->export_data([], $data);
            helper::export_context_files($context, $user);
        }
        $collections->close();

        if (!empty($mappings)) {
            static::export_gallery_data($user->id, $mappings);
            static::export_all_items($user->id, $mappings);
        }
    }

    protected static function export_gallery_data(int $userid, array $mappings) {
        global $DB;
        // Find all galleries the user owns, or has added items to.
        list($collinsql, $collparams) = $DB->get_in_or_equal(array_keys($mappings), SQL_PARAMS_NAMED);

        $sql = "SELECT g.*
                FROM {mediagallery} m
                JOIN {mediagallery_gallery} g ON g.instanceid = m.id
                LEFT JOIN {mediagallery_item} i ON i.galleryid = g.id
                LEFT JOIN {mediagallery_userfeedback} u ON u.itemid = i.id AND u.userid = :userid1
                WHERE m.id {$collinsql} AND (
                    g.userid = :userid2 OR i.userid = :userid3 OR u.id IS NOT NULL
                )";

        $params = ['userid1' => $userid, 'userid2' => $userid, 'userid3' => $userid] + $collparams;
        $galleries = $DB->get_recordset_sql($sql, $params);

        foreach ($galleries as $gallery) {
            $context = \context::instance_by_id($mappings[$gallery->instanceid]);

            $gallerydata = (object) [
                'name' => format_string($gallery->name, true),
                'creator_was_you' => transform::yesno($gallery->userid == $userid),
            ];

            $galleryarea = static::get_gallery_area($gallery);
            writer::with_context($context)
                ->export_data($galleryarea, $gallerydata);

            \core_tag\privacy\provider::export_item_tags($userid, $context, $galleryarea, 'mod_mediagallery', 'gallery',
                                                        $gallery->id);
        }
        $galleries->close();
    }

    protected static function export_all_items(int $userid, array $mappings) {
        global $DB;
        // Find all galleries the user owns, or has added items to.
        list($collinsql, $collparams) = $DB->get_in_or_equal(array_keys($mappings), SQL_PARAMS_NAMED);

        $sql = "SELECT g.id AS id,
                       m.id AS mediagalleryid,
                       g.name
                FROM {mediagallery} m
                JOIN {mediagallery_gallery} g ON g.instanceid = m.id
                LEFT JOIN {mediagallery_item} i ON i.galleryid = g.id
                LEFT JOIN {mediagallery_userfeedback} u ON u.itemid = i.id AND u.userid = :userid1
                WHERE m.id {$collinsql} AND (
                    g.userid = :userid2 OR i.userid = :userid3 OR u.id IS NOT NULL
                )
                GROUP BY m.id, g.id, g.name";
        $params = ['userid1' => $userid, 'userid2' => $userid, 'userid3' => $userid] + $collparams;
        $galleries = $DB->get_records_sql($sql, $params);
        foreach ($galleries as $gallery) {
            $context = \context::instance_by_id($mappings[$gallery->mediagalleryid]);
            static::export_all_items_in_gallery($userid, $context, $gallery);
        }
    }

    /**
     * Store all information about all item that we have detected this user to has uploaded or provided feedback to.
     *
     * @param   int         $userid The userid of the user whose data is to be exported.
     * @param   \context    $context The instance of the mediagallery context.
     * @param   \stdClass   $gallery The gallery whose data is being exported.
     */
    protected static function export_all_items_in_gallery($userid, $context, $gallery) {
        global $DB;
        $sql = "SELECT i.*
                FROM {mediagallery_gallery} g
                JOIN {mediagallery_item} i ON i.galleryid = g.id
                LEFT JOIN {mediagallery_userfeedback} u ON u.itemid = i.id AND u.userid = :userid1
                WHERE g.id = :galleryid AND (
                    i.userid = :userid2 OR u.id IS NOT NULL
                )";
        $params = [
          'galleryid' => $gallery->id,
          'userid1' => $userid,
          'userid2' => $userid,
        ];
        $items = $DB->get_records_sql($sql, $params);

        $galleryarea = static::get_gallery_area($gallery);
        $itemdata = [];
        foreach ($items as $item) {
            $itemdata[] = static::export_item_data($userid, $context, $galleryarea, $item);
        }

        $itemarea = array_merge($galleryarea, static::get_item_area());
        writer::with_context($context)
            ->export_data($itemarea, (object)$itemdata);
    }

    /**
     * Export all data in the item.
     *
     * @param   int         $userid The userid of the user whose data is to be exported.
     * @param   \context    $context The instance of the forum context.
     * @param   array       $galleryarea The subcontext of the gallery.
     * @param   \stdClass   $item The post structure and all of its children
     */
    protected static function export_item_data(int $userid, \context $context, $galleryarea, $item) {
        $itemdata = (object) [
            'id' => $item->id,
            'caption' => format_string($item->caption, true),
            'timecreated' => transform::datetime($item->timecreated),
            'creator_was_you' => transform::yesno($item->userid == $userid),
        ];

        $itemarea = array_merge($galleryarea, static::get_item_area());
        writer::with_context($context)
            ->export_area_files($itemarea, 'mod_mediagallery', 'item', $item->id)
            ->export_area_files($itemarea, 'mod_mediagallery', 'lowres', $item->id)
            ->export_area_files($itemarea, 'mod_mediagallery', 'thumbnail', $item->id);

        \core_tag\privacy\provider::export_item_tags($userid, $context, $itemarea, 'mod_mediagallery', 'item', $item->id);
        return $itemdata;
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context the context to delete in.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }

        $instanceid = $DB->get_field('course_modules', 'instance', ['id' => $context->instanceid], MUST_EXIST);

        $DB->delete_records_select('mediagallery_userfeedback',
            "itemid IN (
                SELECT i.id
                FROM {mediagallery_item} i
                JOIN {mediagallery_gallery} g ON i.galleryid = g.id
                WHERE g.instanceid = :instanceid
            )", ['instanceid' => $instanceid]);

        $DB->delete_records_select('mediagallery_item',
            "galleryid IN (
                SELECT id
                FROM {mediagallery_gallery} g
                WHERE g.instanceid = :instanceid
            )", ['instanceid' => $instanceid]);

        $DB->delete_records('mediagallery_gallery', ['instanceid' => $instanceid]);

        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_mediagallery', 'item');
        $fs->delete_area_files($context->id, 'mod_mediagallery', 'lowres');
        $fs->delete_area_files($context->id, 'mod_mediagallery', 'thumbnail');

        \core_comment\privacy\provider::delete_comments_for_all_users($context, 'mod_mediagallery', 'gallery');
        \core_comment\privacy\provider::delete_comments_for_all_users($context, 'mod_mediagallery', 'item');

        \core_tag\privacy\provider::delete_item_tags($context, 'mod_mediagallery', 'mediagallery_gallery');
        \core_tag\privacy\provider::delete_item_tags($context, 'mod_mediagallery', 'mediagallery_item');
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for deletion.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        $fs = get_file_storage();

        $galleryidsql = "SELECT g.id
                         FROM {mediagallery_gallery} g
                         WHERE userid = :guserid AND instanceid = :instanceid1";
        $itemidsql = "SELECT i.id
                      FROM {mediagallery_item} i
                      WHERE galleryid IN ($galleryidsql)
                      OR userid = :iuserid AND galleryid IN (
                        SELECT id
                        FROM {mediagallery_gallery}
                        WHERE instanceid = :instanceid2
                      )";
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $instanceid = $DB->get_field('course_modules', 'instance', ['id' => $context->instanceid], MUST_EXIST);

            $gparams = ['guserid' => $userid, 'instanceid1' => $instanceid];
            $iparams = ['guserid' => $userid, 'instanceid1' => $instanceid,
                        'iuserid' => $userid, 'instanceid2' => $instanceid];

            $fs->delete_area_files_select($context->id, 'mod_mediagallery', 'item', "IN ($itemidsql)", $iparams);
            $fs->delete_area_files_select($context->id, 'mod_mediagallery', 'lowres', "IN ($itemidsql)", $iparams);
            $fs->delete_area_files_select($context->id, 'mod_mediagallery', 'thumbnail', "IN ($itemidsql)", $iparams);

            \core_tag\privacy\provider::delete_item_tags_select($context, 'mod_mediagallery', 'gallery',
                "IN ($galleryidsql)", $gparams);
            \core_tag\privacy\provider::delete_item_tags_select($context, 'mod_mediagallery', 'item',
                "IN ($itemidsql)", $iparams);

            // We delete these last as the deletes above depend on these records.

            $DB->delete_records_select('mediagallery_userfeedback', "itemid IN ($itemidsql)", $iparams);
            $DB->delete_records_select('mediagallery_item', "id IN ($itemidsql)", $iparams);
            $DB->delete_records('mediagallery_gallery', ['userid' => $userid, 'instanceid' => $instanceid]);

            \core_comment\privacy\provider::delete_comments_for_all_users_select($context, 'mod_mediagallery',
                                                                    'gallery', "IN ($galleryidsql)", $gparams);
            \core_comment\privacy\provider::delete_comments_for_all_users_select($context, 'mod_mediagallery',
                                                                    'item', "IN ($itemidsql)", $iparams);
        }

        \core_comment\privacy\provider::delete_comments_for_user($contextlist, 'mod_mediagallery', 'gallery');
        \core_comment\privacy\provider::delete_comments_for_user($contextlist, 'mod_mediagallery', 'item');

    }

    public static function export_user_preferences(int $userid) {
        $pref = get_user_preferences('mod_mediagallery_mediasize', \mod_mediagallery\output\gallery\renderable::MEDIASIZE_MD,
                                    $userid);
        $string = 'mediasizemd';
        if ($pref == \mod_mediagallery\output\gallery\renderable::MEDIASIZE_SM) {
            $string = 'mediasizesm';
        } else if ($pref == \mod_mediagallery\output\gallery\renderable::MEDIASIZE_LG) {
            $string = 'mediasizelg';
        }
        writer::export_user_preference('mod_mediagallery', 'mod_mediagallery_mediasize', $pref,
                                        get_string($string, 'mod_mediagallery'));
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     *
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!is_a($context, \context_module::class)) {
            return;
        }

        $params = [
            'instanceid' => $context->instanceid,
        ];

        $collectionsql = "SELECT userid
                          FROM {mediagallery}
                          WHERE id = :instanceid";

        $userlist->add_from_sql('userid', $collectionsql, $params);

        $gallerysql = "SELECT userid
                       FROM {mediagallery_gallery}
                       WHERE instanceid = :instanceid";

        $userlist->add_from_sql('userid', $gallerysql, $params);

        $itemsql = "SELECT i.userid
                    FROM {mediagallery_item} i
                    JOIN {mediagallery_gallery} g ON i.galleryid = g.id
                    WHERE g.instanceid = :instanceid";

        $userlist->add_from_sql('userid', $itemsql, $params);
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }
        $instanceid = self::get_mediagallery_id_from_context($context);
        $userids = $userlist->get_userids();

        if (empty($instanceid)) {
            return;
        }

        list($ginsql, $ginparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'guserid');
        list($iinsql, $iinparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'iuserid');

        $fs = get_file_storage();

        $galleryidsql = "SELECT g.id
                         FROM {mediagallery_gallery} g
                         WHERE userid $ginsql AND instanceid = :instanceid1";
        $itemidsql = "SELECT i.id
                      FROM {mediagallery_item} i
                      WHERE galleryid IN ($galleryidsql)
                      OR userid $iinsql AND galleryid IN (
                        SELECT id
                        FROM {mediagallery_gallery}
                        WHERE instanceid = :instanceid2
                      )";

        $gparams = array_merge(['instanceid1' => $instanceid], $ginparams);
        $iparams = array_merge(['instanceid1' => $instanceid], $ginparams,
                               ['instanceid2' => $instanceid], $iinparams);

        $fs->delete_area_files_select($context->id, 'mod_mediagallery', 'item', "IN ($itemidsql)", $iparams);
        $fs->delete_area_files_select($context->id, 'mod_mediagallery', 'lowres', "IN ($itemidsql)", $iparams);
        $fs->delete_area_files_select($context->id, 'mod_mediagallery', 'thumbnail', "IN ($itemidsql)", $iparams);

        \core_tag\privacy\provider::delete_item_tags_select($context, 'mod_mediagallery', 'gallery',
            "IN ($galleryidsql)", $gparams);
        \core_tag\privacy\provider::delete_item_tags_select($context, 'mod_mediagallery', 'item',
            "IN ($itemidsql)", $iparams);

        // We delete these last as the deletes above depend on these records.

        $DB->delete_records_select('mediagallery_userfeedback', "itemid IN ($itemidsql)", $iparams);
        $DB->delete_records_select('mediagallery_item', "id IN ($itemidsql)", $iparams);
        $DB->delete_records_select('mediagallery_gallery', "userid $ginsql AND instanceid = :instanceid1", $gparams);

        \core_comment\privacy\provider::delete_comments_for_all_users_select($context, 'mod_mediagallery',
                                                                    'gallery', "IN ($galleryidsql)", $gparams);
        \core_comment\privacy\provider::delete_comments_for_all_users_select($context, 'mod_mediagallery',
                                                                    'item', "IN ($itemidsql)", $iparams);
        \core_comment\privacy\provider::delete_comments_for_users($userlist, 'mod_mediagallery', 'gallery');
        \core_comment\privacy\provider::delete_comments_for_users($userlist, 'mod_mediagallery', 'item');
    }

    protected static function get_mediagallery_id_from_context(\context_module $context) {
        $cm = get_coursemodule_from_id('mediagallery', $context->instanceid);
        return $cm ? (int) $cm->instance : 0;
    }

}
