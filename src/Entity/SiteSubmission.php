<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Entity;

use Byfareska\StaticSite\Endpoint\SubmissionStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * What a visitor sent through an endpoint of the site: kept in the database of the panel, read there.
 */
#[ORM\Entity]
#[ORM\Table(name: 'static_site_submission')]
#[ORM\Index(fields: ['endpoint', 'status'])]
#[ORM\Index(fields: ['createdAt'])]
class SiteSubmission implements \Stringable
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 64)]
    public string $endpoint;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    public array $data;

    #[ORM\Column(length: 12, enumType: SubmissionStatus::class)]
    public SubmissionStatus $status;

    /** A hash of the address of the sender (with the secret of the application): spotting repeats, no IP kept. */
    #[ORM\Column(length: 64, nullable: true)]
    public ?string $senderHash;

    #[ORM\Column(length: 255, nullable: true)]
    public ?string $userAgent;

    #[ORM\Column(length: 255, nullable: true)]
    public ?string $page;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $handledAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $note = null;

    /** @param array<string, mixed> $data */
    public function __construct(
        string $endpoint,
        array $data,
        SubmissionStatus $status = SubmissionStatus::New,
        ?string $senderHash = null,
        ?string $userAgent = null,
        ?string $page = null,
    ) {
        $this->endpoint = $endpoint;
        $this->data = $data;
        $this->status = $status;
        $this->senderHash = $senderHash;
        $this->userAgent = $userAgent === null ? null : mb_substr($userAgent, 0, 255);
        $this->page = $page === null ? null : mb_substr($page, 0, 255);
        $this->createdAt = new \DateTimeImmutable();
    }

    public function summary(int $length = 80): string
    {
        $text = '';
        foreach ($this->data as $value) {
            if (is_string($value) && trim($value) !== '') {
                $text .= ($text === '' ? '' : ' · ') . trim($value);
            }
        }

        return mb_strimwidth((string) preg_replace('/\s+/u', ' ', $text), 0, $length, '…');
    }

    public function __toString(): string
    {
        return sprintf('%s #%d', $this->endpoint, (int) $this->id);
    }
}
