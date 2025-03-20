<?php declare(strict_types=1);

namespace Salient\Tests\Console;

use Salient\Console\Format\Formatter;
use Salient\Console\Target\AnalogTarget;
use Salient\Console\Target\StreamTarget;
use Salient\Console\Console as ConsoleService;
use Salient\Core\Facade\Console;
use Salient\Testing\Console\MockTarget;
use Salient\Testing\Core\MockPhpStream;
use Salient\Tests\TestCase;
use Salient\Utility\File;
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
 * @covers \Salient\Console\Target\StreamTarget
 * @covers \Salient\Console\Target\AbstractStreamTarget
 * @covers \Salient\Console\Target\AbstractTargetWithPrefix
 * @covers \Salient\Console\Target\AbstractTarget
 */
final class ConsoleTest extends TestCase
{
    private ConsoleService $Console;
    private Formatter $Formatter;
    private Formatter $StreamFormatter;
    private MockTarget $TtyTarget;
    private MockTarget $StdoutTarget;
    private MockTarget $FileTarget;
    private StreamTarget $StreamTarget;

    protected function setUp(): void
    {
        MockPhpStream::register('mock');

        $this->Formatter = new Formatter(null, null, fn() => $this->TtyTarget->getWidth());
        $this->TtyTarget = new MockTarget(null, false, true, true, 80, $this->Formatter);
        $this->StdoutTarget = new MockTarget(null, true, false, false, 80, $this->Formatter);
        $this->FileTarget = new MockTarget(null, false, false, false, null, new Formatter(null, null, fn() => $this->FileTarget->getWidth()));
        $this->StreamTarget = new FakeTtyStreamTarget(File::open('mock://output', 'w'), true);
        /** @var Formatter */
        $streamFormatter = $this->StreamTarget->getFormatter();
        $this->StreamFormatter = $streamFormatter;
        $this->Console = (new ConsoleService())
            ->registerTarget($this->TtyTarget)
            ->registerTarget($this->StdoutTarget, Console::LEVELS_ALL_EXCEPT_DEBUG)
            ->registerTarget($this->FileTarget)
            ->registerTarget($this->StreamTarget);

        Console::load($this->Console);
    }

    protected function tearDown(): void
    {
        Console::unload();

        $this->StreamTarget->close();

        MockPhpStream::deregister();
        MockPhpStream::reset();
    }

    public function testSetPrefix(): void
    {
        $console = $this->Console;
        $console->setPrefix('DRY RUN ');
        $console->info('Foo:', 'bar');
        $console->log('Foo:', "\nbar");
        $console->print('foo bar');

        $this->assertSameTargetMessages([
            [5, '➤ Foo: bar'],
            [6, "- Foo:\n    bar"],
            [6, 'foo bar'],
        ], $this->TtyTarget);

        $this->assertSameTargetOutput(
            "\e[2mDRY RUN \e[22m\e[1m\e[36m➤ \e[39m\e[22m\e[1mFoo:\e[22m\e[36m bar\e[39m\n"
                . "\e[2mDRY RUN \e[22m\e[33m- \e[39mFoo:\e[33m\n"
                . "\e[2mDRY RUN \e[22m    bar\e[39m\n"
                . "\e[2mDRY RUN \e[22mfoo bar\n",
            ["\n" => '"\n"' . \PHP_EOL],
        );
    }

    public function testGetTargets(): void
    {
        $console = $this->Console;
        $console->registerTarget($analogTarget = new AnalogTarget(), Console::LEVELS_ALL_EXCEPT_DEBUG);
        $all = [$this->TtyTarget, $this->StdoutTarget, $this->FileTarget, $this->StreamTarget, $analogTarget];
        $stdio = [$this->TtyTarget, $this->StdoutTarget, $this->StreamTarget];
        $stderr = [$this->TtyTarget, $this->StreamTarget];
        $stdout = [$this->StdoutTarget];
        $notStdio = [$this->FileTarget, $analogTarget];
        $notTty = [$this->StdoutTarget, $this->FileTarget, $analogTarget];
        $debug = [$this->TtyTarget, $this->FileTarget, $this->StreamTarget];
        $this->assertSame($all, $console->getTargets());
        $this->assertSame($stdio, $console->getTargets(null, Console::TARGET_STDIO));
        $this->assertSame($notStdio, $console->getTargets(null, Console::TARGET_STDIO | Console::TARGET_INVERT));
        $this->assertSame($stderr, $console->getTargets(null, Console::TARGET_TTY));
        $this->assertSame($notTty, $console->getTargets(null, Console::TARGET_TTY | Console::TARGET_INVERT));
        $this->assertSame($stderr, $console->getTargets(null, Console::TARGET_STDERR));
        $this->assertSame($stdout, $console->getTargets(null, Console::TARGET_STDOUT));
        $this->assertSame($debug, $console->getTargets(Console::LEVEL_DEBUG));
        $this->assertSame([], $console->getTargets(Console::LEVEL_DEBUG, Console::TARGET_STDOUT));
        $this->assertSame($all, $console->getTargets(null, Console::TARGET_INVERT));
    }

    public function testOutput(): void
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
        $console->deregisterTarget($this->StreamTarget);
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
        $expectedFile = array_values(array_filter(
            $expectedTty,
            fn($msg) => $msg !== [5, ''] && $msg !== [5, '  '],
        ));
        $expectedStream = "\e[1m\e[36m➤ \e[39m\e[22m\e[1minfo(\e[33m\e[4mmsg1\e[24m\e[39m)\e[22m\n"
            . "\e[1m\e[36m➤ \e[39m\e[22m\e[1minfo(\e[33m\e[4mmsg1\e[24m\e[39m,\e[33m\e[4mmsg2\e[24m\e[39m)\e[22m\e[36m <msg2>\e[39m\n"
            . "\e[1m\e[35m» \e[39m\e[22m\e[1m\e[35mgroup(\e[33m\e[4mmsg1\e[24m\e[35m)\e[39m\e[22m\n"
            . "  \e[1m\e[35m» \e[39m\e[22m\e[1m\e[35mgroup(\e[33m\e[4mmsg1\e[24m\e[35m,\e[33m\e[4mmsg2\e[24m\e[35m)\e[39m\e[22m <msg2>\n"
            . "  \e[1m\e[36m➤ \e[39m\e[22m\e[1minfoOnce(\e[33m\e[4mmsg1\e[24m\e[39m)\e[22m\n"
            . "  \e[1m\e[36m➤ \e[39m\e[22m\e[1minfoOnce(\e[33m\e[4mmsg1\e[24m\e[39m,\e[33m\e[4mmsg2\e[24m\e[39m)\e[22m\e[36m <msg2>\e[39m\n"
            . "  \n"
            . "\e[1m\e[35m» \e[39m\e[22m\e[1m\e[35mgroup(\e[33m\e[4mmsg1\e[24m\e[35m,\e[33m\e[4mmsg2\e[24m\e[35m,\e[33m\e[4mendMsg1\e[24m\e[35m,\e[33m\e[4mendMsg2\e[24m\e[35m)\e[39m\e[22m <msg2>\n"
            . "\e[33m- \e[39mlog(\e[33m\e[4mmsg1\e[24m\e[39m)\n"
            . "  \e[1m\e[35m» \e[39m\e[22m\e[1m\e[35mgroup(\e[33m\e[4mmsg1\e[24m\e[35m,null,\e[33m\e[4mendMsg1\e[24m\e[35m)\e[39m\e[22m\n"
            . "  \e[33m- \e[39mlog(\e[33m\e[4mmsg1\e[24m\e[39m,\e[33m\e[4mmsg2\e[24m\e[39m)\e[33m <msg2>\e[39m\n"
            . "  \e[1m\e[35m« \e[39m\e[22m\e[1m\e[35m\e[33m\e[4mendMsg1\e[24m\e[35m\e[39m\e[22m\n"
            . "  \n"
            . "\e[1m\e[35m« \e[39m\e[22m\e[1m\e[35m\e[33m\e[4mendMsg1\e[24m\e[35m\e[39m\e[22m <endMsg2>\n"
            . "\n"
            . "\e[33m- \e[39mlogOnce(\e[33m\e[4mmsg1\e[24m\e[39m)\n"
            . "\e[33m- \e[39mlogOnce(\e[33m\e[4mmsg1\e[24m\e[39m,\e[33m\e[4mmsg2\e[24m\e[39m)\e[33m <msg2>\e[39m\n"
            . "\e[1m\e[35m» \e[39m\e[22m\e[1m\e[35mgroup(\e[33m\e[4mmsg1\e[24m\e[35m,null,null,\e[33m\e[4mendMsg2\e[24m\e[35m)\e[39m\e[22m\n"
            . "  \e[1m\e[35m» \e[39m\e[22m\e[1m\e[35mgroup(\e[33m\e[4mmsg1\e[24m\e[35m,null,\e[33m\e[4mendMsg1\e[24m\e[35m,\e[33m\e[4mendMsg2\e[24m\e[35m)\e[39m\e[22m\n"
            . "  \e[1m\e[31m! \e[39m\e[22m\e[1m\e[31merror(\e[33m\e[4mmsg1\e[24m\e[31m)\e[39m\e[22m\n"
            . "  \e[1m\e[31m! \e[39m\e[22m\e[1m\e[31merror(\e[33m\e[4mmsg1\e[24m\e[31m,\e[33m\e[4mmsg2\e[24m\e[31m)\e[39m\e[22m <msg2>\n"
            . "  \e[1m\e[35m« \e[39m\e[22m\e[1m\e[35m\e[33m\e[4mendMsg1\e[24m\e[35m\e[39m\e[22m <endMsg2>\n"
            . "  \n"
            . "\e[1m\e[31m! \e[39m\e[22m\e[1m\e[31merrorOnce(\e[33m\e[4mmsg1\e[24m\e[31m)\e[39m\e[22m\n"
            . "\e[1m\e[31m! \e[39m\e[22m\e[1m\e[31merrorOnce(\e[33m\e[4mmsg1\e[24m\e[31m,\e[33m\e[4mmsg2\e[24m\e[31m)\e[39m\e[22m <msg2>\n"
            . "\e[1m\e[35m» \e[39m\e[22m\e[1m\e[35mgroup(\e[33m\e[4mmsg1\e[24m\e[35m,\e[33m\e[4mmsg2\e[24m\e[35m,\e[33m\e[4mendMsg1\e[24m\e[35m)\e[39m\e[22m <msg2>\n"
            . "  \e[1m\e[35m» \e[39m\e[22m\e[1m\e[35mgroup(\e[33m\e[4mmsg1\e[24m\e[35m)\e[39m\e[22m\n"
            . "  \e[1m\e[33m^ \e[39m\e[22m\e[33mwarn(\e[33m\e[4mmsg1\e[24m\e[33m)\e[39m\n"
            . "  \e[1m\e[33m^ \e[39m\e[22m\e[33mwarn(\e[33m\e[4mmsg1\e[24m\e[33m,\e[33m\e[4mmsg2\e[24m\e[33m)\e[39m <msg2>\n"
            . "  \n"
            . "\e[1m\e[35m« \e[39m\e[22m\e[1m\e[35m\e[33m\e[4mendMsg1\e[24m\e[35m\e[39m\e[22m\n"
            . "\n"
            . "\e[1m\e[33m^ \e[39m\e[22m\e[33mwarnOnce(\e[33m\e[4mmsg1\e[24m\e[33m)\e[39m\n"
            . "\e[1m\e[33m^ \e[39m\e[22m\e[33mwarnOnce(\e[33m\e[4mmsg1\e[24m\e[33m,\e[33m\e[4mmsg2\e[24m\e[33m)\e[39m <msg2>\n"
            . "\e[2m: \e[22m\e[2m{" . __CLASS__ . '->' . __FUNCTION__ . ':' . ($line) . "} \e[1mdebug(\e[33m\e[4mmsg1\e[24m\e[39m)\e[22;2m\e[22m\n"
            . "\e[2m: \e[22m\e[2m{" . __CLASS__ . '->' . __FUNCTION__ . ':' . ($line + 1) . "} \e[1mdebug(\e[33m\e[4mmsg1\e[24m\e[39m,\e[33m\e[4mmsg2\e[24m\e[39m)\e[22;2m\e[22m\e[2m <msg2>\e[22m\n"
            . "\e[2m: \e[22m\e[2m{" . __CLASS__ . '->' . __FUNCTION__ . ':' . ($line + 3) . "} \e[1mdebugOnce(\e[33m\e[4mmsg1\e[24m\e[39m)\e[22;2m\e[22m\n"
            . "\e[2m: \e[22m\e[2m{" . __CLASS__ . '->' . __FUNCTION__ . ':' . ($line + 4) . "} \e[1mdebugOnce(\e[33m\e[4mmsg1\e[24m\e[39m,\e[33m\e[4mmsg2\e[24m\e[39m)\e[22;2m\e[22m\e[2m <msg2>\e[22m\n";
        $constants = [
            "\n" => '"\n"' . \PHP_EOL,
            __CLASS__ => '__CLASS__',
            __FUNCTION__ => '__FUNCTION__',
            ':' . ($line) => "':' . (\$line)",
            ':' . ($line + 1) => "':' . (\$line + 1)",
            ':' . ($line + 3) => "':' . (\$line + 3)",
            ':' . ($line + 4) => "':' . (\$line + 4)",
        ];
        $this->assertSameTargetMessages($expectedTty, $this->TtyTarget, $constants, '$expectedTty');
        $this->assertSameTargetMessages($expectedStdout, $this->StdoutTarget, $constants, '$expectedStdout');
        $this->assertSameTargetMessages($expectedFile, $this->FileTarget, $constants, '$expectedFile');
        $this->assertSameTargetOutput($expectedStream, $constants, '$expectedStream');
        $this->assertSame(6, $console->errors());
        $this->assertSame(7, $console->warnings());
    }

    public function testOutputWithNewlines(): void
    {
        $target3 = new MockTarget(null, false, false, false, null, new Formatter(null, null, null, [5 => '=> ', 6 => '-> '], [4 => '>> ']));
        $target4 = new MockTarget(null, false, false, false, null, new Formatter(null, null, null, [5 => '==> ', 6 => ' -> '], [4 => '>>> ']));
        $console = $this->Console;
        $console->registerTarget($target3);
        $console->registerTarget($target4);
        $console->info('foo:', "\nbar");
        $console->info('foo:', "bar\nbaz");
        $console->info("foo\nbar:", 'baz');
        $console->info("foo\nbar:", "\nbaz");
        $console->info("foo\nbar:", "baz\nqux");
        $console->log('foo:', "\nbar");
        $console->log('foo:', "bar\nbaz");
        $console->log("foo\nbar:", 'baz');
        $console->log("foo\nbar:", "\nbaz");
        $console->log("foo\nbar:", "baz\nqux");
        $console->group('foo');
        $console->group('bar');
        $console->info('foo:', "\nbar");
        $console->info('foo:', "bar\nbaz");
        $console->info("foo\nbar:", 'baz');
        $console->info("foo\nbar:", "\nbaz");
        $console->info("foo\nbar:", "baz\nqux");
        $console->log('foo:', "\nbar");
        $console->log('foo:', "bar\nbaz");
        $console->log("foo\nbar:", 'baz');
        $console->log("foo\nbar:", "\nbaz");
        $console->log("foo\nbar:", "baz\nqux");

        $this->assertSameTargetMessages([
            [5, "➤ foo:\n    bar"],
            [5, "➤ foo:\n    bar\n    baz"],
            [5, "➤ foo\n  bar: baz"],
            [5, "➤ foo\n  bar:\n    baz"],
            [5, "➤ foo\n  bar:\n    baz\n    qux"],
            [6, "- foo:\n    bar"],
            [6, "- foo:\n    bar\n    baz"],
            [6, "- foo\n  bar: baz"],
            [6, "- foo\n  bar:\n    baz"],
            [6, "- foo\n  bar:\n    baz\n    qux"],
            [5, '» foo'],
            [5, '  » bar'],
            [5, "  ➤ foo:\n      bar"],
            [5, "  ➤ foo:\n      bar\n      baz"],
            [5, "  ➤ foo\n    bar: baz"],
            [5, "  ➤ foo\n    bar:\n      baz"],
            [5, "  ➤ foo\n    bar:\n      baz\n      qux"],
            [6, "  - foo:\n      bar"],
            [6, "  - foo:\n      bar\n      baz"],
            [6, "  - foo\n    bar: baz"],
            [6, "  - foo\n    bar:\n      baz"],
            [6, "  - foo\n    bar:\n      baz\n      qux"],
        ], $this->TtyTarget);
        $this->assertSameTargetMessages([
            [5, "=> foo:\n     bar"],
            [5, "=> foo:\n     bar\n     baz"],
            [5, "=> foo\n   bar: baz"],
            [5, "=> foo\n   bar:\n     baz"],
            [5, "=> foo\n   bar:\n     baz\n     qux"],
            [6, "-> foo:\n     bar"],
            [6, "-> foo:\n     bar\n     baz"],
            [6, "-> foo\n   bar: baz"],
            [6, "-> foo\n   bar:\n     baz"],
            [6, "-> foo\n   bar:\n     baz\n     qux"],
            [5, '>> foo'],
            [5, '  >> bar'],
            [5, "  => foo:\n       bar"],
            [5, "  => foo:\n       bar\n       baz"],
            [5, "  => foo\n     bar: baz"],
            [5, "  => foo\n     bar:\n       baz"],
            [5, "  => foo\n     bar:\n       baz\n       qux"],
            [6, "  -> foo:\n       bar"],
            [6, "  -> foo:\n       bar\n       baz"],
            [6, "  -> foo\n     bar: baz"],
            [6, "  -> foo\n     bar:\n       baz"],
            [6, "  -> foo\n     bar:\n       baz\n       qux"],
        ], $target3);
        $this->assertSameTargetMessages([
            [5, "==> foo:\n  bar"],
            [5, "==> foo:\n  bar\n  baz"],
            [5, "==> foo\n    bar: baz"],
            [5, "==> foo\n    bar:\n      baz"],
            [5, "==> foo\n    bar:\n      baz\n      qux"],
            [6, " -> foo:\n  bar"],
            [6, " -> foo:\n  bar\n  baz"],
            [6, " -> foo\n    bar: baz"],
            [6, " -> foo\n    bar:\n      baz"],
            [6, " -> foo\n    bar:\n      baz\n      qux"],
            [5, '>>> foo'],
            [5, '  >>> bar'],
            [5, "  ==> foo:\n    bar"],
            [5, "  ==> foo:\n    bar\n    baz"],
            [5, "  ==> foo\n      bar: baz"],
            [5, "  ==> foo\n      bar:\n        baz"],
            [5, "  ==> foo\n      bar:\n        baz\n        qux"],
            [6, "   -> foo:\n    bar"],
            [6, "   -> foo:\n    bar\n    baz"],
            [6, "   -> foo\n      bar: baz"],
            [6, "   -> foo\n      bar:\n        baz"],
            [6, "   -> foo\n      bar:\n        baz\n        qux"],
        ], $target4);
    }

    public function testLogProgress(): void
    {
        $console = $this->Console;
        $spinnerState = &$this->getSpinnerState($this->Formatter);
        $streamSpinnerState = &$this->getSpinnerState($this->StreamFormatter);
        for ($i = 0; $i < 11; $i++) {
            if ($spinnerState[1] !== null) {
                $streamSpinnerState[1] = $spinnerState[1] = (float) (hrtime(true) / 1000) - 80000;
                $console->logProgress('Complete:', sprintf('%d%%', ($i + 1) * 100 / 11));
            } else {
                $console->logProgress('Starting');
            }
        }
        $console->clearProgress();

        $this->assertSameTargetMessages([
            [6, "⠋ Starting\r"],
            [6, "⠙ Complete: 18%\r"],
            [6, "⠹ Complete: 27%\r"],
            [6, "⠸ Complete: 36%\r"],
            [6, "⠼ Complete: 45%\r"],
            [6, "⠴ Complete: 54%\r"],
            [6, "⠦ Complete: 63%\r"],
            [6, "⠧ Complete: 72%\r"],
            [6, "⠇ Complete: 81%\r"],
            [6, "⠏ Complete: 90%\r"],
            [6, "⠋ Complete: 100%\r"],
            [6, "\r"],
        ], $this->TtyTarget);
        $this->assertSameTargetMessages([], $this->StdoutTarget);
        $this->assertSameTargetMessages([], $this->FileTarget);
        $this->assertSameTargetOutput(
            "\e[?7l\e[33m⠋ \e[39mStarting\r"
                . "\e[K\e[?7h\e[?7l\e[33m⠙ \e[39mComplete:\e[33m 18%\e[39m\r"
                . "\e[K\e[?7h\e[?7l\e[33m⠹ \e[39mComplete:\e[33m 27%\e[39m\r"
                . "\e[K\e[?7h\e[?7l\e[33m⠸ \e[39mComplete:\e[33m 36%\e[39m\r"
                . "\e[K\e[?7h\e[?7l\e[33m⠼ \e[39mComplete:\e[33m 45%\e[39m\r"
                . "\e[K\e[?7h\e[?7l\e[33m⠴ \e[39mComplete:\e[33m 54%\e[39m\r"
                . "\e[K\e[?7h\e[?7l\e[33m⠦ \e[39mComplete:\e[33m 63%\e[39m\r"
                . "\e[K\e[?7h\e[?7l\e[33m⠧ \e[39mComplete:\e[33m 72%\e[39m\r"
                . "\e[K\e[?7h\e[?7l\e[33m⠇ \e[39mComplete:\e[33m 81%\e[39m\r"
                . "\e[K\e[?7h\e[?7l\e[33m⠏ \e[39mComplete:\e[33m 90%\e[39m\r"
                . "\e[K\e[?7h\e[?7l\e[33m⠋ \e[39mComplete:\e[33m 100%\e[39m\r"
                . "\e[K\e[?7h",
            ["\r" => '"\r"' . \PHP_EOL],
        );
    }

    public function testCloseAfterLogProgress(): void
    {
        $console = $this->Console;
        $console->logProgress('Starting');

        $this->assertSameTargetOutput("\e[?7l\e[33m⠋ \e[39mStarting");

        $console->deregisterTarget($this->TtyTarget);
        $console->deregisterTarget($this->StreamTarget);
        $this->TtyTarget->close();
        $this->StreamTarget->close();

        $this->assertSameTargetMessages([[6, "⠋ Starting\r"]], $this->TtyTarget);
        $this->assertSameTargetOutput("\e[?7l\e[33m⠋ \e[39mStarting\r\e[K\e[?7h");
    }

    /**
     * @return array{int<0,max>,float|null}
     */
    private function &getSpinnerState(Formatter $formatter): array
    {
        return (function &() {
            /** @var Formatter $this */
            // @phpstan-ignore varTag.nativeType, property.private
            return $this->SpinnerState;
        })->bindTo($formatter, $formatter)();
    }

    /**
     * @param array<array{Console::LEVEL_*,string,2?:array<string,mixed>}> $expected
     * @param array<non-empty-string,string> $constants
     */
    private function assertSameTargetMessages(array $expected, MockTarget $target, array $constants = [], string $expectedName = '$expected'): void
    {
        $actual = $target->getMessages();
        $this->assertSameConsoleMessages($expected, $actual, self::getMessage($actual, $constants, $expectedName));
    }

    /**
     * @param array<non-empty-string,string> $constants
     */
    private function assertSameTargetOutput(string $expected, array $constants = [], string $expectedName = '$expected'): void
    {
        $actual = File::getContents('mock://output');
        $this->assertSame($expected, $actual, self::getMessage($actual, $constants, $expectedName));
    }

    /**
     * @param mixed $actual
     * @param array<non-empty-string,string> $constants
     */
    private static function getMessage($actual, array $constants = [], string $expectedName = '$expected'): string
    {
        return sprintf(
            'If output changed, replace %s with: %s',
            $expectedName,
            Get::code($actual, ', ', ' => ', null, '    ', [], $constants),
        );
    }
}

class FakeTtyStreamTarget extends StreamTarget
{
    /**
     * @inheritDoc
     */
    protected function applyStream($stream): void
    {
        parent::applyStream($stream);
        $this->IsStderr = true;
        $this->IsTty = true;
    }
}
