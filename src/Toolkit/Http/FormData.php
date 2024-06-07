<?php declare(strict_types=1);

namespace Salient\Http;

use Salient\Contract\Core\Arrayable;
use Salient\Contract\Core\DateFormatterInterface;
use Salient\Contract\Core\Jsonable;
use Salient\Contract\Http\FormDataFlag;
use Salient\Core\Exception\InvalidArgumentException;
use Salient\Core\DateFormatter;
use Salient\Utility\Arr;
use Salient\Utility\Get;
use Salient\Utility\Json;
use Salient\Utility\Test;
use DateTimeInterface;
use JsonSerializable;
use Traversable;

/**
 * @api
 */
final class FormData
{
    /** @var mixed[]|object */
    private $Data;

    /**
     * Creates a new FormData object from nested arrays and objects
     *
     * @param mixed[]|object $data
     */
    public function __construct($data)
    {
        $this->Data = $data;
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
     * @template T of object|mixed[]|string|null
     *
     * @param int-mask-of<FormDataFlag::*> $flags
     * @param (callable(object): (T|false))|null $callback
     * @return list<array{string,(T&object)|string}>
     */
    public function getValues(
        int $flags = FormDataFlag::PRESERVE_NUMERIC_KEYS | FormDataFlag::PRESERVE_STRING_KEYS,
        ?DateFormatterInterface $dateFormatter = null,
        ?callable $callback = null
    ): array {
        $dateFormatter ??= new DateFormatter();
        /** @var list<array{string,(T&object)|string}> */
        return $this->doGetData($this->Data, $flags, $dateFormatter, $callback);
    }

    /**
     * Get form data as a URL-encoded query string
     *
     * Equivalent to calling {@see FormData::getValues()} and converting the
     * result to a query string.
     *
     * @template T of object|mixed[]|string|null
     *
     * @param int-mask-of<FormDataFlag::*> $flags
     * @param (callable(object): (T|false))|null $callback
     */
    public function getQuery(
        int $flags = FormDataFlag::PRESERVE_NUMERIC_KEYS | FormDataFlag::PRESERVE_STRING_KEYS,
        ?DateFormatterInterface $dateFormatter = null,
        ?callable $callback = null
    ): string {
        $dateFormatter ??= new DateFormatter();
        $data = $this->doGetData($this->Data, $flags, $dateFormatter, $callback);
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
     * Similar to {@see FormData::getValues()}, but scalar types are preserved
     * and data structures are not flattened.
     *
     * @template T of object|mixed[]|string|null
     *
     * @param int-mask-of<FormDataFlag::*> $flags
     * @param (callable(object): (T|false))|null $callback
     * @return mixed[]
     */
    public function getData(
        int $flags = FormDataFlag::PRESERVE_NUMERIC_KEYS | FormDataFlag::PRESERVE_STRING_KEYS,
        ?DateFormatterInterface $dateFormatter = null,
        ?callable $callback = null
    ): array {
        $dateFormatter ??= new DateFormatter();
        return $this->doGetData($this->Data, $flags, $dateFormatter, $callback, false);
    }

    /**
     * @template T of object|mixed[]|string|null
     *
     * @param mixed[]|object|int|float|string|bool|null $data
     * @param int-mask-of<FormDataFlag::*> $flags
     * @param (callable(object): (T|false))|null $cb
     * @param mixed[]|null $query
     * @phpstan-param ($flatten is true ? list<array{string,(T&object)|string}> : ($name is null ? mixed[] : mixed[]|null)) $query
     * @param-out ($flatten is true ? list<array{string,(T&object)|string}> : ($name is null ? mixed[] : mixed[]|(T&object)|int|float|string|bool|null)) $query
     * @return ($flatten is true ? list<array{string,(T&object)|string}> : ($name is null ? mixed[] : mixed[]|(T&object)|int|float|string|bool|null))
     */
    private function doGetData(
        $data,
        int $flags,
        DateFormatterInterface $df,
        ?callable $cb,
        bool $flatten = true,
        bool $fromCallback = false,
        &$query = [],
        ?string $name = null
    ) {
        if ($name === null) {
            $data = $flatten
                ? $this->flattenValue($data, $df, $cb)
                : $this->resolveValue($data, $df, $cb, $fromCallback);
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
                    $value = $this->flattenValue($value, $df, $cb);
                    if (!$hasArray) {
                        $hasArray = is_array($value);
                    }
                }
            } else {
                /** @var array<array-key,bool> */
                $fromCallback = [];
                foreach ($data as $key => &$value) {
                    $value = $this->resolveValue($value, $df, $cb, $fromCallback[$key]);
                }
            }
            unset($value);

            $preserveKeys = $name === null || $hasArray || (
                Arr::isList($data)
                    ? $flags & FormDataFlag::PRESERVE_LIST_KEYS
                    : (Arr::isIndexed($data)
                        ? $flags & FormDataFlag::PRESERVE_NUMERIC_KEYS
                        : $flags & FormDataFlag::PRESERVE_STRING_KEYS)
            );

            $format = $preserveKeys
                ? ($flatten && $name !== null ? '[%s]' : '%s')
                : ($flatten ? '[]' : '');

            if ($flatten) {
                /** @var object|mixed[]|string|null $value */
                foreach ($data as $key => $value) {
                    $_key = sprintf($format, $key);
                    $this->doGetData($value, $flags, $df, $cb, true, false, $query, $name . $_key);
                }
            } else {
                /** @var object|mixed[]|string|null $value */
                foreach ($data as $key => $value) {
                    $_key = sprintf($format, $key);
                    /** @var mixed[] $query */
                    if ($_key === '') {
                        $query[] = null;
                        $_key = array_key_last($query);
                    }
                    $this->doGetData($value, $flags, $df, $cb, false, $fromCallback[$key], $query[$_key], '');
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
     * @template T of object|mixed[]|string|null
     *
     * @param mixed $value
     * @param (callable(object): (T|false))|null $cb
     * @return mixed[]|(T&object)|string|null
     */
    private function flattenValue(
        $value,
        DateFormatterInterface $df,
        ?callable $cb
    ) {
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
            $value = $this->convertObject($value, $df, $cb, $fromCallback);
            if (!$fromCallback && ($value === null || is_scalar($value))) {
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
     * @template T of object|mixed[]|string|null
     *
     * @param mixed $value
     * @param (callable(object): (T|false))|null $cb
     * @return T|mixed[]|int|float|string|bool|null
     */
    private function resolveValue(
        $value,
        DateFormatterInterface $df,
        ?callable $cb,
        ?bool &$fromCallback
    ) {
        $fromCallback = false;

        if ($value === null || is_scalar($value) || is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return $this->convertObject($value, $df, $cb, $fromCallback);
        }

        throw new InvalidArgumentException(sprintf(
            'Invalid value: %s',
            Get::type($value),
        ));
    }

    /**
     * @template T of object|mixed[]|string|null
     *
     * @param (callable(object): (T|false))|null $cb
     * @return T|mixed[]|int|float|string|bool|null
     */
    private function convertObject(
        object $value,
        DateFormatterInterface $df,
        ?callable $cb,
        ?bool &$fromCallback
    ) {
        $fromCallback = false;

        if ($value instanceof DateTimeInterface) {
            return $df->format($value);
        }

        if ($cb !== null) {
            $result = $cb($value);
            if ($result instanceof DateTimeInterface) {
                return $df->format($result);
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
            return Json::parseObjectAsArray(Json::stringify($value));
        }

        if ($value instanceof Jsonable) {
            return Json::parseObjectAsArray($value->toJson());
        }

        if ($value instanceof Traversable) {
            return iterator_to_array($value);
        }

        // Get public property values
        $result = [];
        // @phpstan-ignore-next-line
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
