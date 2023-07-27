<?php declare(strict_types=1);

namespace Lkrms\Console;

use ArrayAccess;
use Lkrms\Console\Catalog\ConsoleTag as Tag;
use Lkrms\Console\Contract\IConsoleFormat;
use Lkrms\Console\Support\ConsoleLoopbackFormat;
use LogicException;
use ReturnTypeWillChange;

/**
 * Maps inline formatting tags to target-defined formats
 *
 * @implements ArrayAccess<Tag::*,IConsoleFormat>
 */
final class ConsoleTagFormats implements ArrayAccess
{
    /**
     * @var IConsoleFormat[]
     */
    private array $TagFormats = [];

    private IConsoleFormat $FallbackFormat;

    public function __construct(?IConsoleFormat $fallbackFormat = null)
    {
        $this->FallbackFormat = $fallbackFormat ?: new ConsoleFormat();
    }

    public function getFallbackFormat(): IConsoleFormat
    {
        return $this->FallbackFormat;
    }

    /**
     * @param Tag::* $offset
     */
    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->TagFormats);
    }

    /**
     * @param Tag::* $offset
     * @return IConsoleFormat
     */
    #[ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->TagFormats[$offset] ?? $this->FallbackFormat;
    }

    /**
     * @param Tag::*|null $offset
     * @param IConsoleFormat $value
     */
    public function offsetSet($offset, $value): void
    {
        if ($offset === null) {
            throw new LogicException('Offset required');
        }
        if (!($value instanceof IConsoleFormat)) {
            throw new LogicException(
                sprintf('Value must implement %s', IConsoleFormat::class)
            );
        }
        $this->TagFormats[$offset] = $value;
    }

    /**
     * @param Tag::* $offset
     */
    public function offsetUnset($offset): void
    {
        unset($this->TagFormats[$offset]);
    }

    public static function getLoopbackFormats(): self
    {
        $instance = new self();
        $instance[Tag::HEADING] = new ConsoleLoopbackFormat('***', '***');
        $instance[Tag::BOLD] = new ConsoleLoopbackFormat('**', '**');
        $instance[Tag::ITALIC] = new ConsoleLoopbackFormat('*', '*');
        $instance[Tag::UNDERLINE] = new ConsoleLoopbackFormat('<', '>');
        $instance[Tag::LOW_PRIORITY] = new ConsoleLoopbackFormat('~~', '~~');
        $instance[Tag::CODE_SPAN] = new ConsoleLoopbackFormat('`', '`');
        $instance[Tag::CODE_BLOCK] = new ConsoleLoopbackFormat('```', '```');

        return $instance;
    }
}
