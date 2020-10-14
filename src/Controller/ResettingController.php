<?php

namespace EWZ\SymfonyAdminBundle\Controller;

use EWZ\SymfonyAdminBundle\Event\FilterUserResponseEvent;
use EWZ\SymfonyAdminBundle\Event\UserEvent;
use EWZ\SymfonyAdminBundle\Events;
use EWZ\SymfonyAdminBundle\Form\ResettingFormType;
use EWZ\SymfonyAdminBundle\Modal\User;
use EWZ\SymfonyAdminBundle\Repository\UserRepository;
use EWZ\SymfonyAdminBundle\Util\StringUtil;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/resetting")
 */
class ResettingController extends AbstractController
{
    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    /** @var UserRepository */
    private $repository;

    /** @var int */
    private $retryTtl;

    /** @var int */
    private $tokenTtl;

    /**
     * @param EventDispatcherInterface $eventDispatcher
     * @param UserRepository           $repository
     * @param int                      $retryTtl
     * @param int                      $retryTtl
     */
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        UserRepository $repository,
        int $retryTtl = 7200,
        int $tokenTtl = 86400
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->repository = $repository;
        $this->retryTtl = $retryTtl;
        $this->tokenTtl = $tokenTtl;
    }

    /**
     * Request reset user password: show form.
     *
     * @Route("/", name="resetting_request")
     *
     * @return Response
     */
    public function index(): Response
    {
        return $this->render('@SymfonyAdmin/resetting/request.html.twig');
    }

    /**
     * Request reset user password: submit form and send email.
     *
     * @Route("/send-email", name="resetting_send_email", methods="POST")
     *
     * @param Request $request
     *
     * @return Response
     */
    public function sendEmail(Request $request): Response
    {
        $email = $request->request->get('email');

        /** @var User $user */
        $user = $this->repository->findUserByEmail($email);

        $error = null;

        if (null !== $user /* && !$user->isPasswordRequestNonExpired($this->retryTtl) */) {
            if (!$user->isEnabled()) {
                return $this->redirectToRoute('resetting_request');
            }

            if (null === $user->getConfirmationToken()) {
                $user->setConfirmationToken(StringUtil::generateToken());
            }

            $event = new UserEvent($user);
            $this->eventDispatcher->dispatch($event, Events::RESETTING_PASSWORD_SENT);

            $user->setPasswordRequestedAt(new \DateTime());
            $this->repository->update($user);

            return $this->redirectToRoute('resetting_check_email', ['email' => $email]);
        }

        $this->addFlash('error', 'resetting.wrong_username');

        return $this->redirectToRoute('resetting_request');
    }

    /**
     * Tell the user to check his email provider.
     *
     * @Route("/check-email", name="resetting_check_email")
     *
     * @param Request $request
     *
     * @return Response
     */
    public function checkEmail(Request $request): Response
    {
        $email = $request->query->get('email');

        if (empty($email)) {
            // the user does not come from the sendEmail action
            return $this->redirectToRoute('resetting_request');
        }

        $this->addFlash('success', 'resetting.password_reset_sent');

        return $this->redirectToRoute('security_login');
    }

    /**
     * Reset user password.
     *
     * @Route("/reset/{token}", name="resetting_reset", methods={"GET","POST"})
     *
     * @param Request $request
     * @param string  $token
     *
     * @return Response
     */
    public function reset(Request $request, string $token): Response
    {
        $user = $this->repository->findUserByConfirmationToken($token);

        if (null === $user) {
            return $this->redirectToRoute('security_login');
        }

        if (!$user->isPasswordRequestNonExpired($this->tokenTtl)) {
            return $this->redirectToRoute('resetting_request');
        }

        $form = $this->createForm(ResettingFormType::class);
        $form->setData($user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $user */
            $user = $form->getData();

            $user->setConfirmationToken(null);
            $user->setPasswordRequestedAt(null);
            $user->setEnabled(true);

            $this->repository->update($user);

            $this->addFlash('success', 'resetting.password_reset_successfully');

            $response = $this->redirectToRoute('admin_homepage');

            $event = new FilterUserResponseEvent($user, $request, $response);
            $this->eventDispatcher->dispatch($event, Events::RESETTING_PASSWORD_CONFIRMED);

            return $response;
        }

        return $this->render('@SymfonyAdmin/resetting/reset.html.twig', [
            'token' => $token,
            'form' => $form->createView(),
        ]);
    }
}
