<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Tests\Unit;

use Byfareska\StaticSite\Target\AdapterFactory;
use Byfareska\StaticSite\Target\Target;
use PHPUnit\Framework\TestCase;

final class AdapterFactoryTest extends TestCase
{
    public function testFtpDsn(): void
    {
        $options = AdapterFactory::ftpOptions('ftps://lk5%40host:p%40ss@ftp.example.com:2121/www?passive=0&timeout=30');

        self::assertSame('ftp.example.com', $options['host']);
        self::assertSame('www', $options['root']);
        self::assertSame('lk5@host', $options['username']);
        self::assertSame('p@ss', $options['password']);
        self::assertSame(2121, $options['port']);
        self::assertTrue($options['ssl']);
        self::assertFalse($options['passive']);
        self::assertSame(30, $options['timeout']);
    }

    public function testTheFtpPathIsInTheDirectoryOfTheAccount(): void
    {
        self::assertSame('', AdapterFactory::ftpOptions('ftp://u:p@host')['root']);
        self::assertSame('', AdapterFactory::ftpOptions('ftp://u:p@host/?passive=1')['root']);
        self::assertSame('public_html/lk5', AdapterFactory::ftpOptions('ftp://u:p@host/public_html/lk5/')['root']);
        // RFC 1738: an absolute path starts with %2F
        self::assertSame('/var/www', AdapterFactory::ftpOptions('ftp://u:p@host/%2Fvar/www')['root']);
    }

    public function testThePasswordIsNotShown(): void
    {
        $target = new Target('prod', 'Prod', 'ftp://user:secret@host/www');

        self::assertSame('ftp://user:***@host/www', $target->safeDsn());
    }

    public function testSymlinkIsForLocalTargetsOnly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Target('prod', 'Prod', 'ftp://user:secret@host/www', strategy: Target::SYMLINK);
    }
}
