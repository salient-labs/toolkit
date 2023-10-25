<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Support;

use Lkrms\Support\Catalog\RegularExpression as Regex;
use Lkrms\Tests\Sync\Entity\User;

final class SyncStoreTest extends \Lkrms\Tests\Sync\SyncTestCase
{
    public function testRun(): void
    {
        $this->assertSame(1, $this->Store->getRunId(), 'getRunId()');
        $this->assertMatchesRegularExpression(Regex::anchorAndDelimit(Regex::UUID), $this->Store->getRunUuid(), 'getRunUuid()');
        $this->assertSame('lkrms-tests:User', $this->Store->getEntityTypeUri(User::class), '$this->Store->getEntityTypeUri()');
        $this->assertSame('https://lkrms.github.io/php-util/tests/entity/User', $this->Store->getEntityTypeUri(User::class, false), '$this->Store->getEntityTypeUri(, false)');
    }
}
