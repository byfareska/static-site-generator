<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Target;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Ftp\FtpConnectionOptions;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\PhpseclibV3\SftpConnectionProvider;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;

/**
 * The Flysystem filesystem of a target, made from its DSN:
 *
 * - local:///var/www/site
 * - ftp://user:password@host:21/public_html?passive=1&timeout=30 (ftps:// for FTP over TLS): the path is in the
 *   directory the account logs into (ftp://host/ is that directory); an absolute one starts with %2F, as in RFC 1738
 *   (ftp://host/%2Fvar/www)
 * - sftp://user:password@host:22/var/www?private_key=/path/to/key
 *
 * or the service named by "service" (a FilesystemOperator of flysystem-bundle, an S3 bucket...).
 */
final readonly class AdapterFactory
{
    public function __construct(
        #[AutowireLocator('static_site.filesystem')]
        private ContainerInterface $services,
    ) {
    }

    public function create(Target $target): FilesystemOperator
    {
        if ($target->service !== null) {
            $service = $this->services->get($target->service);
            if (!$service instanceof FilesystemOperator) {
                throw new \LogicException(sprintf('The service "%s" is no FilesystemOperator.', $target->service));
            }

            return $service;
        }

        $dsn = (string) $target->dsn;
        $scheme = strtolower((string) strstr($dsn, '://', true));

        return match ($scheme) {
            'local' => new Filesystem(new LocalFilesystemAdapter(
                (string) $target->localPath(),
                PortableVisibilityConverter::fromArray(['file' => ['public' => 0644], 'dir' => ['public' => 0755]]),
            )),
            'ftp', 'ftps' => new Filesystem(new FtpAdapter(FtpConnectionOptions::fromArray(self::ftpOptions($dsn)))),
            'sftp' => $this->sftp($dsn),
            default => throw new \InvalidArgumentException(sprintf(
                'The target "%s" has an address of no known kind ("%s"): local://, ftp://, ftps://, sftp://.',
                $target->name,
                $scheme,
            )),
        };
    }

    /** @return array<string, mixed> the options of FtpConnectionOptions::fromArray() */
    public static function ftpOptions(string $dsn): array
    {
        if (!class_exists(FtpAdapter::class)) {
            throw new \LogicException('An FTP target needs league/flysystem-ftp: composer require league/flysystem-ftp');
        }
        $url = self::parse($dsn);
        parse_str($url['query'] ?? '', $query);

        return [
            'host' => $url['host'],
            'root' => self::ftpRoot($url['path'] ?? ''),
            'username' => rawurldecode($url['user'] ?? 'anonymous'),
            'password' => rawurldecode($url['pass'] ?? ''),
            'port' => $url['port'] ?? 21,
            'ssl' => strtolower($url['scheme']) === 'ftps',
            'passive' => self::flag($query['passive'] ?? '1'),
            'timeout' => (int) ($query['timeout'] ?? 90),
            'utf8' => self::flag($query['utf8'] ?? '0'),
            'ignorePassiveAddress' => isset($query['ignore_passive_address'])
                ? self::flag($query['ignore_passive_address'])
                : null,
            'transferMode' => FTP_BINARY,
        ];
    }

    /**
     * The directory of an FTP address: in the one the account logs into, "" being that one. On a server that does
     * not lock the account in its directory, "/" is the root of the server's disk, not the account's.
     */
    private static function ftpRoot(string $path): string
    {
        $path = str_starts_with($path, '/') ? substr($path, 1) : $path;

        return rtrim(rawurldecode($path), '/');
    }

    private function sftp(string $dsn): FilesystemOperator
    {
        if (!class_exists(SftpAdapter::class)) {
            throw new \LogicException('An SFTP target needs league/flysystem-sftp-v3.');
        }
        $url = self::parse($dsn);
        parse_str($url['query'] ?? '', $query);
        $key = $query['private_key'] ?? null;

        return new Filesystem(new SftpAdapter(
            new SftpConnectionProvider(
                $url['host'],
                rawurldecode($url['user'] ?? ''),
                isset($url['pass']) ? rawurldecode($url['pass']) : null,
                is_string($key) ? $key : null,
                null,
                $url['port'] ?? 22,
            ),
            $url['path'] ?? '/',
        ));
    }

    /** @return array{scheme: string, host: string, port?: int, user?: string, pass?: string, path?: string, query?: string} */
    private static function parse(string $dsn): array
    {
        $url = parse_url($dsn);
        if (!is_array($url) || !isset($url['scheme'], $url['host'])) {
            throw new \InvalidArgumentException('The address of a target is not a valid DSN: ' . preg_replace('#:[^:@/]*@#', ':***@', $dsn));
        }

        return $url;
    }

    private static function flag(mixed $value): bool
    {
        return in_array(strtolower((string) (is_scalar($value) ? $value : '')), ['1', 'true', 'yes', 'on'], true);
    }
}
