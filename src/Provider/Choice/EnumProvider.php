<?php

namespace Kerrialnewham\Autocomplete\Provider\Choice;

use Kerrialnewham\Autocomplete\Provider\Contract\AutocompleteProviderInterface;
use Kerrialnewham\Autocomplete\Provider\Contract\ChipProviderInterface;

final class EnumProvider implements AutocompleteProviderInterface, ChipProviderInterface
{
    /** @var class-string<\BackedEnum> */
    private readonly string $enumClass;

    /**
     * @param class-string<\BackedEnum> $enumClass
     */
    public function __construct(
        string $enumClass,
        private readonly string $providerName,
        private readonly ?string $choiceLabel = null,
    ) {
        if (!is_subclass_of($enumClass, \BackedEnum::class)) {
            throw new \InvalidArgumentException(sprintf(
                'EnumProvider requires a BackedEnum class, got "%s". UnitEnum is not supported because it has no stable string identifiers.',
                $enumClass,
            ));
        }

        $this->enumClass = $enumClass;
    }

    public function getName(): string
    {
        return $this->providerName;
    }

    public function search(string $query, int $limit, array $selected): array
    {
        $query = mb_strtolower($query);
        $selected = array_map('strval', $selected);

        $results = [];
        foreach ($this->enumClass::cases() as $case) {
            $id = (string) $case->value;

            if (\in_array($id, $selected, true)) {
                continue;
            }

            $label = $this->resolveLabel($case);
            $hay = mb_strtolower($label . ' ' . $id);

            if ($query === '' || str_contains($hay, $query)) {
                $results[] = ['id' => $id, 'label' => $label];
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
        foreach ($this->enumClass::cases() as $case) {
            if ((string) $case->value === $id) {
                return [
                    'id' => $id,
                    'label' => $this->resolveLabel($case),
                ];
            }
        }

        return null;
    }

    private function resolveLabel(\BackedEnum $case): string
    {
        if ($this->choiceLabel !== null) {
            if (!method_exists($case, $this->choiceLabel)) {
                throw new \InvalidArgumentException(sprintf(
                    'Enum "%s" does not have method "%s" specified as choice_label.',
                    $this->enumClass,
                    $this->choiceLabel,
                ));
            }

            return (string) $case->{$this->choiceLabel}();
        }

        return $case->name;
    }
}
