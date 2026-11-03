<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Tests\Unit;

use Byfareska\StaticSite\Target\SyncPlan;
use PHPUnit\Framework\TestCase;

final class SyncPlanTest extends TestCase
{
    public function testAssetsGoFirstThenPagesThenWhatPointsAtThem(): void
    {
        $plan = SyncPlan::between([], [
            'sitemap.xml' => 'a',
            'index.html' => 'b',
            '.htaccess' => 'c',
            'css/style.css' => 'd',
            'artykuly/x.html' => 'e',
            'robots.txt' => 'f',
            'assets/zsk.png' => 'g',
        ]);

        self::assertSame(
            ['assets/zsk.png', 'css/style.css', 'artykuly/x.html', 'index.html', '.htaccess', 'robots.txt', 'sitemap.xml'],
            $plan->uploads,
        );
    }

    public function testOnlyChangedFilesAreSentAndOnlyOwnFilesDeleted(): void
    {
        $state = ['index.html' => 'old', 'css/style.css' => 'same', 'stary.html' => 'x'];
        $plan = SyncPlan::between($state, ['index.html' => 'new', 'css/style.css' => 'same']);

        self::assertSame(['index.html'], $plan->uploads);
        self::assertSame(['stary.html'], $plan->deletions);
        self::assertSame(1, $plan->unchanged);
        // .htpasswd was never sent by the generator: it is in no state, so it is never in the deletions.
        self::assertNotContains('.htpasswd', $plan->deletions);
    }

    public function testNothingToDo(): void
    {
        self::assertTrue(SyncPlan::between(['a.html' => '1'], ['a.html' => '1'])->isEmpty());
    }
}
