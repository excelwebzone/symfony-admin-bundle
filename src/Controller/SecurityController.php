<?php

namespace EWZ\SymfonyAdminBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Controller used to manage the application security.
 * See https://symfony.com/doc/current/cookbook/security/form_login_setup.html.
 */
class SecurityController
{
    /** @var AuthenticationUtils */
    private $helper;

    /** @var Session */
    private $session;

    /** @var \Twig_Environment */
    private $twig;

    /**
     * @param AuthenticationUtils $helper
     * @param Session             $session
     * @param \Twig_Environment   $twig
     */
    public function __construct(AuthenticationUtils $helper, Session $session, \Twig_Environment $twig)
    {
        $this->helper = $helper;
        $this->session = $session;
        $this->twig = $twig;
    }

    /**
     * @Route("/login", name="security_login")
     *
     * @return Response
     */
    public function login(): Response
    {
        // last authentication error (if any)
        if ($error = $this->helper->getLastAuthenticationError()) {
            $this->session->getFlashBag()->add('error', $error->getMessage());
        }

        $content = $this->twig->render('@SymfonyAdmin/security/login.html.twig', [
            // last username entered by the user (if any)
            'last_username' => $this->helper->getLastUsername(),
        ]);

        return Response::create($content);
    }

    /**
     * This is the route the user can use to logout.
     *
     * But, this will never be executed. Symfony will intercept this first
     * and handle the logout automatically. See logout in config/packages/security.yaml
     *
     * @Route("/logout", name="security_logout")
     */
    public function logout(): void
    {
        throw new \Exception('This should never be reached!');
    }
}
