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

class viewcontroller {

    public $cm;
    public $context;
    public $course;
    public $collection;
    public $gallery;
    public $options;
    public $pageurl;
    public $renderer;

    public function __construct($context, $cm, $course, $collection, $gallery, $pageurl, $options = array()) {
        global $PAGE;
        $this->cm = $cm;
        $this->course = $course;
        $this->context = $context;
        $this->collection = $collection;
        $this->gallery = $gallery;
        $this->options = $options;
        $this->pageurl = $pageurl;
        $this->renderer = $PAGE->get_renderer('mod_mediagallery', $collection->mode);
    }

    public function header() {
        return $this->renderer->view_header($this);
    }

    /**
     * Routes to a valid action.
     *
     * @param string $action
     * @access public
     * @return string Rendered output for display.
     */
    public function display_action($action) {
        $this->renderer->setup_page($this);

        $method = "action_{$action}";
        if (!method_exists(__CLASS__, $method)) {
            throw new \invalid_parameter_exception("Unknown action: $action");
        }

        $output = $this->$method();

        $completion = new \completion_info($this->course);
        $completion->set_module_viewed($this->cm);

        $header = $this->header();
        $footer = $this->renderer->footer();

        return $header.$output.$footer;
    }

    public function action_search() {
        $urlparams = array(
            'search' => optional_param('search', null, PARAM_TEXT),
            'group' => optional_param('group', 0, PARAM_INT),
            'page' => optional_param('page', 1, PARAM_INT),
            'role' => optional_param('role', 0, PARAM_INT),
            'type' => optional_param('type', base::TYPE_ALL, PARAM_INT),
        );

        if (optional_param('resetbutton', 0, PARAM_ALPHA)) {
            redirect(new \moodle_url('/mod/mediagallery/view.php', array('action' => 'search', 'id' => $this->cm->id)));
        }

        $params = array_merge($urlparams, array(
            'collection' => $this->collection,
            'courseid' => $this->course->id,
            'context' => $this->context,
        ));

        $search = new mcsearch($params);
        $results = $search->get_results();

        if (optional_param('exportbutton', 0, PARAM_ALPHA)) {
            return $search->download_csv();
        };

        $form = new form\search(null, array('context' => $this->context, 'collection' => $this->collection),
            'post', '', array('id' => 'searchform'));

        $pageurl = new \moodle_url('/mod/mediagallery/search.php', $urlparams);

        $perpage = 0;
        $totalcount = 0;

        $renderable = new output\searchresults\renderable($results, $pageurl, $totalcount, $params['page'], $perpage);
        return $this->renderer->search_page($form, $renderable);
    }

    public function action_viewcollection() {
        $params = array(
            'context' => $this->context,
            'objectid' => $this->collection->id,
        );
        $event = event\course_module_viewed::create($params);
        $event->add_record_snapshot('course_modules', $this->cm);
        $event->add_record_snapshot('course', $this->course);
        $event->trigger();

        $output = '';
        $output .= groups_print_activity_menu($this->cm, $this->pageurl, true);

        if ($this->collection->intro) {
            $output .= $this->renderer->box(format_module_intro('mediagallery', $this->collection, $this->cm->id),
                'generalbox mod_introbox', 'mediagalleryintro');
        }

        $galleries = $this->collection->get_visible_galleries();
        $renderable = new output\collection\renderable($this->collection, $galleries);
        $output .= $this->renderer->render_collection($renderable);
        return $output;
    }

    public function action_viewgallery() {
        global $DB;
        $gallery = $this->gallery;
        $params = array(
            'context' => $this->context,
            'objectid' => $this->gallery->id,
        );
        $event = event\gallery_viewed::create($params);
        $event->add_record_snapshot('course_modules', $this->cm);
        $event->add_record_snapshot('course', $this->course);
        $event->trigger();

        $output = '';
        if ($gallery->user_can_contribute() || $gallery->user_can_view()) {
            if ($gallery->can_comment()) {
                $cmtopt = new \stdClass();
                $cmtopt->area = 'gallery';
                $cmtopt->context = $this->context;
                $cmtopt->itemid = $gallery->id;
                $cmtopt->showcount = true;
                $cmtopt->component = 'mod_mediagallery';
                $cmtopt->cm = $this->cm;
                $cmtopt->course = $this->course;
                $this->options['comments'] = new \comment($cmtopt);
                \comment::init();
            }
            $renderable = new output\gallery\renderable($gallery, $this->options['editing'], $this->options);
            $output .= $this->renderer->render_gallery($renderable);
        } else {
            print_error('nopermissions', 'error', $this->pageurl, 'view gallery');
        }
        return $output;
    }
}
