<?php declare(strict_types=1);

namespace Lkrms\Console\Target;

use Lkrms\Console\Catalog\ConsoleLevel as Level;
use Lkrms\Console\Contract\IConsoleTarget;
use Lkrms\Console\ConsoleFormatter;

/**
 * Test console output
 */
final class MockTarget implements IConsoleTarget
{
    private bool $IsStdout;

    private bool $IsStderr;

    private bool $IsTty;

    private int $Width;

    /**
     * @var resource|null
     */
    private $Stream;

    private ConsoleFormatter $Formatter;

    /**
     * @var array<array{Level::*,string}>
     */
    private array $Messages = [];

    /**
     * @param resource|null $stream If provided, console messages are also
     * written to this stream.
     */
    public function __construct(
        bool $isStdout = true,
        bool $isStderr = true,
        bool $isTty = true,
        int $width = 80,
        $stream = null,
        ?ConsoleFormatter $formatter = null
    ) {
        $this->IsStdout = $isStdout;
        $this->IsStderr = $isStderr;
        $this->IsTty = $isTty;
        $this->Width = $width;
        $this->Stream = $stream;
        $this->Formatter = $formatter
            ?: new ConsoleFormatter(null, null, fn() => $this->width());

        if ($this->Stream) {
            stream_set_write_buffer($stream, 0);
        }
    }

    public function isStdout(): bool
    {
        return $this->IsStdout;
    }

    public function isStderr(): bool
    {
        return $this->IsStderr;
    }

    public function isTty(): bool
    {
        return $this->IsTty;
    }

    public function getFormatter(): ConsoleFormatter
    {
        return $this->Formatter;
    }

    public function width(): ?int
    {
        return $this->Width;
    }

    public function write($level, string $message, array $context = []): void
    {
        if ($this->Stream) {
            fwrite($this->Stream, $message . "\n");
        }

        $message = [$level, $message];
        if ($context) {
            $message[] = $context;
        }
        $this->Messages[] = $message;
    }

    /**
     * @return array<array{Level::*,string}>
     */
    public function getMessages(): array
    {
        try {
            return $this->Messages;
        } finally {
            $this->Messages = [];
        }
    }
}
