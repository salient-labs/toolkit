<?php declare(strict_types=1);

namespace Salient\Iterator;

use Salient\Contract\Core\Immutable;
use Salient\Contract\Iterator\FluentIteratorInterface;
use Salient\Iterator\Concern\FluentIteratorTrait;
use Salient\Utility\Exception\FilesystemErrorException;
use Salient\Utility\Regex;
use AppendIterator;
use CallbackFilterIterator;
use Countable;
use EmptyIterator;
use FilesystemIterator;
use IteratorAggregate;
use LogicException;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Traversable;

/**
 * Iterates over files and directories
 *
 * @implements IteratorAggregate<string,SplFileInfo>
 * @implements FluentIteratorInterface<string,SplFileInfo>
 */
class RecursiveFilesystemIterator implements
    IteratorAggregate,
    FluentIteratorInterface,
    Immutable,
    Countable
{
    /** @use FluentIteratorTrait<string,SplFileInfo> */
    use FluentIteratorTrait;

    private bool $GetFiles = true;
    private bool $GetDirs = false;
    private bool $DirsFirst = true;
    private bool $Recurse = true;
    private bool $MatchRelative = false;
    /** @var string[] */
    private array $Dirs = [];
    /** @var array<string|callable(SplFileInfo, string, FilesystemIterator): bool> */
    private array $Exclude = [];
    /** @var array<string|callable(SplFileInfo, string, FilesystemIterator, RecursiveIteratorIterator<RecursiveDirectoryIterator|RecursiveCallbackFilterIterator<string,SplFileInfo,RecursiveDirectoryIterator>>|null=): bool> */
    private array $Include = [];

    /**
     * Search in one or more directories
     *
     * @return $this
     */
    public function in(string ...$dirs)
    {
        if (!$dirs) {
            return $this;
        }

        $clone = clone $this;
        array_push($clone->Dirs, ...$dirs);
        return $clone;
    }

    /**
     * Find files?
     *
     * Files are returned by default.
     *
     * @return $this
     */
    public function files(bool $value = true)
    {
        if ($this->GetFiles === $value) {
            return $this;
        }

        $clone = clone $this;
        $clone->GetFiles = $value;
        return $clone;
    }

    /**
     * Do not find directories, only files
     *
     * This is the default.
     *
     * @return $this
     */
    public function noDirs()
    {
        return $this->dirs(false)->files();
    }

    /**
     * Find directories?
     *
     * Directories are not returned by default.
     *
     * @return $this
     */
    public function dirs(bool $value = true)
    {
        if ($this->GetDirs === $value) {
            return $this;
        }

        $clone = clone $this;
        $clone->GetDirs = $value;
        return $clone;
    }

    /**
     * Do not find files, only directories
     *
     * @return $this
     */
    public function noFiles()
    {
        return $this->files(false)->dirs();
    }

    /**
     * Return directories before their children?
     *
     * Directories are returned before their children by default.
     *
     * Ignored unless directories are returned.
     *
     * @return $this
     */
    public function dirsFirst(bool $value = true)
    {
        if ($this->DirsFirst === $value) {
            return $this;
        }

        $clone = clone $this;
        $clone->DirsFirst = $value;
        return $clone;
    }

    /**
     * Return directories after their children
     *
     * Ignored unless directories are returned.
     *
     * @return $this
     */
    public function dirsLast()
    {
        return $this->dirsFirst(false);
    }

    /**
     * Recurse into directories?
     *
     * Recursion into directories is enabled by default.
     *
     * @return $this
     */
    public function recurse(bool $value = true)
    {
        if ($this->Recurse === $value) {
            return $this;
        }

        $clone = clone $this;
        $clone->Recurse = $value;
        return $clone;
    }

    /**
     * Do not recurse into directories
     *
     * @return $this
     */
    public function doNotRecurse()
    {
        return $this->recurse(false);
    }

    /**
     * Exclude files that match a regular expression or satisfy a callback
     *
     * @param string|callable(SplFileInfo, string, FilesystemIterator): bool $value
     * @return $this
     */
    public function exclude($value)
    {
        $this->Exclude[] = $value;
        return $this;
    }

    /**
     * Include files that match a regular expression or satisfy a callback
     *
     * If no regular expressions or callbacks are passed to
     * {@see RecursiveFilesystemIterator::include()}, all files are included.
     *
     * @param string|callable(SplFileInfo, string, FilesystemIterator, RecursiveIteratorIterator<RecursiveDirectoryIterator|RecursiveCallbackFilterIterator<string,SplFileInfo,RecursiveDirectoryIterator>>|null=): bool $value
     * @return $this
     */
    public function include($value)
    {
        $this->Include[] = $value;
        return $this;
    }

    /**
     * Match files to exclude and include by their path relative to the
     * directory being searched?
     *
     * Full pathnames, starting with directory names passed to
     * {@see RecursiveFilesystemIterator::in()}, are used for file matching
     * purposes by default.
     *
     * @return $this
     */
    public function matchRelative(bool $value = true)
    {
        if ($this->MatchRelative === $value) {
            return $this;
        }

        $clone = clone $this;
        $clone->MatchRelative = $value;
        return $clone;
    }

    /**
     * Do not match files to exclude and include by relative path
     *
     * @see RecursiveFilesystemIterator::matchRelative()
     *
     * @return $this
     */
    public function doNotMatchRelative()
    {
        return $this->matchRelative(false);
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return iterator_count($this->getIterator());
    }

    /**
     * @return Traversable<string,SplFileInfo>
     */
    public function getIterator(): Traversable
    {
        if (!$this->Dirs || (!$this->GetFiles && !$this->GetDirs)) {
            return new EmptyIterator();
        }

        $flags =
            FilesystemIterator::KEY_AS_PATHNAME
            | FilesystemIterator::CURRENT_AS_FILEINFO
            | FilesystemIterator::SKIP_DOTS
            | FilesystemIterator::UNIX_PATHS;

        $excludeFilter =
            $this->Exclude
                ? function (
                    SplFileInfo $file,
                    string $path,
                    FilesystemIterator $iterator
                ): bool {
                    $path = $this->getPath($path, $iterator);

                    foreach ($this->Exclude as $exclude) {
                        if (is_callable($exclude)) {
                            if ($exclude($file, $path, $iterator)) {
                                return true;
                            }
                            continue;
                        }
                        if (Regex::match($exclude, $path)
                            || ($this->MatchRelative
                                && Regex::match($exclude, "/{$path}"))) {
                            return true;
                        }
                        if ($file->isDir()
                            && (Regex::match($exclude, "{$path}/")
                                || ($this->MatchRelative
                                    && Regex::match($exclude, "/{$path}/")))) {
                            return true;
                        }
                    }

                    return false;
                }
                : null;

        $includeFilter =
            $this->Include
                ? function (
                    SplFileInfo $file,
                    string $path,
                    FilesystemIterator $iterator,
                    ?RecursiveIteratorIterator $recursiveIterator = null
                ): bool {
                    $path = $this->getPath($path, $iterator);

                    foreach ($this->Include as $include) {
                        if (is_callable($include)) {
                            if ($include($file, $path, $iterator, $recursiveIterator)) {
                                return true;
                            }
                            continue;
                        }
                        if (Regex::match($include, $path)
                            || ($this->MatchRelative
                                && Regex::match($include, "/{$path}"))) {
                            return true;
                        }
                        if ($file->isDir()
                            && (Regex::match($include, "$path/")
                                || ($this->MatchRelative
                                    && Regex::match($include, "/{$path}/")))) {
                            return true;
                        }
                    }

                    return false;
                }
                : null;

        foreach ($this->Dirs as $directory) {
            if (!is_dir($directory)) {
                throw new FilesystemErrorException(sprintf('Not a directory: %s', $directory));
            }

            if (!$this->Recurse) {
                $iterator = new FilesystemIterator($directory, $flags);

                if ($excludeFilter || $includeFilter) {
                    $iterator = new CallbackFilterIterator(
                        $iterator,
                        fn(SplFileInfo $file, string $path, FilesystemIterator $iterator): bool =>
                            (!$excludeFilter || !$excludeFilter($file, $path, $iterator))
                            && (!$includeFilter || $includeFilter($file, $path, $iterator))
                    );
                }

                if (!$this->GetDirs) {
                    $iterator = new CallbackFilterIterator(
                        $iterator,
                        fn(SplFileInfo $file): bool => !$file->isDir(),
                    );
                }

                if (!$this->GetFiles) {
                    $iterator = new CallbackFilterIterator(
                        $iterator,
                        fn(SplFileInfo $file): bool => !$file->isFile(),
                    );
                }

                $iterators[] = $iterator;

                continue;
            }

            $mode =
                $this->GetDirs
                    ? ($this->DirsFirst
                        ? RecursiveIteratorIterator::SELF_FIRST
                        : RecursiveIteratorIterator::CHILD_FIRST)
                    : RecursiveIteratorIterator::LEAVES_ONLY;

            $iterator = new RecursiveDirectoryIterator($directory, $flags);

            // Apply exclude filter early to prevent recursion into excluded
            // directories
            if ($excludeFilter) {
                $iterator = new RecursiveCallbackFilterIterator(
                    $iterator,
                    fn(SplFileInfo $file, string $path, RecursiveDirectoryIterator $iterator): bool =>
                        !$excludeFilter($file, $path, $iterator)
                );
            }

            $iterator = new RecursiveIteratorIterator($iterator, $mode);

            // Apply include filter after recursion to ensure every possible
            // match is found
            if ($includeFilter) {
                $iterator = new CallbackFilterIterator(
                    $iterator,
                    function (SplFileInfo $file, string $path, RecursiveIteratorIterator $iterator) use ($includeFilter): bool {
                        $recursiveIterator = $iterator;
                        /** @var RecursiveCallbackFilterIterator|RecursiveDirectoryIterator */
                        $iterator = $iterator->getInnerIterator();
                        if ($iterator instanceof RecursiveCallbackFilterIterator) {
                            /** @var RecursiveDirectoryIterator */
                            $iterator = $iterator->getInnerIterator();
                        }
                        return $includeFilter($file, $path, $iterator, $recursiveIterator);
                    }
                );
            }

            if (!$this->GetFiles) {
                $iterator = new CallbackFilterIterator(
                    $iterator,
                    fn(SplFileInfo $file): bool => !$file->isFile(),
                );
            }

            $iterators[] = $iterator;
        }

        if (count($iterators) === 1) {
            return $iterators[0];
        }

        $iterator = new AppendIterator();
        foreach ($iterators as $dirIterator) {
            $iterator->append($dirIterator);
        }

        return $iterator;
    }

    /**
     * @inheritDoc
     */
    public function nextWithValue($key, $value, bool $strict = false)
    {
        $name = Regex::replace('/^(?:get|is)/i', '', (string) $key, -1, $count);

        if (method_exists(SplFileInfo::class, $key)) {
            // If `$key` is the name of a method, check that it starts with
            // "get" or "is"
            if (!$count) {
                throw new LogicException(sprintf('Illegal key: %s', $key));
            }
            $method = $key;
        } else {
            foreach (["get{$name}", "is{$name}"] as $method) {
                if (method_exists(SplFileInfo::class, $method)) {
                    break;
                }
                $method = null;
            }
        }

        if (
            $method === null
            || !strcasecmp($method, 'getFileInfo')
            || !strcasecmp($method, 'getPathInfo')
        ) {
            throw new LogicException(sprintf('Invalid key: %s', $key));
        }

        foreach ($this as $current) {
            $_value = $current->$method();
            if ($strict) {
                if ($_value === $value) {
                    return $current;
                }
            } elseif ($_value == $value) {
                return $current;
            }
        }

        return null;
    }

    private function getPath(string $path, FilesystemIterator $iterator): string
    {
        if (!$this->MatchRelative) {
            return $path;
        }

        if ($iterator instanceof RecursiveDirectoryIterator) {
            return $iterator->getSubPathname();
        }

        return basename($path);
    }
}
