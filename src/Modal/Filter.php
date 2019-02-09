<?php

namespace EWZ\SymfonyAdminBundle\Modal;

class Filter
{
    /** @var int */
    protected $id;

    /** @var User */
    protected $user;

    /** @var string */
    protected $name;

    /** @var string */
    protected $section;

    /** @var Report */
    protected $report;

    /** @var array */
    protected $params;

    /** @var \DateTime */
    protected $createdAt;

    /** @var \DateTime */
    protected $modifiedAt;

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
     * @return User|null
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * @param User|null $user
     */
    public function setUser(User $user = null): void
    {
        $this->user = $user;
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
    public function getSection(): ?string
    {
        return $this->section;
    }

    /**
     * @param string|null $section
     */
    public function setSection(string $section = null): void
    {
        $this->section = $section;
    }

    /**
     * @return Report|null
     */
    public function getReport(): ?Report
    {
        return $this->report;
    }

    /**
     * @param Report|null $report
     */
    public function setReport(Report $report = null): void
    {
        $this->report = $report;
    }

    /**
     * @return array
     */
    public function getParams(): array
    {
        return $this->params ?: [];
    }

    /**
     * @param array $params
     */
    public function setParams(array $params = []): void
    {
        $this->params = $params;
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
}
