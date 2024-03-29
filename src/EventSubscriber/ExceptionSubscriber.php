<?php

namespace EWZ\SymfonyAdminBundle\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Handle all exceptions.
 */
class ExceptionSubscriber implements EventSubscriberInterface
{
    /** @var KernelInterface */
    private $kernel;

    /** @var UrlGeneratorInterface */
    private $urlGenerator;

    /**
     * @param KernelInterface       $kernel
     * @param UrlGeneratorInterface $router
     */
    public function __construct(KernelInterface $kernel, UrlGeneratorInterface $urlGenerator)
    {
        $this->kernel = $kernel;
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    /**
     * @param ExceptionEvent $event
     */
    public function onKernelException(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();

        if (!$this->kernel->isDebug() && $event->getRequest()->isXmlHttpRequest()) {
            $response = new JsonResponse([
                'ok' => false,
                'error' => [
                    'message' => $throwable->getMessage(),
                ],
            ]);

            $event->setResponse($response);
        }

        if ($throwable instanceof AccessDeniedHttpException) {
            if (preg_match('/Access Denied by controller annotation @IsGranted\("(.+)"\)/', $throwable->getMessage(), $matches)
                || preg_match('/Access Denied by controller annotation @IsGranted\("(.+)", (.+)\)/', $throwable->getMessage(), $matches)
            ) {
                $redirectUrl = $this->urlGenerator->generate('admin_access_denied', [
                    'rule' => strtolower(explode('|', $matches[1])[0]),
                ]);
                $response = new RedirectResponse($redirectUrl);
                $event->setResponse($response);
            }
        }

        if ($throwable instanceof NotFoundHttpException) {
            if (preg_match('/App:(\w+) object not found by the @ParamConverter annotation/', $throwable->getMessage(), $matches)) {
                $redirectUrl = $this->urlGenerator->generate('admin_missing_entity', [
                    'object' => strtolower($matches[1]),
                ]);
                $response = new RedirectResponse($redirectUrl);
                $event->setResponse($response);
            }
        }
    }
}
