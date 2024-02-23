<?php declare(strict_types=1);

namespace Salient\Tests\Console\Catalog;

use Lkrms\Tests\TestCase;
use Salient\Console\Catalog\ConsoleMessageType as MessageType;
use Salient\Console\Catalog\ConsoleMessageTypeGroup as MessageTypeGroup;

final class ConsoleMessageTypesTest extends TestCase
{
    public function testALL(): void
    {
        $this->assertSame(array_values(MessageType::cases()), MessageTypeGroup::ALL);
    }
}
