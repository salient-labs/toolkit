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
use JsonSerializable;
use LogicException;
use Traversable;

/**
 * @internal
 *
 * @template T of object
 */
final class FormDataEncoder implements HasFormDataFlag
{
    /** @var int-mask-of<FormDataEncoder::*> */
    private int $Flags;
    private DateFormatterInterface $DateFormatter;
    /** @var (Closure(object): (T|mixed[]|string|false|null))|null */
    private ?Closure $Callback;

    /**
     * @param int-mask-of<FormDataEncoder::*> $flags
     * @param (Closure(object): (T|mixed[]|string|false|null))|null $callback
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
     * Get data as a list of key-value pairs
     *
     * @param mixed[]|object $data
     * @return list<array{string,T|string}>
     */
    public function getValues($data): array
    {
        return $this->doGetValues($data);
    }

    /**
     * Get data as a URL-encoded query string
     *
     * @param mixed[]|object $data
     */
    public function getQuery($data): string
    {
        foreach ($this->doGetValues($data) as [$key, $value]) {
            if (!is_string($value)) {
                throw new LogicException(sprintf(
                    'Value must be of type string, %s given: %s',
                    Get::type($value),
                    $key,
                ));
            }
            $query[] = rawurlencode($key) . '=' . rawurlencode($value);
        }
        return implode('&', $query ?? []);
    }

    /**
     * Get data as nested arrays of values
     *
     * Similar to {@see getValues()}, but scalars are not cast to `string` and
     * data structures are not flattened.
     *
     * @param mixed[]|object $data
     * @return mixed[]
     */
    public function getData($data): array
    {
        return $this->doGetData($data);
    }

    /**
     * @param mixed[]|object|int|float|string|bool|null $data
     * @param list<array{string,T|string}> $query
     * @return list<array{string,T|string}>
     */
    private function doGetValues(
        $data,
        bool $isNested = false,
        array &$query = [],
        string $name = ''
    ): array {
        if (!$isNested) {
            $data = $this->flattenValue($data);
        }

        // If `$isNested` is `true`, `$data` has already been flattened
        /** @var T|mixed[]|string|null $data */
        if ($data === null || $data === []) {
            return $query;
        }

        if (is_array($data)) {
            $hasArray = false;
            foreach ($data as $key => $value) {
                $flattened[$key] = $value = $this->flattenValue($value);
                $hasArray = $hasArray || is_array($value);
            }
            $data = $flattened;
            $format = $this->getKeyFormat($data, true, $isNested, $hasArray);
            foreach ($data as $key => $value) {
                $nextKey = sprintf($format, $key);
                $this->doGetValues($value, true, $query, $name . $nextKey);
            }
            return $query;
        }

        $query[] = [$name, $data];
        return $query;
    }

    /**
     * @param mixed[]|object|int|float|string|bool|null $data
     * @param mixed[]|null $query
     * @phpstan-param ($isNested is false ? mixed[] : mixed[]|null) $query
     * @param-out ($isNested is false ? mixed[] : T|mixed[]|int|float|string|bool|null) $query
     * @return ($isNested is false ? mixed[] : T|mixed[]|int|float|string|bool|null)
     */
    private function doGetData(
        $data,
        bool $isNested = false,
        bool $fromCallback = false,
        &$query = []
    ) {
        if (!$isNested) {
            $data = $this->processValue($data, $fromCallback);
        }

        // If `$isNested` is `true`, `$data` has already been processed
        /** @var T|mixed[]|int|float|string|bool|null $data */
        if ($fromCallback && $data === null) {
            return $query;
        }

        if (is_array($data)) {
            $hasArray = false;
            $fromCallback = [];
            foreach ($data as $key => $value) {
                $processed[$key] = $value = $this->processValue($value, $fromCallback[$key]);
            }
            $data = $processed ?? [];
            $format = $this->getKeyFormat($data, false, $isNested, $hasArray);
            foreach ($data as $key => $value) {
                $nextKey = sprintf($format, $key);
                if ($nextKey === '') {
                    $query[] = null;
                    $nextKey = array_key_last($query);
                }
                $this->doGetData($value, true, $fromCallback[$key], $query[$nextKey]);
            }
            return $query;
        }

        if (!$isNested) {
            throw new LogicException(sprintf(
                'Data must be of type array, %s given',
                Get::type($data),
            ));
        }

        $query = $data;
        return $query;
    }

    /**
     * @param mixed[] $data
     */
    private function getKeyFormat(
        array $data,
        bool $flatten,
        bool $isNested,
        bool $hasArray
    ): string {
        $preserveKeys = !$isNested || $hasArray || (
            Arr::isList($data)
                ? $this->Flags & self::DATA_PRESERVE_LIST_KEYS
                : (Arr::hasNumericKeys($data)
                    ? $this->Flags & self::DATA_PRESERVE_NUMERIC_KEYS
                    : $this->Flags & self::DATA_PRESERVE_STRING_KEYS)
        );

        return $preserveKeys
            ? ($flatten && $isNested ? '[%s]' : '%s')
            : ($flatten ? '[]' : '');
    }

    /**
     * @param mixed $value
     * @return T|mixed[]|string|null
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
            $value = $this->processObject($value, $fromCallback);
            if (!$fromCallback && ($value === null || is_scalar($value))) {
                return (string) $value;
            }
            /** @var T|mixed[]|string|null */
            return $value;
        }

        throw new LogicException(sprintf(
            'Value must be of type mixed[]|object|int|float|string|bool|null, %s given',
            Get::type($value),
        ));
    }

    /**
     * @param mixed $value
     * @param-out bool $fromCallback
     * @return T|mixed[]|int|float|string|bool|null
     */
    private function processValue($value, ?bool &$fromCallback)
    {
        $fromCallback = false;

        if ($value === null || is_scalar($value) || is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return $this->processObject($value, $fromCallback);
        }

        throw new LogicException(sprintf(
            'Value must be of type mixed[]|object|int|float|string|bool|null, %s given',
            Get::type($value),
        ));
    }

    /**
     * @param-out bool $fromCallback
     * @return T|mixed[]|int|float|string|bool|null
     */
    private function processObject(object $value, ?bool &$fromCallback)
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

        if (!$value instanceof self) {
            // Get public property values
            $result = [];
            // @phpstan-ignore foreach.nonIterable
            foreach ($value as $key => $val) {
                $result[$key] = $val;
            }
            if ($result) {
                return $result;
            }
        }

        if (Test::isStringable($value)) {
            return (string) $value;
        }

        return [];
    }
}
