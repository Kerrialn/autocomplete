<?php

namespace Kerrialnewham\Autocomplete\Provider\Choice;

use Kerrialnewham\Autocomplete\Provider\Contract\AutocompleteProviderInterface;
use Kerrialnewham\Autocomplete\Provider\Contract\ChipProviderInterface;

final class ChoicesProvider implements AutocompleteProviderInterface, ChipProviderInterface
{
    /** @var array<array{id: string, label: string}> */
    private readonly array $items;

    /**
     * @param array<string|int, mixed> $choices Symfony choices format: label => value (optionally nested for groups)
     */
    public function __construct(array $choices)
    {
        $this->items = $this->flatten($choices);
    }

    public function search(string $query, int $limit, array $selected): array
    {
        $query = mb_strtolower($query);
        $selected = array_map('strval', $selected);

        $results = [];
        foreach ($this->items as $item) {
            if (\in_array($item['id'], $selected, true)) {
                continue;
            }

            $hay = mb_strtolower($item['label'] . ' ' . $item['id']);
            if ($query === '' || str_contains($hay, $query)) {
                $results[] = $item;
            }
        }

        return \array_slice($results, 0, $limit);
    }

    public function get(string $id): ?array
    {
        foreach ($this->items as $item) {
            if ($item['id'] === $id) {
                return $item;
            }
        }

        return null;
    }

    /** @return array<array{id: string, label: string}> */
    private function flatten(array $choices): array
    {
        $items = [];
        foreach ($choices as $label => $value) {
            if (\is_array($value)) {
                // Group: label is the group name, value is a nested choices array
                foreach ($this->flatten($value) as $item) {
                    $items[] = $item;
                }
            } else {
                $items[] = ['id' => (string) $value, 'label' => (string) $label];
            }
        }

        return $items;
    }
}
