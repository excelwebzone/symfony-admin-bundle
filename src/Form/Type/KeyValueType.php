<?php

namespace EWZ\SymfonyAdminBundle\Form\Type;

use EWZ\SymfonyAdminBundle\Form\DataTransformer\HashToKeyValueArrayTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class KeyValueType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addModelTransformer(new HashToKeyValueArrayTransformer($options['ignore_empty_values']));
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'required' => false,
            'entry_type' => KeyValueRowType::class,
            'allow_add' => true,
            'allow_delete' => true,
            'key_type' => TextType::class,
            'key_options' => [],
            'value_options' => [],
            'allowed_keys' => null,
            'ignore_empty_values' => true,
            'entry_options' => function (Options $options) {
                return [
                    'key_type' => $options['key_type'],
                    'value_type' => $options['value_type'],
                    'key_options' => $options['key_options'],
                    'value_options' => $options['value_options'],
                    'allowed_keys' => $options['allowed_keys'],
                ];
            },
        ]);

        $resolver->setRequired(['value_type']);

        if (method_exists($resolver, 'setDefined')) {
            $resolver->setAllowedTypes('allowed_keys', ['null', 'array']);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return CollectionType::class;
    }
}
