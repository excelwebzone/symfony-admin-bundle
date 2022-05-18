<?php

namespace EWZ\SymfonyAdminBundle\Security;

use EWZ\SymfonyAdminBundle\Model\User;
use EWZ\SymfonyAdminBundle\Repository\UserRepository;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface as SecurityUserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserProvider implements UserProviderInterface
{
    /** @var UserRepository */
    protected $userRepository;

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
    public function loadUserByUsername($username): SecurityUserInterface
    {
        return $this->loadUserByIdentifier($username);
    }

    /**
     * {@inheritdoc}
     */
    public function loadUserByIdentifier(string $identifier): SecurityUserInterface
    {
        if (!$user = $this->findUser($identifier)) {
            throw new UsernameNotFoundException(sprintf('Username "%s" does not exist.', $username));
        }

        if (!$user->isEnabled()) {
            throw new DisabledException('Account is disabled.');
        }

        return $user;
    }

    /**
     * {@inheritdoc}
     */
    public function refreshUser(SecurityUserInterface $user): SecurityUserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Expected an instance of %s, but got "%s".', User::class, \get_class($user)));
        }

        if (!$this->supportsClass(\get_class($user))) {
            throw new UnsupportedUserException(sprintf('Expected an instance of %s, but got "%s".', $this->userRepository->getClass(), \get_class($user)));
        }

        if (null === $reloadedUser = $this->userRepository->findUserBy(['id' => $user->getId()])) {
            throw new UsernameNotFoundException(sprintf('User with ID "%s" could not be reloaded.', $user->getId()));
        }

        if (!$reloadedUser->isEnabled()) {
            throw new DisabledException('Account is disabled.');
        }

        return $reloadedUser;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsClass($class): bool
    {
        $userClass = $this->userRepository->getClass();

        return $userClass === $class || is_subclass_of($class, $userClass);
    }

    /**
     * Finds a user by username.
     *
     * This method is meant to be an extension point for child classes.
     *
     * @param string $username
     *
     * @return User|null
     */
    protected function findUser(string $username): ?User
    {
        return $this->userRepository->findUserByUsernameOrEmail($username);
    }
}
