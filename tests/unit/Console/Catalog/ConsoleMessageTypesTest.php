<?php declare(strict_types=1);

namespace Lkrms\Tests\Console\Catalog;

use Lkrms\Console\Catalog\ConsoleMessageType;
use Lkrms\Console\Catalog\ConsoleMessageTypes;

final class ConsoleMessageTypesTest extends \Lkrms\Tests\TestCase
{
    public function testALL(): void
    {
        $this->assertSame(array_values(ConsoleMessageType::cases()), ConsoleMessageTypes::ALL);
    }
}
