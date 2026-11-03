<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Tests\Functional;

use Byfareska\StaticSite\Tests\Functional\App\TestKernel;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;

abstract class FunctionalTestCase extends WebTestCase
{
    protected function setUp(): void
    {
        $fs = new Filesystem();
        foreach (['builds', 'plain', 'app', 'test.db'] as $path) {
            $fs->remove(TestKernel::varDir() . '/' . $path);
        }
        $fs->mkdir(TestKernel::varDir());
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
        self::getContainer()->get('cache.app')->clear();
        self::ensureKernelShutdown();
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    protected static function service(string $class): object
    {
        $service = self::getContainer()->get($class);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
