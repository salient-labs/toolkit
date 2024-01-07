<?php declare(strict_types=1);

namespace Lkrms\Console\Target;

use Lkrms\Console\Catalog\ConsoleLevel as Level;
use Lkrms\Console\Contract\ConsoleTargetStreamInterface;
use Lkrms\Console\ConsoleFormatter;
use Lkrms\Utility\File;

/**
 * Writes console output to an array
 */
final class MockTarget implements ConsoleTargetStreamInterface
{
    private bool $IsStdout;

    private bool $IsStderr;

    private bool $IsTty;

    private ?int $Width;

    /**
     * @var resource|null
     */
    private $Stream;

    private ConsoleFormatter $Formatter;

    /**
     * @var array<array{Level::*,string,2?:array<string,mixed>}>
     */
    private array $Messages = [];

    /**
     * @param resource|null $stream Console messages are also written to
     * `$stream` if given.
     */
    public function __construct(
        $stream = null,
        bool $isStdout = true,
        bool $isStderr = true,
        bool $isTty = true,
        ?int $width = 80,
        ?ConsoleFormatter $formatter = null
    ) {
        if ($stream) {
            stream_set_write_buffer($stream, 0);
        }

        $this->IsStdout = $isStdout;
        $this->IsStderr = $isStderr;
        $this->IsTty = $isTty;
        $this->Width = $width;
        $this->Stream = $stream;
        $this->Formatter = $formatter
            ?: new ConsoleFormatter(null, null, fn(): ?int => $this->getWidth());
    }

    /**
     * @inheritDoc
     */
    public function isStdout(): bool
    {
        return $this->IsStdout;
    }

    /**
     * @inheritDoc
     */
    public function isStderr(): bool
    {
        return $this->IsStderr;
    }

    /**
     * @inheritDoc
     */
    public function isTty(): bool
    {
        return $this->IsTty;
    }

    /**
     * @inheritDoc
     */
    public function getFormatter(): ConsoleFormatter
    {
        return clone $this->Formatter;
    }

    /**
     * @inheritDoc
     */
    public function getWidth(): ?int
    {
        return $this->Width;
    }

    /**
     * @inheritDoc
     */
    public function write($level, string $message, array $context = []): void
    {
        if ($this->Stream) {
            $suffix = $message === '' || $message[-1] !== "\r"
                ? "\n"
                : '';
            File::write($this->Stream, $message . $suffix);
        }

        $message = [$level, $message];
        if ($context) {
            $message[] = $context;
        }
        $this->Messages[] = $message;
    }

    /**
     * @return array<array{Level::*,string,2?:array<string,mixed>}>
     */
    public function getMessages(): array
    {
        $messages = $this->Messages;
        $this->Messages = [];
        return $messages;
    }
}
