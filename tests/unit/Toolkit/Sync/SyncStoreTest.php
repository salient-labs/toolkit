<?php declare(strict_types=1);

namespace Salient\Tests\Sync;

use Salient\Tests\Sync\Entity\Provider\PostProvider;
use Salient\Tests\Sync\Entity\Post;
use Salient\Tests\Sync\Entity\User;
use Salient\Utility\Regex;

/**
 * @covers \Salient\Sync\SyncStore
 * @covers \Salient\Core\AbstractStore
 */
final class SyncStoreTest extends SyncTestCase
{
    public function testRun(): void
    {
        // Trigger the start of a run
        $this->App->get(PostProvider::class)->with(Post::class)->get(1);

        $this->assertSame(1, $this->Store->getRunId(), 'getRunId()');
        $this->assertMatchesRegularExpression(Regex::delimit('^' . Regex::UUID . '$', '/'), $this->Store->getRunUuid(), 'getRunUuid()');
        $this->assertSame('salient-tests:User', $this->Store->getEntityUri(User::class), '$this->Store->getEntityUri()');
        $this->assertSame('https://salient-labs.github.io/toolkit/tests/entity/User', $this->Store->getEntityUri(User::class, false), '$this->Store->getEntityUri(, false)');
    }
}
