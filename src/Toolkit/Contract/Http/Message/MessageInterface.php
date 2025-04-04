<?php declare(strict_types=1);

namespace Salient\Contract\Http\Message;

use Psr\Http\Message\MessageInterface as PsrMessageInterface;
use Psr\Http\Message\RequestInterface as PsrRequestInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Salient\Contract\Core\Immutable;
use Salient\Contract\Http\Exception\InvalidHeaderException;
use Salient\Contract\Http\HasHeader;
use Salient\Contract\Http\HasInnerHeaders;
use Salient\Contract\Http\HasMediaType;
use Salient\Contract\Http\HeadersInterface;
use InvalidArgumentException;
use JsonSerializable;
use Stringable;

interface MessageInterface extends
    PsrMessageInterface,
    HasInnerHeaders,
    Stringable,
    JsonSerializable,
    Immutable,
    HasHeader,
    HasMediaType
{
    /**
     * Get an instance of the class from a compatible PSR-7 message
     *
     * @template T of PsrMessageInterface
     *
     * @param T $message
     * @return T&MessageInterface
     * @throws InvalidArgumentException if the class cannot be instantiated from
     * `$message`, e.g. if the class implements {@see PsrRequestInterface} and
     * `$message` is a {@see PsrResponseInterface}.
     */
    public static function fromPsr7(PsrMessageInterface $message): MessageInterface;

    /**
     * Get message headers
     */
    public function getInnerHeaders(): HeadersInterface;

    /**
     * Get an array that maps lowercase message header names to comma-separated
     * values
     *
     * @return array<string,string>
     */
    public function getHeaderLines(): array;

    /**
     * Get the value of a message header as a list of values, splitting any
     * comma-separated values
     *
     * @return string[]
     */
    public function getHeaderValues(string $name): array;

    /**
     * Get the first value of a message header after splitting any
     * comma-separated values
     */
    public function getFirstHeaderValue(string $name): string;

    /**
     * Get the last value of a message header after splitting any
     * comma-separated values
     */
    public function getLastHeaderValue(string $name): string;

    /**
     * Get the only value of a message header after splitting any
     * comma-separated values
     *
     * @throws InvalidHeaderException if the header has more than one value.
     */
    public function getOnlyHeaderValue(string $name, bool $orSame = false): string;

    /**
     * Get the message as an HTTP payload
     */
    public function getHttpPayload(bool $withoutBody = false): string;

    /**
     * Get the message as an HTTP Archive (HAR) object
     *
     * @return array{httpVersion:string,cookies:array<array{name:string,value:string,path?:string,domain?:string,expires?:string,httpOnly?:bool,secure?:bool}>,headers:array<array{name:string,value:string}>,headersSize:int,bodySize:int}
     */
    public function jsonSerialize(): array;
}
