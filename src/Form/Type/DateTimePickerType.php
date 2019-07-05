<?php

namespace EWZ\SymfonyAdminBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
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
    public function configureOptions(OptionsResolver $resolver)
    {
        $defaults = [
            'html5' => false,
            'widget' => 'single_text',
            'date_label' => 'form.datetime.date',
            'time_label' => 'form.datetime.time',
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
        return DateTimeType::class;
    }
}
