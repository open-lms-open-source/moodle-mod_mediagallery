@mod @mod_mediagallery
Feature: Add mediagallery activities and galleries
  In order to create galleries with other users
  As a teacher
  I need to add mediagallery activities to moodle courses

  @javascript
  Scenario: Add a mediagallery and a gallery
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@asd.com |
    And the following "courses" exists:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exists:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
    And I log in as "teacher1"
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "Media gallery" to section "1" and I set the following fields to these values:
      | Media gallery name | Test mg name |
      | Description | Test mg description |
    When I add a new gallery to "Test mg name" media gallery with:
      | Gallery name | Gallery1 |
    And I wait "6" seconds
    Then I should see "Gallery1"
    When I follow "Add an item"
    And I set the following fields to these values:
      | Caption | The moodle logo |
    And I upload "mod/mediagallery/tests/fixtures/moodle-logo.jpg" file to "Content" filepicker
    And I press "Save changes"
    Then I should see "The moodle logo"
    And ".gallery_items .item" "css_element" should exists
