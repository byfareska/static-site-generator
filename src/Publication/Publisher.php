<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Publication;

use Byfareska\StaticSite\Build\Build;
use Byfareska\StaticSite\Build\BuildStore;
use Byfareska\StaticSite\Build\SiteBuilder;
use Byfareska\StaticSite\Entity\Publication;
use Byfareska\StaticSite\Entity\TargetState;
use Byfareska\StaticSite\Page\RenderContext;
use Byfareska\StaticSite\Target\Target;
use Byfareska\StaticSite\Target\Targets;
use Byfareska\StaticSite\Target\TargetSync;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Publications: requested (from the panel or the console), run (by the runner of static_site.publish.mode) and sent
 * again (a rollback).
 */
final readonly class Publisher
{
    public function __construct(
        private EntityManagerInterface $em,
        private Targets $targets,
        private SiteBuilder $builder,
        private BuildStore $builds,
        private TargetSync $sync,
        private PublicationRunner $runner,
        #[Autowire('%static_site.keep_builds%')]
        private int $keepBuilds,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * Asks for a publication. One running already (or waiting) is returned instead of a second one.
     *
     * @param list<string> $targets
     */
    public function request(array $targets, ?string $requestedBy = null, ?Publication $rollbackOf = null): Publication
    {
        if ($targets === []) {
            throw new \InvalidArgumentException('Choose at least one target.');
        }
        foreach ($targets as $name) {
            $this->targets->get($name);
        }
        if ($rollbackOf !== null && ($rollbackOf->buildId === null || !$this->builds->exists($rollbackOf->buildId))) {
            throw new \RuntimeException(sprintf('The files of the publication %s are no longer kept.', $rollbackOf));
        }
        $active = $this->active();
        if ($active !== null) {
            return $active;
        }
        $publication = new Publication($targets, $requestedBy, $rollbackOf);
        $this->em->persist($publication);
        $this->em->flush();

        $this->runner->dispatch($publication);

        return $publication;
    }

    /** The publication waiting or running, if any. One that has run for an hour is taken for dead. */
    public function active(): ?Publication
    {
        /** @var list<Publication> $active */
        $active = $this->em->getRepository(Publication::class)->findBy(
            ['status' => PublicationStatus::active()],
            ['id' => 'DESC'],
        );
        foreach ($active as $publication) {
            $since = $publication->startedAt ?? $publication->createdAt;
            if ($since > new \DateTimeImmutable('-1 hour')) {
                return $publication;
            }
            $publication->status = PublicationStatus::Failed;
            $publication->addLog('Przerwana: nie skończyła się w ciągu godziny (proces został zatrzymany?).');
            $publication->finishedAt = new \DateTimeImmutable();
        }
        $this->em->flush();

        return null;
    }

    public function run(Publication $publication): void
    {
        if ($publication->status->isFinished()) {
            return;
        }
        $publication->startedAt = new \DateTimeImmutable();
        $publication->status = PublicationStatus::Building;
        $this->em->flush();
        $log = function (string $line) use ($publication): void {
            $publication->addLog(sprintf('[%s] %s', date('H:i:s'), $line));
            $this->em->flush();
        };

        try {
            $build = $this->buildFor($publication, $log);
            $targets = array_map($this->targets->get(...), $publication->targets);
            $this->assertBackend($build, $targets);

            $publication->status = PublicationStatus::Deploying;
            $this->em->flush();
            foreach ($targets as $target) {
                $this->deploy($publication, $target, $build, $log);
            }

            $publication->status = PublicationStatus::Done;
            $removed = $this->builds->prune($this->keepBuilds, $this->protectedBuilds());
            if ($removed !== []) {
                $log('Usunięto stare wersje: ' . implode(', ', $removed));
            }
            $log('Gotowe.');
        } catch (\Throwable $e) {
            $publication->status = PublicationStatus::Failed;
            $log('BŁĄD: ' . $e->getMessage());
            $this->logger->error('The publication {id} failed: {message}', [
                'id' => $publication->id,
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
        } finally {
            $publication->finishedAt = new \DateTimeImmutable();
            $this->em->flush();
        }
    }

    /** Builds the site without sending it anywhere (the console's --build-only, the tests). */
    public function buildOnly(string $id, ?\Closure $log = null): Build
    {
        return $this->builder->build($id, RenderContext::build(), $log);
    }

    public function state(string $target): ?TargetState
    {
        return $this->em->find(TargetState::class, $target);
    }

    /** @param \Closure(string): void $log */
    private function buildFor(Publication $publication, \Closure $log): Build
    {
        if ($publication->rollbackOf !== null) {
            $build = $this->builds->get((string) $publication->rollbackOf->buildId);
            $log(sprintf('Przywracanie wersji %s z publikacji %s.', $build->id, $publication->rollbackOf));
        } else {
            $id = sprintf('%s-%d', $publication->createdAt->format('Ymd-His'), (int) $publication->id);
            $log('Budowanie strony ' . $id);
            $build = $this->builder->build($id, RenderContext::build(), $log);
        }
        $publication->buildId = $build->id;
        $publication->fileCount = count($build->files);
        $this->em->flush();

        return $build;
    }

    /** @param list<Target> $targets */
    private function assertBackend(Build $build, array $targets): void
    {
        if ($build->endpoints === []) {
            return;
        }
        foreach ($targets as $target) {
            if (!$target->backend) {
                throw new \RuntimeException(sprintf(
                    'Strona używa endpointów (%s), a cel „%s” nie ma backendu. Endpointy działają tylko na celu '
                    . 'serwowanym przez tę samą aplikację (backend: true w static_site.targets).',
                    implode(', ', $build->endpoints),
                    $target->name,
                ));
            }
        }
    }

    /** @param \Closure(string): void $log */
    private function deploy(Publication $publication, Target $target, Build $build, \Closure $log): void
    {
        $state = $this->state($target->name);
        if ($state === null) {
            $state = new TargetState($target->name);
            $this->em->persist($state);
        }
        $state->complete = false;
        $this->em->flush();

        $result = $this->sync->sync(
            $target,
            $build,
            $state->files,
            function (array $files) use ($state): void {
                $state->files = $files;
                $this->em->flush();
            },
            $log,
        );

        $state->buildId = $build->id;
        $state->publication = $publication;
        $state->deployedAt = new \DateTimeImmutable();
        $state->complete = true;
        $publication->uploaded += $result->uploaded;
        $publication->deleted += $result->deleted;
        $this->em->flush();
        $log(sprintf('%s: wysłano %d, usunięto %d, bez zmian %d.', $target->label, $result->uploaded, $result->deleted, $result->unchanged));
    }

    /** @return list<string> the builds some target holds: never pruned */
    private function protectedBuilds(): array
    {
        $ids = [];
        foreach ($this->em->getRepository(TargetState::class)->findAll() as $state) {
            if ($state->buildId !== null) {
                $ids[] = $state->buildId;
            }
        }

        return $ids;
    }
}
