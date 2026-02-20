<?php

namespace Kerrialnewham\Autocomplete\Form\Extension;

use Kerrialnewham\Autocomplete\Form\DataTransformer\EntityToIdentifierTransformer;
use Kerrialnewham\Autocomplete\Provider\Doctrine\EntityProviderFactory;
use Kerrialnewham\Autocomplete\Theme\TemplateResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\ChoiceList\Factory\Cache\AbstractStaticOption;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\PropertyAccess\PropertyAccess;

final class AutocompleteEntityTypeExtension extends AbstractTypeExtension
{
    public function __construct(
        private readonly TemplateResolver $templates,
        private readonly EntityProviderFactory $providerFactory,
    ) {
    }

    public static function getExtendedTypes(): iterable
    {
        return [EntityType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (!$options['autocomplete']) {
            return;
        }

        if ($this->hasEntityTransformer($builder)) {
            return;
        }

        $choiceValue = $options['choice_value'] ?? null;

        // If Symfony has already normalized this into an object, ignore it (transformer uses Doctrine id fallback)
        if (!\is_string($choiceValue) && !\is_callable($choiceValue) && $choiceValue !== null) {
            $choiceValue = null;
        }

        // Remove EntityType's built-in ChoiceToValueTransformer.
        // It depends on a pre-loaded ChoiceList which conflicts with
        // autocomplete's dynamic AJAX loading and causes failures
        // when the form is re-submitted (e.g. in Symfony Live Components).
        $builder->resetViewTransformers();

        $builder->addViewTransformer(
            new EntityToIdentifierTransformer(
                registry: $this->providerFactory->getRegistry(),
                class: $options['class'] ?? throw new \InvalidArgumentException('EntityType requires "class" option'),
                choiceValue: $choiceValue,
                multiple: $options['multiple'] ?? false,
            )
        );
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        if (!$options['autocomplete']) {
            return;
        }

        if (($view->vars['expanded'] ?? false) === true) {
            return;
        }

        $provider = $options['provider'];

        if ($provider === null || $provider === '') {
            $provider = $this->resolveEntityProvider($form, $options);
        }

        $view->vars['provider'] = $provider;
        $view->vars['min_chars'] = $options['min_chars'];
        $view->vars['debounce'] = $options['debounce'];
        $view->vars['limit'] = $options['limit'];
        $view->vars['theme'] = $this->templates->theme($options['theme']);

        $cl = $this->normalizeChoiceOption($options['choice_label'] ?? null);
        $cv = $this->normalizeChoiceOption($options['choice_value'] ?? null);

        $view->vars['autocomplete_choice_label'] = \is_string($cl) ? $cl : null;
        $view->vars['autocomplete_choice_value'] = \is_string($cv) ? $cv : null;

        $view->vars['selected_label'] = null;
        $multiple = $options['multiple'] ?? false;

        if ($multiple) {
            // Build {id, label} items for chip rendering from norm data (entities).
            // These go into a separate var so $view->vars['value'] stays as
            // scalar IDs (Symfony's ChoiceType expects that).
            $normData = $form->getNormData();
            $items = [];

            if (\is_iterable($normData)) {
                $accessor = PropertyAccess::createPropertyAccessor();

                foreach ($normData as $entity) {
                    if ($entity === null) {
                        continue;
                    }

                    $id = \is_string($cv) && $cv !== ''
                        ? (string) $accessor->getValue($entity, $cv)
                        : (string) $accessor->getValue($entity, 'id');

                    $label = $id;
                    try {
                        if (\is_string($cl) && $cl !== '') {
                            $label = (string) $accessor->getValue($entity, $cl);
                        } elseif ($cl instanceof \Closure) {
                            $label = (string) $cl($entity);
                        } elseif (method_exists($entity, '__toString')) {
                            $label = (string) $entity;
                        }
                    } catch (\Exception) {
                    }

                    $items[] = ['id' => $id, 'label' => $label];
                }
            }

            $view->vars['selected_items'] = $items;
        } else {
            // For single-select, resolve the label of the currently selected entity
            $normData = $form->getNormData();

            if ($normData !== null && \is_object($normData)) {
                try {
                    if (\is_string($cl) && $cl !== '') {
                        $view->vars['selected_label'] = (string) PropertyAccess::createPropertyAccessor()->getValue($normData, $cl);
                    } elseif ($cl instanceof \Closure) {
                        $view->vars['selected_label'] = (string) $cl($normData);
                    } elseif (method_exists($normData, '__toString')) {
                        $view->vars['selected_label'] = (string) $normData;
                    }
                } catch (\Exception) {
                }
            }
        }
    }

    private function resolveEntityProvider(FormInterface $form, array $options): string
    {
        $class = $options['class'] ?? null;

        if (!$class) {
            throw new \InvalidArgumentException('EntityType requires "class" option.');
        }

        $queryBuilder = $options['query_builder'] ?? null;
        $choiceLabel = $this->normalizeChoiceOption($options['choice_label'] ?? null);
        $choiceValue = $this->normalizeChoiceOption($options['choice_value'] ?? null);

        // Closures cannot survive the HTTP boundary between form rendering
        // and the AJAX search request. Warn developers early so they know
        // to create a custom provider instead.
        if ($queryBuilder !== null) {
            throw new \LogicException(sprintf(
                'EntityType "%s" with autocomplete does not support the "query_builder" option '
                . 'because closures cannot be sent to the AJAX search endpoint. '
                . 'Create a custom provider (implementing AutocompleteProviderInterface) '
                . 'and pass its class or service name via the "provider" option instead.',
                $class,
            ));
        }

        if ($choiceLabel instanceof \Closure) {
            throw new \LogicException(sprintf(
                'EntityType "%s" with autocomplete does not support a closure "choice_label" '
                . 'because closures cannot be sent to the AJAX search endpoint. '
                . 'Use a string property path (e.g. "name") or create a custom provider instead.',
                $class,
            ));
        }

        $this->providerFactory->createProvider(
            class: $class,
            choiceLabel: $choiceLabel,
            choiceValue: $choiceValue,
        );

        return $this->providerFactory->getProviderName($class);
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

    private function hasEntityTransformer(FormBuilderInterface $builder): bool
    {
        foreach ($builder->getViewTransformers() as $transformer) {
            if ($transformer instanceof EntityToIdentifierTransformer) {
                return true;
            }
        }

        return false;
    }
}
