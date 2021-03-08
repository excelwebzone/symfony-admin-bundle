<?php

namespace EWZ\SymfonyAdminBundle\EventSubscriber;

use EWZ\SymfonyAdminBundle\Event\UserEvent;
use EWZ\SymfonyAdminBundle\Events;
use EWZ\SymfonyAdminBundle\Model\User;
use EWZ\SymfonyAdminBundle\Repository\UserRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;

/**
 * Updated user last login timestamp.
 */
class LastLoginSubscriber implements EventSubscriberInterface
{
    /** @var UserRepository */
    private $userRepository;

    /**
     * @param UserRepository $userRepository
     */
    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            Events::SECURITY_IMPLICIT_LOGIN => 'onSecurityImplicitLogin',
            SecurityEvents::INTERACTIVE_LOGIN => 'onSecurityInteractiveLogin',
        ];
    }

    /**
     * @param UserEvent $event
     */
    public function onSecurityImplicitLogin(UserEvent $event): void
    {
        $user = $event->getUser();

        $user->setLastLogin(new \DateTime());
        $this->userRepository->update($user);
    }

    /**
     * @param InteractiveLoginEvent $event
     */
    public function onSecurityInteractiveLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();

        if ($user instanceof User) {
            $user->setLastLogin(new \DateTime());
            $this->userRepository->update($user);
        }
    }
}
