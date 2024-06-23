@core @core_grades @gradepenalty_duedate @penalty_rule @javascript
Feature: As an administrator
  I need to add new penalty rule
  I need to edit penalty rule
  I need to delete penalty rule

  Background:
    # Create first rule.F
    Given I log in as "admin"
    And I navigate to "Grades > Grade penalty > Common settings" in site administration
    And I click on "Grade penalty" "checkbox"
    And I click on "Save changes" "button"
    Then I should see "Changes saved"
    And I navigate to "Grades > Grade penalty > Manage penalty plugins" in site administration
    And I click on "Enable Penalty for late submission" "checkbox"
    And I navigate to "Grades > Grade penalty > Penalty for late submission > Set up penalty rules" in site administration

  Scenario: Edit, add, delete a penalty rule
    And I click on "Add 3 rules" "button"
    And I set the following fields to these values:
      | latefor[0][number]      |  0   |
      | penalty[0]              |  -1  |
    And I click on "Save changes" "button"
    Then I should see "The overdue must be greater than or equal to 1 sec. The penalty must be greater than or equal to 0.0%."
    And I set the following fields to these values:
      | latefor[0][number]      |  1   |
      | penalty[0]              |  10  |
    And I click on "Save changes" "button"
    Then I should see "The overdue must be greater than the value of above rule: 1 day. The penalty must be greater than the value of above rule: 10.0%."
    And I set the following fields to these values:
      | latefor[1][number]      |  2   |
      | penalty[1]              |  20  |
    And I click on "Save changes" "button"
    Then I should see "The overdue must be greater than the value of above rule: 2 days. The penalty must be greater than the value of above rule: 20.0%."
    And I set the following fields to these values:
      | latefor[2][number]      |  3   |
      | penalty[2]              |  101 |
    And I click on "Save changes" "button"
    Then I should see "The penalty cannot be greater than 100.0%."
    And I set the following fields to these values:
      | latefor[2][number]      |  3   |
      | penalty[2]              |  100 |
    And I click on "Save changes" "button"
    Then I should see "Changes saved"
    And I navigate to "Grades > Grade penalty > Penalty for late submission > Set up penalty rules" in site administration
    Then I should see "Penalty rule 1"
    Then I should see "Penalty rule 2"
    Then I should see "Penalty rule 3"
    Then I click on "deleterule[2]" "button"
    And I click on "Save changes" "button"
    Then I should see "Changes saved"
    And I navigate to "Grades > Grade penalty > Penalty for late submission > Set up penalty rules" in site administration
    Then I should see "Penalty rule 1"
    Then I should see "Penalty rule 2"
    And I should not see "Penalty rule 3"
