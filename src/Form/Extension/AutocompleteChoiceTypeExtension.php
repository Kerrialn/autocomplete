<?php

namespace Kerrialnewham\Autocomplete\Form\Extension;

use Kerrialnewham\Autocomplete\Form\ChoiceLoader\AutocompleteChoiceLoader;
use Kerrialnewham\Autocomplete\Form\Type\InternationalDialCodeType;
use Kerrialnewham\Autocomplete\Provider\Choice\ChoicesProvider;
use Kerrialnewham\Autocomplete\Provider\Choice\EnumProvider;
use Kerrialnewham\Autocomplete\Provider\Provider\Symfony\CountryProvider;
use Kerrialnewham\Autocomplete\Provider\Provider\Symfony\CurrencyProvider;
use Kerrialnewham\Autocomplete\Provider\Provider\Symfony\DialCodeProvider;
use Kerrialnewham\Autocomplete\Provider\Provider\Symfony\LanguageProvider;
use Kerrialnewham\Autocomplete\Provider\Provider\Symfony\LocaleProvider;
use Kerrialnewham\Autocomplete\Provider\Provider\Symfony\TimezoneProvider;
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
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Contracts\Translation\TranslatableInterface;
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

    public function configureOptions(OptionsResolver $resolver): void
    {
        // When autocomplete is enabled and no explicit choices were provided
        // (e.g. InternationalDialCodeType), inject a loader that accepts any
        // submitted value so Symfony's ChoiceType validation doesn't reject it.
        // Types with real choices (CountryType, EnumType, etc.) are unaffected.
        $resolver->addNormalizer('choice_loader', static function (Options $options, $choiceLoader) {
            if (!$options['autocomplete'] || !empty($options['choices'])) {
                return $choiceLoader;
            }

            // EntityType (and EnumType) define a 'class' option and manage
            // their own choice_loader. EntityType is handled separately by
            // AutocompleteEntityTypeExtension — don't replace its loader.
            try {
                if ($options['class'] ?? null) {
                    return $choiceLoader;
                }
            } catch (\Throwable) {
                // 'class' option not defined — not an EntityType/EnumType
            }

            return new AutocompleteChoiceLoader();
        });

        // Symfony's ChoiceType normalizer strips placeholder to null for multiple selects.
        // When autocomplete is enabled, we use the placeholder as the text input's placeholder
        // attribute, which works regardless of selection mode. Override the normalizer to
        // preserve the user's value for autocomplete fields.
        $resolver->setNormalizer('placeholder', static function (Options $options, $placeholder) {
            if ($options['autocomplete']) {
                return $placeholder;
            }

            // Non-autocomplete: replicate Symfony ChoiceType default behavior
            if ($options['multiple']) {
                return null;
            }

            if (!$options['required'] && $placeholder === null) {
                return '';
            }

            return $placeholder;
        });
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

        if ($provider === null || $provider === '') {
            $choices = $options['choices'] ?? [];

            if (!empty($choices) && \is_array($choices)) {
                $providerName = 'choices_' . md5(serialize($choices));
                if (!$this->providerRegistry->has($providerName)) {
                    $this->providerRegistry->register(new ChoicesProvider($choices), $providerName);
                }
                $provider = $providerName;
            } else {
                $provider = match (true) {
                    $inner instanceof EnumType => $this->resolveEnumProvider($options),
                    $inner instanceof CountryType => CountryProvider::class,
                    $inner instanceof LanguageType => LanguageProvider::class,
                    $inner instanceof LocaleType => LocaleProvider::class,
                    $inner instanceof CurrencyType => CurrencyProvider::class,
                    $inner instanceof TimezoneType => TimezoneProvider::class,
                    $inner instanceof InternationalDialCodeType => DialCodeProvider::class,
                    default => null,
                };
            }
        }

        if ($provider === null || $provider === '') {
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
                } elseif ($normData instanceof TranslatableInterface && $this->translator !== null) {
                    $view->vars['selected_label'] = $normData->trans($this->translator);
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

            // For provider-backed types with scalar norm data (e.g. InternationalDialCodeType),
            // resolve the label via the provider's get() method
            if ($view->vars['selected_label'] === null && $normData !== null && \is_string($normData) && $normData !== '') {
                $providerInstance = $this->providerRegistry->has($provider) ? $this->providerRegistry->get($provider) : null;
                if ($providerInstance instanceof \Kerrialnewham\Autocomplete\Provider\Contract\ChipProviderInterface) {
                    $item = $providerInstance->get($normData);
                    if ($item !== null) {
                        $view->vars['selected_label'] = $item['label'] ?? null;
                    }
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

        $providerName = $enumClass;
        $choiceLabel = $this->normalizeChoiceOption($options['choice_label'] ?? null);
        $translationDomain = $options['choice_translation_domain'] ?? $options['translation_domain'] ?? null;
        if ($translationDomain === false) {
            $translationDomain = null;
        }

        $isTranslatable = is_subclass_of($enumClass, TranslatableInterface::class);

        if (!$this->providerRegistry->has($providerName)) {
            $provider = new EnumProvider(
                enumClass: $enumClass,
                choiceLabel: \is_string($choiceLabel) ? $choiceLabel : null,
                translator: ($translationDomain !== null || $isTranslatable) ? $this->translator : null,
                translationDomain: $translationDomain !== null ? (string) $translationDomain : null,
            );

            $this->providerRegistry->register($provider, $providerName);
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
