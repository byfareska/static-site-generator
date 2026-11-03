<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Publication;

enum PublicationStatus: string
{
    case Pending = 'pending';
    case Building = 'building';
    case Deploying = 'deploying';
    case Done = 'done';
    case Failed = 'failed';

    public function isFinished(): bool
    {
        return $this === self::Done || $this === self::Failed;
    }

    /** @return list<self> */
    public static function active(): array
    {
        return [self::Pending, self::Building, self::Deploying];
    }
}
