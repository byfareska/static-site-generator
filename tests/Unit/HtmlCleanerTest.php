<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Tests\Unit;

use Byfareska\StaticSite\Content\HtmlCleaner;
use PHPUnit\Framework\TestCase;

final class HtmlCleanerTest extends TestCase
{
    public function testHeadingsGetUniqueIds(): void
    {
        $html = HtmlCleaner::headingIds(
            '<h3>Pytanie 1. Linia nr 18</h3><p>x</p><h3 id="zsk">ZSK</h3><h3>Pytanie 1. Linia nr 18</h3><h2>Źródła</h2>',
            ['h3'],
        );

        self::assertSame(
            '<h3 id="pytanie-1-linia-nr-18">Pytanie 1. Linia nr 18</h3><p>x</p><h3 id="zsk">ZSK</h3>'
            . '<h3 id="pytanie-1-linia-nr-18-2">Pytanie 1. Linia nr 18</h3><h2>Źródła</h2>',
            $html,
        );
    }
}
