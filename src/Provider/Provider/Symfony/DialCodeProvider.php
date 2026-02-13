<?php

namespace Kerrialnewham\Autocomplete\Provider\Provider\Symfony;

use Kerrialnewham\Autocomplete\Phone\DialCodeMap;
use Kerrialnewham\Autocomplete\Provider\Contract\AutocompleteProviderInterface;
use Kerrialnewham\Autocomplete\Provider\Contract\ChipProviderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Intl\Countries;

final class DialCodeProvider implements AutocompleteProviderInterface, ChipProviderInterface
{
    public function __construct(private readonly RequestStack $requestStack) {}

    public function search(string $query, int $limit, array $selected): array
    {
        $query = mb_strtolower($query);
        $selected = array_map('strval', $selected);
        $locale = $this->getLocale();

        $results = [];
        foreach (DialCodeMap::all() as $code => $dialCode) {
            if (in_array($code, $selected, true)) {
                continue;
            }

            $name = Countries::getName($code, $locale);
            $label = $this->formatLabel($code, $name, $dialCode);
            $hay = mb_strtolower($name . ' ' . $code . ' ' . $dialCode);

            if ($query === '' || str_contains($hay, $query)) {
                $results[] = ['id' => $code, 'label' => $label];
            }
        }

        usort($results, function ($a, $b) use ($query) {
            $aLabel = mb_strtolower($a['label']);
            $bLabel = mb_strtolower($b['label']);

            // Prioritize starts-with matches on the country name portion
            $aStarts = str_contains($aLabel, $query) ? 0 : 1;
            $bStarts = str_contains($bLabel, $query) ? 0 : 1;

            return $aStarts <=> $bStarts ?: $aLabel <=> $bLabel;
        });

        return array_slice($results, 0, $limit);
    }

    public function get(string $id): ?array
    {
        $dialCode = DialCodeMap::dialCode($id);

        if ($dialCode === null) {
            return null;
        }

        $name = Countries::getName($id, $this->getLocale());

        return [
            'id' => $id,
            'label' => $this->formatLabel($id, $name, $dialCode),
        ];
    }

    private function formatLabel(string $code, string $name, string $dialCode): string
    {
        return $this->countryFlag($code) . ' ' . $name . ' (' . $dialCode . ')';
    }

    private function countryFlag(string $code): string
    {
        $code = strtoupper($code);

        return mb_chr(0x1F1E6 + mb_ord($code[0]) - ord('A'))
             . mb_chr(0x1F1E6 + mb_ord($code[1]) - ord('A'));
    }

    private function getLocale(): string
    {
        return $this->requestStack->getCurrentRequest()?->getLocale() ?? 'en';
    }
}
