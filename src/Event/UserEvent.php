<?php

namespace EWZ\SymfonyAdminBundle\Event;

use EWZ\SymfonyAdminBundle\Model\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

class UserEvent extends Event
{
    /** @var Request|null */
    private $request;

    /** @var User */
    private $user;

    /**
     * @param User         $user
     * @param Request|null $request
     */
    public function __construct(User $user, Request $request = null)
    {
        $this->user = $user;
        $this->request = $request;
    }

    /**
     * @return User
     */
    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * @return Request|null
     */
    public function getRequest(): ?Request
    {
        return $this->request;
    }
}
