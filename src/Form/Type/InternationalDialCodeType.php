<?php

namespace Kerrialnewham\Autocomplete\Form\Type;

use Kerrialnewham\Autocomplete\Provider\Provider\Symfony\DialCodeProvider;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InternationalDialCodeType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'autocomplete' => true,
            'provider' => DialCodeProvider::class,
            'placeholder' => null,
        ]);
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'international_dial_code';
    }
}
