<?php

namespace EWZ\SymfonyAdminBundle\Model;

class Report
{
    /** @var int */
    protected $id;

    /** @var string */
    protected $token;

    /** @var string */
    protected $name;

    /** @var string */
    protected $group;

    /** @var bool */
    protected $enabled = true;

    /**
     * @return string
     */
    public function __toString(): string
    {
        return (string) $this->getName();
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
    public function getToken(): ?string
    {
        return $this->token;
    }

    /**
     * @param string|null $token
     */
    public function setToken(string $token = null): void
    {
        $this->token = $token;
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
    public function getGroup(): ?string
    {
        return $this->group;
    }

    /**
     * @param string|null $group
     */
    public function setGroup(string $group = null): void
    {
        $this->group = $group;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
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
}
