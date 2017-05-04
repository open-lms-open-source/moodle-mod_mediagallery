@mod @mod_mediagallery
Feature: Social interactions with items
  Items can be liked and commented on.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email            |
      | teacher1 | Teacher   | 1        | teacher1@asd.com |
      | student1 | Student   | 1        | student1@asd.com |
      | student2 | Student   | 2        | student2@asd.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
    And the following "activities" exist:
      | activity     | name                | intro                         | course | idnumber      | groupmode |
      | mediagallery | Test mg groups name | Test mediagallery description | C1     | mediagallery1 | 0         |

  @javascript
  Scenario: Liking an item
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test mg groups name"
    And I add a new gallery to "Test mg groups name" media gallery with:
      | Gallery name | Gallery1 |
    And I should see "Gallery1"
    And I add a new item to "Gallery1" gallery uploading "mod/mediagallery/tests/fixtures/moodle-logo.jpg" with:
      | Caption | Test item |
    And I follow "View gallery"
    And I click on ".jcarousel a[title=\"Test item\"]" "css_element"
    And I wait "2" seconds
    And I click on "#mediabox a.like" "css_element"
    And I wait "1" seconds
    Then I should see "Unlike"
    And I click on "#mediabox .mbclose" "css_element"
    When I log out
    And I log in as "student2"
    And I am on "Course 1" course homepage
    And I follow "Test mg groups name"
    And I click on ".gallery_list_item[data-title=\"Gallery1\"] a" "css_element"
    And I wait to be redirected
    And I click on ".jcarousel a[title=\"Test item\"]" "css_element"
    And I wait "2" seconds
    Then I should see "Liked by: 1 other"
