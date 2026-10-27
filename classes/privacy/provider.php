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
        $contextlist = new contextlist();

        $params = [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'mediagallery',
            'userid' => $userid,
        ];
        $from = "FROM {context} c
                 JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                 JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                 JOIN {mediagallery} mg ON mg.id = cm.instance";

        // Collections the user created.
        $contextlist->add_from_sql("SELECT c.id $from WHERE mg.userid = :userid", $params);

        // Galleries the user owns.
        $contextlist->add_from_sql("SELECT c.id $from
                                      JOIN {mediagallery_gallery} g ON g.instanceid = mg.id
                                     WHERE g.userid = :userid", $params);

        // Items the user added.
        $contextlist->add_from_sql("SELECT c.id $from
                                      JOIN {mediagallery_gallery} g ON g.instanceid = mg.id
                                      JOIN {mediagallery_item} i ON i.galleryid = g.id
                                     WHERE i.userid = :userid", $params);

        // Items the user liked or rated.
        $contextlist->add_from_sql("SELECT c.id $from
                                      JOIN {mediagallery_gallery} g ON g.instanceid = mg.id
                                      JOIN {mediagallery_item} i ON i.galleryid = g.id
                                      JOIN {mediagallery_userfeedback} f ON f.itemid = i.id
                                     WHERE f.userid = :userid", $params);

        // Comments the user made on galleries and items.
        $contextlist->add_from_sql("SELECT contextid
                                      FROM {comments}
                                     WHERE component = :component AND userid = :userid",
            ['component' => 'mod_mediagallery', 'userid' => $userid]);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_module) {
            return;
        }
        $instanceid = self::get_mediagallery_id_from_context($context);
        if (empty($instanceid)) {
            return;
        }
        $params = ['instanceid' => $instanceid];

        $userlist->add_from_sql('userid', "SELECT userid FROM {mediagallery} WHERE id = :instanceid", $params);

        $userlist->add_from_sql('userid', "SELECT userid FROM {mediagallery_gallery} WHERE instanceid = :instanceid",
            $params);

        $sql = "SELECT i.userid
                  FROM {mediagallery_item} i
                  JOIN {mediagallery_gallery} g ON g.id = i.galleryid
                 WHERE g.instanceid = :instanceid";
        $userlist->add_from_sql('userid', $sql, $params);

        $sql = "SELECT f.userid
                  FROM {mediagallery_userfeedback} f
                  JOIN {mediagallery_item} i ON i.id = f.itemid
                  JOIN {mediagallery_gallery} g ON g.id = i.galleryid
                 WHERE g.instanceid = :instanceid";
        $userlist->add_from_sql('userid', $sql, $params);

        \core_comment\privacy\provider::get_users_in_context_from_sql($userlist, 'cg', 'mod_mediagallery', 'gallery',
            $context->id);
        \core_comment\privacy\provider::get_users_in_context_from_sql($userlist, 'ci', 'mod_mediagallery', 'item',
            $context->id);
    }

    /**
     * Export personal data for the given approved_contextlist. User and context information is contained within the contextlist.
     *
     * Comments are exported through the core comment API, which exports the comments of the current user. Data requests
     * run as the user the request is for.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for export.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $instanceid = self::get_mediagallery_id_from_context($context);
            if (empty($instanceid)) {
                continue;
            }

            $created = $DB->record_exists('mediagallery', ['id' => $instanceid, 'userid' => $user->id]);
            $exported = static::export_galleries($user->id, $context, $instanceid);
            if (!$created && !$exported) {
                continue;
            }

            $data = helper::get_context_data($context, $user);
            if ($created) {
                $data->creator_was_you = transform::yesno(true);
            }
            writer::with_context($context)->export_data([], $data);
            helper::export_context_files($context, $user);
        }
    }

    /**
     * Export the galleries and items in one collection that hold data about a user.
     *
     * That covers galleries the user owns or commented on, and items the user added, liked, rated or commented on.
     *
     * @param int $userid
     * @param \context_module $context The collection's context.
     * @param int $instanceid The collection's id.
     * @return bool True if anything was exported.
     */
    protected static function export_galleries(int $userid, \context_module $context, int $instanceid): bool {
        global $DB;

        $params = ['instanceid' => $instanceid, 'userid' => $userid];
        $itemfrom = "FROM {mediagallery_item} i
                     JOIN {mediagallery_gallery} g ON g.id = i.galleryid
                    WHERE g.instanceid = :instanceid";

        $itemids = array_merge(
            $DB->get_fieldset_sql("SELECT i.id $itemfrom AND i.userid = :userid", $params),
            $DB->get_fieldset_sql("SELECT i.id $itemfrom AND i.id IN (
                                       SELECT itemid FROM {mediagallery_userfeedback} WHERE userid = :userid
                                   )", $params),
            static::get_commented_ids($userid, $context, 'item')
        );
        $items = $itemids ? $DB->get_records_list('mediagallery_item', 'id', array_unique($itemids), 'id') : [];

        $galleryids = array_merge(
            $DB->get_fieldset_select('mediagallery_gallery', 'id', 'instanceid = :instanceid AND userid = :userid', $params),
            array_column($items, 'galleryid'),
            static::get_commented_ids($userid, $context, 'gallery')
        );
        if (empty($galleryids)) {
            return false;
        }
        // Comment ids come from the comments table, so only keep galleries in this collection.
        $galleries = $DB->get_records_list('mediagallery_gallery', 'id', array_unique($galleryids), 'id');
        $galleries = array_filter($galleries, fn($gallery) => $gallery->instanceid == $instanceid);

        $feedback = $DB->get_records_list('mediagallery_userfeedback', 'itemid', array_keys($items));
        $userfeedback = [];
        foreach ($feedback as $record) {
            if ($record->userid == $userid) {
                $userfeedback[$record->itemid] = $record;
            }
        }

        foreach ($galleries as $gallery) {
            $galleryarea = static::get_gallery_area($gallery);
            writer::with_context($context)->export_data($galleryarea, (object) [
                'name' => format_string($gallery->name, true),
                'creator_was_you' => transform::yesno($gallery->userid == $userid),
            ]);
            \core_tag\privacy\provider::export_item_tags($userid, $context, $galleryarea, 'mod_mediagallery',
                'mediagallery_gallery', $gallery->id);
            \core_comment\privacy\provider::export_comments($context, 'mod_mediagallery', 'gallery', $gallery->id,
                $galleryarea);

            $itemarea = array_merge($galleryarea, static::get_item_area());
            $itemdata = [];
            foreach ($items as $item) {
                if ($item->galleryid == $gallery->id) {
                    $itemdata[] = static::export_item_data($userid, $context, $itemarea, $item,
                        $userfeedback[$item->id] ?? null);
                }
            }
            if ($itemdata) {
                writer::with_context($context)->export_data($itemarea, (object) $itemdata);
            }
        }

        return true;
    }

    /**
     * Get the ids of the galleries or items a user commented on in a context.
     *
     * @param int $userid
     * @param \context $context
     * @param string $commentarea Either 'gallery' or 'item'.
     * @return int[]
     */
    protected static function get_commented_ids(int $userid, \context $context, string $commentarea): array {
        global $DB;
        return $DB->get_fieldset_select('comments', 'itemid',
            'contextid = :contextid AND component = :component AND commentarea = :commentarea AND userid = :userid', [
                'contextid' => $context->id,
                'component' => 'mod_mediagallery',
                'commentarea' => $commentarea,
                'userid' => $userid,
            ]);
    }

    /**
     * Export one item's data, files, tags and comments.
     *
     * Content and files are only exported for items the user added. For other items, only the caption and the user's
     * own like, rating and comments are exported.
     *
     * @param int $userid
     * @param \context $context
     * @param array $itemarea The subcontext items are exported to.
     * @param \stdClass $item The item record.
     * @param \stdClass|null $feedback The user's like or rating of the item, if any.
     * @return \stdClass The item data.
     */
    protected static function export_item_data(int $userid, \context $context, array $itemarea, \stdClass $item,
            ?\stdClass $feedback): \stdClass {
        $ownitem = $item->userid == $userid;
        $itemdata = (object) [
            'id' => $item->id,
            'caption' => format_string($item->caption, true),
            'timecreated' => transform::datetime($item->timecreated),
            'creator_was_you' => transform::yesno($ownitem),
        ];

        if ($ownitem) {
            $itemdata->description = $item->description;
            foreach (['originalauthor', 'medium', 'publisher', 'broadcaster', 'reference', 'externalurl'] as $field) {
                $itemdata->$field = $item->$field;
            }
            $itemdata->moralrights = transform::yesno($item->moralrights);
            if (!empty($item->productiondate)) {
                $itemdata->productiondate = transform::date($item->productiondate);
            }

            writer::with_context($context)
                ->export_area_files($itemarea, 'mod_mediagallery', 'item', $item->id)
                ->export_area_files($itemarea, 'mod_mediagallery', 'lowres', $item->id)
                ->export_area_files($itemarea, 'mod_mediagallery', 'thumbnail', $item->id);
            \core_tag\privacy\provider::export_item_tags($userid, $context, $itemarea, 'mod_mediagallery',
                'mediagallery_item', $item->id);
        }

        if ($feedback) {
            $itemdata->liked_by_you = transform::yesno($feedback->liked);
            if (!is_null($feedback->rating)) {
                $itemdata->your_rating = $feedback->rating;
            }
        }

        \core_comment\privacy\provider::export_comments($context, 'mod_mediagallery', 'item', $item->id,
            array_merge($itemarea, [$item->id]));

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

        $instanceid = self::get_mediagallery_id_from_context($context);
        if (empty($instanceid)) {
            return;
        }

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
        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_module) {
                static::delete_user_data($context, [$userid]);
            }
        }

        \core_comment\privacy\provider::delete_comments_for_user($contextlist, 'mod_mediagallery', 'gallery');
        \core_comment\privacy\provider::delete_comments_for_user($contextlist, 'mod_mediagallery', 'item');
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }

        static::delete_user_data($context, $userlist->get_userids());

        \core_comment\privacy\provider::delete_comments_for_users($userlist, 'mod_mediagallery', 'gallery');
        \core_comment\privacy\provider::delete_comments_for_users($userlist, 'mod_mediagallery', 'item');
    }

    /**
     * Delete the data a set of users hold in one collection.
     *
     * This deletes the items the users added and the galleries they own, with each one's files, tags, likes and
     * comments. Deleting a gallery deletes every item in it, which matches what happens when its owner deletes it.
     * It also deletes the likes and ratings the users gave, and removes them as the collection's creator. The
     * comments the users made are deleted by the callers.
     *
     * @param \context_module $context The collection's context.
     * @param int[] $userids
     */
    protected static function delete_user_data(\context_module $context, array $userids) {
        global $DB;

        $instanceid = self::get_mediagallery_id_from_context($context);
        if (empty($instanceid) || empty($userids)) {
            return;
        }

        [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'mgu');
        $params = ['instanceid' => $instanceid] + $userparams;

        $galleryids = $DB->get_fieldset_select('mediagallery_gallery', 'id',
            "instanceid = :instanceid AND userid $usersql", $params);

        $sql = "SELECT i.id
                  FROM {mediagallery_item} i
                  JOIN {mediagallery_gallery} g ON g.id = i.galleryid
                 WHERE g.instanceid = :instanceid AND i.userid $usersql";
        $itemids = $DB->get_fieldset_sql($sql, $params);
        if ($galleryids) {
            [$gallerysql, $galleryparams] = $DB->get_in_or_equal($galleryids, SQL_PARAMS_NAMED, 'mgg');
            $itemids = array_merge($itemids,
                $DB->get_fieldset_select('mediagallery_item', 'id', "galleryid $gallerysql", $galleryparams));
        }

        if ($itemids) {
            [$itemsql, $itemparams] = $DB->get_in_or_equal(array_unique($itemids), SQL_PARAMS_NAMED, 'mgi');
            $fs = get_file_storage();
            foreach (['item', 'lowres', 'thumbnail'] as $filearea) {
                $fs->delete_area_files_select($context->id, 'mod_mediagallery', $filearea, $itemsql, $itemparams);
            }
            \core_tag\privacy\provider::delete_item_tags_select($context, 'mod_mediagallery', 'mediagallery_item',
                $itemsql, $itemparams);
            \core_comment\privacy\provider::delete_comments_for_all_users_select($context, 'mod_mediagallery', 'item',
                $itemsql, $itemparams);
            $DB->delete_records_select('mediagallery_userfeedback', "itemid $itemsql", $itemparams);
            $DB->delete_records_select('mediagallery_item', "id $itemsql", $itemparams);
        }

        if ($galleryids) {
            \core_tag\privacy\provider::delete_item_tags_select($context, 'mod_mediagallery', 'mediagallery_gallery',
                $gallerysql, $galleryparams);
            \core_comment\privacy\provider::delete_comments_for_all_users_select($context, 'mod_mediagallery', 'gallery',
                $gallerysql, $galleryparams);
            $DB->delete_records_select('mediagallery_gallery', "id $gallerysql", $galleryparams);
        }

        // Likes and ratings the users gave on items that remain.
        $DB->delete_records_select('mediagallery_userfeedback',
            "userid $usersql AND itemid IN (
                SELECT i.id
                  FROM {mediagallery_item} i
                  JOIN {mediagallery_gallery} g ON g.id = i.galleryid
                 WHERE g.instanceid = :instanceid
            )", $params);

        // The collection stays, but no longer records who created it.
        $DB->set_field_select('mediagallery', 'userid', 0, "id = :instanceid AND userid $usersql", $params);
    }

    /**
     * Export all user preferences for the plugin.
     *
     * @param int $userid The userid of the user whose data is to be exported.
     */
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
     * Get the mediagallery id for a module context.
     *
     * @param \context_module $context
     * @return int The mediagallery id, or 0 if the context is not a mediagallery.
     */
    protected static function get_mediagallery_id_from_context(\context_module $context) {
        $cm = get_coursemodule_from_id('mediagallery', $context->instanceid);
        return $cm ? (int) $cm->instance : 0;
    }

}
