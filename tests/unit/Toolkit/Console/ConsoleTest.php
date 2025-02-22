<?php declare(strict_types=1);

namespace Salient\Tests\Console;

use Salient\Console\Console as ConsoleService;
use Salient\Console\ConsoleState;
use Salient\Core\Facade\Console;
use Salient\Testing\Console\MockTarget;
use Salient\Tests\TestCase;

/**
 * @covers \Salient\Console\Console
 * @covers \Salient\Console\ConsoleFormatter
 */
final class ConsoleTest extends TestCase
{
    private ConsoleService $Console;
    private MockTarget $Target;

    protected function setUp(): void
    {
        $this->Console = new ConsoleService();
        $this->Target = new MockTarget();
        $this->Console->registerTarget($this->Target);

        Console::load($this->Console);
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
            [Console::LEVEL_INFO, "⠋ Complete: 9%\r"],
            [Console::LEVEL_INFO, "⠙ Complete: 18%\r"],
            [Console::LEVEL_INFO, "⠹ Complete: 27%\r"],
            [Console::LEVEL_INFO, "⠸ Complete: 36%\r"],
            [Console::LEVEL_INFO, "⠼ Complete: 45%\r"],
            [Console::LEVEL_INFO, "⠴ Complete: 54%\r"],
            [Console::LEVEL_INFO, "⠦ Complete: 63%\r"],
            [Console::LEVEL_INFO, "⠧ Complete: 72%\r"],
            [Console::LEVEL_INFO, "⠇ Complete: 81%\r"],
            [Console::LEVEL_INFO, "⠏ Complete: 90%\r"],
            [Console::LEVEL_INFO, "⠋ Complete: 100%\r"],
        ], $this->Target->getMessages());
    }

    private function getState(): ConsoleState
    {
        return (function () {
            /** @var ConsoleService $this */
            // @phpstan-ignore property.private, varTag.nativeType
            return $this->State;
        })->bindTo($this->Console, $this->Console)();
    }
}
