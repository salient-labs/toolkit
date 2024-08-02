<?php declare(strict_types=1);

namespace Salient\Tests\Console;

use Salient\Console\Support\ConsoleWriterState;
use Salient\Console\Target\MockTarget;
use Salient\Console\ConsoleWriter;
use Salient\Contract\Core\MessageLevel;
use Salient\Core\Facade\Console;
use Salient\Tests\TestCase;

/**
 * @covers \Salient\Console\ConsoleWriter
 * @covers \Salient\Console\ConsoleFormatter
 */
final class ConsoleWriterTest extends TestCase
{
    private ConsoleWriter $Writer;
    private MockTarget $Target;

    protected function setUp(): void
    {
        $this->Writer = new ConsoleWriter();
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

    private function getState(): ConsoleWriterState
    {
        return (function () {
            /** @var ConsoleWriter $this */
            // @phpstan-ignore property.private
            return $this->State;
        })->bindTo($this->Writer, $this->Writer)();
    }
}
