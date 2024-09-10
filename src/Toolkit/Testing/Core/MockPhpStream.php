<?php declare(strict_types=1);

namespace Salient\Testing\Core;

use Salient\Core\AbstractStreamWrapper;
use Salient\Utility\Inflect;
use Salient\Utility\Regex;
use Salient\Utility\Str;
use LogicException;
use RuntimeException;

/**
 * @api
 */
final class MockPhpStream extends AbstractStreamWrapper
{
    /** @var array<string,string> */
    private static array $Data = [];
    /** @var array<string,int> */
    private static array $Length = [];
    /** @var array<string,int> */
    private static array $OpenCount = [];
    private static ?string $Protocol = null;
    private static bool $RestoreProtocol;
    private string $Path;
    private int $Offset;
    private bool $Eof;

    /**
     * Register the wrapper as a protocol handler
     *
     * @throws LogicException if the wrapper is already registered.
     */
    public static function register(string $protocol = 'php'): void
    {
        if (self::$Protocol !== null) {
            throw new LogicException('Already registered');
        }
        $restore = in_array($protocol, stream_get_wrappers(), true);
        if (!(
            (!$restore || stream_wrapper_unregister($protocol))
            && stream_wrapper_register($protocol, static::class)
        )) {
            throw new RuntimeException('Stream wrapper registration failed');
        }
        self::$Protocol = $protocol;
        self::$RestoreProtocol = $restore;
    }

    /**
     * Deregister the wrapper and restore the protocol handler it replaced (if
     * applicable)
     *
     * @throws LogicException if the wrapper is not registered.
     */
    public static function deregister(): void
    {
        if (self::$Protocol === null) {
            throw new LogicException('Not registered');
        }
        if (!(
            stream_wrapper_unregister(self::$Protocol)
            && (!self::$RestoreProtocol || stream_wrapper_restore(self::$Protocol))
        )) {
            throw new RuntimeException('Stream wrapper deregistration failed');
        }
        self::$Protocol = null;
    }

    /**
     * Clear the wrapper's stream cache
     *
     * @throws LogicException if the wrapper has any open streams.
     */
    public static function reset(): void
    {
        if (self::$OpenCount) {
            throw new LogicException(Inflect::format(
                array_sum(self::$OpenCount),
                '{{#}} {{#:stream}} {{#:is}} open',
            ));
        }
        self::$Data = [];
        self::$Length = [];
        self::$OpenCount = [];
    }

    /**
     * @inheritDoc
     */
    public function stream_cast(int $cast_as)
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function stream_close(): void
    {
        if (--self::$OpenCount[$this->Path] === 0) {
            unset(self::$OpenCount[$this->Path]);
        }
        unset($this->Path, $this->Offset, $this->Eof);
    }

    /**
     * @inheritDoc
     */
    public function stream_eof(): bool
    {
        return $this->Eof;
    }

    /**
     * @inheritDoc
     */
    public function stream_flush(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        $path = $this->normalisePath($path, $options);
        if ($path === false) {
            return false;
        }
        self::$Data[$path] ??= '';
        self::$Length[$path] ??= 0;
        self::$OpenCount[$path] ??= 0;
        self::$OpenCount[$path]++;
        $this->Path = $path;
        $this->Offset = 0;
        $this->Eof = false;
        return true;
    }

    /**
     * @inheritDoc
     */
    public function stream_read(int $count)
    {
        $count = max(0, min($count, self::$Length[$this->Path] - $this->Offset));
        $data = substr(self::$Data[$this->Path], $this->Offset, $count);
        $this->Offset += $count;
        if ($this->Offset >= self::$Length[$this->Path]) {
            $this->Eof = true;
        }
        return $data;
    }

    /**
     * @inheritDoc
     */
    public function stream_seek(int $offset, int $whence = \SEEK_SET): bool
    {
        if ($offset < 0) {
            return false;
        }
        switch ($whence) {
            case \SEEK_SET:
                $this->Offset = $offset;
                break;
            case \SEEK_CUR:
                $this->Offset += $offset;
                break;
            case \SEEK_END:
                $this->Offset = self::$Length[$this->Path] + $offset;
                break;
        }
        $this->Eof = false;
        return true;
    }

    /**
     * @inheritDoc
     */
    public function stream_tell(): int
    {
        return $this->Offset;
    }

    /**
     * @inheritDoc
     */
    public function stream_truncate(int $new_size): bool
    {
        if ($new_size < 0) {
            return false;
        }
        if ($new_size <= self::$Length[$this->Path]) {
            self::$Data[$this->Path] = substr(self::$Data[$this->Path], 0, $new_size);
        } else {
            self::$Data[$this->Path] .= str_repeat("\0", $new_size - self::$Length[$this->Path]);
        }
        self::$Length[$this->Path] = $new_size;
        return true;
    }

    /**
     * @inheritDoc
     */
    public function stream_write(string $data): int
    {
        if ($this->Offset > self::$Length[$this->Path]) {
            self::$Data[$this->Path] .= str_repeat("\0", $this->Offset - self::$Length[$this->Path]);
        }
        $length = strlen($data);
        self::$Data[$this->Path] = substr_replace(self::$Data[$this->Path], $data, $this->Offset, $length);
        $this->Offset += $length;
        self::$Length[$this->Path] = max(self::$Length[$this->Path], $this->Offset);
        return $length;
    }

    /**
     * @return string|false
     */
    private function normalisePath(string $path, int $options)
    {
        $path = Str::lower($path);
        [$scheme, $path] = explode('://', $path, 2);
        $parts = explode('/', $path);
        switch ($parts[0]) {
            case 'stdin':
            case 'stdout':
            case 'stderr':
            case 'input':
            case 'output':
            case 'memory':
                if (count($parts) > 1) {
                    return $this->maybeReportError($options);
                }
                break;

            case 'fd':
                if (count($parts) !== 2 || Regex::match('/[^0-9]/', $parts[1])) {
                    return $this->maybeReportError($options);
                }
                $parts[1] = (string) (int) $parts[1];
                break;

            case 'temp':
                $parts = [$parts[0]];
                break;

            case 'filter':
                return $this->maybeReportError($options, $scheme . '://filter not supported');

            default:
                return $this->maybeReportError($options);
        }

        return $scheme . '://' . implode('/', $parts);
    }

    /**
     * @return false
     */
    private function maybeReportError(int $options, string $message = 'Invalid path'): bool
    {
        if ($options & \STREAM_REPORT_ERRORS) {
            trigger_error($message, \E_USER_ERROR);
        }
        return false;
    }
}
