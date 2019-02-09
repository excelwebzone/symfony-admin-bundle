<?php

namespace EWZ\SymfonyAdminBundle\Controller\Admin\Api\Traits;

use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

trait UpdateTrait
{
    /**
     * @param Request       $request
     * @param string        $formTypeClass
     * @param mixed         $object
     * @param string        $template
     * @param \Closure|null $preSetData
     * @param \Closure|null $postSetData
     * @param \Closure|null $preSubmitData
     * @param \Closure|null $postSubmitData
     * @param \Closure|null $onCompleted
     *
     * @return JsonResponse
     */
    private function doUpdate(
        Request $request,
        string $formTypeClass,
        $object,
        string $template = null,
        $preSetData = null,
        $postSetData = null,
        $preSubmitData = null,
        $postSubmitData = null,
        $onCompleted = null
    ): JsonResponse {
        if ($preSetData instanceof \Closure) {
            $preSetData->bindTo($this)($object);
        }

        $form = $this->createForm($formTypeClass);
        $form->setData($object);

        if ($postSetData instanceof \Closure) {
            $postSetData->bindTo($this)($object);
        }

        if ('GET' === $request->getMethod()) {
            $objectName = join('', array_slice(explode('\\', get_class($object)), -1));

            $html = $this->renderView($template, [
                'form' => $form->createView(),
                lcfirst($objectName) => $object,
            ]);

            return $this->json([
                'ok' => true,
                'html' => $html,
            ]);
        }

        $form->handleRequest($request);

        if ($preSubmitData instanceof \Closure) {
            $preSubmitData->bindTo($this)($object);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            try {
                if ($postSubmitData instanceof \Closure) {
                    $postSubmitData->bindTo($this)($object, $data);
                }

                $this->getRepository()->update($object);

                $data = [
                    'id' => $object->getId(),
                    'label' => (string) $object,
                ];
                if ($onCompleted instanceof \Closure) {
                    $data = array_merge($data, $onCompleted->bindTo($this)($object));
                }

                return $this->json(array_merge($data, [
                    'ok' => true,
                ]));
            } catch (\Exception $e) {
                $form->addError(new FormError($e->getMessage()));
            }
        }

        return $this->json([
            'ok' => false,
            'errors' => $this->getErrorsFromForm($form),
        ]);
    }
}
