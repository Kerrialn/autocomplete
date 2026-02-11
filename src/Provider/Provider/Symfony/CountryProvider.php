<?php

namespace Kerrialnewham\Autocomplete\Provider\Provider\Symfony;
use Kerrialnewham\Autocomplete\Provider\Contract\AutocompleteProviderInterface;
use Kerrialnewham\Autocomplete\Provider\Contract\ChipProviderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Intl\Countries;

final class CountryProvider implements AutocompleteProviderInterface, ChipProviderInterface
{
    public function __construct(private readonly RequestStack $requestStack) {}

    public function getName(): string
    {
        return 'symfony_countries';
    }

    public function search(string $query, int $limit, array $selected): array
    {
        $query = mb_strtolower($query);
        $selected = array_map('strval', $selected);

        // Countries::getNames() returns [ 'US' => 'United States', ... ] for a locale
        $all = Countries::getNames($this->getLocale());

        $results = [];
        foreach ($all as $code => $name) {
            if (in_array($code, $selected, true)) {
                continue;
            }

            $hay = mb_strtolower($name.' '.$code);
            if ($query === '' || str_contains($hay, $query)) {
                $results[] = ['id' => $code, 'label' => $name];
            }
        }

        // Prioritize starts-with
        usort($results, fn($a, $b) => str_starts_with(mb_strtolower($a['label']), $query) <=> str_starts_with(mb_strtolower($b['label']), $query) ?:
            (mb_strtolower($a['label']) <=> mb_strtolower($b['label']))
        );

        return array_slice($results, 0, $limit);
    }

    public function get(string $id): ?array
    {
        $all = Countries::getNames($this->getLocale());

        if (!isset($all[$id])) {
            return null;
        }

        return [
            'id' => $id,
            'label' => $all[$id],
        ];
    }

    private function getLocale(): string
    {
        return $this->requestStack->getCurrentRequest()?->getLocale() ?? 'en';
    }
}
