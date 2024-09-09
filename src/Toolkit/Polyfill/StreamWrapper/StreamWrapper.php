<?php declare(strict_types=1);

namespace Salient\Polyfill\StreamWrapper;

/**
 * Base class for stream wrappers derived from the streamWrapper prototype
 * described in the PHP manual
 */
abstract class StreamWrapper implements StreamWrapperInterface
{
    /** @var array{int,int,int,int,int,int,int,int,int,int,int,int,int} */
    protected const DEFAULT_STAT = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, -1, -1];

    private const STAT_KEYS = [
        'dev',
        'ino',
        'mode',
        'nlink',
        'uid',
        'gid',
        'rdev',
        'size',
        'atime',
        'mtime',
        'ctime',
        'blksize',
        'blocks',
    ];

    /** @var resource|null */
    public $context;

    /**
     * @inheritDoc
     */
    public function __construct() {}

    /**
     * @inheritDoc
     */
    public function stream_metadata(string $path, int $option, $value): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function stream_set_option(int $option, int $arg1, int $arg2): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function stream_stat()
    {
        return $this->buildStat(static::DEFAULT_STAT);
    }

    /**
     * @inheritDoc
     */
    public function url_stat(string $path, int $flags)
    {
        return $this->buildStat(static::DEFAULT_STAT);
    }

    /**
     * @param array{int,int,int,int,int,int,int,int,int,int,int,int,int} $stat
     * @return array<int>
     */
    protected function buildStat(array $stat): array
    {
        return $stat + array_combine(self::STAT_KEYS, $stat);
    }
}
