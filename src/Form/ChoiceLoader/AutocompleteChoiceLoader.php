<?php

namespace Kerrialnewham\Autocomplete\Form\ChoiceLoader;

use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;

/**
 * Choice loader for autocomplete-only ChoiceType fields (no static choices).
 *
 * When a ChoiceType uses autocomplete with an AJAX provider and defines no
 * explicit choices, Symfony's default validation rejects every submitted
 * value ("The selected option is not allowed"). This loader accepts all
 * non-empty submitted values, deferring real validation to the provider.
 */
final class AutocompleteChoiceLoader implements ChoiceLoaderInterface
{
    public function loadChoiceList(callable $value = null): ChoiceListInterface
    {
        return new ArrayChoiceList([]);
    }

    public function loadChoicesForValues(array $values, callable $value = null): array
    {
        return array_filter($values, static fn ($v) => $v !== '' && $v !== null);
    }

    public function loadValuesForChoices(array $choices, callable $value = null): array
    {
        return array_map('strval', $choices);
    }
}
