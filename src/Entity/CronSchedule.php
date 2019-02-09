<?php

namespace EWZ\SymfonyAdminBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use EWZ\SymfonyAdminBundle\Modal\CronSchedule as BaseCronSchedule;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\MappedSuperclass
 */
class CronSchedule extends BaseCronSchedule
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer", unique=true)
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @ORM\Column(type="string")
     * @Assert\NotBlank()
     */
    protected $command;

    /**
     * @ORM\Column(type="array")
     * @Assert\NotBlank()
     */
    protected $arguments = [];

    /**
     * @ORM\Column(type="string")
     * @Assert\NotBlank()
     */
    protected $argumentsHash;

    /**
     * @ORM\Column(type="string")
     * @Assert\NotBlank()
     */
    protected $definition;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $enabled = true;
}
