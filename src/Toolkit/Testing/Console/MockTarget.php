<?php declare(strict_types=1);

namespace Salient\Testing\Console;

use Salient\Console\ConsoleFormatter as Formatter;
use Salient\Contract\Console\ConsoleFormatterInterface as FormatterInterface;
use Salient\Contract\Console\ConsoleInterface as Console;
use Salient\Contract\Console\ConsoleTargetStreamInterface;
use Salient\Utility\File;
use LogicException;

/**
 * @api
 */
final class MockTarget implements ConsoleTargetStreamInterface
{
    private bool $IsStdout;
    private bool $IsStderr;
    private bool $IsTty;
    private ?int $Width;
    /** @var resource|null */
    private $Stream;
    private FormatterInterface $Formatter;
    /** @var array<array{Console::LEVEL_*,string,2?:array<string,mixed>}> */
    private array $Messages = [];
    private bool $IsValid = true;

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
        ?FormatterInterface $formatter = null
    ) {
        $this->IsStdout = $isStdout;
        $this->IsStderr = $isStderr;
        $this->IsTty = $isTty;
        $this->Width = $width;
        $this->Stream = $stream;
        $this->Formatter = $formatter ?? new Formatter(null, null, fn() => $this->Width);
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
    public function getEol(): string
    {
        return "\n";
    }

    /**
     * @inheritDoc
     */
    public function reopen(): void {}

    /**
     * @inheritDoc
     */
    public function getFormatter(): FormatterInterface
    {
        $this->assertIsValid();

        return $this->Formatter;
    }

    /**
     * @inheritDoc
     */
    public function getWidth(): ?int
    {
        $this->assertIsValid();

        return $this->Width;
    }

    /**
     * @inheritDoc
     */
    public function write(int $level, string $message, array $context = []): void
    {
        $this->assertIsValid();

        if ($this->Stream) {
            $suffix = $message !== '' && $message[-1] === "\r"
                ? ''
                : "\n";
            File::writeAll($this->Stream, $message . $suffix);
        }

        $message = [$level, $message];
        if ($context) {
            $message[] = $context;
        }
        $this->Messages[] = $message;
    }

    /**
     * @inheritDoc
     */
    public function close(): void
    {
        if (!$this->IsValid) {
            return;
        }

        $this->IsStdout = false;
        $this->IsStderr = false;
        $this->IsTty = false;
        $this->Width = null;
        $this->Stream = null;
        unset($this->Formatter);

        $this->IsValid = false;
    }

    /**
     * Get messages written to the target and flush its message cache
     *
     * @return array<array{Console::LEVEL_*,string,2?:array<string,mixed>}>
     */
    public function getMessages(): array
    {
        $messages = $this->Messages;
        $this->Messages = [];
        return $messages;
    }

    private function assertIsValid(): void
    {
        if (!$this->IsValid) {
            throw new LogicException('Target is closed');
        }
    }
}
