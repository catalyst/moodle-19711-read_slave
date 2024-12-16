@gradereport @gradereport_grader @gradereport_grader_deduction
Feature: As a teacher, I want to override a grade with a deduction and check the gradebook.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And I enable penalty for overridden grade
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "grade items" exist:
      | itemname        | grademin | grademax | course |
      | Manual grade 01 | 0       | 100       | C1     |
      | Manual grade 02 | 0       | 100       | C1     |
    And the following "grade grades" exist:
      | gradeitem       | user     | grade | deductedmark |
      | Manual grade 01 | student1 | 60    | 10           |
      | Manual grade 02 | student1 | 80    | 20           |
    When I log in as "teacher1"

  @javascript
  Scenario: Override a grade with a deduction and check the gradebook
    And I am on "Course 1" course homepage
    And I navigate to "View > Grader report" in the course gradebook
    And the following should exist in the "user-grades" table:
      | -1-                | -2-                  | -3-       | -4-       | -5-       |
      | Student 1          | student1@example.com | 60        | 80        | 140       |
    And I turn editing mode on
    And I set the field "Student 1 Manual grade 01 grade" to "80"
    And I click on "Deduct 10.00" "checkbox"
    And I click on "Save changes" "button"
    And I turn editing mode off
    And the following should exist in the "user-grades" table:
      | -1-                | -2-                  | -3-       | -4-       | -5-       |
      | Student 1          | student1@example.com | 70        | 80        | 150       |
    And I turn editing mode on
    And I set the field "Student 1 Manual grade 02 grade" to "100"
    And I click on "Save changes" "button"
    And I turn editing mode off
    And the following should exist in the "user-grades" table:
      | -1-                | -2-                  | -3-       | -4-       | -5-       |
      | Student 1          | student1@example.com | 70        | 100       | 170       |
