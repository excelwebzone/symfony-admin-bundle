<?php

namespace EWZ\SymfonyAdminBundle\EventSubscriber;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Types\Type;
use EWZ\SymfonyAdminBundle\Doctrine\DBAL\Types\DateTimeType;
use EWZ\SymfonyAdminBundle\Model\User;
use EWZ\SymfonyAdminBundle\Util\DateTimeKernel;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Twig\Environment;
use Twig\Extension\CoreExtension;

/**
 * Timezone management and additional \DateTime methods.
 */
class TimezoneSubscriber implements EventSubscriberInterface
{
    /** @var KernelInterface */
    private $kernel;

    /** @var RequestStack */
    private $requestStack;

    /** @var TokenStorageInterface */
    private $tokenStorage;

    /** @var Environment */
    private $twig;

    /** @var string */
    private $timeZoneDatabase = null;

    /** @var string */
    private $timeZoneClient = null;

    /**
     * @param KernelInterface       $kernel
     * @param RequestStack          $requestStack
     * @param TokenStorageInterface $tokenStorage
     * @param Environment           $twig
     * @param string|null           $timeZoneDatabase
     * @param string|null           $timeZoneClient
     */
    public function __construct(
        KernelInterface $kernel,
        RequestStack $requestStack,
        TokenStorageInterface $tokenStorage,
        Environment $twig,
        string $timeZoneDatabase = null,
        string $timeZoneClient = null
    ) {
        $this->kernel = $kernel;
        $this->requestStack = $requestStack;
        $this->tokenStorage = $tokenStorage;
        $this->twig = $twig;
        $this->timeZoneDatabase = $timeZoneDatabase;
        $this->timeZoneClient = $timeZoneClient;

        $this->initializeTypeOverrides();
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => 'onConsoleCommand',
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    /**
     * @param ConsoleCommandEvent $event
     */
    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $this->updateDateTimeKernel();
    }

    /**
     * @param GetResponseEvent $event
     */
    public function onKernelRequest(GetResponseEvent $event): void
    {
        $this->updateDateTimeKernel();
    }

    /**
     * @throws DBALException
     */
    private function initializeTypeOverrides(): void
    {
        // save all datetime objects in the configured time zone,
        // see {@link http://docs.doctrine-project.org/projects/doctrine-orm/en/latest/cookbook/working-with-datetime.html}
        Type::overrideType('datetime', DateTimeType::class);
        Type::overrideType('datetimetz', DateTimeType::class);
    }

    /**
     * Method called:.
     *
     * - before controller action and
     * - before console command.
     */
    private function updateDateTimeKernel(): void
    {
        $this->updateKernelTimeZones();

        if (!$this->updateKernelDateTime()) {
            throw new \RuntimeException('Unable to initialize the DateTimeKernel object.');
        }
    }

    /**
     * Updates the timezones.
     */
    private function updateKernelTimeZones(): void
    {
        DateTimeKernel::setTimeZoneDatabase(new \DateTimeZone($this->timeZoneDatabase ?: date_default_timezone_get()));
        DateTimeKernel::setTimeZoneClient(new \DateTimeZone($this->timeZoneClient ?: date_default_timezone_get()));

        /** @var TokenInterface $token */
        if ($token = $this->tokenStorage->getToken()) {
            $user = $token->getUser();

            if ($user instanceof User) {
                DateTimeKernel::setTimeZoneClient(new \DateTimeZone($user->getTimezone()));
            }
        }

        // set PHP server timezone
        date_default_timezone_set(DateTimeKernel::getTimeZoneClient()->getName());

        // set Twig default timezone
        $this->twig->getExtension(CoreExtension::class)
            ->setTimezone(DateTimeKernel::getTimeZoneClient());
    }

    /**
     * Prerequisites: Method {@see self::updateKernelTimeZones()} must be called before.
     *
     * @return bool
     */
    private function updateKernelDateTime(): bool
    {
        return $this->updateKernelDateTimeByRequestTime()
            || $this->updateKernelDateTimeByKernelStartTime()
            || $this->updateKernelDateTimeByCurrentServerTime();
    }

    /**
     * @return bool
     */
    private function updateKernelDateTimeByRequestTime(): bool
    {
        if (is_null($this->requestStack) || is_null($this->requestStack->getMasterRequest())) {
            return false;
        }

        $request = $this->requestStack->getMasterRequest();

        if (is_numeric($request->server->get('REQUEST_TIME_FLOAT'))) {
            $datetime = \DateTimeImmutable::createFromFormat('U.u', $request->server->get('REQUEST_TIME_FLOAT'));
        } elseif (is_numeric($request->server->get('REQUEST_TIME'))) {
            $datetime = new \DateTimeImmutable(sprintf('@%d', $request->server->get('REQUEST_TIME')));
        }

        DateTimeKernel::setDateTimeServer($datetime);

        return true;
    }

    /**
     * @return bool
     */
    private function updateKernelDateTimeByKernelStartTime(): bool
    {
        if (is_null($this->kernel) || !is_numeric($this->kernel->getStartTime())) {
            return false;
        }

        DateTimeKernel::setDateTimeServer(
            new \DateTimeImmutable(sprintf('@%d', $this->kernel->getStartTime()))
        );

        return true;
    }

    /**
     * @return bool
     */
    private function updateKernelDateTimeByCurrentServerTime(): bool
    {
        DateTimeKernel::setDateTimeServer(
            new \DateTimeImmutable('now', new \DateTimeZone('+00:00'))
        );

        return true;
    }
}
