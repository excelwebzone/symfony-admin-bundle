<?php

namespace EWZ\SymfonyAdminBundle\Controller\Admin\Api\Traits;

use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

trait CreateTrait
{
    /**
     * @param Request       $request
     * @param string        $formTypeClass
     * @param string        $template
     * @param \Closure|null $preSetData
     * @param \Closure|null $postSetData
     * @param \Closure|null $preSubmitData
     * @param \Closure|null $postSubmitData
     * @param \Closure|null $onCompleted
     * @param \Closure|null $object         // for special objects
     *
     * @return JsonResponse
     */
    private function doCreate(
        Request $request,
        string $formTypeClass,
        string $template = null,
        $preSetData = null,
        $postSetData = null,
        $preSubmitData = null,
        $postSubmitData = null,
        $onCompleted = null,
        $object = null
    ): JsonResponse {
        if (!$object) {
            $object = $this->getRepository()->create();
        }

        if ($preSetData instanceof \Closure) {
            $preSetData->bindTo($this)($object);
        }

        $form = $this->createForm($formTypeClass);
        $form->setData($object);

        if ($postSetData instanceof \Closure) {
            $postSetData->bindTo($this)($object);
        }

        if ('GET' === $request->getMethod()) {
            $objectName = implode('', \array_slice(explode('\\', \get_class($object)), -1));

            $html = $template
                ? $this->renderView($template, [
                    'form' => $form->createView(),
                    lcfirst($objectName) => $object,
                ])
                : null;

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
            $object = $form->getData();

            try {
                if ($postSubmitData instanceof \Closure) {
                    $postSubmitData->bindTo($this)($object);
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
