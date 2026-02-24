<?php

namespace Kerrialnewham\Autocomplete\Tests\Form\ChoiceLoader;

use Kerrialnewham\Autocomplete\Form\ChoiceLoader\AutocompleteChoiceLoader;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AutocompleteChoiceLoader
 * 
 * Validates that the choice loader correctly handles:
 * - Pre-filled values submitted as {id, label} objects
 * - Scalar IDs from newly selected values
 * - Empty value filtering
 * - Nested array structures
 */
class AutocompleteChoiceLoaderTest extends TestCase
{
    private AutocompleteChoiceLoader $loader;

    protected function setUp(): void
    {
        $this->loader = new AutocompleteChoiceLoader();
    }

    /**
     * Test: Newly selected scalar values work correctly
     * 
     * When users select values from the dropdown, they submit as scalar IDs.
     * This is the normal working case that must be preserved.
     */
    public function testLoadChoicesForValuesWithScalarIds(): void
    {
        $values = ['en', 'es', 'fr'];
        
        $result = $this->loader->loadChoicesForValues($values);
        
        $this->assertSame(['en', 'es', 'fr'], $result);
    }

    /**
     * Test: Pre-filled values submitted as {id, label} objects
     * 
     * When forms with pre-filled chips are resubmitted, the data may come
     * back as {id, label} objects. The loader must extract the scalar IDs.
     */
    public function testLoadChoicesForValuesWithIdLabelObjects(): void
    {
        $values = [
            ['id' => 'en', 'label' => 'English'],
            ['id' => 'es', 'label' => 'Spanish'],
            ['id' => 'fr', 'label' => 'French'],
        ];
        
        $result = $this->loader->loadChoicesForValues($values);
        
        $this->assertSame(['en', 'es', 'fr'], $result);
    }

    /**
     * Test: Mixed scalar IDs and {id, label} objects
     * 
     * In some scenarios, the submitted data may contain both formats.
     * The loader must handle both correctly.
     */
    public function testLoadChoicesForValuesWithMixedFormats(): void
    {
        $values = [
            'en',
            ['id' => 'es', 'label' => 'Spanish'],
            'fr',
            ['id' => 'de', 'label' => 'German'],
        ];
        
        $result = $this->loader->loadChoicesForValues($values);
        
        $this->assertSame(['en', 'es', 'fr', 'de'], $result);
    }

    /**
     * Test: Empty values are filtered out
     * 
     * Empty strings, null values, and empty arrays should be filtered
     * to prevent validation count mismatches.
     */
    public function testLoadChoicesForValuesFiltersEmptyValues(): void
    {
        $values = ['en', '', 'es', null, 'fr', []];
        
        $result = $this->loader->loadChoicesForValues($values);
        
        $this->assertSame(['en', 'es', 'fr'], $result);
    }

    /**
     * Test: Empty values in {id, label} objects are filtered
     */
    public function testLoadChoicesForValuesFiltersEmptyIdLabelObjects(): void
    {
        $values = [
            ['id' => 'en', 'label' => 'English'],
            ['id' => '', 'label' => 'Empty'],
            ['id' => 'es', 'label' => 'Spanish'],
            ['id' => null, 'label' => 'Null'],
        ];
        
        $result = $this->loader->loadChoicesForValues($values);
        
        $this->assertSame(['en', 'es'], $result);
    }

    /**
     * Test: Nested array structures are handled
     * 
     * In edge cases, the id field itself may be an array with an id field.
     * The loader must unwrap these nested structures.
     */
    public function testLoadChoicesForValuesWithNestedStructures(): void
    {
        $values = [
            ['id' => ['id' => 'en']],
            ['id' => 'es'],
            'fr',
        ];
        
        $result = $this->loader->loadChoicesForValues($values);
        
        $this->assertSame(['en', 'es', 'fr'], $result);
    }

    /**
     * Test: Arrays without 'id' key are skipped
     */
    public function testLoadChoicesForValuesSkipsArraysWithoutIdKey(): void
    {
        $values = [
            'en',
            ['label' => 'No ID'],
            ['id' => 'es', 'label' => 'Spanish'],
            ['value' => 'wrong_key'],
        ];
        
        $result = $this->loader->loadChoicesForValues($values);
        
        $this->assertSame(['en', 'es'], $result);
    }

    /**
     * Test: Empty input returns empty array
     */
    public function testLoadChoicesForValuesWithEmptyInput(): void
    {
        $result = $this->loader->loadChoicesForValues([]);
        
        $this->assertSame([], $result);
    }

    /**
     * Test: getChoicesForValues (Symfony 5.x compatibility)
     * 
     * Symfony 5.x calls loadChoiceList() then getChoicesForValues() on the list.
     * This tests the same normalization logic in the ChoiceListInterface implementation.
     */
    public function testGetChoicesForValuesWithIdLabelObjects(): void
    {
        $choiceList = $this->loader->loadChoiceList();
        
        $values = [
            ['id' => 'en', 'label' => 'English'],
            ['id' => 'es', 'label' => 'Spanish'],
        ];
        
        $result = $choiceList->getChoicesForValues($values);
        
        $this->assertSame(['en', 'es'], $result);
    }

    /**
     * Test: getChoicesForValues with mixed formats
     */
    public function testGetChoicesForValuesWithMixedFormats(): void
    {
        $choiceList = $this->loader->loadChoiceList();
        
        $values = [
            'en',
            ['id' => 'es', 'label' => 'Spanish'],
            '',
            ['id' => 'fr', 'label' => 'French'],
            null,
        ];
        
        $result = $choiceList->getChoicesForValues($values);
        
        $this->assertSame(['en', 'es', 'fr'], $result);
    }

    /**
     * Test: loadValuesForChoices returns string values
     */
    public function testLoadValuesForChoices(): void
    {
        $choices = ['en', 'es', 123, 'fr'];
        
        $result = $this->loader->loadValuesForChoices($choices);
        
        $this->assertSame(['en', 'es', '123', 'fr'], $result);
    }

    /**
     * Test: getValuesForChoices returns string values
     */
    public function testGetValuesForChoices(): void
    {
        $choiceList = $this->loader->loadChoiceList();
        
        $choices = ['en', 'es', 123, 'fr'];
        
        $result = $choiceList->getValuesForChoices($choices);
        
        $this->assertSame(['en', 'es', '123', 'fr'], $result);
    }
}
