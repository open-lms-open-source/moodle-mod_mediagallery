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

require_once($CFG->libdir.'/form/url.php');

class MoodleQuickForm_limitedurl extends MoodleQuickForm_url {
    /** @var string html for help button, if empty then no help */
    var $_helpbutton='';

    /** @var bool if true label will be hidden */
    var $_hiddenLabel=false;

    /**
     * Constructor
     *
     * @param string $elementName Element name
     * @param mixed $elementLabel Label(s) for an element
     * @param mixed $attributes Either a typical HTML attribute string or an associative array.
     * @param array $options data which need to be posted.
     */
    function __construct($elementName = null, $elementLabel = null, $attributes = null, $options = null) {
        parent::__construct($elementName, $elementLabel, $attributes, $options);
    }

    /**
     * Legacy style constructor, for BC.
     * @deprecated since 2.9, use MoodleQuickForm_limitedurl::__construct instead
     */
    public function MoodleQuickForm_limitedurl() {
        $msg = 'Legacy constructor called, please update your code to call php5 constructor!';
        if (function_exists('debugging')) {
            debugging($msg, DEBUG_DEVELOPER);
        } else {
            trigger_error($msg, E_USER_DEPRECATED);
        }
        $args = func_get_args();
        call_user_func_array('self::__construct', $args);
    }

    /**
     * Returns HTML for this form element.
     *
     * @return string
     */
    function toHtml(){
        global $PAGE, $OUTPUT;

        $id     = $this->_attributes['id'];
        $elname = $this->_attributes['name'];

        if ($this->_hiddenLabel) {
            $this->_generateId();
            $str = '<label class="accesshide" for="'.$this->getAttribute('id').'" >'.
                        $this->getLabel().'</label>'.parent::toHtml();
        } else {
            $str = HTML_QuickForm_Text::toHtml();
        }
        if (empty($this->_options['usefilepicker'])) {
            return $str;
        }

        $client_id = uniqid();

        $args = new stdClass();
        $args->accepted_types = '*';
        $args->return_types = FILE_EXTERNAL;
        $args->context = $PAGE->context;
        $args->client_id = $client_id;
        $args->env = 'url';

        $refrepos = repository::get_instances(array(
            'currentcontext' => $PAGE->context,
            'return_types' => FILE_EXTERNAL,
        ));
        $disabled = array();
        foreach ($refrepos as $repo) {
            if (($name = $repo->get_typename()) != $this->_options['repo']) {
                $disabled[] = $name;
            }
        }
        $args->disable_types = $disabled;

        $fp = new file_picker($args);
        $options = $fp->options;

        if (count($options->repositories) > 0) {
            $straddlink = get_string('choosealink', 'repository');
            $str .= <<<EOD
<button id="filepicker-button-{$client_id}" class="visibleifjs">
$straddlink
</button>
EOD;
        }

        // print out file picker
        $str .= $OUTPUT->render($fp);

        $module = array('name'=>'form_url', 'fullpath'=>'/lib/form/url.js', 'requires'=>array('core_filepicker'));
        $PAGE->requires->js_init_call('M.form_url.init', array($options), true, $module);

        return $str;
    }

}

MoodleQuickForm::registerElementType('limitedurl', $CFG->dirroot."/mod/mediagallery/classes/quickform/limitedurl.php", 'MoodleQuickForm_limitedurl');
