<?php declare(strict_types=1);

namespace Salient\Http\Internal;

use Salient\Contract\Core\Arrayable;
use Salient\Contract\Core\DateFormatterInterface;
use Salient\Contract\Core\Jsonable;
use Salient\Contract\Http\HasFormDataFlag;
use Salient\Core\Date\DateFormatter;
use Salient\Utility\Arr;
use Salient\Utility\Get;
use Salient\Utility\Json;
use Salient\Utility\Test;
use Closure;
use DateTimeInterface;
use InvalidArgumentException;
use JsonSerializable;
use Traversable;

/**
 * @internal
 *
 * @template T of mixed[]|object|string|null
 */
final class FormDataEncoder implements HasFormDataFlag
{
    /** @var int-mask-of<FormDataEncoder::*> */
    private int $Flags;
    private DateFormatterInterface $DateFormatter;
    /** @var (Closure(object): (T|false))|null */
    private ?Closure $Callback;

    /**
     * @param int-mask-of<FormDataEncoder::*> $flags
     * @param (Closure(object): (T|false))|null $callback
     */
    public function __construct(
        int $flags = FormDataEncoder::DATA_PRESERVE_NUMERIC_KEYS | FormDataEncoder::DATA_PRESERVE_STRING_KEYS,
        ?DateFormatterInterface $dateFormatter = null,
        ?Closure $callback = null
    ) {
        $this->Flags = $flags;
        $this->DateFormatter = $dateFormatter ?? new DateFormatter();
        $this->Callback = $callback;
    }

    /**
     * Get form data as a list of key-value pairs
     *
     * List keys are not preserved by default. Use `$flags` to modify this
     * behaviour.
     *
     * If no `$dateFormatter` is given, a {@see DateFormatter} is created to
     * convert {@see DateTimeInterface} instances to ISO-8601 strings.
     *
     * `$callback` is applied to objects other than {@see DateTimeInterface}
     * instances found in `$data`. It may return `null` to skip the value, or
     * `false` to process the value as if no callback had been given. If a
     * {@see DateTimeInterface} is returned, it is converted to `string` as per
     * the `$dateFormatter` note above.
     *
     * If no `$callback` is given, objects are resolved as follows:
     *
     * - {@see DateTimeInterface}: converted to `string` (see `$dateFormatter`
     *   note above)
     * - {@see Arrayable}: replaced with {@see Arrayable::toArray()}
     * - {@see JsonSerializable}: replaced with
     *   {@see JsonSerializable::jsonSerialize()} if it returns an `array`
     * - {@see Jsonable}: replaced with {@see Jsonable::toJson()} after decoding
     *   if {@see json_decode()} returns an `array`
     * - `object` with at least one public property: replaced with an array that
     *   maps public property names to values
     * - {@see Stringable}: cast to `string`
     *
     * @param mixed[]|object $data
     * @return list<array{string,(T&object)|string}>
     */
    public function getValues($data): array
    {
        // @phpstan-ignore return.type
        return $this->doGetData($data);
    }

    /**
     * Get form data as a URL-encoded query string
     *
     * Equivalent to calling {@see FormDataEncoder::getValues()} and converting
     * the result to a query string.
     *
     * @param mixed[]|object $data
     */
    public function getQuery($data): string
    {
        $data = $this->doGetData($data);
        foreach ($data as [$key, $value]) {
            if (!is_string($value)) {
                throw new InvalidArgumentException(sprintf(
                    "Invalid value at '%s': %s",
                    $key,
                    Get::type($value),
                ));
            }
            $query[] = rawurlencode($key) . '=' . rawurlencode($value);
        }
        return implode('&', $query ?? []);
    }

    /**
     * Get form data as nested arrays of scalar values
     *
     * Similar to {@see FormDataEncoder::getValues()}, but scalar types are
     * preserved and data structures are not flattened.
     *
     * @param mixed[]|object $data
     * @return mixed[]
     */
    public function getData($data): array
    {
        return $this->doGetData($data, false);
    }

    /**
     * @param mixed[]|object|int|float|string|bool|null $data
     * @param mixed[]|null $query
     * @phpstan-param ($flatten is true ? list<array{string,(T&object)|string}> : ($name is null ? mixed[] : mixed[]|null)) $query
     * @param-out ($flatten is true ? list<array{string,(T&object)|string}> : ($name is null ? mixed[] : mixed[]|(T&object)|int|float|string|bool|null)) $query
     * @return ($flatten is true ? list<array{string,(T&object)|string}> : ($name is null ? mixed[] : mixed[]|(T&object)|int|float|string|bool|null))
     */
    private function doGetData(
        $data,
        bool $flatten = true,
        bool $fromCallback = false,
        &$query = [],
        ?string $name = null
    ) {
        if ($name === null) {
            $data = $flatten
                ? $this->flattenValue($data)
                : $this->resolveValue($data, $fromCallback);
        }

        /** @var mixed[]|(T&object)|int|float|string|bool|null $data */
        if (
            ($flatten && ($data === null || $data === []))
            || ($fromCallback && $data === null)
        ) {
            return $query;
        }

        if (is_array($data)) {
            $hasArray = false;
            if ($flatten) {
                foreach ($data as $key => &$value) {
                    $value = $this->flattenValue($value);
                    if (!$hasArray) {
                        $hasArray = is_array($value);
                    }
                }
            } else {
                /** @var array<array-key,bool> */
                $fromCallback = [];
                foreach ($data as $key => &$value) {
                    $value = $this->resolveValue($value, $fromCallback[$key]);
                }
            }
            unset($value);

            $preserveKeys = $name === null || $hasArray || (
                Arr::isList($data)
                    ? $this->Flags & self::DATA_PRESERVE_LIST_KEYS
                    : (Arr::hasNumericKeys($data)
                        ? $this->Flags & self::DATA_PRESERVE_NUMERIC_KEYS
                        : $this->Flags & self::DATA_PRESERVE_STRING_KEYS)
            );

            $format = $preserveKeys
                ? ($flatten && $name !== null ? '[%s]' : '%s')
                : ($flatten ? '[]' : '');

            if ($flatten) {
                /** @var mixed[]|object|string|null $value */
                foreach ($data as $key => $value) {
                    $_key = sprintf($format, $key);
                    $this->doGetData($value, true, false, $query, $name . $_key);
                }
            } else {
                /** @var mixed[]|object|string|null $value */
                foreach ($data as $key => $value) {
                    $_key = sprintf($format, $key);
                    if ($_key === '') {
                        $query[] = null;
                        $_key = array_key_last($query);
                    }
                    // @phpstan-ignore offsetAccess.nonOffsetAccessible
                    $this->doGetData($value, false, $fromCallback[$key], $query[$_key], '');
                }
            }

            return $query;
        }

        if ($flatten) {
            $query[] = [$name, $data];
            return $query;
        }

        if ($name === null) {
            throw new InvalidArgumentException('Argument #1 ($data) must resolve to an array');
        }

        $query = $data;
        return $query;
    }

    /**
     * @param mixed $value
     * @return mixed[]|(T&object)|string|null
     */
    private function flattenValue($value)
    {
        if (is_bool($value)) {
            return (string) (int) $value;
        }

        if ($value === null || is_scalar($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            $value = $this->convertObject($value, $fromCallback);
            if (!$fromCallback && ($value === null || is_scalar($value))) {
                // @phpstan-ignore cast.string
                return (string) $value;
            }
            /** @var mixed[]|(T&object)|string|null */
            return $value;
        }

        throw new InvalidArgumentException(sprintf(
            'Invalid value: %s',
            Get::type($value),
        ));
    }

    /**
     * @param mixed $value
     * @param-out bool $fromCallback
     * @return T|mixed[]|int|float|string|bool|null
     */
    private function resolveValue($value, ?bool &$fromCallback)
    {
        $fromCallback = false;

        if ($value === null || is_scalar($value) || is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return $this->convertObject($value, $fromCallback);
        }

        throw new InvalidArgumentException(sprintf(
            'Invalid value: %s',
            Get::type($value),
        ));
    }

    /**
     * @param-out bool $fromCallback
     * @return T|mixed[]|int|float|string|bool|null
     */
    private function convertObject(object $value, ?bool &$fromCallback)
    {
        $fromCallback = false;

        if ($value instanceof DateTimeInterface) {
            return $this->DateFormatter->format($value);
        }

        if ($this->Callback !== null) {
            $result = ($this->Callback)($value);
            if ($result instanceof DateTimeInterface) {
                return $this->DateFormatter->format($result);
            }
            if ($result !== false) {
                $fromCallback = true;
                return $result;
            }
        }

        if ($value instanceof Arrayable) {
            return $value->toArray();
        }

        if ($value instanceof JsonSerializable) {
            return Json::objectAsArray(Json::encode($value));
        }

        if ($value instanceof Jsonable) {
            return Json::objectAsArray($value->toJson());
        }

        if ($value instanceof Traversable) {
            return iterator_to_array($value);
        }

        // Get public property values
        $result = [];
        // @phpstan-ignore foreach.nonIterable
        foreach ($value as $key => $val) {
            $result[$key] = $val;
        }
        if ($result !== []) {
            return $result;
        }

        if (Test::isStringable($value)) {
            return (string) $value;
        }

        return [];
    }
}
