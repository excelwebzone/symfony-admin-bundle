<?php

namespace EWZ\SymfonyAdminBundle\Modal;

use EWZ\SymfonyAdminBundle\Util\StringUtil;
use Scheb\TwoFactorBundle\Model\Google\TwoFactorInterface;
use Symfony\Component\Security\Core\Encoder\BCryptPasswordEncoder;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface, TwoFactorInterface
{
    const ROLE_DEFAULT = 'ROLE_USER';
    const ROLE_ADMIN = 'ROLE_ADMIN';
    const ROLE_SUPER_ADMIN = 'ROLE_SUPER_ADMIN';

    /** @var int */
    protected $id;

    /** @var string */
    protected $name;

    /** @var string */
    protected $timezone;

    /** @var string */
    protected $dateFormat;

    /** @var bool */
    protected $allowSystemAdmin = false;

    /** @var string */
    protected $username;

    /** @var string */
    protected $usernameCanonical;

    /** @var string */
    protected $email;

    /** @var string */
    protected $emailCanonical;

    /** @var string */
    protected $googleAuthenticatorSecret;

    /** @var bool */
    protected $enabled;

    /** @var string */
    protected $salt;

    /** @var string */
    protected $password;

    /** @var string */
    protected $plainPassword;

    /** @var string */
    protected $passwordRequestedAt;

    /** @var string */
    protected $confirmationToken;

    /** @var array */
    protected $roles = [];

    /** @var array */
    protected $settings;

    /** @var \DateTime */
    protected $lastLogin;

    /** @var \DateTime */
    protected $createdAt;

    /** @var \DateTime */
    protected $modifiedAt;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->timezone = date_default_timezone_get();
        $this->dateFormat = 'm/d/Y';
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return (string) ($this->getName() ?: $this->getUsername());
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string|null $name
     */
    public function setName(string $name = null): void
    {
        $this->name = $name;
    }

    /**
     * @return string|null
     */
    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    /**
     * @param string|null $timezone
     */
    public function setTimezone(string $timezone = null): void
    {
        $this->timezone = $timezone;
    }

    /**
     * @return string|null
     */
    public function getDateFormat(): ?string
    {
        return $this->dateFormat;
    }

    /**
     * @param string|null $dateFormat
     */
    public function setDateFormat(string $dateFormat = null): void
    {
        $this->dateFormat = $dateFormat;
    }

    /**
     * @return bool
     */
    public function isAllowSystemAdmin()
    {
        return $this->allowSystemAdmin;
    }

    /**
     * @param bool $boolean
     */
    public function setAllowSystemAdmin(bool $boolean): void
    {
        $this->allowSystemAdmin = (bool) $boolean;
    }

    /**
     * @return string|null
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * @param string $username
     */
    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    /**
     * @return string
     */
    public function getUsernameCanonical(): string
    {
        return $this->usernameCanonical;
    }

    /**
     * @param string $usernameCanonical
     */
    public function setUsernameCanonical(string $usernameCanonical): void
    {
        $this->usernameCanonical = $usernameCanonical;
    }

    /**
     * @return string|null
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * @param string $email
     */
    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    /**
     * @return string
     */
    public function getEmailCanonical(): string
    {
        return $this->emailCanonical;
    }

    /**
     * @param string $emailCanonical
     */
    public function setEmailCanonical(string $emailCanonical): void
    {
        $this->emailCanonical = $emailCanonical;
    }

    /**
     * Updates canonical fields.
     */
    public function updateCanonicalFields(): void
    {
        $this->setUsernameCanonical($this->canonicalizeUsername((string) $this->getUsername()));
        $this->setEmailCanonical($this->canonicalizeEmail((string) $this->getEmail()));
    }

    /**
     * {@inheritdoc}
     */
    public function isGoogleAuthenticatorEnabled(): bool
    {
        return $this->googleAuthenticatorSecret ? true : false;
    }

    /**
     * {@inheritdoc}
     */
    public function getGoogleAuthenticatorUsername(): string
    {
        return $this->getUsername();
    }

    /**
     * @return string|null
     */
    public function getGoogleAuthenticatorSecret(): string
    {
        return $this->googleAuthenticatorSecret ?? '';
    }

    /**
     * @param string|null $googleAuthenticatorSecret
     */
    public function setGoogleAuthenticatorSecret(?string $googleAuthenticatorSecret): void
    {
        $this->googleAuthenticatorSecret = $googleAuthenticatorSecret;
    }

    /**
     * @return string|null
     */
    public function getSalt(): ?string
    {
        return $this->salt;
    }

    /**
     * @param string|null $salt
     */
    public function setSalt(string $salt = null): void
    {
        $this->salt = $salt;
    }

    /**
     * @return string|null
     */
    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    /**
     * @param string|null $password
     */
    public function setPlainPassword(string $password = null): void
    {
        $this->plainPassword = $password;
    }

    /**
     * Removes sensitive data from the user.
     */
    public function eraseCredentials(): void
    {
        // if you had a plainPassword property, you'd nullify it here
        // $this->plainPassword = null;
    }

    /**
     * @return string
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @param string $password
     */
    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    /**
     * Updates the hashed password in the user when there is a new password.
     *
     * The implement should be a no-op in case there is no new password (it should not erase the
     * existing hash with a wrong one).
     *
     * @param EncoderFactoryInterface $encoderFactory
     */
    public function hashPassword(EncoderFactoryInterface $encoderFactory): void
    {
        $plainPassword = $this->getPlainPassword();

        if (0 === strlen($plainPassword)) {
            return;
        }

        $encoder = $encoderFactory->getEncoder($this);

        if ($encoder instanceof BCryptPasswordEncoder) {
            $this->setSalt(null);
        } else {
            $salt = rtrim(str_replace('+', '.', base64_encode(random_bytes(32))), '=');
            $this->setSalt($salt);
        }

        $hashedPassword = $encoder->encodePassword($plainPassword, $this->getSalt());
        $this->setPassword($hashedPassword);
        $this->eraseCredentials();
    }

    /**
     * Gets the timestamp that the user requested a password reset.
     *
     * @return \DateTimeInterface|null
     */
    public function getPasswordRequestedAt(): ?\DateTimeInterface
    {
        return $this->passwordRequestedAt;
    }

    /**
     * Sets the timestamp that the user requested a password reset.
     *
     * @param \DateTimeInterface|null $date
     */
    public function setPasswordRequestedAt(\DateTimeInterface $date = null): void
    {
        $this->passwordRequestedAt = $date;
    }

    /**
     * Checks whether the password reset request has expired.
     *
     * @param int $ttl Requests older than this many seconds will be considered expired
     *
     * @return bool
     */
    public function isPasswordRequestNonExpired(int $ttl): bool
    {
        return $this->getPasswordRequestedAt() instanceof \DateTimeInterface &&
               $this->getPasswordRequestedAt()->getTimestamp() + $ttl > time();
    }

    /**
     * {@inheritdoc}
     *
     * @see AdvancedUserInterface
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * @param bool $boolean
     */
    public function setEnabled(bool $boolean): void
    {
        $this->enabled = (bool) $boolean;
    }

    /**
     * @return string|null
     */
    public function getConfirmationToken(): ?string
    {
        return $this->confirmationToken;
    }

    /**
     * @param string|null $confirmationToken
     */
    public function setConfirmationToken(string $confirmationToken = null): void
    {
        $this->confirmationToken = $confirmationToken;
    }

    /**
     * Tells if the the given user has the admin role.
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->hasRole(static::ROLE_ADMIN);
    }

    /**
     * Tells if the the given user has the super admin role.
     *
     * @return bool
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole(static::ROLE_SUPER_ADMIN);
    }

    /**
     * Sets the super admin status.
     *
     * @param bool $boolean
     */
    public function setSuperAdmin(bool $boolean): void
    {
        if (true === $boolean) {
            $this->addRole(static::ROLE_SUPER_ADMIN);
        } else {
            $this->removeRole(static::ROLE_SUPER_ADMIN);
        }
    }

    /**
     * @return array
     */
    public function getRoles(): array
    {
        $roles = $this->roles ?: [];

        // we need to make sure to have at least one role
        $roles[] = static::ROLE_DEFAULT;

        return array_unique($roles);
    }

    /**
     * This overwrites any previous roles.
     *
     * @param array $roles
     */
    public function setRoles(array $roles): void
    {
        $this->roles = [];

        foreach ($roles as $role) {
            $this->addRole($role);
        }
    }

    /**
     * Never use this to check if this user has access to anything!
     *
     * Use the AuthorizationChecker, or an implementation of AccessDecisionManager
     * instead, e.g.
     *
     *         $authorizationChecker->isGranted('ROLE_USER');
     *
     * @param string $role
     *
     * @return bool
     */
    public function hasRole(string $role): bool
    {
        return in_array(strtoupper($role), $this->getRoles(), true);
    }

    /**
     * @param string $role
     */
    public function addRole(string $role): void
    {
        $role = strtoupper($role);
        if ($role === static::ROLE_DEFAULT) {
            return;
        }

        if (!in_array($role, $this->roles, true)) {
            $this->roles[] = $role;
        }
    }

    /**
     * @param string $role
     */
    public function removeRole(string $role): void
    {
        if (false !== $key = array_search(strtoupper($role), $this->roles, true)) {
            unset($this->roles[$key]);
            $this->roles = array_values($this->roles);
        }
    }

    /**
     * @return array|null
     */
    public function getSettings(): ?array
    {
        return $this->settings;
    }

    /**
     * @param array|null $settings
     */
    public function setSettings(array $settings = null): void
    {
        $this->settings = $settings;
    }

    /**
     * @param string $table
     *
     * @return array|null
     */
    public function getTableColumns(string $table): ?array
    {
        return $this->settings['tables'][$table] ?? null;
    }

    /**
     * @param string $table
     * @param array  $columns
     */
    public function setTableColumns(string $table, array $columns): void
    {
        if (!isset($this->settings['tables'])) {
            $this->settings['tables'] = [];
        }

        $this->settings['tables'][$table] = $columns;
    }

    /**
     * @return \DateTimeInterface|null
     */
    public function getLastLogin(): ?\DateTimeInterface
    {
        return $this->lastLogin;
    }

    /**
     * @param \DateTimeInterface|null $lastLogin
     */
    public function setLastLogin(\DateTimeInterface $lastLogin = null): void
    {
        $this->lastLogin = $lastLogin;
    }

    /**
     * @return \DateTimeInterface
     */
    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * @param \DateTimeInterface $createdAt
     */
    public function setCreatedAt(\DateTimeInterface $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return \DateTimeInterface
     */
    public function getModifiedAt(): \DateTimeInterface
    {
        return $this->modifiedAt;
    }

    /**
     * @param \DateTimeInterface $modifiedAt
     */
    public function setModifiedAt(\DateTimeInterface $modifiedAt): void
    {
        $this->modifiedAt = $modifiedAt;
    }

    /**
     * @return string|null
     */
    protected function canonicalizeEmail(string $email): ?string
    {
        return StringUtil::canonicalize($email);
    }

    /**
     * @return string|null
     */
    protected function canonicalizeUsername(string $username): ?string
    {
        return StringUtil::canonicalize($username);
    }
}
