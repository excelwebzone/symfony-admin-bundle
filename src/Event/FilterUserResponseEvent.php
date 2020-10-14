<?php

namespace EWZ\SymfonyAdminBundle\Event;

use EWZ\SymfonyAdminBundle\Model\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class FilterUserResponseEvent extends UserEvent
{
    /** @var Response */
    private $response;

    /**
     * @param User          $user
     * @param Request|null  $request
     * @param Response|null $response
     */
    public function __construct(User $user, Request $request = null, Response $response = null)
    {
        parent::__construct($user, $request);

        $this->response = $response;
    }

    /**
     * @return Response
     */
    public function getResponse(): ?Response
    {
        return $this->response;
    }

    /**
     * @param Response $response
     */
    public function setResponse(Response $response = null): void
    {
        $this->response = $response;
    }
}
