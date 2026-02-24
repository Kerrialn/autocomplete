# Implementation Plan

- [x] 1. Write bug condition exploration test
  - **Property 1: Fault Condition** - Pre-filled Multi-Select Values Validate Successfully
  - **CRITICAL**: This test MUST FAIL on unfixed code - failure confirms the bug exists
  - **DO NOT attempt to fix the test or the code when it fails**
  - **NOTE**: This test encodes the expected behavior - it will validate the fix when it passes after implementation
  - **GOAL**: Surface counterexamples that demonstrate the bug exists
  - **Scoped PBT Approach**: Scope the property to concrete failing cases - multi-select autocomplete forms with pre-filled values submitted without modification
  - Test that forms with pre-filled multi-select autocomplete values (chips displayed) validate successfully when submitted without user modification
  - Test implementation details from Fault Condition: `input.fieldType == 'EntityType' AND input.autocomplete == true AND input.multiple == true AND input.hasPrefilledValues == true AND input.userModifiedValues == false`
  - The test assertions should verify that validation passes and entities match the pre-filled values
  - Test cases to include:
    - Basic pre-filled submission: Form with 2 pre-filled language entities, submit without changes
    - Post-validation refresh: Trigger validation error on another field, refresh form, resubmit pre-filled values
    - Multiple pre-filled items: Form with 5+ pre-filled entities, submit without changes
    - Empty value mixed in: Simulate empty string in submitted array alongside valid IDs
  - Run test on UNFIXED code
  - **EXPECTED OUTCOME**: Test FAILS with validation errors like "Please select a valid choice" or "Please select a valid language" (this is correct - it proves the bug exists)
  - Document counterexamples found: validation errors, data format in reverseTransform (may show {id, label} objects instead of scalar IDs)
  - Inspect data format received by `EntityToIdentifierTransformer.reverseTransform()` to confirm root cause
  - Mark task complete when test is written, run, and failure is documented
  - _Requirements: 1.1, 1.2, 1.3, 2.1, 2.2, 2.3_

- [x] 2. Write preservation property tests (BEFORE implementing fix)
  - **Property 2: Preservation** - Newly Selected and Modified Values Continue Working
  - **IMPORTANT**: Follow observation-first methodology
  - Observe behavior on UNFIXED code for non-buggy inputs (cases where isBugCondition returns false)
  - Write property-based tests capturing observed behavior patterns from Preservation Requirements
  - Property-based testing generates many test cases for stronger guarantees
  - Test cases to observe and encode:
    - Newly selected values: Observe that selecting new values from dropdown works correctly, write property test to verify
    - Modified selections: Observe that adding/removing chips works correctly, write property test to verify
    - Single-select fields: Observe that single-select autocomplete fields work correctly, write property test to verify
    - Empty value filtering: Observe that empty strings are filtered out before validation, write property test to verify
  - For all non-buggy inputs (newly selected, modified, single-select), verify that transformation and validation produce the same results as unfixed code
  - Run tests on UNFIXED code
  - **EXPECTED OUTCOME**: Tests PASS (this confirms baseline behavior to preserve)
  - Mark task complete when tests are written, run, and passing on unfixed code
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

- [x] 3. Fix for multi-select autocomplete pre-filled validation bug

  - [x] 3.1 Implement the fix in EntityToIdentifierTransformer
    - Modify `src/Form/DataTransformer/EntityToIdentifierTransformer.php` reverseTransform() method
    - Add logic to normalize submitted data format: detect and handle {id, label} objects that may be submitted when chips are pre-rendered
    - Extract the 'id' field from objects before processing
    - Improve empty value filtering: ensure empty strings, null values, and empty arrays are consistently filtered out before entity lookup
    - Enhance defensive array handling: improve the existing `\is_array($id)` check to handle nested array structures and ensure robust extraction of scalar IDs
    - Add debug logging (via trigger_error or logger) to track incoming data format during reverseTransform for troubleshooting
    - Review and potentially enhance normalization logic in `templates/form/autocomplete_widget.html.twig` to ensure consistent handling during form re-submission
    - _Bug_Condition: isBugCondition(input) where input.fieldType == 'EntityType' AND input.autocomplete == true AND input.multiple == true AND input.hasPrefilledValues == true AND input.userModifiedValues == false_
    - _Expected_Behavior: For all inputs where isBugCondition(input), reverseTransform returns array of valid entities, validation passes, and entities match pre-filled values_
    - _Preservation: For all inputs where NOT isBugCondition(input), reverseTransform produces same result as original code (newly selected values, modified selections, single-select fields, empty value filtering all work identically)_
    - _Requirements: 1.1, 1.2, 1.3, 2.1, 2.2, 2.3, 3.1, 3.2, 3.3, 3.4, 3.5_

  - [x] 3.2 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - Pre-filled Multi-Select Values Validate Successfully
    - **IMPORTANT**: Re-run the SAME test from task 1 - do NOT write a new test
    - The test from task 1 encodes the expected behavior
    - When this test passes, it confirms the expected behavior is satisfied
    - Run bug condition exploration test from step 1
    - **EXPECTED OUTCOME**: Test PASSES (confirms bug is fixed)
    - Verify that forms with pre-filled multi-select autocomplete values now validate successfully
    - Verify that all test cases pass: basic pre-filled submission, post-validation refresh, multiple pre-filled items, empty value handling
    - _Requirements: 2.1, 2.2, 2.3_

  - [x] 3.3 Verify preservation tests still pass
    - **Property 2: Preservation** - Newly Selected and Modified Values Continue Working
    - **IMPORTANT**: Re-run the SAME tests from task 2 - do NOT write new tests
    - Run preservation property tests from step 2
    - **EXPECTED OUTCOME**: Tests PASS (confirms no regressions)
    - Confirm all tests still pass after fix: newly selected values, modified selections, single-select fields, empty value filtering
    - Verify no regressions in existing functionality
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

- [x] 4. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise
