<?php

namespace Kerrialnewham\Autocomplete\Form\ChoiceLoader;

use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;

/**
 * Choice loader for autocomplete-only ChoiceType fields (no static choices).
 *
 * When a ChoiceType uses autocomplete with an AJAX provider and defines no
 * explicit choices, Symfony's default validation rejects every submitted
 * value ("The selected option is not allowed"). This loader accepts all
 * non-empty submitted values, deferring real validation to the provider.
 *
 * Symfony 5.x calls loadChoiceList() then getChoicesForValues() on the returned list.
 * Symfony 6.x calls loadChoicesForValues() directly on the loader.
 * Both paths are handled here.
 */
final class AutocompleteChoiceLoader implements ChoiceLoaderInterface
{
    public function loadChoiceList(callable $value = null): ChoiceListInterface
    {
        return new class implements ChoiceListInterface {
            public function getChoices(): array
            {
                return [];
            }

            public function getValues(): array
            {
                return [];
            }

            public function getStructuredValues(): array
            {
                return [];
            }

            public function getOriginalKeys(): array
            {
                return [];
            }

            public function getChoicesForValues(array $values): array
            {
                $normalized = [];
                foreach ($values as $value) {
                    // Handle {id, label} objects that may be submitted when chips are pre-rendered
                    if (\is_array($value)) {
                        if (isset($value['id'])) {
                            $id = $value['id'];
                            
                            // Handle nested structures
                            while (\is_array($id) && isset($id['id'])) {
                                $id = $id['id'];
                            }
                            
                            $value = $id;
                        } else {
                            // No 'id' key, skip this entry
                            continue;
                        }
                    }
                    
                    // Filter out empty values
                    if ($value !== '' && $value !== null && $value !== []) {
                        $normalized[] = $value;
                    }
                }
                return array_values($normalized);
            }

            public function getValuesForChoices(array $choices): array
            {
                return array_map('strval', $choices);
            }
        };
    }

    public function loadChoicesForValues(array $values, callable $value = null): array
    {
        $normalized = [];
        foreach ($values as $val) {
            // Handle {id, label} objects that may be submitted when chips are pre-rendered
            if (\is_array($val)) {
                if (isset($val['id'])) {
                    $id = $val['id'];
                    
                    // Handle nested structures
                    while (\is_array($id) && isset($id['id'])) {
                        $id = $id['id'];
                    }
                    
                    $val = $id;
                } else {
                    // No 'id' key, skip this entry
                    continue;
                }
            }
            
            // Filter out empty values
            if ($val !== '' && $val !== null && $val !== []) {
                $normalized[] = $val;
            }
        }
        return array_values($normalized);
    }

    public function loadValuesForChoices(array $choices, callable $value = null): array
    {
        return array_map('strval', $choices);
    }
}
