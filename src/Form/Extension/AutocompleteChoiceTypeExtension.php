<?php

namespace Kerrialnewham\Autocomplete\Form\Extension;

use Kerrialnewham\Autocomplete\Provider\Choice\EnumProvider;
use Kerrialnewham\Autocomplete\Provider\ProviderRegistry;
use Kerrialnewham\Autocomplete\Theme\TemplateResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\ChoiceList\Factory\Cache\AbstractStaticOption;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\CurrencyType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\LanguageType;
use Symfony\Component\Form\Extension\Core\Type\LocaleType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AutocompleteChoiceTypeExtension extends AbstractTypeExtension
{
    public function __construct(
        private readonly TemplateResolver $templates,
        private readonly ProviderRegistry $providerRegistry,
        private readonly ?TranslatorInterface $translator = null,
    ) {
    }

    public static function getExtendedTypes(): iterable
    {
        return [ChoiceType::class];
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        if (!$options['autocomplete']) {
            return;
        }

        if (($view->vars['expanded'] ?? false) === true) {
            return;
        }

        $inner = $form->getConfig()->getType()->getInnerType();

        // EntityType is handled by AutocompleteEntityTypeExtension
        if (class_exists(EntityType::class) && $inner instanceof EntityType) {
            return;
        }

        $provider = $options['provider'];

        if ($provider === null || $provider === '' || $provider === 'default') {
            $provider = match (true) {
                $inner instanceof EnumType => $this->resolveEnumProvider($options),
                $inner instanceof CountryType => 'symfony_countries',
                $inner instanceof LanguageType => 'symfony_languages',
                $inner instanceof LocaleType => 'symfony_locales',
                $inner instanceof CurrencyType => 'symfony_currencies',
                $inner instanceof TimezoneType => 'symfony_timezones',
                default => null,
            };
        }

        if ($provider === null || $provider === '' || $provider === 'default') {
            throw new \InvalidArgumentException(sprintf(
                'Autocomplete is enabled but no provider could be resolved for field "%s".',
                $view->vars['full_name'] ?? '(unknown)',
            ));
        }

        $view->vars['provider'] = $provider;
        $view->vars['debounce'] = $options['debounce'];
        $view->vars['theme'] = $this->templates->theme($options['theme']);

        // Choice-based types behave like searchable selects:
        // show options on focus (min_chars=0) unless explicitly overridden
        $view->vars['min_chars'] = $options['min_chars'] === 1 ? 0 : $options['min_chars'];

        // For EnumType, default the limit to the number of cases (enums are small)
        if ($inner instanceof EnumType && $options['limit'] === 10) {
            $enumClass = $options['class'] ?? null;
            $view->vars['limit'] = $enumClass !== null && enum_exists($enumClass)
                ? \count($enumClass::cases())
                : $options['limit'];
        } else {
            $view->vars['limit'] = $options['limit'];
        }

        $cl = $this->normalizeChoiceOption($options['choice_label'] ?? null);

        $view->vars['autocomplete_choice_label'] = \is_string($cl) ? $cl : null;
        $view->vars['autocomplete_choice_value'] = null;

        // For single-select enums, resolve the label of the currently selected value
        $view->vars['selected_label'] = null;
        $multiple = $options['multiple'] ?? false;

        if (!$multiple && $inner instanceof EnumType) {
            $normData = $form->getNormData();

            if ($normData instanceof \BackedEnum) {
                $choiceLabel = \is_string($cl) ? $cl : null;

                if ($choiceLabel !== null && method_exists($normData, $choiceLabel)) {
                    $view->vars['selected_label'] = (string) $normData->{$choiceLabel}();
                } else {
                    $view->vars['selected_label'] = $normData->name;
                }
            }
        }

        // For single-select CountryType/other choices, resolve label from object if possible
        if (!$multiple && !$inner instanceof EnumType) {
            $normData = $form->getNormData();

            if ($normData !== null && \is_object($normData)) {
                try {
                    if (\is_string($cl) && $cl !== '') {
                        $view->vars['selected_label'] = (string) PropertyAccess::createPropertyAccessor()->getValue($normData, $cl);
                    } elseif (method_exists($normData, '__toString')) {
                        $view->vars['selected_label'] = (string) $normData;
                    }
                } catch (\Exception) {
                }
            }
        }
    }

    private function resolveEnumProvider(array $options): string
    {
        $enumClass = $options['class'] ?? null;

        if ($enumClass === null) {
            throw new \InvalidArgumentException('EnumType requires a "class" option.');
        }

        if (!is_subclass_of($enumClass, \BackedEnum::class)) {
            throw new \InvalidArgumentException(sprintf(
                'Autocomplete for EnumType requires a BackedEnum class, got "%s". UnitEnum is not supported because it has no stable string identifiers.',
                $enumClass,
            ));
        }

        $providerName = 'enum.' . $enumClass;
        $choiceLabel = $this->normalizeChoiceOption($options['choice_label'] ?? null);
        $translationDomain = $options['choice_translation_domain'] ?? $options['translation_domain'] ?? null;
        if ($translationDomain === false) {
            $translationDomain = null;
        }

        if (!$this->providerRegistry->has($providerName)) {
            $provider = new EnumProvider(
                enumClass: $enumClass,
                providerName: $providerName,
                choiceLabel: \is_string($choiceLabel) ? $choiceLabel : null,
                translator: $translationDomain !== null ? $this->translator : null,
                translationDomain: $translationDomain !== null ? (string) $translationDomain : null,
            );

            $this->providerRegistry->register($provider);
        }

        return $providerName;
    }

    private function normalizeChoiceOption(mixed $opt): string|\Closure|null
    {
        // Unwrap Symfony's ChoiceLabel/ChoiceValue cache wrappers
        if ($opt instanceof AbstractStaticOption) {
            $opt = $opt->getOption();
        }

        if (\is_string($opt) && $opt !== '') {
            return $opt;
        }

        if (\is_object($opt) && method_exists($opt, '__toString')) {
            $s = (string) $opt;
            if ($s !== '') {
                return $s;
            }
        }

        if ($opt !== null && \is_callable($opt)) {
            return \Closure::fromCallable($opt);
        }

        return null;
    }
}
