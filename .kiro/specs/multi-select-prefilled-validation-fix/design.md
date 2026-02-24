# Multi-Select Autocomplete Pre-filled Validation Fix Design

## Overview

This design addresses a validation bug in the Symfony autocomplete bundle where multi-select autocomplete fields with pre-filled values fail validation on form submission. The bug manifests when users submit forms without modifying pre-existing selections, resulting in "Please select a valid choice" errors despite the values being valid database entities. The fix will ensure that pre-filled values are properly transformed and validated during form submission while preserving all existing functionality for newly selected, modified, and single-select autocomplete fields.

## Glossary

- **Bug_Condition (C)**: The condition that triggers the bug - when a multi-select autocomplete form with pre-filled values (chips displayed) is submitted without modification
- **Property (P)**: The desired behavior - pre-filled values should validate successfully and be preserved during form submission
- **Preservation**: Existing behavior for newly selected values, modified selections, single-select fields, and empty value filtering that must remain unchanged
- **EntityToIdentifierTransformer**: The data transformer in `src/Form/DataTransformer/EntityToIdentifierTransformer.php` that converts between entities and their identifiers for form processing
- **AutocompleteEntityTypeExtension**: The form extension in `src/Form/Extension/AutocompleteEntityTypeExtension.php` that configures autocomplete behavior for EntityType fields
- **selected_items**: The template variable containing `{id, label}` objects used to render chips for multi-select fields
- **Chip**: A visual UI element representing a selected item in a multi-select autocomplete field, containing a hidden input with the item's ID

## Bug Details

### Fault Condition

The bug manifests when a multi-select autocomplete form field has pre-filled values displayed as chips AND the form is submitted without the user modifying those selections. The validation system incorrectly rejects these pre-filled values even though they are valid entities from the database.

**Formal Specification:**
```
FUNCTION isBugCondition(input)
  INPUT: input of type FormSubmissionData
  OUTPUT: boolean
  
  RETURN input.fieldType == 'EntityType'
         AND input.autocomplete == true
         AND input.multiple == true
         AND input.hasPrefilledValues == true
         AND input.userModifiedValues == false
         AND validationFails(input.submittedValues)
END FUNCTION
```

### Examples

- **Example 1**: A user profile form has a "Languages" multi-select autocomplete field pre-filled with ["English", "Spanish"]. User submits the form without changing languages. Expected: Form validates successfully. Actual: Validation error "Please select a valid language".

- **Example 2**: A project form has a "Team Members" multi-select autocomplete pre-filled with 3 members. User submits to update a different field. Expected: Team members are preserved. Actual: Validation fails with "Please select a valid choice".

- **Example 3**: After a validation failure on another field, the form is refreshed and displays the original pre-filled chips. User resubmits. Expected: Pre-filled values are maintained. Actual: Values are overwritten with empty data, losing selections.

- **Edge Case**: A multi-select autocomplete has pre-filled values AND the user adds one more selection. Expected: All values (pre-filled + new) validate successfully. This should continue to work after the fix.

## Expected Behavior

### Preservation Requirements

**Unchanged Behaviors:**
- Newly selected values in multi-select autocomplete fields must continue to validate and save correctly
- Adding selections to pre-filled values must continue to work (both pre-filled and new values accepted)
- Removing selections from pre-filled values must continue to work (only remaining values accepted)
- Single-select autocomplete fields with pre-filled values must continue to validate correctly
- Empty strings or null values in submitted arrays must continue to be filtered out before validation

**Scope:**
All inputs that do NOT involve submitting unmodified pre-filled multi-select autocomplete values should be completely unaffected by this fix. This includes:
- Forms where users actively select new values from the autocomplete dropdown
- Forms where users modify existing selections (add or remove chips)
- Single-select autocomplete fields (not multi-select)
- Multi-select fields where users clear all values and select new ones

## Hypothesized Root Cause

Based on the bug description and code analysis, the most likely issues are:

1. **Data Format Mismatch in reverseTransform**: The `EntityToIdentifierTransformer.reverseTransform()` method may not be correctly handling the format of submitted data when chips are pre-rendered. The method expects an array of scalar IDs, but may be receiving a different structure (possibly `{id, label}` objects) on form re-submission.

2. **Empty Value Handling**: The transformer's reverseTransform method skips null or empty string values, but the validation layer may be counting these filtered-out values, causing a mismatch between submitted count and validated count.

3. **View Transformer Reset Issue**: The `AutocompleteEntityTypeExtension.buildForm()` method calls `$builder->resetViewTransformers()` to remove Symfony's default ChoiceToValueTransformer. This may be interfering with how pre-filled values are processed during form binding.

4. **Template Normalization Logic**: The `autocomplete_widget.html.twig` template has normalization logic to handle both `{id, label}` objects and scalar IDs, but this normalization may not be applied consistently during form submission, leading to validation failures.

## Correctness Properties

Property 1: Fault Condition - Pre-filled Multi-Select Values Validate Successfully

_For any_ form submission where a multi-select autocomplete field has pre-filled values (chips displayed) and the user submits without modifying those values, the fixed EntityToIdentifierTransformer SHALL successfully transform the submitted IDs back to their corresponding entities and pass validation, allowing the form to be processed with the pre-filled values intact.

**Validates: Requirements 2.1, 2.2, 2.3**

Property 2: Preservation - Newly Selected and Modified Values Continue Working

_For any_ form submission where a multi-select autocomplete field has newly selected values OR modified selections (added/removed chips) OR is a single-select field, the fixed code SHALL produce exactly the same validation and transformation behavior as the original code, preserving all existing functionality for non-pre-filled-only scenarios.

**Validates: Requirements 3.1, 3.2, 3.3, 3.4, 3.5**

## Fix Implementation

### Changes Required

Assuming our root cause analysis is correct:

**File**: `src/Form/DataTransformer/EntityToIdentifierTransformer.php`

**Function**: `reverseTransform()`

**Specific Changes**:
1. **Normalize Submitted Data Format**: Add logic at the beginning of `reverseTransform()` to detect and normalize `{id, label}` objects that may be submitted when chips are pre-rendered. Extract the `id` field from such objects before processing.

2. **Improve Empty Value Filtering**: Ensure that empty strings, null values, and empty arrays are consistently filtered out before entity lookup, and that this filtering happens early enough to prevent validation count mismatches.

3. **Add Defensive Array Handling**: The current code has a check for `\is_array($id)` that extracts `$id['id']`, but this may not be sufficient. Enhance this to handle nested array structures and ensure robust extraction of scalar IDs.

4. **Logging for Debugging**: Add temporary debug logging (via trigger_error or a logger) to track the format of incoming data during reverseTransform, which will help identify if the data format is the root cause.

**File**: `templates/form/autocomplete_widget.html.twig`

**Changes**:
5. **Ensure Consistent Normalization**: Review and potentially enhance the normalization logic that converts between scalar IDs and `{id, label}` objects to ensure it handles all edge cases during form re-submission.

## Testing Strategy

### Validation Approach

The testing strategy follows a two-phase approach: first, surface counterexamples that demonstrate the bug on unfixed code, then verify the fix works correctly and preserves existing behavior.

### Exploratory Fault Condition Checking

**Goal**: Surface counterexamples that demonstrate the bug BEFORE implementing the fix. Confirm or refute the root cause analysis. If we refute, we will need to re-hypothesize.

**Test Plan**: Create test forms with multi-select autocomplete fields pre-filled with database entities. Submit these forms without user interaction and capture the validation errors. Inspect the data format received by `reverseTransform()` to confirm the root cause.

**Test Cases**:
1. **Basic Pre-filled Submission**: Create a form with 2 pre-filled language entities, submit without changes (will fail on unfixed code with "Please select a valid choice")
2. **Post-Validation Refresh**: Trigger a validation error on another field, refresh the form, then resubmit the pre-filled autocomplete values (will fail and may lose data on unfixed code)
3. **Multiple Pre-filled Items**: Create a form with 5+ pre-filled entities, submit without changes (will fail on unfixed code)
4. **Empty Value Mixed In**: Simulate a scenario where an empty string is in the submitted array alongside valid IDs (may cause count mismatch on unfixed code)

**Expected Counterexamples**:
- Validation errors like "Please select a valid choice" or "Please select a valid language"
- Data format in `reverseTransform()` may show `{id, label}` objects instead of scalar IDs
- Possible causes: data format mismatch, empty value handling, view transformer interference

### Fix Checking

**Goal**: Verify that for all inputs where the bug condition holds, the fixed function produces the expected behavior.

**Pseudocode:**
```
FOR ALL input WHERE isBugCondition(input) DO
  result := EntityToIdentifierTransformer_fixed.reverseTransform(input)
  ASSERT result is array of valid entities
  ASSERT validation passes
  ASSERT entities match the pre-filled values
END FOR
```

### Preservation Checking

**Goal**: Verify that for all inputs where the bug condition does NOT hold, the fixed function produces the same result as the original function.

**Pseudocode:**
```
FOR ALL input WHERE NOT isBugCondition(input) DO
  ASSERT EntityToIdentifierTransformer_original.reverseTransform(input) 
         = EntityToIdentifierTransformer_fixed.reverseTransform(input)
END FOR
```

**Testing Approach**: Property-based testing is recommended for preservation checking because:
- It generates many test cases automatically across the input domain
- It catches edge cases that manual unit tests might miss
- It provides strong guarantees that behavior is unchanged for all non-buggy inputs

**Test Plan**: Observe behavior on UNFIXED code first for newly selected values, modified selections, and single-select fields, then write property-based tests capturing that behavior.

**Test Cases**:
1. **Newly Selected Values Preservation**: Observe that selecting new values from the dropdown works correctly on unfixed code, then write test to verify this continues after fix
2. **Modified Selections Preservation**: Observe that adding/removing chips works correctly on unfixed code, then write test to verify this continues after fix
3. **Single-Select Preservation**: Observe that single-select autocomplete fields work correctly on unfixed code, then write test to verify this continues after fix
4. **Empty Value Filtering Preservation**: Observe that empty strings are filtered out on unfixed code, then write test to verify this continues after fix

### Unit Tests

- Test `reverseTransform()` with array of scalar IDs (existing behavior)
- Test `reverseTransform()` with array containing `{id, label}` objects (bug scenario)
- Test `reverseTransform()` with mixed array of scalars and objects
- Test `reverseTransform()` with empty strings and null values in array
- Test `reverseTransform()` in single-select mode with pre-filled value
- Test that entities are correctly loaded from database by ID

### Property-Based Tests

- Generate random arrays of entity IDs and verify transformation works correctly
- Generate random combinations of scalar IDs and `{id, label}` objects to verify normalization
- Generate random entity configurations and verify that validation passes for all valid pre-filled scenarios
- Test across many form submission scenarios with varying numbers of pre-filled items

### Integration Tests

- Test full form submission flow with pre-filled multi-select autocomplete
- Test form re-submission after validation failure on another field
- Test that chips are correctly re-rendered after form errors
- Test that database entities are correctly persisted when form validates successfully
