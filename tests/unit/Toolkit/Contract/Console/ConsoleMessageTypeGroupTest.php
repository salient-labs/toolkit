<?php declare(strict_types=1);

namespace Salient\Tests\Contract\Console;

use Salient\Contract\Console\ConsoleMessageType as MessageType;
use Salient\Contract\Console\ConsoleMessageTypeGroup as MessageTypeGroup;
use Salient\Tests\TestCase;

/**
 * @coversNothing
 */
final class ConsoleMessageTypeGroupTest extends TestCase
{
    public function testALL(): void
    {
        $this->assertSame(array_values(MessageType::cases()), MessageTypeGroup::ALL);
    }
}
