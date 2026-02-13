<?php

namespace Kerrialnewham\Autocomplete\Provider\Provider\Symfony;

use Kerrialnewham\Autocomplete\Provider\Contract\AutocompleteProviderInterface;
use Kerrialnewham\Autocomplete\Provider\Contract\ChipProviderInterface;

final class TimezoneProvider implements AutocompleteProviderInterface, ChipProviderInterface
{
    public function search(string $query, int $limit, array $selected): array
    {
        $query = mb_strtolower($query);
        $selected = array_map('strval', $selected);

        $results = [];
        foreach (\DateTimeZone::listIdentifiers(\DateTimeZone::ALL) as $tz) {
            if (\in_array($tz, $selected, true)) {
                continue;
            }

            $label = str_replace(['/', '_'], [' / ', ' '], $tz);
            $hay = mb_strtolower($label . ' ' . $tz);

            if ($query === '' || str_contains($hay, $query)) {
                $results[] = ['id' => $tz, 'label' => $label];
            }
        }

        usort($results, function (array $a, array $b) use ($query): int {
            $aStarts = $query !== '' && str_starts_with(mb_strtolower($a['label']), $query);
            $bStarts = $query !== '' && str_starts_with(mb_strtolower($b['label']), $query);

            if ($aStarts !== $bStarts) {
                return $bStarts <=> $aStarts;
            }

            return mb_strtolower($a['label']) <=> mb_strtolower($b['label']);
        });

        return \array_slice($results, 0, $limit);
    }

    public function get(string $id): ?array
    {
        $identifiers = \DateTimeZone::listIdentifiers(\DateTimeZone::ALL);

        if (!\in_array($id, $identifiers, true)) {
            return null;
        }

        return [
            'id' => $id,
            'label' => str_replace(['/', '_'], [' / ', ' '], $id),
        ];
    }
}
