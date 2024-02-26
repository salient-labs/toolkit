<?php declare(strict_types=1);

namespace Salient\Tests\Sync\Support;

use Salient\Core\Catalog\Regex;
use Salient\Core\Utility\Pcre;
use Salient\Tests\Sync\Entity\User;
use Salient\Tests\Sync\SyncTestCase;

final class SyncStoreTest extends SyncTestCase
{
    public function testRun(): void
    {
        $this->assertSame(1, $this->Store->getRunId(), 'getRunId()');
        $this->assertMatchesRegularExpression(Pcre::delimit('^' . Regex::UUID . '$', '/'), $this->Store->getRunUuid(), 'getRunUuid()');
        $this->assertSame('salient-tests:User', $this->Store->getEntityTypeUri(User::class), '$this->Store->getEntityTypeUri()');
        $this->assertSame('https://salient-labs.github.io/toolkit/tests/entity/User', $this->Store->getEntityTypeUri(User::class, false), '$this->Store->getEntityTypeUri(, false)');
    }
}
