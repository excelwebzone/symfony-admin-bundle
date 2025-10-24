<?php

namespace EWZ\SymfonyAdminBundle\Event;

use EWZ\SymfonyAdminBundle\Entity\Report;
use EWZ\SymfonyAdminBundle\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

final class ReportExportedEvent extends Event
{
    /** @var Report */
    private $report;

    /** @var User */
    private $user;

    /** @var string */
    private $fileName;

    /**
     * @param Report $report
     * @param User   $user
     * @param string $fileName
     */
    public function __construct(Report $report, User $user, string $fileName)
    {
        $this->report = $report;
        $this->user = $user;
        $this->fileName = $fileName;
    }

    /**
     * @return Report
     */
    public function getReport(): Report
    {
        return $this->report;
    }

    /**
     * @return User
     */
    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * @return string
     */
    public function getFileName(): string
    {
        return $this->fileName;
    }
}
