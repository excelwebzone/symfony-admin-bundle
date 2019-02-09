<?php

namespace EWZ\SymfonyAdminBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\ChoiceList\SimpleChoiceList;
use Symfony\Component\Form\Extension\Core\ChoiceList\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class KeyValueRowType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if (null === $options['allowed_keys']) {
            $builder->add('key', $options['key_type'], $options['key_options']);
        } else {
            $builder->add('key', 'choice', array_merge([
                'choice_list' => new SimpleChoiceList($options['allowed_keys']),
            ], $options['key_options']));
        }

        $builder->add('value', $options['value_type'], $options['value_options']);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'key_type' => TextType::class,
            'key_options' => [],
            'value_options' => [],
            'allowed_keys' => null,
        ]);

        $resolver->setRequired(['value_type']);

        if (method_exists($resolver, 'setDefined')) {
            $resolver->setAllowedTypes('allowed_keys', ['null', 'array']);
        }
    }
}
