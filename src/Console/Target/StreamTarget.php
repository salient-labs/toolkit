<?php declare(strict_types=1);

namespace Lkrms\Console\Target;

use Lkrms\Console\Concept\ConsoleStreamTarget;
use Lkrms\Support\Catalog\TtyControlSequence;
use Lkrms\Utility\Date;
use Lkrms\Utility\File;
use DateTime;
use DateTimeZone;
use LogicException;

/**
 * Writes console output to a PHP stream
 */
final class StreamTarget extends ConsoleStreamTarget
{
    /**
     * @var resource
     */
    private $Stream;

    private bool $AddTimestamp = false;

    private string $TimestampFormat = '[d M y H:i:s.vO] ';

    private ?DateTimeZone $Timezone = null;

    private bool $IsStdout;

    private bool $IsStderr;

    private bool $IsTty;

    private ?string $Path = null;

    private static bool $HasPendingClearLine = false;

    /**
     * Use an open stream as a console output target
     *
     * @param resource $stream
     * @param bool|null $addTimestamp If `null`, timestamps are added if
     * `$stream` is not STDOUT or STDERR.
     * @param string|null $timestampFormat Default: `[d M y H:i:s.vO] `
     * @param DateTimeZone|string|null $timezone Default: as per
     * `date_default_timezone_set` or INI setting `date.timezone`
     */
    public function __construct(
        $stream,
        ?bool $addTimestamp = null,
        ?string $timestampFormat = null,
        $timezone = null
    ) {
        stream_set_write_buffer($stream, 0);

        $this->Stream = $stream;
        $this->IsStdout = File::getStreamUri($stream) === 'php://stdout';
        $this->IsStderr = File::getStreamUri($stream) === 'php://stderr';
        $this->IsTty = stream_isatty($stream);

        if ($addTimestamp || (
            $addTimestamp === null &&
            !($this->IsStdout || $this->IsStderr)
        )) {
            $this->AddTimestamp = true;
            if ($timestampFormat !== null) {
                $this->TimestampFormat = $timestampFormat;
            }
            if ($timezone !== null) {
                $this->Timezone = Date::timezone($timezone);
            }
        }
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
     * @return $this
     */
    public function reopen(?string $path = null)
    {
        if ($this->Path === null) {
            throw new LogicException(sprintf(
                'Only instances created by %s::fromPath() can be reopened',
                static::class,
            ));
        }

        if ((string) $path === '') {
            $path = $this->Path;
        }

        File::close($this->Stream, $this->Path);
        File::create($path, 0600);
        $this->Stream = File::open($path, 'a');
        $this->Path = $path;

        return $this;
    }

    /**
     * Open a file in append mode and return a console output target for it
     *
     * @param bool|null $addTimestamp If `null`, timestamps will be added unless
     * `$path` is STDOUT, STDERR, or a TTY
     * @param string|null $timestampFormat Default: `[d M y H:i:s.vO] `
     * @param DateTimeZone|string|null $timezone Default: as per
     * `date_default_timezone_set` or INI setting `date.timezone`
     */
    public static function fromPath(
        string $path,
        ?bool $addTimestamp = null,
        ?string $timestampFormat = null,
        $timezone = null
    ): self {
        File::create($path, 0600);
        $stream = new self(
            File::open($path, 'a'),
            $addTimestamp,
            $timestampFormat,
            $timezone
        );
        $stream->Path = $path;

        return $stream;
    }

    /**
     * @inheritDoc
     */
    protected function writeToTarget($level, string $message, array $context): void
    {
        if ($this->AddTimestamp) {
            $now = (new DateTime('now', $this->Timezone))->format($this->TimestampFormat);
            $message = $now . str_replace("\n", "\n" . $now, $message);
        }

        // If writing a progress message to a TTY, suppress the usual newline
        // and write a "clear to end of line" sequence before the next message
        if ($this->IsTty) {
            if (self::$HasPendingClearLine) {
                $this->clearLine();
            }
            if ($message === "\r") {
                return;
            }
            if ($message !== '' && $message[-1] === "\r") {
                File::write($this->Stream, TtyControlSequence::WRAP_OFF . rtrim($message, "\r"));
                self::$HasPendingClearLine = true;
                return;
            }
        }

        File::write($this->Stream, rtrim($message, "\r") . "\n");
    }

    public function getPath(): ?string
    {
        return $this->Path;
    }

    public function __destruct()
    {
        if ($this->IsTty && self::$HasPendingClearLine && is_resource($this->Stream)) {
            $this->clearLine();
        }
    }

    private function clearLine(): void
    {
        File::write($this->Stream, "\r" . TtyControlSequence::CLEAR_LINE . TtyControlSequence::WRAP_ON);
        self::$HasPendingClearLine = false;
    }
}
