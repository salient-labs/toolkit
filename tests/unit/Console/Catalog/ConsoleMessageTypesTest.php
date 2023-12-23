<?php declare(strict_types=1);

namespace Lkrms\Tests\Console\Catalog;

use Lkrms\Console\Catalog\ConsoleMessageType;
use Lkrms\Console\Catalog\ConsoleMessageTypes;
use Lkrms\Tests\TestCase;

final class ConsoleMessageTypesTest extends TestCase
{
    public function testALL(): void
    {
        $this->assertSame(array_values(ConsoleMessageType::cases()), ConsoleMessageTypes::ALL);
    }
}
