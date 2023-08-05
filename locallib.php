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
 * Internal library of functions for module mediagallery
 *
 * All the mediagallery specific functions, needed to implement the module
 * logic, should go here. Never include this file from your lib.php!
 *
 * @package    mod_mediagallery
 * @copyright  NetSpot Pty Ltd
 * @author     Adam Olley <adam.olley@netspot.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__).'/classes/base.php');
require_once(dirname(__FILE__).'/classes/collection.php');
require_once(dirname(__FILE__).'/classes/gallery.php');
require_once(dirname(__FILE__).'/classes/item.php');
require_once(dirname(__FILE__).'/classes/imagehelper.php');
require_once($CFG->dirroot.'/comment/lib.php');
require_once($CFG->libdir.'/filelib.php');
require_once($CFG->dirroot.'/repository/lib.php');
require_once($CFG->dirroot.'/tag/lib.php');

/**
 * Options to pass to the filepicker when adding items to a gallery.
 *
 * @param \mod_mediagallery\gallery $gallery
 * @return array
 */
function mediagallery_filepicker_options($gallery) {
    $pickeroptions = array(
        'maxbytes' => $gallery->get_collection()->maxbytes,
        'maxfiles' => 1,
        'return_types' => FILE_INTERNAL | FILE_REFERENCE,
        'subdirs' => false,
    );
    return $pickeroptions;
}

/**
 * Get a list of mediagallery's the current user has permission to import a
 * gallery into.
 *
 * @param stdClass $course
 * @param \mod_mediagallery\gallery $gallery
 * @return array List of mediagallery's.
 */
function mediagallery_get_sample_targets($course, $gallery) {
    $list = array();
    $modinfo = get_fast_modinfo($course);
    foreach ($modinfo->get_instances_of('mediagallery') as $mgid => $cm) {
        if (!$cm->uservisible) {
            continue;
        }
        $list[$cm->instance] = $cm->name;
    }
    return $list;
}

/**
 * This methods does weak url validation, we are looking for major problems only,
 * no strict RFE validation.
 *
 * Code taken from mod_url.
 *
 * @param string $url
 * @return bool true is seems valid, false if definitely not valid URL
 */
function mediagallery_appears_valid_url($url) {
    if (preg_match('/^(\/|https?:|ftp:)/i', $url)) {
        // Note: this is not exact validation, we look for severely malformed URLs only.
        return (bool)preg_match('/^[a-z]+:\/\/([^:@\s]+:[^@\s]+@)?[a-z0-9_\.\-]+(:[0-9]+)?(\/[^#]*)?(#.*)?$/i', $url);
    } else {
        return (bool)preg_match('/^[a-z]+:\/\/...*$/i', $url);
    }
}

/**
 * Calculate disk usage by collections.
 *
 * @return array Usage data.
 */
function mediagallery_get_file_storage_usage() {
    global $DB;
    $usagedata = array('course' => array(), 'total' => 0);

    $sql = "SELECT c.id, c.fullname, c.category, f.contenthash, f.filesize
            FROM {course_modules} cm
            JOIN {context} cx ON cx.contextlevel = ".CONTEXT_MODULE." AND cx.instanceid = cm.id
            JOIN {course} c ON c.id = cm.course
            JOIN {files} f ON f.contextid = cx.id
            WHERE f.component = 'mod_mediagallery'
            ORDER BY f.id ASC";
    $seen = array();
    if ($result = $DB->get_recordset_sql($sql)) {
        foreach ($result as $record) {
            if (isset($seen[$record->contenthash])) {
                continue;
            }
            $seen[$record->contenthash] = true;
            if (!isset($usagedata['course'][$record->id])) {
                $usagedata['course'][$record->id] = (object)array(
                    'id' => $record->id,
                    'fullname' => $record->fullname,
                    'category' => $record->category,
                    'sum' => 0,
                );
            }
            $usagedata['course'][$record->id]->sum += $record->filesize;
        }
    }

    foreach ($usagedata['course'] as $key => $data) {
        $usagedata['category'][$data->category][] = $key;
    }

    $sql = "SELECT SUM(filesize)
            FROM (
                SELECT DISTINCT(f.contenthash), f.filesize AS filesize
                FROM {files} f
                WHERE component = 'mod_mediagallery'
            ) fs";
    $usagedata['total'] = $DB->get_field_sql($sql);

    return $usagedata;
}

/**
 * Get all the collections for a list of courses.
 *
 * @param array $courses
 * @return array
 */
function mediagallery_get_readable_collections($courses) {
    $instances = get_all_instances_in_courses('mediagallery', $courses);
    $list = array();
    foreach ($instances as $instance) {
        $instance->cm = $instance->coursemodule;
        $list[$instance->id] = new \mod_mediagallery\collection($instance);
    }
    return $list;
}

/**
 * Search media collections for given terms.
 *
 * @param array $searchterms
 * @param array $courses
 * @param int $limitfrom
 * @param int $limitnum
 * @param string $extrasql
 * @return array
 */
function mediagallery_search_items($searchterms, $courses, $limitfrom = 0, $limitnum = 50, $extrasql = '') {
    global $CFG, $DB, $USER;
    require_once($CFG->libdir.'/searchlib.php');

    $collections = mediagallery_get_readable_collections($courses);

    if (count($collections) == 0) {
        return array(false, 0);
    }

    $fullaccess = array();
    $where = array();
    $params = array();

    foreach ($collections as $collectionid => $collection) {
        $select = array();

        $cm = $collection->cm;
        $context = $collection->context;

        if (!empty($collection->onlygroups)) {
            list($groupidsql, $groupidparams) = $DB->get_in_or_equal($collection->onlygroups, SQL_PARAMS_NAMED,
                'grps'.$collectionid.'_');
            $params = array_merge($params, $groupidparams);
            $select[] = "g.groupid $groupidsql";
        }

        if ($select) {
            $selects = implode(" AND ", $select);
            $where[] = "(g.instanceid = :mediagallery{$collectionid} AND $selects)";
            $params['mediagallery'.$collectionid] = $collectionid;
        } else {
            $fullaccess[] = $collectionid;
        }
    }

    if ($fullaccess) {
        list($fullidsql, $fullidparams) = $DB->get_in_or_equal($fullaccess, SQL_PARAMS_NAMED, 'fula');
        $params = array_merge($params, $fullidparams);
        $where[] = "(g.instanceid $fullidsql)";
    }

    $selectgallery = "(".implode(" OR ", $where).")";
    $messagesearch = '';
    $searchstring = '';

    // Need to concat these back together for parser to work.
    foreach ($searchterms as $searchterm) {
        if ($searchstring != '') {
            $searchstring .= ' ';
        }
        $searchstring .= $searchterm;
    }

    // We need to allow quoted strings for the search. The quotes *should* be stripped
    // by the parser, but this should be examined carefully for security implications.
    $searchstring = str_replace("\\\"", "\"", $searchstring);
    $parser = new search_parser();
    $lexer = new search_lexer($parser);

    if ($lexer->parse($searchstring)) {
        if ($parsearray = $parser->get_parsed_array()) {
            list($messagesearch, $msparams) = mediagallery_generate_search_sql($parsearray);
            $params = array_merge($params, $msparams);
        }
    }
    $fromsql = "{mediagallery_item} i,
                {mediagallery_gallery} g,
                {user} u";

    $selectsql = " $messagesearch
                    AND i.galleryid = g.id
                    AND g.userid = u.id
                    AND $selectgallery
                        $extrasql";

    $countsql = "SELECT COUNT(*)
                 FROM $fromsql
                 WHERE $selectsql";

    $searchsql = "SELECT i.*, g.name as galleryname, g.instanceid, u.firstname, u.lastname, u.email, u.picture, u.imagealt
                  FROM $fromsql
                  WHERE $selectsql
                  ORDER BY i.id DESC";

    $totalcount = $DB->count_records_sql($countsql, $params);
    $records = $DB->get_records_sql($searchsql, $params, $limitfrom, $limitnum);

    return array($records, $totalcount);
}

/**
 * Build the search query based off the parsetree.
 *
 * @param array $parsetree
 * @return array A list containing the SQL and parameters list.
 */
function mediagallery_generate_search_sql($parsetree) {
    global $CFG, $DB;
    static $p = 0;

    if ($DB->sql_regex_supported()) {
        $regexp = $DB->sql_regex(true);
        $notregexp = $DB->sql_regex(false);
    }

    $params = array();

    $ntokens = count($parsetree);
    if ($ntokens == 0) {
        return;
    }

    $sqlstring = '';

    $fields = array('caption', 'originalauthor', 'moralrights', 'medium', 'publisher', 'collection', 'name');

    for ($i = 0; $i < $ntokens; $i++) {
        if ($i > 0) { // We have more than one clause, need to tack on AND.
            $sqlstring .= ' AND ';
        }

        $type = $parsetree[$i]->getType();
        $value = $parsetree[$i]->getValue();

        // Under Oracle and MSSQL, transform TOKEN searches into STRING searches and trim +- chars.
        if (!$DB->sql_regex_supported()) {
            $value = trim($value, '+-');
            if ($type == TOKEN_EXACT) {
                $type = TOKEN_STRING;
            }
        }

        $name1 = 'sq'.$p++;
        $name2 = 'sq'.$p++;
        $name3 = 'sq'.$p++;

        $datafield = 'i.caption';
        $metafield = 'i.description';

        $specific = false;
        foreach ($fields as $field) {
            if (substr($value, 0, strlen($field) + 1) == "$field:") {
                $datafield = "i.$field";
                $metafield = null;
                $specific = true;
                $value = substr($value, strlen($field) + 1);
            }
        }

        $sqlstring .= '(';
        if ($datafield == 'i.moralrights') {
            $sqlstring .= "(i.moralrights = :$name1)";
            $params[$name1] = "$value";
        } else {
            $sqlstring .= "(".$DB->sql_like($datafield, ":$name1", false).")";
            $params[$name1] = "%$value%";
        }
        if (!is_null($metafield)) {
            $sqlstring .= "OR (".$DB->sql_like($metafield, ":$name2", false).")";
            $params[$name2] = "%$value%";
        }
        if (!$specific) {
            $sqlstring .= "OR (".$DB->sql_like('name', ":$name3", false).")";
            $params[$name3] = "%$value%";
        }

        $sqlstring .= ")";
    }
    return array($sqlstring, $params);

}

/**
 * Add metainfo fields to a moodleform.
 *
 * @param moodleform $mform
 * @return void
 */
function mediagallery_add_metainfo_fields(&$mform) {
    $mform->addElement('selectyesno', 'moralrights', get_string('moralrights', 'mediagallery'));
    $mform->addHelpButton('moralrights', 'moralrights', 'mediagallery');
    $mform->setDefault('moralrights', 1);

    $mform->addElement('text', 'originalauthor', get_string('originalauthor', 'mediagallery'));
    $mform->setType('originalauthor', PARAM_TEXT);
    $mform->addRule('originalauthor', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
    $mform->addHelpButton('originalauthor', 'originalauthor', 'mediagallery');

    $mform->addElement('date_selector', 'productiondate', get_string('productiondate', 'mediagallery'),
        array('optional' => true, 'startyear' => 0));
    $mform->addHelpButton('productiondate', 'productiondate', 'mediagallery');

    $mform->addElement('text', 'medium', get_string('medium', 'mediagallery'));
    $mform->setType('medium', PARAM_TEXT);
    $mform->addRule('medium', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
    $mform->addHelpButton('medium', 'medium', 'mediagallery');

    $mform->addElement('text', 'publisher', get_string('publisher', 'mediagallery'));
    $mform->setType('publisher', PARAM_TEXT);
    $mform->addRule('publisher', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
    $mform->addHelpButton('publisher', 'publisher', 'mediagallery');

    $mform->addElement('text', 'broadcaster', get_string('broadcaster', 'mediagallery'));
    $mform->setType('broadcaster', PARAM_TEXT);
    $mform->addRule('broadcaster', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
    $mform->addHelpButton('broadcaster', 'broadcaster', 'mediagallery');

    $mform->addElement('text', 'reference', get_string('reference', 'mediagallery'));
    $mform->setType('reference', PARAM_TEXT);
    $mform->addRule('reference', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
    $mform->addHelpButton('reference', 'reference', 'mediagallery');
}

/**
 * Returns collections tagged with a specified tag.
 *
 * This is a callback used by the tag area mod_mediagallery/mediagallery to search for collections
 * tagged with a specific tag.
 *
 * @param core_tag_tag $tag
 * @param bool $exclusivemode if set to true it means that no other entities tagged with this tag
 *             are displayed on the page and the per-page limit may be bigger
 * @param int $fromctx context id where the link was displayed, may be used by callbacks
 *            to display items in the same context first
 * @param int $ctx context id where to search for records
 * @param bool $rec search in subcontexts as well
 * @param int $page 0-based number of page being displayed
 * @return \core_tag\output\tagindex
 */
function mod_mediagallery_get_tagged_collections($tag, $exclusivemode = false, $fromctx = 0, $ctx = 0, $rec = 1, $page = 0) {
    global $OUTPUT;
    $perpage = $exclusivemode ? 20 : 5;

    // Build the SQL query.
    $ctxselect = context_helper::get_preload_record_columns_sql('ctx');
    $query = "SELECT mc.id, mc.name, cm.id AS cmid, c.id AS courseid, c.shortname, c.fullname, $ctxselect
                FROM {mediagallery} mc
                JOIN {modules} m ON m.name = 'mediagallery'
                JOIN {course_modules} cm ON cm.module = m.id AND cm.instance = mc.id
                JOIN {tag_instance} tt ON mc.id = tt.itemid
                JOIN {course} c ON cm.course = c.id
                JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :coursemodulecontextlevel
               WHERE tt.itemtype = :itemtype AND tt.tagid = :tagid AND tt.component = :component
                 AND cm.deletioninprogress = 0
                 AND mc.id %ITEMFILTER% AND c.id %COURSEFILTER%";

    $params = array('itemtype' => 'mediagallery', 'tagid' => $tag->id, 'component' => 'mod_mediagallery',
                    'coursemodulecontextlevel' => CONTEXT_MODULE);

    if ($ctx) {
        $context = $ctx ? context::instance_by_id($ctx) : context_system::instance();
        $query .= $rec ? ' AND (ctx.id = :contextid OR ctx.path LIKE :path)' : ' AND ctx.id = :contextid';
        $params['contextid'] = $context->id;
        $params['path'] = $context->path.'/%';
    }

    $query .= " ORDER BY ";
    if ($fromctx) {
        // In order-clause specify that modules from inside "fromctx" context should be returned first.
        $fromcontext = context::instance_by_id($fromctx);
        $query .= ' (CASE WHEN ctx.id = :fromcontextid OR ctx.path LIKE :frompath THEN 0 ELSE 1 END),';
        $params['fromcontextid'] = $fromcontext->id;
        $params['frompath'] = $fromcontext->path.'/%';
    }
    $query .= ' c.sortorder, cm.id, mc.id';

    $totalpages = $page + 1;

    $builder = new core_tag_index_builder('mod_mediagallery', 'mediagallery', $query, $params, $page * $perpage, $perpage + 1);

    while ($item = $builder->has_item_that_needs_access_check()) {
        context_helper::preload_from_record($item);
        $courseid = $item->courseid;
        if (!$builder->can_access_course($courseid)) {
            $builder->set_accessible($item, false);
            continue;
        }
        $modinfo = get_fast_modinfo($builder->get_course($courseid));
        // Set accessibility of this item and all other items in the same course.
        $builder->walk(function ($taggeditem) use ($courseid, $modinfo, $builder, $item) {
            $accessible = false;
            if (($cm = $modinfo->get_cm($taggeditem->cmid)) && $cm->uservisible) {
                $accessible = true;
            }
            $builder->set_accessible($item, $accessible);
        });
    }

    $items = $builder->get_items();
    if (count($items) > $perpage) {
        $totalpages = $page + 2; // We don't need exact page count, just indicate that the next page exists.
        array_pop($items);
    }

    // Build the display contents.
    if ($items) {
        $tagfeed = new core_tag\output\tagfeed();
        foreach ($items as $item) {
            context_helper::preload_from_record($item);
            $modinfo = get_fast_modinfo($item->courseid);
            $cm = $modinfo->get_cm($item->cmid);
            $pageurl = new moodle_url('/mod/mediagallery/view.php', array('g' => $item->id));
            $pagename = format_string($item->name, true, array('context' => context_module::instance($item->cmid)));
            $pagename = html_writer::link($pageurl, $pagename);
            $courseurl = course_get_url($item->courseid, $cm->sectionnum);
            $cmname = html_writer::link($cm->url, $cm->get_formatted_name());
            $coursename = format_string($item->fullname, true, array('context' => context_course::instance($item->courseid)));
            $coursename = html_writer::link($courseurl, $coursename);
            $icon = html_writer::link($pageurl, html_writer::empty_tag('img', array('src' => $cm->get_icon_url())));
            $tagfeed->add($icon, $pagename, $cmname.'<br>'.$coursename);
        }

        $content = $OUTPUT->render_from_template('core_tag/tagfeed',
            $tagfeed->export_for_template($OUTPUT));

        return new core_tag\output\tagindex($tag, 'mod_mediagallery', 'mediagallery', $content,
            $exclusivemode, $fromctx, $ctx, $rec, $page, $totalpages);
    }
}

/**
 * Returns galleries tagged with a specified tag.
 *
 * This is a callback used by the tag area mod_mediagallery/mediagallery_gallery to search for galleries
 * tagged with a specific tag.
 *
 * @param core_tag_tag $tag
 * @param bool $exclusivemode if set to true it means that no other entities tagged with this tag
 *             are displayed on the page and the per-page limit may be bigger
 * @param int $fromctx context id where the link was displayed, may be used by callbacks
 *            to display items in the same context first
 * @param int $ctx context id where to search for records
 * @param bool $rec search in subcontexts as well
 * @param int $page 0-based number of page being displayed
 * @return \core_tag\output\tagindex
 */
function mod_mediagallery_get_tagged_galleries($tag, $exclusivemode = false, $fromctx = 0, $ctx = 0, $rec = 1, $page = 0) {
    global $OUTPUT;
    $perpage = $exclusivemode ? 20 : 5;

    // Build the SQL query.
    $ctxselect = context_helper::get_preload_record_columns_sql('ctx');
    $query = "SELECT mg.*, cm.id AS cmid, c.id AS courseid, c.shortname, c.fullname, $ctxselect
                FROM {mediagallery_gallery} mg
                JOIN {mediagallery} mc ON mc.id = mg.instanceid
                JOIN {modules} m ON m.name = 'mediagallery'
                JOIN {course_modules} cm ON cm.module = m.id AND cm.instance = mc.id
                JOIN {tag_instance} tt ON mg.id = tt.itemid
                JOIN {course} c ON cm.course = c.id
                JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :coursemodulecontextlevel
               WHERE tt.itemtype = :itemtype AND tt.tagid = :tagid AND tt.component = :component
                 AND cm.deletioninprogress = 0
                 AND mg.id %ITEMFILTER% AND c.id %COURSEFILTER%";

    $params = array('itemtype' => 'mediagallery_gallery', 'tagid' => $tag->id, 'component' => 'mod_mediagallery',
                    'coursemodulecontextlevel' => CONTEXT_MODULE);

    if ($ctx) {
        $context = $ctx ? context::instance_by_id($ctx) : context_system::instance();
        $query .= $rec ? ' AND (ctx.id = :contextid OR ctx.path LIKE :path)' : ' AND ctx.id = :contextid';
        $params['contextid'] = $context->id;
        $params['path'] = $context->path.'/%';
    }

    $query .= " ORDER BY ";
    if ($fromctx) {
        // In order-clause specify that modules from inside "fromctx" context should be returned first.
        $fromcontext = context::instance_by_id($fromctx);
        $query .= ' (CASE WHEN ctx.id = :fromcontextid OR ctx.path LIKE :frompath THEN 0 ELSE 1 END),';
        $params['fromcontextid'] = $fromcontext->id;
        $params['frompath'] = $fromcontext->path.'/%';
    }
    $query .= ' c.sortorder, cm.id, mg.id';

    $totalpages = $page + 1;

    $builder = new core_tag_index_builder('mod_mediagallery', 'mediagallery_gallery', $query, $params, $page * $perpage,
                                        $perpage + 1);

    while ($item = $builder->has_item_that_needs_access_check()) {
        context_helper::preload_from_record($item);
        $courseid = $item->courseid;
        if (!$builder->can_access_course($courseid)) {
            $builder->set_accessible($item, false);
            continue;
        }
        $modinfo = get_fast_modinfo($builder->get_course($courseid));
        // Set accessibility of this item and all other items in the same course.
        $builder->walk(function ($taggeditem) use ($courseid, $modinfo, $builder, $item) {
            $accessible = false;
            if (($cm = $modinfo->get_cm($taggeditem->cmid)) && $cm->uservisible) {
                $gallery = new \mod_mediagallery\gallery($item);
                $accessible = $gallery->user_can_view();
            }
            $builder->set_accessible($item, $accessible);
        });
    }

    $items = $builder->get_items();
    if (count($items) > $perpage) {
        $totalpages = $page + 2; // We don't need exact page count, just indicate that the next page exists.
        array_pop($items);
    }

    // Build the display contents.
    if ($items) {
        $tagfeed = new core_tag\output\tagfeed();
        foreach ($items as $item) {
            context_helper::preload_from_record($item);
            $modinfo = get_fast_modinfo($item->courseid);
            $cm = $modinfo->get_cm($item->cmid);
            $pageurl = new moodle_url('/mod/mediagallery/view.php', array('g' => $item->id));
            $pagename = format_string($item->name, true, array('context' => context_module::instance($item->cmid)));
            $pagename = html_writer::link($pageurl, $pagename);
            $courseurl = course_get_url($item->courseid, $cm->sectionnum);
            $cmname = html_writer::link($cm->url, $cm->get_formatted_name());
            $coursename = format_string($item->fullname, true, array('context' => context_course::instance($item->courseid)));
            $coursename = html_writer::link($courseurl, $coursename);
            $icon = html_writer::link($pageurl, html_writer::empty_tag('img', array('src' => $cm->get_icon_url())));
            $tagfeed->add($icon, $pagename, $cmname.'<br>'.$coursename);
        }

        $content = $OUTPUT->render_from_template('core_tag/tagfeed',
            $tagfeed->export_for_template($OUTPUT));

        return new core_tag\output\tagindex($tag, 'mod_mediagallery', 'mediagallery_gallery', $content,
            $exclusivemode, $fromctx, $ctx, $rec, $page, $totalpages);
    }
}

/**
 * Returns media items tagged with a specified tag.
 *
 * This is a callback used by the tag area mod_mediagallery/mediagallery_item to search for items
 * tagged with a specific tag.
 *
 * @param core_tag_tag $tag
 * @param bool $exclusivemode if set to true it means that no other entities tagged with this tag
 *             are displayed on the page and the per-page limit may be bigger
 * @param int $fromctx context id where the link was displayed, may be used by callbacks
 *            to display items in the same context first
 * @param int $ctx context id where to search for records
 * @param bool $rec search in subcontexts as well
 * @param int $page 0-based number of page being displayed
 * @return \core_tag\output\tagindex
 */
function mod_mediagallery_get_tagged_items($tag, $exclusivemode = false, $fromctx = 0, $ctx = 0, $rec = 1, $page = 0) {
    global $OUTPUT;
    $perpage = $exclusivemode ? 20 : 5;

    // Build the SQL query.
    $ctxselect = context_helper::get_preload_record_columns_sql('ctx');
    $query = "SELECT mg.*, mi.id, mi.caption,
                    cm.id AS cmid, c.id AS courseid, c.shortname, c.fullname, $ctxselect
                FROM {mediagallery_item} mi
                JOIN {mediagallery_gallery} mg ON mg.id = mi.galleryid
                JOIN {mediagallery} mc ON mc.id = mg.instanceid
                JOIN {modules} m ON m.name = 'mediagallery'
                JOIN {course_modules} cm ON cm.module = m.id AND cm.instance = mc.id
                JOIN {tag_instance} tt ON mi.id = tt.itemid
                JOIN {course} c ON cm.course = c.id
                JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :coursemodulecontextlevel
               WHERE tt.itemtype = :itemtype AND tt.tagid = :tagid AND tt.component = :component
                 AND cm.deletioninprogress = 0
                 AND mi.id %ITEMFILTER% AND c.id %COURSEFILTER%";

    $params = array('itemtype' => 'mediagallery_item', 'tagid' => $tag->id, 'component' => 'mod_mediagallery',
                    'coursemodulecontextlevel' => CONTEXT_MODULE);

    if ($ctx) {
        $context = $ctx ? context::instance_by_id($ctx) : context_system::instance();
        $query .= $rec ? ' AND (ctx.id = :contextid OR ctx.path LIKE :path)' : ' AND ctx.id = :contextid';
        $params['contextid'] = $context->id;
        $params['path'] = $context->path.'/%';
    }

    $query .= " ORDER BY ";
    if ($fromctx) {
        // In order-clause specify that modules from inside "fromctx" context should be returned first.
        $fromcontext = context::instance_by_id($fromctx);
        $query .= ' (CASE WHEN ctx.id = :fromcontextid OR ctx.path LIKE :frompath THEN 0 ELSE 1 END),';
        $params['fromcontextid'] = $fromcontext->id;
        $params['frompath'] = $fromcontext->path.'/%';
    }
    $query .= ' c.sortorder, cm.id, mi.id';

    $totalpages = $page + 1;

    $builder = new core_tag_index_builder('mod_mediagallery', 'mediagallery_item', $query, $params, $page * $perpage, $perpage + 1);

    while ($item = $builder->has_item_that_needs_access_check()) {
        context_helper::preload_from_record($item);
        $courseid = $item->courseid;
        if (!$builder->can_access_course($courseid)) {
            $builder->set_accessible($item, false);
            continue;
        }
        $modinfo = get_fast_modinfo($builder->get_course($courseid));
        // Set accessibility of this item and all other items in the same course.
        $builder->walk(function ($taggeditem) use ($courseid, $modinfo, $builder, $item) {
            $accessible = false;
            if (($cm = $modinfo->get_cm($taggeditem->cmid)) && $cm->uservisible) {
                $gallery = new \mod_mediagallery\gallery($item);
                $accessible = $gallery->user_can_view();
            }
            $builder->set_accessible($item, $accessible);
        });
    }

    $items = $builder->get_items();
    if (count($items) > $perpage) {
        $totalpages = $page + 2; // We don't need exact page count, just indicate that the next page exists.
        array_pop($items);
    }

    // Build the display contents.
    if ($items) {
        $tagfeed = new core_tag\output\tagfeed();
        foreach ($items as $item) {
            context_helper::preload_from_record($item);
            $modinfo = get_fast_modinfo($item->courseid);
            $cm = $modinfo->get_cm($item->cmid);
            $pageurl = new moodle_url('/mod/mediagallery/view.php', array('g' => $item->id));
            $pagename = format_string($item->caption, true, array('context' => context_module::instance($item->cmid)));
            $pagename = html_writer::link($pageurl, $pagename);
            $courseurl = course_get_url($item->courseid, $cm->sectionnum);
            $cmname = html_writer::link($cm->url, $cm->get_formatted_name());
            $coursename = format_string($item->fullname, true, array('context' => context_course::instance($item->courseid)));
            $coursename = html_writer::link($courseurl, $coursename);
            $icon = html_writer::link($pageurl, html_writer::empty_tag('img', array('src' => $cm->get_icon_url())));
            $tagfeed->add($icon, $pagename, $cmname.'<br>'.$coursename);
        }

        $content = $OUTPUT->render_from_template('core_tag/tagfeed',
            $tagfeed->export_for_template($OUTPUT));

        return new core_tag\output\tagindex($tag, 'mod_mediagallery', 'mediagallery_item', $content,
            $exclusivemode, $fromctx, $ctx, $rec, $page, $totalpages);
    }
}
