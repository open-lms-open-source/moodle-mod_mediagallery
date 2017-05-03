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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/form/url.php');

class MoodleQuickForm_limitedurl extends MoodleQuickForm_url {
    // HTML for help button, if empty then no help.
    public $_helpbutton = '';

    // If true label will be hidden.
    public $_hiddenLabel = false;

    /**
     * Returns HTML for this form element.
     *
     * @return string
     */
    public function toHtml() {
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

        $clientid = uniqid();

        $args = new stdClass();
        $args->accepted_types = '*';
        $args->return_types = FILE_EXTERNAL;
        $args->context = $PAGE->context;
        $args->client_id = $clientid;
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
<button id="filepicker-button-{$clientid}" class="visibleifjs">
$straddlink
</button>
EOD;
        }

        // Print out file picker.
        $str .= $OUTPUT->render($fp);

        $module = [
            'name' => 'form_url',
            'fullpath' => '/lib/form/url.js',
            'requires' => ['core_filepicker']
        ];
        $PAGE->requires->js_init_call('M.form_url.init', array($options), true, $module);

        return $str;
    }

}

MoodleQuickForm::registerElementType('limitedurl',
    $CFG->dirroot."/mod/mediagallery/classes/quickform/limitedurl.php", 'MoodleQuickForm_limitedurl');
