<?php

namespace EWZ\SymfonyAdminBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use EWZ\SymfonyAdminBundle\Model\Report as BaseReport;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\MappedSuperclass
 */
class Report extends BaseReport
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
    protected $token;

    /**
     * @ORM\Column(type="string")
     * @Assert\NotBlank()
     */
    protected $name;

    /**
     * @ORM\Column(type="string")
     * @Assert\NotBlank()
     */
    protected $group;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $enabled = true;
}
