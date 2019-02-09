<?php

namespace EWZ\SymfonyAdminBundle\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin")
 */
class DefaultController extends Controller
{
    /**
     * @Route("/access/denied/{rule}", name="admin_access_denied")
     *
     * @param string $rule
     *
     * @return Response
     */
    public function accessDenied(string $rule): Response
    {
        return $this->render('@SymfonyAdmin/admin/error/access_denied.html.twig', [
            'rule' => strtolower($rule),
        ]);
    }

    /**
     * @Route("/missing/{object}", name="admin_missing_entity")
     *
     * @param string $object
     *
     * @return Response
     */
    public function missingEntity(string $object): Response
    {
        return $this->render('@SymfonyAdmin/admin/error/missing_entity.html.twig', [
            'object' => strtolower($object),
        ]);
    }
}
