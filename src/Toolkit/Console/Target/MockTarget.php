<?php declare(strict_types=1);

namespace Salient\Console\Target;

use Salient\Console\Catalog\ConsoleLevel as Level;
use Salient\Console\Contract\ConsoleTargetStreamInterface;
use Salient\Console\Exception\ConsoleInvalidTargetException;
use Salient\Console\ConsoleFormatter as Formatter;
use Salient\Core\Utility\File;

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

    private Formatter $Formatter;

    /**
     * @var array<array{Level::*,string,2?:array<string,mixed>}>
     */
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
        ?Formatter $formatter = null
    ) {
        $this->IsStdout = $isStdout;
        $this->IsStderr = $isStderr;
        $this->IsTty = $isTty;
        $this->Width = $width;
        $this->Stream = $stream;
        $this->Formatter = $formatter
            ?: new Formatter(null, null, fn(): ?int => $this->getWidth());
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
    public function close(): void
    {
        if (!$this->Stream) {
            return;
        }

        $stream = $this->Stream;

        $this->IsStdout = false;
        $this->IsStderr = false;
        $this->IsTty = false;
        $this->Width = null;
        $this->Stream = null;
        unset($this->Formatter);

        $this->IsValid = false;

        if ($stream === \STDOUT || $stream === \STDERR) {
            return;
        }

        File::close($stream);
    }

    /**
     * @inheritDoc
     */
    public function reopen(): void {}

    /**
     * @inheritDoc
     */
    public function getFormatter(): Formatter
    {
        $this->assertIsValid();

        return clone $this->Formatter;
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
    public function write($level, string $message, array $context = []): void
    {
        $this->assertIsValid();

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
     * Get messages written to the target and flush its message cache
     *
     * @return array<array{Level::*,string,2?:array<string,mixed>}>
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
            throw new ConsoleInvalidTargetException('Target is closed');
        }
    }
}
