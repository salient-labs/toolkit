<?php declare(strict_types=1);

namespace Lkrms\Iterator;

use Lkrms\Contract\IImmutable;
use Lkrms\Exception\Exception;
use Lkrms\Utility\Pcre;
use AppendIterator;
use CallbackFilterIterator;
use Closure;
use EmptyIterator;
use FilesystemIterator;
use IteratorAggregate;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Traversable;

/**
 * Iterates over files and directories
 *
 * @implements IteratorAggregate<string,SplFileInfo>
 */
class DirectoryIterator implements IteratorAggregate, IImmutable
{
    private bool $GetFiles = true;
    private bool $GetDirs = false;
    private bool $DirsFirst = true;
    private bool $Recurse = true;

    /**
     * @var string[]
     */
    private array $Dirs = [];

    /**
     * @var array<string|callable(SplFileInfo, string, FilesystemIterator): bool>
     */
    private array $Exclude = [];

    /**
     * @var array<string|callable(SplFileInfo, string, FilesystemIterator): bool>
     */
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
     * {@see DirectoryIterator::include()}, all files are included.
     *
     * @param string|callable(SplFileInfo, string, FilesystemIterator): bool $value
     * @return $this
     */
    public function include($value)
    {
        $this->Include[] = $value;
        return $this;
    }

    /**
     * @return Traversable<string,SplFileInfo>
     */
    public function getIterator(): Traversable
    {
        if (!$this->Dirs || !($this->GetFiles || $this->GetDirs)) {
            return new EmptyIterator();
        }

        $flags =
            FilesystemIterator::KEY_AS_PATHNAME
                | FilesystemIterator::CURRENT_AS_FILEINFO
                | FilesystemIterator::SKIP_DOTS
                | FilesystemIterator::UNIX_PATHS;

        $filter = $this->getFilter();

        foreach ($this->Dirs as $directory) {
            if (!is_dir($directory)) {
                throw new Exception(sprintf('Not a directory: %s', $directory));
            }

            if (!$this->Recurse) {
                $iterator = new FilesystemIterator($directory, $flags);

                if ($filter) {
                    $iterator = new CallbackFilterIterator($iterator, $filter);
                }

                if (!$this->GetDirs) {
                    $iterator = new CallbackFilterIterator(
                        $iterator,
                        fn(SplFileInfo $file) => !$file->isDir(),
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

            if ($filter) {
                $iterator = new RecursiveCallbackFilterIterator($iterator, $filter);
            }

            $iterators[] = new RecursiveIteratorIterator($iterator, $mode);
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
     * @return (Closure(SplFileInfo, string, FilesystemIterator): bool)|null
     */
    private function getFilter(): ?Closure
    {
        if (!$this->Exclude && !$this->Include) {
            return null;
        }

        return
            function (SplFileInfo $file, string $path, FilesystemIterator $iterator) {
                if ($iterator instanceof RecursiveDirectoryIterator) {
                    $relPath = $iterator->getSubPathname();
                } else {
                    $relPath = basename($path);
                }

                foreach ($this->Exclude as $exclude) {
                    if (is_callable($exclude)) {
                        if ($exclude($file, $relPath, $iterator)) {
                            return false;
                        }
                        continue;
                    }
                    if (Pcre::match($exclude, $relPath) ||
                            Pcre::match($exclude, "/{$relPath}")) {
                        return false;
                    }
                    if ($file->isDir() &&
                        (Pcre::match($exclude, "{$relPath}/") ||
                            Pcre::match($exclude, "/{$relPath}/"))) {
                        return false;
                    }
                }

                if (!$this->Include) {
                    return true;
                }

                // Always recurse into directories unless they are explicitly
                // excluded
                if ($this->Recurse && $file->isDir()) {
                    return true;
                }

                foreach ($this->Include as $include) {
                    if (is_callable($include)) {
                        if ($include($file, $relPath, $iterator)) {
                            return true;
                        }
                        continue;
                    }
                    if (Pcre::match($include, $relPath) ||
                            Pcre::match($include, "/{$relPath}")) {
                        return true;
                    }
                }

                return false;
            };
    }
}
