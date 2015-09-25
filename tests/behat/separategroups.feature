@mod @mod_mediagallery
Feature: Separate users galleries based on groups
  In order to separate users into groups that can
  only see their own galleries
  As a teacher
  I need to setup a mediagallery in separate groups
  mode

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email            |
      | teacher1 | Teacher   | 1        | teacher1@asd.com |
      | student1 | Student   | 1        | student1@asd.com |
      | student2 | Student   | 2        | student2@asd.com |
      | student3 | Student   | 3        | student2@asd.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | student3 | C1     | student        |
    And the following "groups" exist:
      | name   | idnumber | course |
      | GroupA | g1       | C1     |
      | GroupB | g2       | C1     |
    And the following "group members" exist:
      | user     | group |
      | student1 | g1    |
      | student2 | g2    |
      | student3 | g1    |
    And the following "activities" exist:
      | activity     | name                | intro                         | course | idnumber      | groupmode |
      | mediagallery | Test mg groups name | Test mediagallery description | C1     | mediagallery1 | 1         |
      | mediagallery | Test mg groups visi | Test mediagallery description | C1     | mediagallery1 | 2         |

  Scenario: Only group members should see their groups galleries
    When I log in as "student1"
    And I follow "Course 1"
    And I follow "Test mg groups name"
    And I add a new gallery to "Test mg groups name" media gallery with:
      | Gallery name | Gallery1 |
    And I should see "Gallery1"
    Then I log out
    When I log in as "student2"
    And I follow "Course 1"
    And I follow "Test mg groups name"
    Then I should not see "Gallery1"
    When I log out
    And I log in as "student3"
    And I follow "Course 1"
    And I follow "Test mg groups name"
    Then I should see "Gallery1"
    When I log out
    And I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Test mg groups name"
    Then I should see "Gallery1"

  Scenario: In visible groups mode, all galleries should be visible, but not editable
    When I log in as "student1"
    And I follow "Course 1"
    And I follow "Test mg groups visi"
    And I add a new gallery to "Test mg groups visi" media gallery with:
      | Gallery name | Gallery1 |
    Then I should see "Gallery1"
    When I log out
    And I log in as "student2"
    And I follow "Course 1"
    And I follow "Test mg groups visi"
    And I go to view with all groups
    Then I should see "Gallery1"
    And ".gallery_list_item[data-title=\"Gallery1\"] .controls .delete" "css_element" should not exist
    When I log out
    And I log in as "student3"
    And I follow "Course 1"
    And I follow "Test mg groups visi"
    Then I should see "Gallery1"
    And ".gallery_list_item[data-title=\"Gallery1\"] .controls .delete" "css_element" should exist
