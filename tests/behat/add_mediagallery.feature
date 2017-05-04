@mod @mod_mediagallery
Feature: Add mediagallery activities and galleries
  In order to create galleries with other users
  As a teacher
  I need to add mediagallery activities to moodle courses

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student        |
    And the following "activities" exist:
      | activity     | name                     | intro                            | course | idnumber      | groupmode | colltype     |
      | mediagallery | Instructor collection    | Test mediacollection description | C1     | mediagallery1 | 0         | instructor   |
      | mediagallery | Contributed collection   | Test mediacollection description | C1     | mediagallery2 | 0         | contributed  |
      | mediagallery | Assignment collection    | Test mediacollection description | C1     | mediagallery2 | 0         | assignment   |
      | mediagallery | Peer reviewed collection | Test mediacollection description | C1     | mediagallery2 | 0         | peerreviewed |

  @javascript
  Scenario: Add a mediagallery and a standard gallery
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I turn editing mode on
    And I add a "Media collection" to section "1" and I fill the form with:
      | Media collection name | Test mg name |
      | Description | Test mg description |
    When I add a new gallery to "Test mg name" media gallery with:
      | Gallery name | Gallery1 |
      | Gallery mode | Standard |
    Then I should see "Gallery1"
    When I follow "Add an item"
    And I set the following fields to these values:
      | Caption | The moodle logo |
    And I upload "mod/mediagallery/tests/fixtures/moodle-logo.jpg" file to "Content" filemanager
    And I press "Save changes"
    Then I should see "The moodle logo"
    And ".gallery_items .item" "css_element" should exist
    And I follow "View gallery"
    Then I should see "Gallery focus"
    And I should not see "Other files"
    And I click on "Video" "option" in the ".focus_selector select" "css_element"
    Then I should see "Other files"

  Scenario: Add a youtube gallery to a collection
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I add a new gallery to "Instructor collection" media gallery with:
      | Gallery name | GalleryYT |
      | Gallery mode | YouTube   |
    Then I should see "GalleryYT"
    And I follow "Add an item"
    And I set the following fields to these values:
      | Caption | Some video |
      | YouTube URL | https://www.youtube.com/watch?v=CuKhBKAgQcA |
    And I press "Save changes"
    Then I should see "Some video"
    And ".gallery_items .item" "css_element" should exist
    And I follow "View gallery"
    Then I should not see "Gallery focus"

  Scenario: Student cannot add a gallery to an instructor collection
   Given I log in as "student1"
   And I am on "Course 1" course homepage
   And I follow "Instructor collection"
   Then I should not see "Add a gallery"
