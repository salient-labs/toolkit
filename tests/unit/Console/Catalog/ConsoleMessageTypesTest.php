<?php declare(strict_types=1);

namespace Lkrms\Tests\Console\Catalog;

use Lkrms\Console\Catalog\ConsoleMessageType as MessageType;
use Lkrms\Console\Catalog\ConsoleMessageTypeGroup as MessageTypeGroup;
use Lkrms\Tests\TestCase;

final class ConsoleMessageTypesTest extends TestCase
{
    public function testALL(): void
    {
        $this->assertSame(array_values(MessageType::cases()), MessageTypeGroup::ALL);
    }
}
