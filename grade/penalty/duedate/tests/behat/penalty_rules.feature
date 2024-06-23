@core @core_grades @gradepenalty_duedate @penalty_rule @javascript
Feature: As an administrator
  I need to add new penalty rule
  I need to edit penalty rule
  I need to delete penalty rule

  Background:
    # Create first rule.
    Given I log in as "admin"
    And I navigate to "Grades > Grade penalty > Common settings" in site administration
    And I click on "Grade penalty" "checkbox"
    And I click on "Save changes" "button"
    Then I should see "Changes saved"
    And I navigate to "Grades > Grade penalty > Manage penalty plugins" in site administration
    Then "Enable" "icon" should exist in the "Penalty for late submission" "table_row"
    And I click on "Enable" "icon" in the "Penalty for late submission" "table_row"
    And I navigate to "Grades > Grade penalty > Penalty for late submission > Set up penalty rules" in site administration
    And I click on "Click here to add a penalty rule" "link"
    And I set the following fields in the "Late" "fieldset" to these values:
      | Time      | 1      |
      | Time unit | days   |
    And I set the field "Penalty" to "50"
    And I click on "Save changes" "button"
    Then I should see "50.0%" in the "1 day" "table_row"

  Scenario: Edit, add, delete a penalty rule
    # Edit rule.
    And I open the action menu in "1 day" "table_row"
    And I choose "Edit" in the open action menu
    And I set the field "Penalty" to "55"
    And I click on "Save changes" "button"
    Then I should see "55.0%" in the "1 day" "table_row"
    # Insert new rule above.
    And I open the action menu in "1 day" "table_row"
    And I choose "Insert rule above" in the open action menu
    And I set the following fields in the "Late" "fieldset" to these values:
      | Time      | 1       |
      | Time unit | hours   |
    And I set the field "Penalty" to "10"
    And I click on "Save changes" "button"
    Then I should see "10.0%" in the "1 hour" "table_row"
    # Insert a penalty rule below.
    And I open the action menu in "1 day" "table_row"
    And I choose "Insert rule below" in the open action menu
    And I set the following fields in the "Late" "fieldset" to these values:
      | Time      | 2      |
      | Time unit | days   |
    And I set the field "Penalty" to "70"
    And I click on "Save changes" "button"
    Then I should see "70.0%" in the "2 days" "table_row"
    # Delete rule.
    And I should see "1 day"
    And I open the action menu in "1 day" "table_row"
    And I choose "Delete" in the open action menu
    Then I should not see "1 day"
    # Invalid penalty value.
    And I open the action menu in "2 days" "table_row"
    And I choose "Edit" in the open action menu
    And I set the following fields in the "Late" "fieldset" to these values:
      | Time      | 0       |
      | Time unit | seconds |
    And I set the field "Penalty" to "0"
    And I click on "Save changes" "button"
    Then I should see "Minimum value for late submission is 1 hour"
    And I should see "Minimum value for penalty is 11.0%"
    And I set the field "Penalty" to "101"
    And I click on "Save changes" "button"
    Then I should see "Maximum value for penalty is 100.0%"
