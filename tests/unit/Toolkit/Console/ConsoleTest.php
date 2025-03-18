<?php declare(strict_types=1);

namespace Salient\Tests\Console;

use Salient\Console\Format\Formatter;
use Salient\Console\Format\TtyFormat;
use Salient\Console\Console as ConsoleService;
use Salient\Core\Facade\Console;
use Salient\Testing\Console\MockTarget;
use Salient\Tests\TestCase;
use Salient\Utility\Get;

/**
 * @covers \Salient\Console\Console
 * @covers \Salient\Console\Format\Formatter
 * @covers \Salient\Console\Format\TagFormats
 * @covers \Salient\Console\Format\MessageFormats
 * @covers \Salient\Console\Format\TagAttributes
 * @covers \Salient\Console\Format\MessageAttributes
 * @covers \Salient\Console\Format\AbstractFormat
 * @covers \Salient\Console\Format\MessageFormat
 * @covers \Salient\Console\Format\NullFormat
 * @covers \Salient\Console\Format\NullMessageFormat
 * @covers \Salient\Console\Format\TtyFormat
 */
final class ConsoleTest extends TestCase
{
    private ConsoleService $Console;
    private Formatter $Formatter;
    private MockTarget $TtyTarget;
    private MockTarget $StdoutTarget;
    private MockTarget $ColourTarget;

    protected function setUp(): void
    {
        $this->Formatter = new Formatter(null, null, fn() => $this->TtyTarget->getWidth());
        $this->TtyTarget = new MockTarget(null, false, true, true, 80, $this->Formatter);
        $this->StdoutTarget = new MockTarget(null, true, false, false, 80, $this->Formatter);
        $this->ColourTarget = new MockTarget(null, false, false, false, 80, TtyFormat::getFormatter(fn() => $this->ColourTarget->getWidth()));
        $this->Console = (new ConsoleService())
            ->registerTarget($this->TtyTarget)
            ->registerTarget($this->StdoutTarget, Console::LEVELS_ALL_EXCEPT_DEBUG)
            ->registerTarget($this->ColourTarget);

        Console::load($this->Console);
    }

    protected function tearDown(): void
    {
        Console::unload();
    }

    public function testGetTargets(): void
    {
        $all = [$this->TtyTarget, $this->StdoutTarget, $this->ColourTarget];
        $stdio = [$this->TtyTarget, $this->StdoutTarget];
        $stderr = [$this->TtyTarget];
        $stdout = [$this->StdoutTarget];
        $colour = [$this->ColourTarget];
        $notTty = [$this->StdoutTarget, $this->ColourTarget];
        $debug = [$this->TtyTarget, $this->ColourTarget];
        $console = $this->Console;
        $this->assertSame($all, $console->getTargets());
        $this->assertSame($stdio, $console->getTargets(null, Console::TARGET_STDIO));
        $this->assertSame($colour, $console->getTargets(null, Console::TARGET_STDIO | Console::TARGET_INVERT));
        $this->assertSame($stderr, $console->getTargets(null, Console::TARGET_TTY));
        $this->assertSame($notTty, $console->getTargets(null, Console::TARGET_TTY | Console::TARGET_INVERT));
        $this->assertSame($stderr, $console->getTargets(null, Console::TARGET_STDERR));
        $this->assertSame($stdout, $console->getTargets(null, Console::TARGET_STDOUT));
        $this->assertSame($debug, $console->getTargets(Console::LEVEL_DEBUG));
        $this->assertSame([], $console->getTargets(Console::LEVEL_DEBUG, Console::TARGET_STDOUT));
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
        Console::debug('debug(<msg1>)');
        $console->debug('debug(<msg1>,<msg2>)', '<msg2>');
        for ($i = 0; $i < 2; $i++) {  // Method chains must remain on one line
            Console::debugOnce('debugOnce(<msg1>)')->debugOnce('debugOnce(<msg1>)');
            Console::debugOnce('debugOnce(<msg1>,<msg2>)', '<msg2>')->debugOnce('debugOnce(<msg1>,<msg2>)', '<msg2>');
        }

        $console->errorOnce('errorOnce(<msg1>,<msg2>)', '<msg2>');
        $console->errorOnce('errorOnce(<msg1>)');
        $console->infoOnce('infoOnce(<msg1>,<msg2>)', '<msg2>');
        $console->infoOnce('infoOnce(<msg1>)');
        $console->logOnce('logOnce(<msg1>,<msg2>)', '<msg2>');
        $console->logOnce('logOnce(<msg1>)');
        $console->warnOnce('warnOnce(<msg1>,<msg2>)', '<msg2>');
        $console->warnOnce('warnOnce(<msg1>)');

        $console->deregisterTarget($this->TtyTarget);
        $console->logProgress('logProgress(<msg1>,<msg2>)', '<msg2>');
        $console->logProgress('logProgress(<msg1>)');
        $console->clearProgress();

        $console->count(Console::LEVEL_WARNING);

        $expectedStdout = [
            [5, '➤ info(msg1)'],
            [5, '➤ info(msg1,msg2) <msg2>'],
            [5, '» group(msg1)'],
            [5, '  » group(msg1,msg2) <msg2>'],
            [5, '  ➤ infoOnce(msg1)'],
            [5, '  ➤ infoOnce(msg1,msg2) <msg2>'],
            [5, '  '],
            [5, '» group(msg1,msg2,endMsg1,endMsg2) <msg2>'],
            [6, '- log(msg1)'],
            [5, '  » group(msg1,null,endMsg1)'],
            [6, '  - log(msg1,msg2) <msg2>'],
            [5, '  « endMsg1'],
            [5, '  '],
            [5, '« endMsg1 <endMsg2>'],
            [5, ''],
            [6, '- logOnce(msg1)'],
            [6, '- logOnce(msg1,msg2) <msg2>'],
            [5, '» group(msg1,null,null,endMsg2)'],
            [5, '  » group(msg1,null,endMsg1,endMsg2)'],
            [3, '  ! error(msg1)'],
            [3, '  ! error(msg1,msg2) <msg2>'],
            [5, '  « endMsg1 <endMsg2>'],
            [5, '  '],
            [3, '! errorOnce(msg1)'],
            [3, '! errorOnce(msg1,msg2) <msg2>'],
            [5, '» group(msg1,msg2,endMsg1) <msg2>'],
            [5, '  » group(msg1)'],
            [4, '  ^ warn(msg1)'],
            [4, '  ^ warn(msg1,msg2) <msg2>'],
            [5, '  '],
            [5, '« endMsg1'],
            [5, ''],
            [4, '^ warnOnce(msg1)'],
            [4, '^ warnOnce(msg1,msg2) <msg2>'],
        ];
        $expectedTty = array_merge($expectedStdout, [
            [7, ': {' . __CLASS__ . '->' . __FUNCTION__ . ':' . ($line) . '} debug(msg1)'],
            [7, ': {' . __CLASS__ . '->' . __FUNCTION__ . ':' . ($line + 1) . '} debug(msg1,msg2) <msg2>'],
            [7, ': {' . __CLASS__ . '->' . __FUNCTION__ . ':' . ($line + 3) . '} debugOnce(msg1)'],
            [7, ': {' . __CLASS__ . '->' . __FUNCTION__ . ':' . ($line + 4) . '} debugOnce(msg1,msg2) <msg2>'],
        ]);
        $expectedColour = [
            [5, "\e[1m\e[36m➤ \e[39m\e[22m\e[1minfo(\e[33m\e[4mmsg1\e[24m\e[39m)\e[22m"],
            [5, "\e[1m\e[36m➤ \e[39m\e[22m\e[1minfo(\e[33m\e[4mmsg1\e[24m\e[39m,\e[33m\e[4mmsg2\e[24m\e[39m)\e[22m\e[36m <msg2>\e[39m"],
            [5, "\e[1m\e[35m» \e[39m\e[22m\e[1m\e[35mgroup(\e[33m\e[4mmsg1\e[24m\e[35m)\e[39m\e[22m"],
            [5, "  \e[1m\e[35m» \e[39m\e[22m\e[1m\e[35mgroup(\e[33m\e[4mmsg1\e[24m\e[35m,\e[33m\e[4mmsg2\e[24m\e[35m)\e[39m\e[22m <msg2>"],
            [5, "  \e[1m\e[36m➤ \e[39m\e[22m\e[1minfoOnce(\e[33m\e[4mmsg1\e[24m\e[39m)\e[22m"],
            [5, "  \e[1m\e[36m➤ \e[39m\e[22m\e[1minfoOnce(\e[33m\e[4mmsg1\e[24m\e[39m,\e[33m\e[4mmsg2\e[24m\e[39m)\e[22m\e[36m <msg2>\e[39m"],
            [5, "\e[1m\e[35m» \e[39m\e[22m\e[1m\e[35mgroup(\e[33m\e[4mmsg1\e[24m\e[35m,\e[33m\e[4mmsg2\e[24m\e[35m,\e[33m\e[4mendMsg1\e[24m\e[35m,\e[33m\e[4mendMsg2\e[24m\e[35m)\e[39m\e[22m <msg2>"],
            [6, "\e[33m- \e[39mlog(\e[33m\e[4mmsg1\e[24m\e[39m)"],
            [5, "  \e[1m\e[35m» \e[39m\e[22m\e[1m\e[35mgroup(\e[33m\e[4mmsg1\e[24m\e[35m,null,\e[33m\e[4mendMsg1\e[24m\e[35m)\e[39m\e[22m"],
            [6, "  \e[33m- \e[39mlog(\e[33m\e[4mmsg1\e[24m\e[39m,\e[33m\e[4mmsg2\e[24m\e[39m)\e[33m <msg2>\e[39m"],
            [5, "  \e[1m\e[35m« \e[39m\e[22m\e[1m\e[35m\e[33m\e[4mendMsg1\e[24m\e[35m\e[39m\e[22m"],
            [5, "\e[1m\e[35m« \e[39m\e[22m\e[1m\e[35m\e[33m\e[4mendMsg1\e[24m\e[35m\e[39m\e[22m <endMsg2>"],
            [6, "\e[33m- \e[39mlogOnce(\e[33m\e[4mmsg1\e[24m\e[39m)"],
            [6, "\e[33m- \e[39mlogOnce(\e[33m\e[4mmsg1\e[24m\e[39m,\e[33m\e[4mmsg2\e[24m\e[39m)\e[33m <msg2>\e[39m"],
            [5, "\e[1m\e[35m» \e[39m\e[22m\e[1m\e[35mgroup(\e[33m\e[4mmsg1\e[24m\e[35m,null,null,\e[33m\e[4mendMsg2\e[24m\e[35m)\e[39m\e[22m"],
            [5, "  \e[1m\e[35m» \e[39m\e[22m\e[1m\e[35mgroup(\e[33m\e[4mmsg1\e[24m\e[35m,null,\e[33m\e[4mendMsg1\e[24m\e[35m,\e[33m\e[4mendMsg2\e[24m\e[35m)\e[39m\e[22m"],
            [3, "  \e[1m\e[31m! \e[39m\e[22m\e[1m\e[31merror(\e[33m\e[4mmsg1\e[24m\e[31m)\e[39m\e[22m"],
            [3, "  \e[1m\e[31m! \e[39m\e[22m\e[1m\e[31merror(\e[33m\e[4mmsg1\e[24m\e[31m,\e[33m\e[4mmsg2\e[24m\e[31m)\e[39m\e[22m <msg2>"],
            [5, "  \e[1m\e[35m« \e[39m\e[22m\e[1m\e[35m\e[33m\e[4mendMsg1\e[24m\e[35m\e[39m\e[22m <endMsg2>"],
            [3, "\e[1m\e[31m! \e[39m\e[22m\e[1m\e[31merrorOnce(\e[33m\e[4mmsg1\e[24m\e[31m)\e[39m\e[22m"],
            [3, "\e[1m\e[31m! \e[39m\e[22m\e[1m\e[31merrorOnce(\e[33m\e[4mmsg1\e[24m\e[31m,\e[33m\e[4mmsg2\e[24m\e[31m)\e[39m\e[22m <msg2>"],
            [5, "\e[1m\e[35m» \e[39m\e[22m\e[1m\e[35mgroup(\e[33m\e[4mmsg1\e[24m\e[35m,\e[33m\e[4mmsg2\e[24m\e[35m,\e[33m\e[4mendMsg1\e[24m\e[35m)\e[39m\e[22m <msg2>"],
            [5, "  \e[1m\e[35m» \e[39m\e[22m\e[1m\e[35mgroup(\e[33m\e[4mmsg1\e[24m\e[35m)\e[39m\e[22m"],
            [4, "  \e[1m\e[33m^ \e[39m\e[22m\e[33mwarn(\e[33m\e[4mmsg1\e[24m\e[33m)\e[39m"],
            [4, "  \e[1m\e[33m^ \e[39m\e[22m\e[33mwarn(\e[33m\e[4mmsg1\e[24m\e[33m,\e[33m\e[4mmsg2\e[24m\e[33m)\e[39m <msg2>"],
            [5, "\e[1m\e[35m« \e[39m\e[22m\e[1m\e[35m\e[33m\e[4mendMsg1\e[24m\e[35m\e[39m\e[22m"],
            [4, "\e[1m\e[33m^ \e[39m\e[22m\e[33mwarnOnce(\e[33m\e[4mmsg1\e[24m\e[33m)\e[39m"],
            [4, "\e[1m\e[33m^ \e[39m\e[22m\e[33mwarnOnce(\e[33m\e[4mmsg1\e[24m\e[33m,\e[33m\e[4mmsg2\e[24m\e[33m)\e[39m <msg2>"],
            [7, "\e[2m: \e[22m\e[2m{" . __CLASS__ . '->' . __FUNCTION__ . ':' . ($line) . "} \e[1mdebug(\e[33m\e[4mmsg1\e[24m\e[39m)\e[22;2m\e[22m"],
            [7, "\e[2m: \e[22m\e[2m{" . __CLASS__ . '->' . __FUNCTION__ . ':' . ($line + 1) . "} \e[1mdebug(\e[33m\e[4mmsg1\e[24m\e[39m,\e[33m\e[4mmsg2\e[24m\e[39m)\e[22;2m\e[22m\e[2m <msg2>\e[22m"],
            [7, "\e[2m: \e[22m\e[2m{" . __CLASS__ . '->' . __FUNCTION__ . ':' . ($line + 3) . "} \e[1mdebugOnce(\e[33m\e[4mmsg1\e[24m\e[39m)\e[22;2m\e[22m"],
            [7, "\e[2m: \e[22m\e[2m{" . __CLASS__ . '->' . __FUNCTION__ . ':' . ($line + 4) . "} \e[1mdebugOnce(\e[33m\e[4mmsg1\e[24m\e[39m,\e[33m\e[4mmsg2\e[24m\e[39m)\e[22;2m\e[22m\e[2m <msg2>\e[22m"],
        ];
        $constants = [
            __CLASS__ => '__CLASS__',
            __FUNCTION__ => '__FUNCTION__',
            ':' . ($line) => "':' . (\$line)",
            ':' . ($line + 1) => "':' . (\$line + 1)",
            ':' . ($line + 3) => "':' . (\$line + 3)",
            ':' . ($line + 4) => "':' . (\$line + 4)",
        ];

        $actualTty = $this->TtyTarget->getMessages();
        $actualStdout = $this->StdoutTarget->getMessages();
        $actualColour = $this->ColourTarget->getMessages();
        $this->assertSameConsoleMessages($expectedTty, $actualTty, self::getMessage($actualTty, $constants, '$expectedTty'));
        $this->assertSameConsoleMessages($expectedStdout, $actualStdout, self::getMessage($actualStdout, $constants, '$expectedStdout'));
        $this->assertSameConsoleMessages($expectedColour, $actualColour, self::getMessage($actualColour, $constants, '$expectedColour'));
        $this->assertSame(6, $console->errors());
        $this->assertSame(7, $console->warnings());
    }

    public function testLogProgress(): void
    {
        $spinnerState = &$this->getSpinnerState();
        for ($i = 0; $i < 11; $i++) {
            if ($spinnerState[1] !== null) {
                $spinnerState[1] = (float) (hrtime(true) / 1000) - 80000;
                Console::logProgress('Complete:', sprintf('%d%%', ($i + 1) * 100 / 11));
            } else {
                Console::logProgress('Starting');
            }
        }
        Console::clearProgress();

        $this->assertSameConsoleMessages([
            [Console::LEVEL_INFO, "⠋ Starting\r"],
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
        ], $this->TtyTarget->getMessages());
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

    /**
     * @param mixed $actual
     * @param array<non-empty-string,string> $constants
     */
    private static function getMessage($actual, array $constants, string $expectedName = '$expected'): string
    {
        return sprintf(
            'If output changed, replace %s with: %s',
            $expectedName,
            Get::code($actual, ', ', ' => ', null, '    ', [], $constants),
        );
    }
}
