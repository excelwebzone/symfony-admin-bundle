<?php

namespace EWZ\SymfonyAdminBundle\Repository;

use EWZ\SymfonyAdminBundle\Model\User;
use EWZ\SymfonyAdminBundle\Util\StringUtil;

abstract class UserRepository extends AbstractRepository
{
    /**
     * Returns a collection with all user instances.
     *
     * @return \Traversable
     */
    public function findUsers(): array
    {
        return $this->findAll();
    }

    /**
     * Finds one user by the given criteria.
     *
     * @param array $criteria
     *
     * @return \Traversable
     */
    public function findUsersBy(array $criteria): array
    {
        $queryBuilder = $this->createQueryBuilder('q');

        foreach ($criteria as $key => $value) {
            // removes all non-alphanumeric characters except whitespaces.
            $value = trim(preg_replace('/[[:space:]]+/', ' ', $value));

            // splits the search query into terms and removes the ones which are irrelevant.
            $terms = array_unique(explode(' ', $value));
            $searchTerms = array_filter($terms, function ($term) {
                return 2 <= mb_strlen($term);
            });

            if (0 === \count($searchTerms)) {
                continue;
            }

            foreach ($searchTerms as $index => $term) {
                $queryBuilder
                    ->orWhere(sprintf('q.%s LIKE :%s_%s', $key, $key, $index))
                    ->setParameter(sprintf(':%s_%s', $key, $index), sprintf('%%%s%%', $term))
                ;
            }
        }

        return $queryBuilder
            ->orderBy('q.createdAt', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Finds one user by the given criteria.
     *
     * @param array $criteria
     *
     * @return User|null
     */
    public function findUserBy(array $criteria): ?User
    {
        return $this->findOneBy($criteria);
    }

    /**
     * Find a user by its username.
     *
     * @param string $username
     *
     * @return User|null
     */
    public function findUserByUsername(string $username): ?User
    {
        return $this->findUserBy([
            'usernameCanonical' => StringUtil::canonicalize($username),
        ]);
    }

    /**
     * Finds a user by its email.
     *
     * @param string $email
     *
     * @return User|null
     */
    public function findUserByEmail(string $email): ?User
    {
        return $this->findUserBy([
            'emailCanonical' => StringUtil::canonicalize($email),
        ]);
    }

    /**
     * Finds a user by its username or email.
     *
     * @param string $usernameOrEmail
     *
     * @return User|null
     */
    public function findUserByUsernameOrEmail(string $usernameOrEmail): ?User
    {
        if (preg_match('/^.+\@\S+\.\S+$/', $usernameOrEmail)) {
            $user = $this->findUserByEmail($usernameOrEmail);
            if (null !== $user) {
                return $user;
            }
        }

        return $this->findUserByUsername($usernameOrEmail);
    }

    /**
     * Finds a user by its confirmationToken.
     *
     * @param string $token
     *
     * @return User|null
     */
    public function findUserByConfirmationToken(string $token): ?User
    {
        return $this->findUserBy(['confirmationToken' => $token]);
    }
}
