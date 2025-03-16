<?php declare(strict_types=1);

namespace Salient\Tests\Console;

use Salient\Console\Format\ConsoleFormatter;
use Salient\Console\Console as ConsoleService;
use Salient\Core\Facade\Console;
use Salient\Testing\Console\MockTarget;
use Salient\Tests\TestCase;

/**
 * @covers \Salient\Console\Console
 * @covers \Salient\Console\Format\ConsoleFormatter
 */
final class ConsoleTest extends TestCase
{
    private ConsoleService $Console;
    private ConsoleFormatter $Formatter;
    private MockTarget $Target;

    protected function setUp(): void
    {
        $this->Console = new ConsoleService();
        $this->Formatter = new ConsoleFormatter(null, null, fn() => $this->Target->getWidth());
        $this->Target = new MockTarget(null, true, true, true, 80, $this->Formatter);
        $this->Console->registerTarget($this->Target);

        Console::load($this->Console);
    }

    protected function tearDown(): void
    {
        Console::unload();
    }

    public function testLogProgress(): void
    {
        $spinnerState = &$this->getSpinnerState();
        for ($i = 0; $i < 11; $i++) {
            if ($spinnerState[1] !== null) {
                $spinnerState[1] = (float) (hrtime(true) / 1000) - 80000;
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

    /**
     * @return array{int<0,max>,float|null}
     */
    private function &getSpinnerState(): array
    {
        return (function &() {
            /** @var ConsoleFormatter $this */
            // @phpstan-ignore varTag.nativeType, property.private
            return $this->SpinnerState;
        })->bindTo($this->Formatter, $this->Formatter)();
    }
}
