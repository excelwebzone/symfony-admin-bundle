<?php

namespace EWZ\SymfonyAdminBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TimePickerType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['time_interval'] = (int) ($options['interval']);
        $view->vars['time_24hour'] = (bool) ($options['24hour']);
        $view->vars['time_hours'] = (array) $options['hours'];
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'html5' => false,
            'widget' => 'single_text',
            'interval' => 30,
            '24hour' => false,
            'hours' => [],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getParent(): ?string
    {
        return TimeType::class;
    }
}
