<?php declare(strict_types=1);

namespace Salient\Iterator;

use Salient\Contract\Core\Immutable;
use Salient\Contract\Iterator\FluentIteratorInterface;
use Salient\Iterator\Concern\FluentIteratorTrait;
use Salient\Utility\Exception\FilesystemErrorException;
use Salient\Utility\Exception\InvalidArgumentTypeException;
use Salient\Utility\Arr;
use Salient\Utility\Regex;
use AppendIterator;
use CallbackFilterIterator;
use Countable;
use EmptyIterator;
use FilesystemIterator;
use InvalidArgumentException;
use Iterator;
use IteratorAggregate;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Traversable;

/**
 * Iterates over filesystem entries
 *
 * @api
 *
 * @implements IteratorAggregate<string,SplFileInfo>
 * @implements FluentIteratorInterface<string,SplFileInfo>
 */
class RecursiveFilesystemIterator implements
    IteratorAggregate,
    FluentIteratorInterface,
    Countable,
    Immutable
{
    /** @use FluentIteratorTrait<string,SplFileInfo> */
    use FluentIteratorTrait;

    /** @var string[] */
    private array $Directories = [];
    private bool $ReturnFiles = true;
    private bool $ReturnDirectories = true;
    private bool $ReturnDirectoriesFirst = true;
    private bool $Recurse = true;
    /** @var array<callable(SplFileInfo $file, string $path, int $depth): bool> */
    private array $ExcludeCallback = [];
    /** @var string[] */
    private array $ExcludeRegex = [];
    private bool $Exclude = false;
    /** @var array<callable(SplFileInfo $file, string $path, int $depth): bool> */
    private array $IncludeCallback = [];
    /** @var string[] */
    private array $IncludeRegex = [];
    private bool $Include = false;
    private bool $Relative = false;

    /**
     * Get an instance that iterates over entries in the given directories
     *
     * @return static
     */
    public function in(string ...$directory)
    {
        return $this->with('Directories', Arr::push($this->Directories, ...$directory));
    }

    /**
     * Get an instance that only returns files, not directories
     *
     * The default behaviour is to return files and directories.
     *
     * @return static
     */
    public function files()
    {
        return $this->with('ReturnFiles', true)->with('ReturnDirectories', false);
    }

    /**
     * Get an instance that only returns directories, not files
     *
     * The default behaviour is to return files and directories.
     *
     * @return static
     */
    public function directories()
    {
        return $this->with('ReturnFiles', false)->with('ReturnDirectories', true);
    }

    /**
     * Get an instance that returns directories before their children
     *
     * This is the default behaviour.
     *
     * @return static
     */
    public function directoriesFirst()
    {
        return $this->with('ReturnDirectoriesFirst', true);
    }

    /**
     * Get an instance that returns directories after their children
     *
     * The default behaviour is to return directories before their children.
     *
     * @return static
     */
    public function directoriesLast()
    {
        return $this->with('ReturnDirectoriesFirst', false);
    }

    /**
     * Get an instance that recurses into directories
     *
     * This is the default behaviour.
     *
     * @return static
     */
    public function recurse()
    {
        return $this->with('Recurse', true);
    }

    /**
     * Get an instance that does not recurse into directories
     *
     * The default behaviour is to recurse into directories.
     *
     * @return static
     */
    public function doNotRecurse()
    {
        return $this->with('Recurse', false);
    }

    /**
     * Get an instance that does not return entries that match a regular
     * expression or satisfy a callback
     *
     * Regular expressions are tested against:
     *
     * - pathname (all entries)
     * - pathname with trailing `/` (directory entries)
     * - pathname with leading `/` (all entries if pathname is relative)
     * - pathname with leading and trailing `/` (directory entries if pathname
     *   is relative)
     *
     * Pathnames are relative after calling {@see relative()}.
     *
     * @param string|callable(SplFileInfo $file, string $path, int $depth): bool $value
     * @return static
     */
    public function exclude($value)
    {
        return (is_callable($value)
                ? $this->with('ExcludeCallback', Arr::push($this->ExcludeCallback, $value))
                : $this->with('ExcludeRegex', Arr::push($this->ExcludeRegex, $value)))
            ->with('Exclude', true);
    }

    /**
     * Get an instance that only returns entries that match a regular expression
     * or satisfy a callback
     *
     * The default behaviour is to return all entries.
     *
     * Regular expressions are tested against:
     *
     * - pathname (all entries)
     * - pathname with trailing `/` (directory entries)
     * - pathname with leading `/` (all entries if pathname is relative)
     * - pathname with leading and trailing `/` (directory entries if pathname
     *   is relative)
     *
     * Pathnames are relative after calling {@see relative()}.
     *
     * @param string|callable(SplFileInfo $file, string $path, int $depth): bool $value
     * @return static
     */
    public function include($value)
    {
        return (is_callable($value)
                ? $this->with('IncludeCallback', Arr::push($this->IncludeCallback, $value))
                : $this->with('IncludeRegex', Arr::push($this->IncludeRegex, $value)))
            ->with('Include', true);
    }

    /**
     * Get an instance where entries to return are matched by pathname relative
     * to the directory being iterated over
     *
     * The default behaviour is to use full pathnames, starting with directory
     * names passed to {@see in()}, when matching entries.
     *
     * @return static
     */
    public function relative()
    {
        return $this->with('Relative', true);
    }

    /**
     * Get an instance where entries to return are matched by full pathname,
     * starting with the name of the directory being iterated over
     *
     * This is the default behaviour.
     *
     * @return static
     */
    public function notRelative()
    {
        return $this->with('Relative', false);
    }

    /**
     * Get the number of entries the instance would return
     */
    public function count(): int
    {
        return iterator_count($this->getIterator());
    }

    /**
     * @internal
     */
    public function getIterator(): Traversable
    {
        if (!$this->Directories) {
            return new EmptyIterator();
        }

        $flags = FilesystemIterator::KEY_AS_PATHNAME
            | FilesystemIterator::CURRENT_AS_FILEINFO
            | FilesystemIterator::SKIP_DOTS
            | FilesystemIterator::UNIX_PATHS;

        foreach ($this->Directories as $directory) {
            if (!is_dir($directory)) {
                throw new FilesystemErrorException(sprintf(
                    'Not a directory: %s',
                    $directory,
                ));
            }

            if (!$this->Recurse) {
                $iterator = new FilesystemIterator($directory, $flags);

                if ($this->Exclude || $this->Include) {
                    $iterator = new CallbackFilterIterator(
                        $iterator,
                        ($this->Exclude && $this->Include)
                            ? fn(SplFileInfo $file, string $path, FilesystemIterator $iterator) =>
                                !$this->checkExclude($file, $path, $iterator)
                                && $this->checkInclude($file, $path, $iterator)
                            : ($this->Exclude
                                ? fn(SplFileInfo $file, string $path, FilesystemIterator $iterator) =>
                                    !$this->checkExclude($file, $path, $iterator)
                                : fn(SplFileInfo $file, string $path, FilesystemIterator $iterator) =>
                                    $this->checkInclude($file, $path, $iterator))
                    );
                }

                if (!$this->ReturnFiles || !$this->ReturnDirectories) {
                    $iterator = new CallbackFilterIterator(
                        $iterator,
                        !$this->ReturnFiles
                            ? fn(SplFileInfo $file) => !$file->isFile()
                            : fn(SplFileInfo $file) => !$file->isDir(),
                    );
                }

                /** @var Iterator<string,SplFileInfo> $iterator */
                $iterators[] = $iterator;
                continue;
            }

            $mode = $this->ReturnDirectories
                ? ($this->ReturnDirectoriesFirst
                    ? RecursiveIteratorIterator::SELF_FIRST
                    : RecursiveIteratorIterator::CHILD_FIRST)
                : RecursiveIteratorIterator::LEAVES_ONLY;

            $iterator = new RecursiveDirectoryIterator($directory, $flags);

            // Apply exclusions early to prevent recursion into excluded
            // directories
            if ($this->Exclude) {
                $iterator = new RecursiveCallbackFilterIterator(
                    $iterator,
                    fn(SplFileInfo $file, string $path, RecursiveDirectoryIterator $iterator) =>
                        !$this->checkExclude($file, $path, $iterator),
                );
            }

            $iterator = new RecursiveIteratorIterator($iterator, $mode);

            // Apply inclusions after recursion to ensure every possible match
            // is found
            if ($this->Include) {
                $iterator = new CallbackFilterIterator(
                    $iterator,
                    fn(SplFileInfo $file, string $path, RecursiveIteratorIterator $iterator) =>
                        $this->checkInclude($file, $path, $iterator),
                );
            }

            if (!$this->ReturnFiles) {
                $iterator = new CallbackFilterIterator(
                    $iterator,
                    fn(SplFileInfo $file) => !$file->isFile(),
                );
            }

            $iterators[] = $iterator;
        }

        if (count($iterators) === 1) {
            return $iterators[0];
        }

        /** @var AppendIterator<string,SplFileInfo,Iterator<string,SplFileInfo>> */
        $iterator = new AppendIterator();
        foreach ($iterators as $dirIterator) {
            $iterator->append($dirIterator);
        }

        return $iterator;
    }

    /**
     * @inheritDoc
     */
    public function getFirstWith($key, $value, bool $strict = false)
    {
        if (!is_string($key)) {
            throw new InvalidArgumentTypeException(1, 'key', 'string', $key);
        }

        $key = Regex::replace('/^(?:get|is)/i', '', $key);
        $method = null;
        foreach (['get', 'is'] as $prefix) {
            if (method_exists(SplFileInfo::class, $name = $prefix . $key)) {
                $method = $name;
                break;
            }
        }

        if ($method === null) {
            throw new InvalidArgumentException(sprintf('Invalid key: %s', $key));
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

    private function checkExclude(SplFileInfo $file, string $path, FilesystemIterator $iterator): bool
    {
        [$path, $depth] = $this->getCallbackArgs($path, $iterator);
        foreach ($this->ExcludeCallback as $exclude) {
            if ($exclude($file, $path, $depth)) {
                return true;
            }
        }
        foreach ($this->ExcludeRegex as $exclude) {
            if (
                Regex::match($exclude, $path) || (
                    $this->Relative
                    && Regex::match($exclude, "/{$path}")
                ) || (
                    $file->isDir() && (
                        Regex::match($exclude, "{$path}/") || (
                            $this->Relative
                            && Regex::match($exclude, "/{$path}/")
                        )
                    )
                )
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param RecursiveIteratorIterator<RecursiveDirectoryIterator|RecursiveCallbackFilterIterator<string,SplFileInfo,RecursiveDirectoryIterator>>|FilesystemIterator $iterator
     */
    private function checkInclude(SplFileInfo $file, string $path, Iterator $iterator): bool
    {
        [$path, $depth] = $this->getCallbackArgs($path, $iterator);
        foreach ($this->IncludeCallback as $include) {
            if ($include($file, $path, $depth)) {
                return true;
            }
        }
        foreach ($this->IncludeRegex as $include) {
            if (
                Regex::match($include, $path) || (
                    $this->Relative
                    && Regex::match($include, "/{$path}")
                ) || (
                    $file->isDir() && (
                        Regex::match($include, "{$path}/")
                        || ($this->Relative
                            && Regex::match($include, "/{$path}/"))
                    )
                )
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param RecursiveIteratorIterator<RecursiveDirectoryIterator|RecursiveCallbackFilterIterator<string,SplFileInfo,RecursiveDirectoryIterator>>|FilesystemIterator $iterator
     * @return array{string,int}
     */
    private function getCallbackArgs(string $path, Iterator $iterator): array
    {
        if ($iterator instanceof RecursiveIteratorIterator) {
            $depth = $iterator->getDepth();
            $iterator = $iterator->getInnerIterator();
            if ($iterator instanceof RecursiveCallbackFilterIterator) {
                $iterator = $iterator->getInnerIterator();
            }
        } else {
            $depth = 0;
        }
        if ($this->Relative) {
            $path = $iterator instanceof RecursiveDirectoryIterator
                ? $iterator->getSubPathname()
                : basename($path);
        }
        return [$path, $depth];
    }

    /**
     * @param mixed $value
     * @return static
     */
    private function with(string $property, $value)
    {
        if ($value === $this->$property) {
            return $this;
        }
        $clone = clone $this;
        $clone->$property = $value;
        return $clone;
    }
}
