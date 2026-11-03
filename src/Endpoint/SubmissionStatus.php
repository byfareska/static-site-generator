<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Endpoint;

enum SubmissionStatus: string
{
    case New = 'new';
    case Handled = 'handled';
    case Spam = 'spam';
}
