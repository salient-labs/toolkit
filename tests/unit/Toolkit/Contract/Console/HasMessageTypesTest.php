<?php declare(strict_types=1);

namespace Salient\Tests\Contract\Console;

use Salient\Contract\Console\HasMessageType;
use Salient\Contract\Console\HasMessageTypes;
use Salient\Tests\TestCase;
use Salient\Utility\Reflect;

/**
 * @coversNothing
 */
final class HasMessageTypesTest extends TestCase
{
    public function testALL(): void
    {
        $this->assertSame(array_values(Reflect::getConstants(HasMessageType::class)), HasMessageTypes::TYPES_ALL);
    }
}
