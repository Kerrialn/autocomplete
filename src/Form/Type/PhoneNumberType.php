<?php

namespace Kerrialnewham\Autocomplete\Form\Type;

use Kerrialnewham\Autocomplete\Form\DataTransformer\PhoneNumberTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PhoneNumberType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dialCode', InternationalDialCodeType::class, [
                'theme' => $options['theme'],
            ])
            ->add('number', TextType::class, [
                'label' => false,
                'attr' => ['placeholder' => 'Phone number'],
            ]);

        $builder->addModelTransformer(new PhoneNumberTransformer());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'compound' => true,
            'error_bubbling' => false,
            'theme' => null,
        ]);

        $resolver->setAllowedTypes('theme', ['string', 'null']);
    }

    public function getBlockPrefix(): string
    {
        return 'phone_number';
    }
}
