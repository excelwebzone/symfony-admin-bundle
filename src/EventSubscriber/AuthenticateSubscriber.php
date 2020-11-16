<?php

namespace EWZ\SymfonyAdminBundle\EventSubscriber;

use EWZ\SymfonyAdminBundle\Event\FilterUserResponseEvent;
use EWZ\SymfonyAdminBundle\Event\UserEvent;
use EWZ\SymfonyAdminBundle\Events;
use EWZ\SymfonyAdminBundle\Security\LoginManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Exception\AccountStatusException;

/**
 * Authenticate (login) user.
 */
class AuthenticateSubscriber implements EventSubscriberInterface
{
    /** @var LoginManager */
    protected $loginManager;

    /** @var string */
    protected $firewallName;

    /**
     * @param LoginManager $loginManager
     * @param string       $firewallName
     */
    public function __construct(LoginManager $loginManager, string $firewallName)
    {
        $this->loginManager = $loginManager;
        $this->firewallName = $firewallName;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            Events::RESETTING_PASSWORD_CONFIRMED => 'onAuthenticate',
        ];
    }

    /**
     * @param FilterUserResponseEvent  $event
     * @param string                   $eventName
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function onAuthenticate(FilterUserResponseEvent $event, string $eventName, EventDispatcherInterface $eventDispatcher): void
    {
        try {
            $this->loginManager->loginUser($this->firewallName, $event->getUser(), $event->getResponse());

            $eventDispatcher->dispatch(new UserEvent($event->getUser(), $event->getRequest()), Events::SECURITY_IMPLICIT_LOGIN);
        } catch (AccountStatusException $ex) {
            // We simply do not authenticate users which do not pass the user
            // checker (not enabled, expired, etc.).
        }
    }
}
