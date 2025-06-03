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
 * Backup steps for mod_mediagallery
 *
 * @package    mod_mediagallery
 * @copyright  2014 NetSpot Pty Ltd
 * @author     Adam Olley <adam.olley@netspot.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define the complete mediagallery structure for backup, with file and id annotations
 */
class backup_mediagallery_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        // To know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');
        $includecomments = $this->get_setting_value('comments');

        // Define each element separated.
        $mediagallery = new backup_nested_element('mediagallery', array('id'), array(
            'course', 'name', 'intro', 'introformat', 'timecreated', 'timemodified',
            'thumbnailsperpage', 'thumbnailsperrow', 'displayfullcaption',
            'captionposition', 'galleryfocus', 'carousel', 'grid', 'gridrows',
            'gridcolumns', 'enforcedefaults', 'readonlyfrom', 'readonlyto',
            'maxbytes', 'maxitems', 'maxgalleries', 'allowcomments', 'allowlikes',
            'colltype',
            'completiongalleries', 'completionitems', 'completioncomments',
            'objectid', 'source', 'mode', 'creator', 'userid',
        ));

        $userfeedbacks = new backup_nested_element('userfeedback');
        $userfeedback = new backup_nested_element('feedback', array('id'), array(
            'itemid', 'userid', 'liked', 'rating'
        ));

        $gallerys = new backup_nested_element('gallerys');
        $gallery = new backup_nested_element('gallery', array('id'), array(
            'instanceid', 'name', 'userid', 'nameposition', 'exportable', 'galleryview',
            'gridrows', 'gridcolumns', 'visibleinstructor', 'visibleother', 'thumbnail',
            'galleryfocus', 'groupid', 'mode', 'objectid', 'source', 'creator',
            'contributable',
        ));

        $items = new backup_nested_element('items');
        $item = new backup_nested_element('item', array('id'), array(
            'galleryid', 'userid', 'caption', 'description', 'sortorder', 'display', 'moralrights',
            'originalauthor', 'productiondate', 'medium', 'publisher', 'reference', 'externalurl',
            'timecreated', 'broadcaster', 'objectid', 'source', 'processing_status', 'creator',
        ));

        $gcomments = new backup_nested_element('gallerycomments');
        $gcomment = new backup_nested_element('gallerycomment', ['id'], [
            'contextid', 'component', 'commentarea', 'itemid', 'content', 'format', 'userid', 'timecreated',
        ]);

        $icomments = new backup_nested_element('itemcomments');
        $icomment = new backup_nested_element('itemcomment', ['id'], [
            'contextid', 'component', 'commentarea', 'itemid', 'content', 'format', 'userid', 'timecreated',
        ]);

        $ctags = new backup_nested_element('collectiontags');
        $ctag = new backup_nested_element('collectiontag', array('id'), array('itemid', 'rawname'));

        $gtags = new backup_nested_element('gallerytags');
        $gtag = new backup_nested_element('gallerytag', array('id'), array('itemid', 'rawname'));

        $itags = new backup_nested_element('itemtags');
        $itag = new backup_nested_element('itemtag', array('id'), array('itemid', 'rawname'));

        // Build the tree.

        $mediagallery->add_child($gallerys);
        $mediagallery->add_child($ctags);
        $ctags->add_child($ctag);

        $gallerys->add_child($gallery);
        $gallery->add_child($items);
        $gallery->add_child($gcomments);
        $gcomments->add_child($gcomment);
        $gallery->add_child($gtags);
        $gtags->add_child($gtag);

        $items->add_child($item);
        $userfeedbacks->add_child($userfeedback);
        $item->add_child($userfeedbacks);
        $item->add_child($icomments);
        $icomments->add_child($icomment);
        $item->add_child($itags);
        $itags->add_child($itag);

        // Define sources.
        $mediagallery->set_source_table('mediagallery', array('id' => backup::VAR_ACTIVITYID));

        // All the rest of elements only happen if we are including user info.
        if ($userinfo) {
            $gallery->set_source_table('mediagallery_gallery', array('instanceid' => backup::VAR_PARENTID));
            $item->set_source_table('mediagallery_item', array('galleryid' => backup::VAR_PARENTID));
            $userfeedback->set_source_table('mediagallery_userfeedback', array('itemid' => backup::VAR_PARENTID));

            if ($includecomments) {
                $gcomment->set_source_table('comments', [
                                                        'contextid' => backup::VAR_CONTEXTID,
                                                        'commentarea' => backup_helper::is_sqlparam('gallery'),
                                                        'itemid' => backup::VAR_PARENTID]);
                $icomment->set_source_table('comments', [
                                                        'contextid' => backup::VAR_CONTEXTID,
                                                        'commentarea' => backup_helper::is_sqlparam('item'),
                                                        'itemid' => backup::VAR_PARENTID]);
            }

            if (core_tag_tag::is_enabled('mod_mediagallery', 'mediagallery')) {
                $ctag->set_source_sql('SELECT t.id, ti.itemid, t.rawname
                                FROM {tag} t
                                JOIN {tag_instance} ti ON ti.tagid = t.id
                               WHERE ti.itemtype = ?
                                 AND ti.component = ?
                                 AND ti.contextid = ?', [
                    backup_helper::is_sqlparam('mediagallery'),
                    backup_helper::is_sqlparam('mod_mediagallery'),
                    backup::VAR_CONTEXTID]);
            }

            if (core_tag_tag::is_enabled('mod_mediagallery', 'mediagallery_gallery')) {
                $gtag->set_source_sql('SELECT t.id, ti.itemid, t.rawname
                                FROM {tag} t
                                JOIN {tag_instance} ti ON ti.tagid = t.id
                               WHERE ti.itemtype = ?
                                 AND ti.component = ?
                                 AND ti.contextid = ?', [
                    backup_helper::is_sqlparam('mediagallery_gallery'),
                    backup_helper::is_sqlparam('mod_mediagallery'),
                    backup::VAR_CONTEXTID]);
            }

            if (core_tag_tag::is_enabled('mod_mediagallery', 'mediagallery_item')) {
                $itag->set_source_sql('SELECT t.id, ti.itemid, t.rawname
                                FROM {tag} t
                                JOIN {tag_instance} ti ON ti.tagid = t.id
                               WHERE ti.itemtype = ?
                                 AND ti.component = ?
                                 AND ti.contextid = ?', [
                    backup_helper::is_sqlparam('mediagallery_item'),
                    backup_helper::is_sqlparam('mod_mediagallery'),
                    backup::VAR_CONTEXTID]);
            }
        }

        // Define file annotations.
        $mediagallery->annotate_files('mod_mediagallery', 'item', null);
        $mediagallery->annotate_files('mod_mediagallery', 'lowres', null);
        $mediagallery->annotate_files('mod_mediagallery', 'thumbnail', null);

        $userfeedback->annotate_ids('user', 'userid');
        $gallery->annotate_ids('user', 'userid');
        $item->annotate_ids('user', 'userid');

        $gcomment->annotate_ids('user', 'userid');
        $icomment->annotate_ids('user', 'userid');

        // Return the root element (mediagallery), wrapped into standard activity structure.
        return $this->prepare_activity_structure($mediagallery);
    }
}
