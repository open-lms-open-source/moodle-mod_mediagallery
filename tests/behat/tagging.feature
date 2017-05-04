@mod @mod_mediagallery
Feature: Users can tag items, galleries and collections
  Users should be able to [un]set tags for things when
  editing them.

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
      | mediagallery | Contributed collection   | Test mediacollection description | C1     | mediagallery2 | 0         | contributed  |

  Scenario: Add and remove tags for a collection
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Contributed collection"
    And I click on "Edit settings" "link"
    And I set the field "Tags" to "tag1,tag2"
    And I press "Save and display"
    Then I should see "tag1, tag2"
    When I click on "Edit settings" "link"
    And I set the field "Tags" to "tag2"
    And I press "Save and display"
    Then I should see "tag2"
    And I should not see "tag1"

  Scenario: Add and remove tags for a gallery
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I add a new gallery to "Contributed collection" media gallery with:
      | Gallery name | GalleryYT |
      | Gallery mode | YouTube   |
    And I follow "Edit gallery settings"
    And I set the field "Tags" to "tag3, tag4, tag5"
    And I press "Save changes"
    Then I should see "tag3, tag4, tag5"
    When I follow "Edit gallery settings"
    And I set the field "Tags" to "tag3, tag5"
    And I press "Save changes"
    Then I should see "tag3"
    And I should see "tag5"
    And I should not see "tag4"

  Scenario: Add and remove tags for an item
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I add a new gallery to "Contributed collection" media gallery with:
      | Gallery name | GalleryYT |
      | Gallery mode | YouTube   |
    And I follow "Add an item"
    And I set the following fields to these values:
      | Caption | Some video |
      | YouTube URL | https://www.youtube.com/watch?v=CuKhBKAgQcA |
    And I press "Save changes"
    Then I should see "Some video"
    When I click on ".gallery_items .controls .edit" "css_element"
    And I set the field "Tags" to "tag6, tag7"
    And I press "Save changes"
    When I click on ".gallery_items .controls .edit" "css_element"
    And "//*[@id=\"id_tags\"][contains(@value, 'tag6, tag7')]" "xpath_element" should exist
    And I set the field "Tags" to ""
    And I press "Save changes"
    When I click on ".gallery_items .controls .edit" "css_element"
    And "//*[@id=\"id_tags\"][contains(@value, 'tag6, tag7')]" "xpath_element" should not exist
