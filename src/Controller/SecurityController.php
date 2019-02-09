<?php

namespace EWZ\SymfonyAdminBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Controller used to manage the application security.
 * See https://symfony.com/doc/current/cookbook/security/form_login_setup.html.
 */
class SecurityController extends AbstractController
{
    /**
     * @Route("/login", name="security_login")
     */
    public function login(): Response
    {
        /** @var AuthenticationUtils $helper */
        $helper = $this->get('security.authentication_utils');

        // last authentication error (if any)
        if ($error = $helper->getLastAuthenticationError()) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->render('@SymfonyAdmin/security/login.html.twig', [
            // last username entered by the user (if any)
            'last_username' => $helper->getLastUsername(),
        ]);
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
