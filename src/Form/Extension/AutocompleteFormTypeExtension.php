<?php

namespace Kerrialnewham\Autocomplete\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class AutocompleteFormTypeExtension extends AbstractTypeExtension
{
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
        ]);

        $resolver->setAllowedTypes('autocomplete', 'bool');
        $resolver->setAllowedTypes('provider', ['null', 'string']);
        $resolver->setAllowedTypes('min_chars', 'int');
        $resolver->setAllowedTypes('debounce', 'int');
        $resolver->setAllowedTypes('limit', 'int');
        $resolver->setAllowedTypes('theme', ['null', 'string']);
        $resolver->setAllowedTypes('floating_label', ['null', 'bool']);
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

        if ($options['floating_label'] === true) {
            $rowAttr = $view->vars['row_attr'] ?? [];
            $rowAttr['class'] = trim(($rowAttr['class'] ?? '') . ' form-floating');
            $view->vars['row_attr'] = $rowAttr;
        }
    }
}
