<?php

namespace Kerrialnewham\Autocomplete\Provider\Provider\Symfony;

use Kerrialnewham\Autocomplete\Provider\Contract\AutocompleteProviderInterface;
use Kerrialnewham\Autocomplete\Provider\Contract\ChipProviderInterface;
use Symfony\Component\Intl\Currencies;

final class CurrencyProvider implements AutocompleteProviderInterface, ChipProviderInterface
{
    public function __construct(private readonly string $locale = 'en') {}

    public function getName(): string
    {
        return 'symfony_currencies';
    }

    public function search(string $query, int $limit, array $selected): array
    {
        $query = mb_strtolower($query);
        $selected = array_map('strval', $selected);

        $all = Currencies::getNames($this->locale);

        $results = [];
        foreach ($all as $code => $name) {
            if (\in_array($code, $selected, true)) {
                continue;
            }

            $hay = mb_strtolower($name . ' ' . $code);
            if ($query === '' || str_contains($hay, $query)) {
                $results[] = ['id' => $code, 'label' => $name];
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
        $all = Currencies::getNames($this->locale);

        if (!isset($all[$id])) {
            return null;
        }

        return [
            'id' => $id,
            'label' => $all[$id],
        ];
    }
}
