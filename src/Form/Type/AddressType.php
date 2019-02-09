<?php

namespace EWZ\SymfonyAdminBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AddressType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('street', $options['defaultType'], [
                'label' => false,
                'attr' => [
                    'placeholder' => 'form.address.street',
                ],
            ])
            ->add('city', $options['defaultType'], [
                'label' => false,
                'attr' => [
                    'placeholder' => 'form.address.city',
                ],
            ])
            ->add('state', $options['defaultType'], [
                'label' => false,
                'attr' => [
                    'placeholder' => 'form.address.state',
                ],
            ])
            ->add('zip', $options['defaultType'], [
                'label' => false,
                'attr' => [
                    'placeholder' => 'form.address.zip',
                ],
            ])
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'required' => false,
            'defaultType' => null,
        ]);
    }
}
