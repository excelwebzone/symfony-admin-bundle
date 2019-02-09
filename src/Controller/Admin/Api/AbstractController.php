<?php

namespace EWZ\SymfonyAdminBundle\Controller\Admin\Api;

use Doctrine\Common\Persistence\ObjectManager;
use EWZ\SymfonyAdminBundle\Repository\AbstractRepository;
use EWZ\SymfonyAdminBundle\Service\FileUploader;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

abstract class AbstractController extends Controller
{
    /** @var ObjectManager */
    protected $objectManager;

    /** @var ValidatorInterface */
    protected $validator;

    /** @var TranslatorInterface */
    protected $translator;

    /** @var EventDispatcherInterface */
    protected $eventDispatcher;

    /** @var FileUploader */
    protected $fileUploader;

    /**
     * @param ObjectManager            $objectManager
     * @param ValidatorInterface       $validator
     * @param TranslatorInterface      $translator
     * @param EventDispatcherInterface $eventDispatcher
     * @param FileUploader             $fileUploader
     */
    public function __construct(
        ObjectManager $objectManager,
        ValidatorInterface $validator,
        TranslatorInterface $translator,
        EventDispatcherInterface $eventDispatcher,
        FileUploader $fileUploader
    ) {
        $this->objectManager = $objectManager;
        $this->validator = $validator;
        $this->translator = $translator;
        $this->eventDispatcher = $eventDispatcher;
        $this->fileUploader = $fileUploader;
    }

    /**
     * @return AbstractRepository
     */
    abstract public function getRepository(): AbstractRepository;

    /**
     * @param FormInterface $form
     *
     * @return array
     */
    protected function getErrorsFromForm(FormInterface $form): array
    {
        $errors = [];

        foreach ($form->getErrors() as $error) {
            $errors[] = $error->getMessage();
        }

        foreach ($form->all() as $childForm) {
            if ($childForm instanceof FormInterface) {
                if ($childErrors = $this->getErrorsFromForm($childForm)) {
                    $errors[$childForm->getName()] = $childErrors;
                }
            }
        }

        return $errors;
    }
}
