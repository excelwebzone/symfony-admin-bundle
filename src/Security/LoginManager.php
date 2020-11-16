<?php

namespace EWZ\SymfonyAdminBundle\Security;

use EWZ\SymfonyAdminBundle\Model\User;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Http\RememberMe\RememberMeServicesInterface;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface;

/**
 * Abstracts process for manually logging in a user.
 */
class LoginManager
{
    /** @var TokenStorageInterface */
    private $tokenStorage;

    /** @var UserCheckerInterface */
    private $userChecker;

    /** @var SessionAuthenticationStrategyInterface */
    private $sessionStrategy;

    /** @var RequestStack */
    private $requestStack;

    /** @var RememberMeServicesInterface */
    private $rememberMeServices;

    /**
     * @param TokenStorageInterface                  $tokenStorage
     * @param UserCheckerInterface                   $userChecker
     * @param SessionAuthenticationStrategyInterface $sessionStrategy
     * @param RequestStack                           $requestStack
     * @param RememberMeServicesInterface|null       $rememberMeService
     */
    public function __construct(
        TokenStorageInterface $tokenStorage,
        UserCheckerInterface $userChecker,
        SessionAuthenticationStrategyInterface $sessionStrategy,
        RequestStack $requestStack,
        ?RememberMeServicesInterface $rememberMeServices
    ) {
        $this->tokenStorage = $tokenStorage;
        $this->userChecker = $userChecker;
        $this->sessionStrategy = $sessionStrategy;
        $this->requestStack = $requestStack;
        $this->rememberMeServices = $rememberMeServices;
    }

    /**
     * @param string        $firewallName
     * @param User          $user
     * @param Response|null $response
     */
    final public function loginUser(string $firewallName, User $user, Response $response = null): void
    {
        $this->userChecker->checkPreAuth($user);

        $token = $this->createToken($firewallName, $user);
        $request = $this->requestStack->getCurrentRequest();

        if (null !== $request) {
            $this->sessionStrategy->onAuthentication($request, $token);

            if (null !== $response && null !== $this->rememberMeServices) {
                $this->rememberMeServices->loginSuccess($request, $response, $token);
            }
        }

        $this->tokenStorage->setToken($token);
    }

    /**
     * @param string $firewall
     * @param User   $user
     *
     * @return UsernamePasswordToken
     */
    protected function createToken(string $firewall, User $user): UsernamePasswordToken
    {
        return new UsernamePasswordToken($user, null, $firewall, $user->getRoles());
    }
}
