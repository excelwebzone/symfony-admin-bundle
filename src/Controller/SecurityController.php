<?php

namespace EWZ\SymfonyAdminBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Twig\Environment as TwigEnvironment;

/**
 * Controller used to manage the application security.
 * See https://symfony.com/doc/current/security/form_login_setup.html.
 */
class SecurityController
{
    /** @var AuthenticationUtils */
    private $helper;

    /** @var TwigEnvironment */
    private $twig;

    /**
     * @param AuthenticationUtils $helper
     * @param TwigEnvironment     $twig
     */
    public function __construct(AuthenticationUtils $helper, TwigEnvironment $twig)
    {
        $this->helper = $helper;
        $this->twig = $twig;
    }

    /**
     * @Route("/login", name="security_login")
     *
     * @return Response
     */
    public function login(Request $request): Response
    {
        // last authentication error (if any)
        if ($error = $this->helper->getLastAuthenticationError()) {
            $request->getSession()->getFlashBag()->add('error', $error->getMessage());
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
