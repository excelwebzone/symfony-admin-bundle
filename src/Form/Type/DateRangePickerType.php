<?php

namespace EWZ\SymfonyAdminBundle\Form\Type;

use EWZ\SymfonyAdminBundle\Form\DataTransformer\StringToDateRangeTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class DateRangePickerType extends AbstractType
{
    public const DEFAULT_SEPARATOR = ' - ';

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
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addModelTransformer(new StringToDateRangeTransformer($options['locale_separator']));
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        if ($options['locale_separator']) {
            $view->vars['locale_separator'] = $options['locale_separator'];
        }

        if ($options['time_picker']) {
            $view->vars['time_picker'] = true;
            $view->vars['time_interval'] = (int) ($options['time_interval']);
            $view->vars['time_24hour'] = (bool) ($options['time_24hour']);
            $view->vars['time_hours'] = (array) $options['time_hours'];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $defaults = [
            'locale_separator' => self::DEFAULT_SEPARATOR,
            'time_picker' => false,
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
    public function getParent()
    {
        return TextType::class;
    }
}
