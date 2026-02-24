<?php

namespace Kerrialnewham\Autocomplete\Form\Extension;

use Kerrialnewham\Autocomplete\Theme\TemplateResolver;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class AutocompleteFormTypeExtension extends AbstractTypeExtension
{
    public function __construct(
        private readonly TemplateResolver $templates,
    ) {
    }

    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'autocomplete' => false,
            'provider' => null,
            'min_chars' => 1,
            'debounce' => 300,
            'limit' => 10,
            'theme' => null,
            'floating_label' => null,
            'extra_params' => [],
            'autocomplete_choice_label' => null,
            'autocomplete_choice_value' => null,
            'chip_size' => 'md',
        ]);

        $resolver->setAllowedTypes('autocomplete', 'bool');
        $resolver->setAllowedTypes('provider', ['null', 'string']);
        $resolver->setAllowedTypes('min_chars', 'int');
        $resolver->setAllowedTypes('debounce', 'int');
        $resolver->setAllowedTypes('limit', 'int');
        $resolver->setAllowedTypes('theme', ['null', 'string']);
        $resolver->setAllowedTypes('floating_label', ['null', 'bool']);
        $resolver->setAllowedTypes('extra_params', 'array');
        $resolver->setAllowedTypes('autocomplete_choice_label', ['null', 'string']);
        $resolver->setAllowedTypes('autocomplete_choice_value', ['null', 'string']);
        $resolver->setAllowedTypes('chip_size', 'string');
        
        $resolver->setAllowedValues('chip_size', ['sm', 'md', 'lg', 'xl']);
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        if (!$options['autocomplete']) {
            return;
        }

        if (($view->vars['expanded'] ?? false) === true) {
            return;
        }

        $prefixes = $view->vars['block_prefixes'];
        $unique = array_pop($prefixes);
        $prefixes[] = 'autocomplete';
        $prefixes[] = $unique;

        $view->vars['block_prefixes'] = array_values(array_unique($prefixes));
        $view->vars['floating_label'] = $options['floating_label'] === true;
        $view->vars['extra_params'] = $options['extra_params'];

        if ($options['floating_label'] === true) {
            $rowAttr = $view->vars['row_attr'] ?? [];
            $rowAttr['class'] = trim(($rowAttr['class'] ?? '') . ' form-floating');
            $view->vars['row_attr'] = $rowAttr;
        }

        // Set view vars as defaults for types not handled by ChoiceType/EntityType extensions
        // (e.g. TextType with autocomplete: true and a custom provider).
        // The more specific extensions set these in buildView() which runs before finishView(),
        // so we only fill in what's missing.
        if (!isset($view->vars['provider'])) {
            $view->vars['provider'] = $options['provider'];
        }

        if (!isset($view->vars['min_chars'])) {
            $view->vars['min_chars'] = $options['min_chars'];
        }

        if (!isset($view->vars['debounce'])) {
            $view->vars['debounce'] = $options['debounce'];
        }

        if (!isset($view->vars['limit'])) {
            $view->vars['limit'] = $options['limit'];
        }

        if (!isset($view->vars['theme'])) {
            $view->vars['theme'] = $this->templates->theme($options['theme']);
        }

        if (!isset($view->vars['autocomplete_choice_label'])) {
            $view->vars['autocomplete_choice_label'] = $options['autocomplete_choice_label'];
        }

        if (!isset($view->vars['autocomplete_choice_value'])) {
            $view->vars['autocomplete_choice_value'] = $options['autocomplete_choice_value'];
        }

        if (!isset($view->vars['selected_label'])) {
            $view->vars['selected_label'] = null;
        }
        
        if (!isset($view->vars['chip_size'])) {
            $view->vars['chip_size'] = $options['chip_size'];
        }
    }
}
