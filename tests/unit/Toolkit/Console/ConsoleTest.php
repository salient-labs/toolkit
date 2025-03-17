<?php declare(strict_types=1);

namespace Salient\Tests\Console;

use Salient\Console\Format\Formatter;
use Salient\Console\Console as ConsoleService;
use Salient\Core\Facade\Console;
use Salient\Testing\Console\MockTarget;
use Salient\Tests\TestCase;

/**
 * @covers \Salient\Console\Console
 * @covers \Salient\Console\Format\Formatter
 */
final class ConsoleTest extends TestCase
{
    private ConsoleService $Console;
    private Formatter $Formatter;
    private MockTarget $Target;
    private MockTarget $StdoutTarget;

    protected function setUp(): void
    {
        $this->Formatter = new Formatter(null, null, fn() => 80);
        $this->Target = new MockTarget(null, false, true, true, 80, $this->Formatter);
        $this->StdoutTarget = new MockTarget(null, true, false, false, 80, $this->Formatter);
        $this->Console = (new ConsoleService())
            ->registerTarget($this->Target)
            ->registerTarget($this->StdoutTarget, Console::LEVELS_ALL_EXCEPT_DEBUG);

        Console::load($this->Console);
    }

    protected function tearDown(): void
    {
        Console::unload();
    }

    public function testGetTargets(): void
    {
        $all = [$this->Target, $this->StdoutTarget];
        $stderr = [$this->Target];
        $stdout = [$this->StdoutTarget];
        $this->assertSame($all, $this->Console->getTargets());
        $this->assertSame($all, $this->Console->getTargets(null, Console::TARGET_STDIO));
        $this->assertSame([], $this->Console->getTargets(null, Console::TARGET_STDIO | Console::TARGET_INVERT));
        $this->assertSame($stderr, $this->Console->getTargets(null, Console::TARGET_TTY));
        $this->assertSame($stdout, $this->Console->getTargets(null, Console::TARGET_TTY | Console::TARGET_INVERT));
        $this->assertSame($stderr, $this->Console->getTargets(null, Console::TARGET_STDERR));
        $this->assertSame($stdout, $this->Console->getTargets(null, Console::TARGET_STDOUT));
        $this->assertSame($stderr, $this->Console->getTargets(Console::LEVEL_DEBUG));
        $this->assertSame([], $this->Console->getTargets(Console::LEVEL_DEBUG, Console::TARGET_STDOUT));
    }

    public function testMessageOutput(): void
    {
        $console = $this->Console;
        $console->info('info(<msg1>)');
        $console->info('info(<msg1>,<msg2>)', '<msg2>');
        $console->group('group(<msg1>)');
        $console->group('group(<msg1>,<msg2>)', '<msg2>');
        $console->infoOnce('infoOnce(<msg1>)');
        $console->infoOnce('infoOnce(<msg1>,<msg2>)', '<msg2>');
        $console->groupEnd();
        $console->groupEnd();
        $console->group('group(<msg1>,<msg2>,<endMsg1>,<endMsg2>)', '<msg2>', '<endMsg1>', '<endMsg2>');
        $console->log('log(<msg1>)');
        $console->group('group(<msg1>,null,<endMsg1>)', null, '<endMsg1>');
        $console->log('log(<msg1>,<msg2>)', '<msg2>');
        $console->groupEnd();
        $console->groupEnd();
        $console->logOnce('logOnce(<msg1>)');
        $console->logOnce('logOnce(<msg1>,<msg2>)', '<msg2>');
        $console->group('group(<msg1>,null,null,<endMsg2>)', null, null, '<endMsg2>');
        $console->group('group(<msg1>,null,<endMsg1>,<endMsg2>)', null, '<endMsg1>', '<endMsg2>');
        $console->error('error(<msg1>)');
        $console->error('error(<msg1>,<msg2>)', '<msg2>');
        $console->groupEnd();
        $console->groupEnd();
        $console->errorOnce('errorOnce(<msg1>)');
        $console->errorOnce('errorOnce(<msg1>,<msg2>)', '<msg2>');
        $console->group('group(<msg1>,<msg2>,<endMsg1>)', '<msg2>', '<endMsg1>');
        $console->group('group(<msg1>)');
        $console->warn('warn(<msg1>)');
        $console->warn('warn(<msg1>,<msg2>)', '<msg2>');
        $console->groupEnd();
        $console->groupEnd();
        $console->warnOnce('warnOnce(<msg1>)');
        $console->warnOnce('warnOnce(<msg1>,<msg2>)', '<msg2>');

        $line = __LINE__ + 1;
        $console->debug('debug(<msg1>)');
        $console->debug('debug(<msg1>,<msg2>)', '<msg2>');
        for ($i = 0; $i < 2; $i++) {
            $console->debugOnce('debugOnce(<msg1>)');
            $console->debugOnce('debugOnce(<msg1>,<msg2>)', '<msg2>');
        }

        $console->errorOnce('errorOnce(<msg1>,<msg2>)', '<msg2>');
        $console->errorOnce('errorOnce(<msg1>)');
        $console->infoOnce('infoOnce(<msg1>,<msg2>)', '<msg2>');
        $console->infoOnce('infoOnce(<msg1>)');
        $console->logOnce('logOnce(<msg1>,<msg2>)', '<msg2>');
        $console->logOnce('logOnce(<msg1>)');
        $console->warnOnce('warnOnce(<msg1>,<msg2>)', '<msg2>');
        $console->warnOnce('warnOnce(<msg1>)');

        $level3 = Console::LEVEL_ERROR;
        $level4 = Console::LEVEL_WARNING;
        $level5 = Console::LEVEL_NOTICE;
        $level6 = Console::LEVEL_INFO;
        $level7 = Console::LEVEL_DEBUG;
        $expectedStdout = [
            [$level5, '➤ info(msg1)'],
            [$level5, '➤ info(msg1,msg2) <msg2>'],
            [$level5, '» group(msg1)'],
            [$level5, '  » group(msg1,msg2) <msg2>'],
            [$level5, '  ➤ infoOnce(msg1)'],
            [$level5, '  ➤ infoOnce(msg1,msg2) <msg2>'],
            [$level5, '  '],
            [$level5, '» group(msg1,msg2,endMsg1,endMsg2) <msg2>'],
            [$level6, '- log(msg1)'],
            [$level5, '  » group(msg1,null,endMsg1)'],
            [$level6, '  - log(msg1,msg2) <msg2>'],
            [$level5, '  « endMsg1'],
            [$level5, '  '],
            [$level5, '« endMsg1 <endMsg2>'],
            [$level5, ''],
            [$level6, '- logOnce(msg1)'],
            [$level6, '- logOnce(msg1,msg2) <msg2>'],
            [$level5, '» group(msg1,null,null,endMsg2)'],
            [$level5, '  » group(msg1,null,endMsg1,endMsg2)'],
            [$level3, '  ! error(msg1)'],
            [$level3, '  ! error(msg1,msg2) <msg2>'],
            [$level5, '  « endMsg1 <endMsg2>'],
            [$level5, '  '],
            [$level3, '! errorOnce(msg1)'],
            [$level3, '! errorOnce(msg1,msg2) <msg2>'],
            [$level5, '» group(msg1,msg2,endMsg1) <msg2>'],
            [$level5, '  » group(msg1)'],
            [$level4, '  ^ warn(msg1)'],
            [$level4, '  ^ warn(msg1,msg2) <msg2>'],
            [$level5, '  '],
            [$level5, '« endMsg1'],
            [$level5, ''],
            [$level4, '^ warnOnce(msg1)'],
            [$level4, '^ warnOnce(msg1,msg2) <msg2>'],
        ];
        $expected = array_merge($expectedStdout, [
            [$level7, ': {' . __CLASS__ . '->' . __FUNCTION__ . ':' . ($line) . '} debug(msg1)'],
            [$level7, ': {' . __CLASS__ . '->' . __FUNCTION__ . ':' . ($line + 1) . '} debug(msg1,msg2) <msg2>'],
            [$level7, ': {' . __CLASS__ . '->' . __FUNCTION__ . ':' . ($line + 3) . '} debugOnce(msg1)'],
            [$level7, ': {' . __CLASS__ . '->' . __FUNCTION__ . ':' . ($line + 4) . '} debugOnce(msg1,msg2) <msg2>'],
        ]);
        $this->assertSameConsoleMessages($expected, $this->Target->getMessages());
        $this->assertSameConsoleMessages($expectedStdout, $this->StdoutTarget->getMessages());
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
        Console::clearProgress();

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
            [Console::LEVEL_INFO, "\r"],
        ], $this->Target->getMessages());
        $this->assertSame([], $this->StdoutTarget->getMessages());
    }

    /**
     * @return array{int<0,max>,float|null}
     */
    private function &getSpinnerState(): array
    {
        return (function &() {
            /** @var Formatter $this */
            // @phpstan-ignore varTag.nativeType, property.private
            return $this->SpinnerState;
        })->bindTo($this->Formatter, $this->Formatter)();
    }
}
