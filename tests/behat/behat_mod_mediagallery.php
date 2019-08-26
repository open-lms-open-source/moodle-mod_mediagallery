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
 * Steps definitions related with the forum activity.
 *
 * @package    mod_mediagallery
 * @category   test
 * @copyright  2014 NetSpot Pty Ltd
 * @author     Adam Olley <adam.olley@netspot.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Behat\Context\Step\Given as Given,
    Behat\Gherkin\Node\TableNode as TableNode;
/**
 * Media gallery-related steps definitions.
 *
 * @package    mod_mediagallery
 * @category   test
 * @copyright  2014 NetSpot Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_mediagallery extends behat_base {

    /**
     * Adds a gallery to the mediagallery specified by it's name with the provided table data.
     * The step begins from the mediagallery's course page.
     *
     * @Given /^I add a new gallery to "(?P<mediagallery_name_string>(?:[^"]|\\")*)" media gallery with:$/
     * @param string $name
     * @param TableNode $table
     */
    public function i_add_a_gallery_to_mediagallery_with($name, TableNode $table) {
        $this->execute('behat_general::click_link', [$this->escape($name)]);
        $this->execute('behat_general::click_link', [get_string('addagallery', 'mediagallery')]);
        $this->execute('behat_forms::i_set_the_following_fields_to_these_values', $table);
        $this->execute('behat_forms::press_button', get_string('savechanges'));
        $this->execute('behat_general::i_wait_to_be_redirected');
    }

    /**
     * We assume we're on the gallery page in editing mode.
     *
     * @Given /^I add a new item to "([^"]*)" gallery uploading "([^"]*)" with:$/
     * @param string $gallery
     * @param string $file
     * @param TableNode $table
     */
    public function i_add_a_new_item_to_gallery_uploading_with($gallery, $file, TableNode $table) {
        $this->execute('behat_general::click_link', [get_string('addanitem', 'mediagallery')]);
        $this->execute('behat_forms::i_set_the_following_fields_to_these_values', $table);
        $uploadcontext = behat_context_helper::get('behat_repository_upload');
        $uploadcontext->i_upload_file_to_filemanager($file, 'Content');
        $this->execute('behat_forms::press_button', get_string('savechanges'));
        $this->execute('behat_general::i_wait_to_be_redirected');
    }

    /**
     * View the current page with all groups selected.
     *
     * @Given /^I go to view with all groups$/
     */
    public function i_goto_view_with_all_groups() {
        $s = $this->getSession();
        $s->visit($s->getCurrentUrl()."&group=0");
    }

}
