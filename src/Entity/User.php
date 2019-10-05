<?php

namespace EWZ\SymfonyAdminBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use EWZ\SymfonyAdminBundle\Model\User as BaseUser;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\MappedSuperclass
 */
class User extends BaseUser
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer", unique=true)
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    protected $name;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    protected $timezone;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    protected $dateFormat;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    protected $timeFormat;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $allowSystemAdmin = false;

    /**
     * @ORM\Column(type="string")
     * @Assert\NotBlank()
     * @Assert\Length(min = 4)
     */
    protected $username;

    /**
     * @ORM\Column(type="string", unique=true)
     */
    protected $usernameCanonical;

    /**
     * @ORM\Column(type="string")
     * @Assert\NotBlank()
     * @Assert\Email()
     */
    protected $email;

    /**
     * @ORM\Column(type="string", unique=true)
     */
    protected $emailCanonical;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $enabled = true;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    protected $salt;

    /**
     * @ORM\Column(type="string")
     */
    protected $password;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @Assert\Length(min=6)
     */
    protected $plainPassword;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $passwordRequestedAt;

    /**
     * @ORM\Column(type="string", nullable=true, unique=true)
     */
    protected $confirmationToken;

    /**
     * @ORM\Column(type="array")
     */
    protected $roles = [];

    /**
     * @ORM\Column(type="array", nullable=true)
     */
    protected $settings;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $lastLogin;

    /**
     * @ORM\Column(type="datetime")
     */
    protected $createdAt;

    /**
     * @ORM\Column(type="datetime")
     */
    protected $modifiedAt;

    /**
     * @ORM\PrePersist
     */
    public function prePersist()
    {
        $this->createdAt = new \DateTime();
        $this->modifiedAt = new \DateTime();
    }

    /**
     * @ORM\PreUpdate
     */
    public function preUpdate()
    {
        $this->modifiedAt = new \DateTime();
    }
}
