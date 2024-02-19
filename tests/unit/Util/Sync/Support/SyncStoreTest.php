<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Support;

use Lkrms\Tests\Sync\Entity\User;
use Lkrms\Tests\Sync\SyncTestCase;
use Salient\Core\Catalog\Regex;

final class SyncStoreTest extends SyncTestCase
{
    public function testRun(): void
    {
        $this->assertSame(1, $this->Store->getRunId(), 'getRunId()');
        $this->assertMatchesRegularExpression(Regex::anchorAndDelimit(Regex::UUID), $this->Store->getRunUuid(), 'getRunUuid()');
        $this->assertSame('lkrms-tests:User', $this->Store->getEntityTypeUri(User::class), '$this->Store->getEntityTypeUri()');
        $this->assertSame('https://lkrms.github.io/php-util/tests/entity/User', $this->Store->getEntityTypeUri(User::class, false), '$this->Store->getEntityTypeUri(, false)');
    }
}
