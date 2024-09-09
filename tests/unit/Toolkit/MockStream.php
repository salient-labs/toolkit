<?php declare(strict_types=1);

namespace Salient\Tests;

use Salient\Polyfill\StreamWrapper\StreamWrapper;

class MockStream extends StreamWrapper
{
    private static string $Data = '';
    private static int $Length = 0;
    private int $Offset = 0;
    private bool $Eof = false;

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
        unset($this->Offset, $this->Eof);
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
        $this->Offset = 0;
        $this->Eof = false;
        return true;
    }

    /**
     * @inheritDoc
     */
    public function stream_read(int $count)
    {
        $count = max(0, min($count, self::$Length - $this->Offset));
        $data = substr(self::$Data, $this->Offset, $count);
        $this->Offset += $count;
        if ($this->Offset >= self::$Length) {
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
                $this->Offset = self::$Length + $offset;
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
        self::$Data = substr(self::$Data, 0, $new_size);
        self::$Length = min(self::$Length, $new_size);
        return true;
    }

    /**
     * @inheritDoc
     */
    public function stream_write(string $data): int
    {
        if ($this->Offset > self::$Length) {
            self::$Data .= str_repeat("\0", $this->Offset - self::$Length);
        }
        $length = strlen($data);
        self::$Data = substr_replace(self::$Data, $data, $this->Offset, $length);
        $this->Offset += $length;
        self::$Length = max(self::$Length, $this->Offset);
        return $length;
    }
}
