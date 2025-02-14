<?php declare(strict_types=1);

namespace Salient\Utility\Exception;

use Salient\Utility\Arr;
use Salient\Utility\Format;
use Stringable;

/**
 * @api
 */
class PcreErrorException extends UtilityException
{
    private const ERROR_MESSAGE_MAP = [
        \PREG_NO_ERROR => 'No error',
        \PREG_INTERNAL_ERROR => 'Internal error',
        \PREG_BAD_UTF8_ERROR => 'Malformed UTF-8 characters, possibly incorrectly encoded',
        \PREG_BAD_UTF8_OFFSET_ERROR => 'The offset did not correspond to the beginning of a valid UTF-8 code point',
        \PREG_BACKTRACK_LIMIT_ERROR => 'Backtrack limit exhausted',
        \PREG_RECURSION_LIMIT_ERROR => 'Recursion limit exhausted',
        \PREG_JIT_STACKLIMIT_ERROR => 'JIT stack limit exhausted',
    ];

    /** @var array<string,callable>|string[]|string */
    protected $Pattern;
    /** @var array<int|float|string|bool|Stringable|null>|string */
    protected $Subject;
    /** @var array<int,string> */
    private static array $ErrorNameMap;

    /**
     * @internal
     *
     * @param array<string,callable>|string[]|string $pattern
     * @param array<int|float|string|bool|Stringable|null>|string $subject
     */
    public function __construct(?int $error, string $function, $pattern, $subject)
    {
        $error ??= preg_last_error();
        $message = \PHP_VERSION_ID < 80000
            ? self::ERROR_MESSAGE_MAP[$error] ?? 'Unknown error'
            : preg_last_error_msg();

        $this->Pattern = $pattern;
        $this->Subject = $subject;

        parent::__construct(sprintf(
            'Call to %s() failed with %s (%s)',
            $function,
            (self::$ErrorNameMap ??= self::getErrorNameMap())[$error] ?? "#$error",
            $message,
        ));
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        $detail = '';
        foreach ([
            'Pattern' => is_array($this->Pattern)
                ? array_map(
                    fn($value) => is_string($value) ? $value : '<callable>',
                    $this->Pattern,
                )
                : $this->Pattern,
            'Subject' => $this->Subject,
        ] as $key => $value) {
            if (is_array($value)) {
                $value = Arr::isList($value)
                    ? Format::list($value)
                    : Format::array($value);
            }
            $detail .= sprintf("\n\n%s:\n%s", $key, rtrim($value, "\n"));
        }

        return parent::__toString() . $detail;
    }

    /**
     * @return array<int,string>
     */
    private static function getErrorNameMap(): array
    {
        /** @var array<string,mixed> */
        $constants = get_defined_constants(true)['pcre'];
        $map = [];
        foreach ($constants as $name => $value) {
            if (substr($name, -6) === '_ERROR') {
                /** @var int $value */
                $map[$value] = $name;
            }
        }
        return $map;
    }
}
