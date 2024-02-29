<?php declare(strict_types=1);

namespace Salient\Tests\Console\Catalog;

use Salient\Contract\Console\ConsoleMessageType as MessageType;
use Salient\Contract\Console\ConsoleMessageTypeGroup as MessageTypeGroup;
use Salient\Tests\TestCase;

final class ConsoleMessageTypesTest extends TestCase
{
    public function testALL(): void
    {
        $this->assertSame(array_values(MessageType::cases()), MessageTypeGroup::ALL);
    }
}
