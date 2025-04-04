<?php declare(strict_types=1);

namespace Salient\Tests\PsrLog;

use Psr\Log\Test\LoggerInterfaceTest as PsrLoggerInterfaceTest;
use Psr\Log\InvalidArgumentException as PsrInvalidArgumentException;
use Psr\Log\LoggerInterface as PsrLoggerInterface;
use Salient\Console\Format\Formatter;
use Salient\Console\Console;
use Salient\Contract\HasMessageLevel;
use Salient\Testing\Console\MockTarget;
use Salient\Utility\Reflect;
use Salient\Utility\Str;

/**
 * @covers \Salient\Console\ConsoleLogger
 * @covers \Salient\Console\Console
 */
final class LoggerTest extends PsrLoggerInterfaceTest
{
    private MockTarget $Target;

    /**
     * @inheritDoc
     */
    public function getLogger(): PsrLoggerInterface
    {
        return (new Console())
            ->registerTarget($this->Target = new MockTarget(
                null,
                true,
                true,
                true,
                null,
                new Formatter(null, null, fn() => null, [], []),
            ))
            ->logger();
    }

    /**
     * @return string[]
     */
    public function getLogs(): array
    {
        foreach ($this->Target->getMessages() as [$level, $message]) {
            if (
                $level === Console::LEVEL_DEBUG
                && Str::startsWith($message, '{')
                && ($pos = strpos($message, '} ')) !== false
            ) {
                $message = substr($message, $pos + 2);
            }
            $logs[] = sprintf('%s %s', Str::lower(substr(Reflect::getConstantName(HasMessageLevel::class, $level), 6)), $message);
        }
        return $logs ?? [];
    }

    /**
     * @inheritDoc
     */
    public function testThrowsOnInvalidLevel(): void
    {
        $this->expectException(PsrInvalidArgumentException::class);
        parent::testThrowsOnInvalidLevel();
    }
}
