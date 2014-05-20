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
 * Renderers for mediagallery.
 *
 * @package    mod_mediagallery
 * @copyright  NetSpot Pty Ltd
 * @author     Adam Olley <adam.olley@netspot.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use \mod_mediagallery\gallery;
use \mod_mediagallery\item;

class mod_mediagallery_renderer extends plugin_renderer_base {

    /**
     * Render a list of galleries for the user to browse through.
     *
     * @param $name string      The page heading.
     * @param $galleries array  A list of galleries to list.
     */
    public function gallery_list_page($mediagallery, $galleries) {
        $column = 1;
        $row = 1;
        $rowopen = false;
        $count = 0;

        $o = $this->output->heading($mediagallery->name);
        $o .= html_writer::start_tag('div', array('class' => 'gallery_list'));
        foreach ($galleries as $gallery) {
            if ($mediagallery->thumbnailsperrow > 0 && $column > $mediagallery->thumbnailsperrow) {
                // Row complete.
                $o .= html_writer::end_tag('div');
                $rowopen = false;
                $column = 1;
                $row++;
            }
            if ($column == 1) {
                $o .= html_writer::start_tag('div', array('class' => 'row clearfix'));
                $rowopen = true;
            }
            if ($mediagallery->thumbnailsperpage > 0 && $count > $mediagallery->thumbnailsperpage) {
                break;
            }

            $o .= $this->gallery_list_item($gallery);
            $column++;
            $count++;
        }
        if ($rowopen) {
            $o .= html_writer::end_tag('div');
        }
        $o .= html_writer::end_tag('div');
        return $o;
    }

    public function gallery_list_item($gallery) {
        global $COURSE;
        $o = html_writer::start_tag('div',
            array('class' => 'gallery_list_item', 'data-title' => $gallery->name, 'data-id' => $gallery->id));

        $url = new moodle_url('/mod/mediagallery/view.php', array('g' => $gallery->id));
        $img = html_writer::empty_tag('img', array('src' => $gallery->get_thumbnail()));
        $link = html_writer::link($url, $img);
        $o .= html_writer::tag('div', $link, array('class' => 'gthumbnail'));
        $o .= html_writer::start_tag('div', array('class' => 'title'));
        $o .= $this->output->heading(format_string($gallery->name), 6);
        $o .= html_writer::end_tag('div');

        $o .= html_writer::start_tag('div', array('class' => 'controls'));

        $this->page->requires->yui_module('moodle-mod_mediagallery-base', 'M.mod_mediagallery.base.add_gallery_info_modal', array($COURSE->id, $gallery->get_metainfo()), null, true);
        $url = new moodle_url('/mod/mediagallery/gallery.php', array('g' => $gallery->id, 'action' => 'info'));
        $o .= $this->output->action_icon($url, new pix_icon('i/info', get_string('information', 'mediagallery')), null, array('class' => 'action-icon info'));

        if ($gallery->user_can_edit()) {
            $url = new moodle_url('/mod/mediagallery/gallery.php', array('g' => $gallery->id, 'action' => 'delete'));
            $o .= $this->output->action_icon($url, new pix_icon('t/delete', get_string('delete')), null, array('class' => 'action-icon delete'));

            $url = new moodle_url('/mod/mediagallery/view.php', array('g' => $gallery->id, 'editing' => 1));
            $o .= $this->output->action_icon($url, new pix_icon('t/edit', get_string('edit')));
        }
        $o .= html_writer::end_tag('div');

        $o .= html_writer::end_tag('div');
        return $o;
    }

    public function gallery_heading(gallery $gallery) {
        $name = format_string($gallery->get_collection()->name).' '.$this->output->rarrow().' '.format_string($gallery->name);
        $head = $this->output->heading($name);
        return html_writer::div($head, 'heading');
    }

    /**
     * Render a specific gallery.
     *
     * @param $gallery \mod_mediagallery\gallery The gallery to display.
     * @param $editing bool Display editing controls.
     * @param $options array Any further options for display.
     *                       'offset' int Used for browsing through pages of items.
     */
    public function gallery_page(gallery $gallery, $editing = false, $options = array()) {
        $o = $this->gallery_heading($gallery);
        $class = '';
        $pix = 't/check';
        if (!$gallery->moral_rights_asserted()) {
            $class = ' no';
            $pix = 'i/cross_red_big';
        }
        $indicator = html_writer::empty_tag('img', array('src' => $this->output->pix_url($pix)));
        $o .= html_writer::tag('div', $indicator, array('class' => 'moralrights'.$class));
        $link = html_writer::link('#', get_string('sample', 'mediagallery'), array('id' => 'mg_sample'));
        $o .= html_writer::tag('div', $link, array('class' => 'moralrights_title'));
        if ($editing) {
            $o .= $this->gallery_editing_page($gallery);
        } else {
            $o .= html_writer::start_tag('div', array('class' => 'gallery'));
            $items = $gallery->get_items();
            if (empty($items)) {
                $o .= get_string('noitemsadded', 'mediagallery');
            } else if ($gallery->galleryview == MEDIAGALLERY_VIEW_GRID) {
                $o .= $this->view_grid($gallery, $options);
            } else {
                $o .= $this->view_carousel($gallery, $options);
            }
            $o .= html_writer::end_tag('div');
            if ($otheritems = $gallery->get_items_by_type(false)) {
                $o .= $this->output->heading(get_string('otherfiles', 'mediagallery'), 3);
                $o .= $this->list_other_items($otheritems);
            }
        }
        if (!empty($options['comments']) && !$editing) {
            $o .= html_writer::div($options['comments']->output(true), 'commentarea');
        }
        // If the user normally could edit, but can't currently due to read-only time or submission, display export link.
        if ($gallery->user_can_edit(null, true) && !$gallery->user_can_edit()) {
            $exporturl = new moodle_url('/mod/mediagallery/export.php', array('g' => $gallery->id));
            $o .= html_writer::div(html_writer::link($exporturl, get_string('exportgallery', 'mediagallery')), 'exportlink');
        }
        return $o;
    }

    /**
     * Render editing interface for a specific gallery.
     *
     * @param $gallery \mod_mediagallery\gallery The gallery to display.
     */
    public function gallery_editing_page(gallery $gallery) {
        $o = html_writer::start_tag('div', array('class' => 'gallery_items editing'));
        foreach ($gallery->get_items() as $item) {
            $o .= $this->item_editing($item);
        }
        $o .= html_writer::end_tag('div');

        $o .= html_writer::start_tag('div', array('class' => 'actions'));

        $additemurl = new moodle_url('/mod/mediagallery/item.php', array('g' => $gallery->id));
        $addbulkitemurl = new moodle_url('/mod/mediagallery/item.php', array('g' => $gallery->id, 'bulk' => 1));
        $viewurl = new moodle_url('/mod/mediagallery/view.php', array('g' => $gallery->id));
        $editurl = new moodle_url('/mod/mediagallery/gallery.php', array('g' => $gallery->id));
        $exporturl = new moodle_url('/mod/mediagallery/export.php', array('g' => $gallery->id));
        $actions = array();

        $maxitems = $gallery->get_collection()->maxitems;
        if ($maxitems == 0 || count($gallery->get_items()) < $maxitems) {
            $actions[] = html_writer::link($additemurl, get_string('addanitem', 'mediagallery'));
            $actions[] = html_writer::link($addbulkitemurl, get_string('addbulkitems', 'mediagallery'));
        } else {
            $actions[] = html_writer::span(get_string('maxitemsreached', 'mediagallery'));
        }
        $actions[] = html_writer::link($viewurl, get_string('viewgallery', 'mediagallery'));
        $actions[] = html_writer::link($editurl, get_string('editgallery', 'mediagallery'));
        $actions[] = html_writer::link($exporturl, get_string('exportgallery', 'mediagallery'));
        $o .= implode(' &bull; ', $actions);

        $o .= html_writer::end_tag('div');

        return $o;
    }

    public function item_editing(item $item) {
        $o = html_writer::start_tag('div', array('class' => 'item', 'data-id' => $item->id, 'data-title' => $item->caption));

        $img = html_writer::empty_tag('img', array('src' => $item->get_image_url_by_type('thumbnail')));
        $link = html_writer::link(null, $img);
        $o .= html_writer::tag('div', $link, array('class' => 'gthumbnail'));
        $o .= html_writer::start_tag('div', array('class' => 'title'));
        $o .= $this->output->heading(format_string($item->caption), 6);
        $o .= html_writer::end_tag('div');

        $o .= html_writer::start_tag('div', array('class' => 'controls'));

        $this->page->requires->yui_module('moodle-mod_mediagallery-base', 'M.mod_mediagallery.base.add_item_info_modal', array($item->get_metainfo()), null, true);
        $url = new moodle_url('/mod/mediagallery/item.php', array('i' => $item->id, 'action' => 'info'));
        $o .= $this->output->action_icon($url, new pix_icon('i/info', get_string('information', 'mediagallery')), null, array('class' => 'action-icon info'));

        $url = new moodle_url('/mod/mediagallery/item.php', array('i' => $item->id, 'action' => 'delete'));
        $o .= $this->output->action_icon($url, new pix_icon('t/delete', get_string('delete')), null, array('class' => 'action-icon delete'));

        $url = new moodle_url('/mod/mediagallery/item.php', array('i' => $item->id));
        $o .= $this->output->action_icon($url, new pix_icon('t/edit', get_string('edit')));

        $o .= html_writer::end_tag('div');

        $o .= html_writer::end_tag('div');
        return $o;
    }

    protected function list_other_items($items) {
        $o = html_writer::start_tag('ul');
        foreach ($items as $item) {
            $image = $this->output->pix_icon($item->file_icon(), $item->caption, 'moodle', array('class'=>'icon'));
            $entry = html_writer::link($item->get_embed_url(), $image.$item->caption);
            $o .= html_writer::tag('li', $entry);
        }
        $o .= html_writer::end_tag('ul');
        return $o;
    }

    public function view_carousel(gallery $gallery, array $options = array()) {
        $o = html_writer::start_tag('div', array('class' => 'jcarousel-wrapper'));
        $o .= html_writer::start_tag('div', array('class' => 'jcarousel type_'.$gallery->type(true), 'data-jcarousel' => 'true', 'data-wrap' => 'circular'));

        $o .= html_writer::start_tag('ul');
        foreach ($gallery->get_items_by_type() as $item) {
            if (!$item->display) {
                continue;
            }
            $itemhtml = html_writer::empty_tag('img', array('src' => $item->get_image_url_by_type('thumbnail')));
            $attribs = $this->linkattribs($gallery, $item);
            if (!empty($options['filter'])) {
                $attribs['href'] = new moodle_url('/mod/mediagallery/view.php', array('g' => $gallery->id));
            } else {
                $attribs['href'] = $item->get_image_url_by_type('lowres');
            }
            if ($gallery->gallerytype == MEDIAGALLERY_TYPE_AUDIO) {
                $itemhtml .= $this->embed_html($item);
            }
            $o .= html_writer::tag('li', html_writer::tag('a', $itemhtml, $attribs));
        }
        $o .= html_writer::end_tag('ul');

        $o .= html_writer::end_tag('div');
        $o .= html_writer::tag('a', '&lsaquo;', array('data-jcarousel-control' => 'true', 'data-target' => '-=1',
            'href' => '#', 'class' => 'jcarousel-control-prev'));
        $o .= html_writer::tag('a', '&rsaquo;', array('data-jcarousel-control' => 'true', 'data-target' => '+=1',
            'href' => '#', 'class' => 'jcarousel-control-next'));
        $o .= html_writer::tag('p', '', array('data-jcarouselpagination' => 'true', 'class' => 'jcarousel-pagination'));
        $o .= html_writer::end_tag('div');

        // For whatever reason, including the JS earlier doesn't work.
        $o .= html_writer::tag('script', '', array(
            'type' => 'text/javascript',
            'src' => new moodle_url('/theme/jquery.php/mod_mediagallery/jcarousel/jquery.jcarousel.v2.js'),
        ));

        return $o;
    }

    public function embed_html($item) {
        $mediarenderer = $this->page->get_renderer('core', 'media');
        return $mediarenderer->embed_url(new moodle_url($item->get_embed_url()));
    }

    protected function linkattribs($gallery, $item) {
        $type = $item->type();
        $player = $type == MEDIAGALLERY_TYPE_AUDIO || ($type == MEDIAGALLERY_TYPE_VIDEO && $item->externalurl == '') ? $type : 1;
        $attribs = array(
            'data-mediabox' => 'gallery_'.$gallery->id,
            'data-lightbox' => 'gallery_'.$gallery->id,
            'title' => $item->caption,
            'data-id' => $item->id,
            'data-type' => $item->externalurl != '' ? 'external' : 'internal',
            'data-player' => $player,
            'data-url' => $item->get_embed_url(),
        );
        return $attribs;
    }

    protected function view_grid(gallery $gallery, array $options) {
        $o = '';

        $column = 1;
        $row = 1;
        $rowopen = false;
        $perpage = $gallery->gridcolumns * $gallery->gridrows;
        $offset = $perpage * $options['page'];

        $cappos = $gallery->get_collection()->captionposition;

        $items = $gallery->get_items_by_type();
        foreach ($items as $item) {
            if (!$item->display) {
                continue;
            }
            if ($offset) {
                $offset--;
                continue;
            }
            if ($column > $gallery->gridcolumns && $gallery->gridcolumns != 0) {
                // Row complete.
                $o .= html_writer::end_tag('div');
                $rowopen = false;
                $column = 1;
                $row++;
            }
            if ($column == 1) {
                $o .= html_writer::start_tag('div', array('class' => 'grid_row clearfix'));
                $rowopen = true;
            }
            if ($row > $gallery->gridrows && $gallery->gridrows != 0) {
                // Grid is now full.
                break;
            }

            $caption = html_writer::tag('div', $item->caption, array('class' => 'caption'));
            $img = html_writer::empty_tag('img', array('src' => $item->get_image_url_by_type('thumbnail')));
            $linkattribs = $this->linkattribs($gallery, $item);
            $link = html_writer::link($item->get_image_url_by_type('lowres'), $img, $linkattribs);

            $itemframe = '';
            if ($cappos == MEDIAGALLERY_POS_TOP) {
                $itemframe .= $caption;
            }
            $itemframe .= html_writer::tag('div', $link, array('class' => 'item-thumb'));
            if ($gallery->gallerytype == MEDIAGALLERY_TYPE_AUDIO) {
                $itemframe .= $this->embed_html($item);
            }
            if ($cappos == MEDIAGALLERY_POS_BOTTOM) {
                $itemframe .= $caption;
            }
            $url = new moodle_url('/mod/mediagallery/item.php', array('i' => $item->id, 'action' => 'info'));
            $itemframe .= html_writer::tag('div',
                $this->output->action_icon($url, new pix_icon('i/info', get_string('information', 'mediagallery')), null, array('class' => 'action-icon info')),
                array('class' => 'info')
            );
            $itemframe = html_writer::tag('div', $itemframe, array('class' => 'item-wrapper'));

            $o .= html_writer::tag('div', $itemframe, array('class' => 'item grid_item', 'data-id' => $item->id,
                'data-title' => $item->caption, 'id' => 'gallery_item_'.$item->id));
            $this->page->requires->yui_module('moodle-mod_mediagallery-base', 'M.mod_mediagallery.base.add_item_info_modal', array($item->get_metainfo()), null, true);


            $column++;
        }
        if ($rowopen) {
            $o .= html_writer::end_tag('div');
        }
        $count = count($items);
        if ($count > $perpage && $perpage != 0) {
            $url = new moodle_url('/mod/mediagallery/view.php', array('g' => $gallery->id, 'page' => $options['page']));
            $o .= $this->output->paging_bar($count, $options['page'], $perpage, $url);
        }

        return $o;
    }

    public function search_results($items, $totalcount, $page, $perpage) {
        $counts = new stdClass();
        $counts->total = $totalcount;
        $counts->from = $page*$perpage + 1;
        $counts->to = ($page+1)*$perpage;
        if ($counts->to > $totalcount) {
            $counts->to = $totalcount;
        }
        $o = get_string('searchdisplayxtoyofzresults', 'mediagallery', $counts);
        $o .= html_writer::start_tag('ol', array('start' => $counts->from));
        foreach ($items as $item) {
            $url = new moodle_url('/mod/mediagallery/view.php', array('g' => $item->galleryid));
            $text = html_writer::link($url, $item->caption);
            $o .= html_writer::tag('li', $text);
        }
        $o .= html_writer::end_tag('ol');

        $o .= $this->output->paging_bar($totalcount, $page, $perpage, $this->page->url, 'page');
        return $o;
    }

    public function storage_report($usagedata) {

        $size = $this->convert_size($usagedata['total']);
        $o = get_string('storagetotalusage', 'mediagallery', $size);
        $o .= html_writer::empty_tag('br');
        $o .= html_writer::empty_tag('br');

        $catlist = coursecat::make_categories_list();
        $o .= html_writer::start_tag('ul');
        foreach ($catlist as $catid => $catname) {
            if (empty($usagedata['category'][$catid])) {
                continue;
            }
            $o .= html_writer::start_tag('li');
            $o .= $catname;
            $o .= html_writer::start_tag('ul');
            foreach ($usagedata['category'][$catid] as $courseid) {
                $link = html_writer::link(new moodle_url('/course/view.php', array('id' => $courseid)), $usagedata['course'][$courseid]->fullname);
                $entry = $link.' : '.$this->convert_size($usagedata['course'][$courseid]->sum);
                $o .= html_writer::tag('li', $entry);
            }
            $o .= html_writer::end_tag('ul');
        }
        $o .= html_writer::end_tag('ul');

        return $o;
    }

    private function convert_size($size) {
        // TODO: Load once only.
        $gb = ' ' . get_string('sizegb');
        $mb = ' ' . get_string('sizemb');
        $kb = ' ' . get_string('sizekb');
        $b  = ' ' . get_string('sizeb');
        if ($size >= 1073741824) {
            $size = number_format(round($size / 1073741824 * 10, 1) / 10, 1) . $gb;
        } else if ($size >= 1048576) {
            $size = number_format(round($size / 1048576 * 10) / 10) . $mb;
        } else if ($size >= 1024) {
            $size = number_format(round($size / 1024 * 10) / 10) . $kb;
        } else {
            $size = number_format($size) . $b;
        }
        return $size;
    }
}
