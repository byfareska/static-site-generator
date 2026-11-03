<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Tests\Functional\App;

use Symfony\Component\Validator\Constraints as Assert;

final class ContactRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Podaj adres e-mail.')]
        #[Assert\Email(message: 'To nie jest adres e-mail.')]
        public string $email = '',
        #[Assert\NotBlank(message: 'Napisz wiadomość.')]
        public string $message = '',
    ) {
    }
}
