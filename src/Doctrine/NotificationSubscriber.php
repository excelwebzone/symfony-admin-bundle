<?php

namespace EWZ\SymfonyAdminBundle\Doctrine;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events as ORMEvents;
use EWZ\SymfonyAdminBundle\Annotation\AllowedTags;
use EWZ\SymfonyAdminBundle\Event\ObjectEvent;
use EWZ\SymfonyAdminBundle\Events;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Doctrine listener to handle user notifications.
 */
class NotificationSubscriber implements EventSubscriber
{
    /** @var EventDispatcherInterface */
    protected $eventDispatcher;

    /** @var TokenStorageInterface */
    protected $tokenStorage;

    /** @var ObjectEvent */
    protected $notificationEvent;

    /**
     * @param EventDispatcherInterface $eventDispatcher
     * @param TokenStorageInterface    $tokenStorage
     */
    public function __construct(EventDispatcherInterface $eventDispatcher, TokenStorageInterface $tokenStorage)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * {@inheritdoc}
     */
    public function getSubscribedEvents(): array
    {
        return [
            ORMEvents::postPersist,
            ORMEvents::preUpdate,
            ORMEvents::postUpdate,
            ORMEvents::onFlush,
        ];
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function postPersist(LifecycleEventArgs $args): void
    {
        $event = new ObjectEvent($args->getObject());
        $this->eventDispatcher->dispatch($event, Events::NOTIFICATION_OBJECT_CREATED);
    }

    /**
     * @param PreUpdateEventArgs $args
     */
    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $object = $args->getObject();
        $entityChangeSet = $args->getEntityChangeSet();

        $this->notificationEvent = new ObjectEvent($object, $entityChangeSet);
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function postUpdate(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        /** @var TokenInterface $token */
        if (!$token = $this->tokenStorage->getToken()) {
            return;
        }

        // @TODO: override in project
    }

    /**
     * @param OnFlushEventArgs $args
     */
    public function onFlush(OnFlushEventArgs $args): void
    {
        foreach ($args->getEntityManager()->getUnitOfWork()->getScheduledEntityUpdates() as $entity) {
            $this->checkEntityForXSS($args->getEntityManager(), $entity);
        }

        foreach ($args->getEntityManager()->getUnitOfWork()->getScheduledEntityInsertions() as $entity) {
            $this->checkEntityForXSS($args->getEntityManager(), $entity);
        }
    }

    /**
     * @param EntityManagerInterface $em
     * @param mixed                  $entity
     */
    private function checkEntityForXSS(EntityManagerInterface $em, $entity): void
    {
        $annotationReader = new AnnotationReader();
        $entityClass = ClassUtils::getClass($entity);
        $fieldMappings = $em->getClassMetadata($entityClass)->fieldMappings;

        foreach ($em->getUnitOfWork()->getEntityChangeSet($entity) as $propertyName => $propertyChangeSet) {
            if (isset($fieldMappings[$propertyName]['type'])
                && in_array($fieldMappings[$propertyName]['type'], ['string', 'text'])
            ) {
                $reflectionProperty = new \ReflectionProperty(
                    $fieldMappings[$propertyName]['inherited'] ?? $entityClass,
                    $propertyName
                );

                /** @var AllowedTags $allowedTagsAnnotation */
                $allowedTagsAnnotation = $annotationReader->getPropertyAnnotation($reflectionProperty, AllowedTags::class);

                $allowedTags = $allowedTagsAnnotation ? $allowedTagsAnnotation->getTags() : [];

                $propertyGetter = sprintf('get%s', ucfirst($propertyName));
                $propertySetter = sprintf('set%s', ucfirst($propertyName));

                if (method_exists($entity, $propertySetter)
                    && method_exists($entity, $propertyGetter)
                    && $entity->$propertyGetter()
                ) {
                    $entity->$propertySetter(strip_tags($entity->$propertyGetter(), implode('', $allowedTags)));
                }
            }
        }
    }
}
