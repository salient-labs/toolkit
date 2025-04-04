<?php declare(strict_types=1);

namespace Salient\Contract\Http\Message;

use Psr\Http\Message\MessageInterface as PsrMessageInterface;
use Salient\Contract\Core\Immutable;
use Salient\Contract\Http\HasHttpHeader;
use Salient\Contract\Http\HasInnerHeaders;
use Salient\Contract\Http\HasMediaType;
use Salient\Contract\Http\HeadersInterface;
use JsonSerializable;
use Stringable;

/**
 * @api
 *
 * @template TPsr7 of PsrMessageInterface
 */
interface MessageInterface extends
    PsrMessageInterface,
    HasInnerHeaders,
    Stringable,
    JsonSerializable,
    Immutable,
    HasHttpHeader,
    HasMediaType
{
    /**
     * Get an instance from a PSR-7 message
     *
     * @param TPsr7 $message
     * @return static
     */
    public static function fromPsr7(PsrMessageInterface $message): MessageInterface;

    /**
     * Get message headers
     */
    public function getInnerHeaders(): HeadersInterface;

    /**
     * Get an HTTP payload for the message
     */
    public function __toString(): string;

    /**
     * Get an HTTP Archive (HAR) object for the message
     *
     * @return array{httpVersion:string,cookies:array<array{name:string,value:string,path?:string,domain?:string,expires?:string,httpOnly?:bool,secure?:bool}>,headers:array<array{name:string,value:string}>,headersSize:int,bodySize:int}
     */
    public function jsonSerialize(): array;
}
