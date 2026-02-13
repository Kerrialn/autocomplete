<?php

namespace Kerrialnewham\Autocomplete\Provider\Choice;

use Kerrialnewham\Autocomplete\Provider\Contract\AutocompleteProviderInterface;
use Kerrialnewham\Autocomplete\Provider\Contract\ChipProviderInterface;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class EnumProvider implements AutocompleteProviderInterface, ChipProviderInterface
{
    /** @var class-string<\BackedEnum> */
    private readonly string $enumClass;

    /**
     * @param class-string<\BackedEnum> $enumClass
     */
    public function __construct(
        string $enumClass,
        private readonly ?string $choiceLabel = null,
        private readonly ?TranslatorInterface $translator = null,
        private readonly ?string $translationDomain = null,
    ) {
        if (!is_subclass_of($enumClass, \BackedEnum::class)) {
            throw new \InvalidArgumentException(sprintf(
                'EnumProvider requires a BackedEnum class, got "%s". UnitEnum is not supported because it has no stable string identifiers.',
                $enumClass,
            ));
        }

        $this->enumClass = $enumClass;
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
            $translatedLabel = $this->translateLabel($label);
            $hay = mb_strtolower($translatedLabel . ' ' . $label . ' ' . $id);

            if ($query === '' || str_contains($hay, $query)) {
                $results[] = ['id' => $id, 'label' => $label];
            }
        }

        usort($results, function (array $a, array $b) use ($query): int {
            $aTranslated = mb_strtolower($this->translateLabel($a['label']));
            $bTranslated = mb_strtolower($this->translateLabel($b['label']));

            $aStarts = $query !== '' && str_starts_with($aTranslated, $query);
            $bStarts = $query !== '' && str_starts_with($bTranslated, $query);

            if ($aStarts !== $bStarts) {
                return $bStarts <=> $aStarts;
            }

            return $aTranslated <=> $bTranslated;
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

    private function translateLabel(string $label): string
    {
        if ($this->translator === null || $this->translationDomain === null) {
            return $label;
        }

        return $this->translator->trans($label, [], $this->translationDomain);
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

        if ($this->translator !== null && $case instanceof TranslatableInterface) {
            return $case->trans($this->translator);
        }

        return $case->name;
    }
}
