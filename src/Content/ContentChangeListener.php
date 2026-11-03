<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Content;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;

/**
 * On every save of a SiteContent entity: its #[SiteHtml] properties are cleaned, and the time of the change is kept.
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class ContentChangeListener
{
    private bool $changed = false;

    /** @var array<class-string, list<array{\ReflectionProperty, SiteHtml}>> */
    private array $htmlProperties = [];

    public function __construct(
        private readonly HtmlCleaner $cleaner,
        private readonly ContentChanges $changes,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();
        $entities = [
            ...$uow->getScheduledEntityInsertions(),
            ...$uow->getScheduledEntityUpdates(),
            ...$uow->getScheduledEntityDeletions(),
        ];
        foreach ($uow->getScheduledCollectionUpdates() as $collection) {
            $entities[] = $collection->getOwner();
        }
        foreach ($uow->getScheduledCollectionDeletions() as $collection) {
            $entities[] = $collection->getOwner();
        }

        foreach ($entities as $entity) {
            if (!$entity instanceof SiteContent) {
                continue;
            }
            $this->changed = true;
            if ($uow->isScheduledForDelete($entity) || !$this->clean($entity)) {
                continue;
            }
            $uow->recomputeSingleEntityChangeSet($em->getClassMetadata($entity::class), $entity);
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if (!$this->changed) {
            return;
        }
        $this->changed = false;
        $this->changes->touch();
    }

    /** @return bool whether a property changed */
    private function clean(SiteContent $entity): bool
    {
        $changed = false;
        foreach ($this->htmlPropertiesOf($entity::class) as [$property, $html]) {
            $value = $property->isInitialized($entity) ? $property->getValue($entity) : null;
            if (!is_string($value) || $value === '') {
                continue;
            }
            $clean = $this->cleaner->clean($value, $html->sanitizer, $html->headingIds);
            if ($clean !== $value) {
                $property->setValue($entity, $clean);
                $changed = true;
            }
        }

        return $changed;
    }

    /**
     * @param class-string $class
     *
     * @return list<array{\ReflectionProperty, SiteHtml}>
     */
    private function htmlPropertiesOf(string $class): array
    {
        if (!isset($this->htmlProperties[$class])) {
            $this->htmlProperties[$class] = [];
            foreach ((new \ReflectionClass($class))->getProperties() as $property) {
                foreach ($property->getAttributes(SiteHtml::class) as $attribute) {
                    $this->htmlProperties[$class][] = [$property, $attribute->newInstance()];
                }
            }
        }

        return $this->htmlProperties[$class];
    }
}
