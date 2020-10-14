<?php

namespace EWZ\SymfonyAdminBundle;

/**
 * This class defines the names of all the events dispatched in the application.
 *
 * For the event naming conventions, see:
 * https://symfony.com/doc/current/components/event_dispatcher.html#naming-conventions.
 */
final class Events
{
    /**
     * @Event("EWZ\SymfonyAdminBundle\Event\UserEvent")
     *
     * @var string
     */
    public const RESETTING_PASSWORD_SENT = 'app.resetting.password_sent';

    /**
     * @Event("EWZ\SymfonyAdminBundle\Event\FilterUserResponseEvent")
     *
     * @var string
     */
    public const RESETTING_PASSWORD_CONFIRMED = 'app.resetting.password_confirmed';

    /**
     * @Event("EWZ\SymfonyAdminBundle\Event\ObjectEvent")
     *
     * @var string
     */
    public const NOTIFICATION_OBJECT_CREATED = 'app.notification.object_created';
}
