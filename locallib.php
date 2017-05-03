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
require_once($CFG->libdir.'/coursecatlib.php');
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
        $regexp    = $DB->sql_regex(true);
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
 * Add a tag field to a given moodleform.
 *
 * @param moodleform $mform
 * @param array $tags
 * @param bool $useajax
 * @param bool $loadjs
 * @param string $element
 * @return void
 */
function mediagallery_add_tag_field($mform, array $tags, $useajax = false, $loadjs = true, $element = 'tags') {
    global $PAGE;
    $mform->addElement('text', $element, get_string('tags', 'mediagallery'));
    $mform->setType($element, PARAM_TAGLIST);
    if ($loadjs) {
        $PAGE->requires->yui_module('moodle-mod_mediagallery-tagselector', 'M.mod_mediagallery.tagselector.init',
            array('id_'.$element, $tags, $useajax), null, true);
    }
}
