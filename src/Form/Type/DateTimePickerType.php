<?php

namespace EWZ\SymfonyAdminBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class DateTimePickerType extends AbstractType
{
    /** @var TokenStorageInterface */
    private $tokenStorage;

    /**
     * @param TokenStorageInterface $tokenStorage
     */
    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['time_interval'] = (int) ($options['time_interval']);
        $view->vars['time_24hour'] = (bool) ($options['time_24hour']);
        $view->vars['time_hours'] = (array) $options['time_hours'];
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $defaults = [
            'html5' => false,
            'date_widget' => 'single_text',
            'time_widget' => 'single_text',
            'date_label' => 'form.datetime.date',
            'time_label' => 'form.datetime.time',
            'time_interval' => 30,
            'time_24hour' => false,
            'time_hours' => [],
        ];

        /** @var TokenInterface $token */
        if ($token = $this->tokenStorage->getToken()) {
            $user = $token->getUser();

            if ($user instanceof User) {
                $defaults['view_timezone'] = $user->getTimezone();
            }
        }

        $resolver->setDefaults($defaults);
    }

    /**
     * {@inheritdoc}
     */
    public function getParent(): ?string
    {
        return DateTimeType::class;
    }
}
