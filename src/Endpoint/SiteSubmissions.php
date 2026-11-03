<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Endpoint;

use Byfareska\StaticSite\Entity\SiteSubmission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

/**
 * Keeps what visitors send. An endpoint records a submission, then hands the slow part (an e-mail) to a message:
 *
 *     $submission = $submissions->record('contact', $request, ['email' => $form->email, 'message' => $form->message]);
 *     $bus->dispatch(new ContactReceived($submission->id));
 */
final readonly class SiteSubmissions
{
    public function __construct(
        private EntityManagerInterface $em,
        #[Autowire('%kernel.secret%')]
        private string $secret,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function record(
        string $endpoint,
        Request $request,
        array $data,
        SubmissionStatus $status = SubmissionStatus::New,
    ): SiteSubmission {
        $ip = $request->getClientIp();
        $submission = new SiteSubmission(
            $endpoint,
            $data,
            $status,
            $ip === null ? null : hash_hmac('sha256', $ip, $this->secret),
            $request->headers->get('User-Agent'),
            $request->headers->get('Referer'),
        );
        $this->em->persist($submission);
        $this->em->flush();

        return $submission;
    }

    public function countNew(): int
    {
        return $this->em->getRepository(SiteSubmission::class)->count(['status' => SubmissionStatus::New]);
    }

    /** Removes the submissions older than $days; returns how many. */
    public function purgeOlderThan(int $days): int
    {
        return (int) $this->em->createQueryBuilder()
            ->delete(SiteSubmission::class, 's')
            ->where('s.createdAt < :before')
            ->setParameter('before', new \DateTimeImmutable(sprintf('-%d days', $days)))
            ->getQuery()
            ->execute();
    }
}
