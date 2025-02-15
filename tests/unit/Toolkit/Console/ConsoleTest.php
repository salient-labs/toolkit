<?php declare(strict_types=1);

namespace Salient\Tests\Console;

use Salient\Console\Support\ConsoleState;
use Salient\Console\Console as ConsoleService;
use Salient\Contract\Catalog\MessageLevel;
use Salient\Core\Facade\Console;
use Salient\Testing\Console\MockTarget;
use Salient\Tests\TestCase;

/**
 * @covers \Salient\Console\Console
 * @covers \Salient\Console\ConsoleFormatter
 */
final class ConsoleTest extends TestCase
{
    private ConsoleService $Writer;
    private MockTarget $Target;

    protected function setUp(): void
    {
        $this->Writer = new ConsoleService();
        $this->Target = new MockTarget();
        $this->Writer->registerTarget($this->Target);

        Console::load($this->Writer);
    }

    protected function tearDown(): void
    {
        Console::unload();
    }

    public function testLogProgress(): void
    {
        $state = $this->getState();
        for ($i = 0; $i < 11; $i++) {
            if (isset($state->SpinnerState)) {
                // @phpstan-ignore assign.propertyType
                $state->SpinnerState[1] = (float) (hrtime(true) / 1000) - 80000;
            }
            Console::logProgress('Complete:', sprintf('%d%%', ($i + 1) * 100 / 11));
        }

        $this->assertSameConsoleMessages([
            [MessageLevel::INFO, "⠋ Complete: 9%\r"],
            [MessageLevel::INFO, "⠙ Complete: 18%\r"],
            [MessageLevel::INFO, "⠹ Complete: 27%\r"],
            [MessageLevel::INFO, "⠸ Complete: 36%\r"],
            [MessageLevel::INFO, "⠼ Complete: 45%\r"],
            [MessageLevel::INFO, "⠴ Complete: 54%\r"],
            [MessageLevel::INFO, "⠦ Complete: 63%\r"],
            [MessageLevel::INFO, "⠧ Complete: 72%\r"],
            [MessageLevel::INFO, "⠇ Complete: 81%\r"],
            [MessageLevel::INFO, "⠏ Complete: 90%\r"],
            [MessageLevel::INFO, "⠋ Complete: 100%\r"],
        ], $this->Target->getMessages());
    }

    private function getState(): ConsoleState
    {
        return (function () {
            /** @var ConsoleService $this */
            // @phpstan-ignore property.private, varTag.nativeType
            return $this->State;
        })->bindTo($this->Writer, $this->Writer)();
    }
}
