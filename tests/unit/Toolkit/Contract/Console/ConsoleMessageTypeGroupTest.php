<?php declare(strict_types=1);

namespace Salient\Tests\Contract\Console;

use Salient\Contract\Console\ConsoleMessageTypeGroup;
use Salient\Contract\Console\HasMessageType;
use Salient\Tests\TestCase;
use Salient\Utility\Reflect;

/**
 * @coversNothing
 */
final class ConsoleMessageTypeGroupTest extends TestCase
{
    public function testALL(): void
    {
        $this->assertSame(array_values(Reflect::getConstants(HasMessageType::class)), ConsoleMessageTypeGroup::ALL);
    }
}
