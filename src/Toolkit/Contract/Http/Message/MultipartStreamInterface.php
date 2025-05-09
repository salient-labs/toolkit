<?php declare(strict_types=1);

namespace Salient\Contract\Http\Message;

use Salient\Contract\Http\HasHttpHeader;

/**
 * @api
 */
interface MultipartStreamInterface extends StreamInterface, HasHttpHeader
{
    /**
     * Get an array that contains each of the stream's parts
     *
     * @return StreamPartInterface[]
     */
    public function getParts(): array;

    /**
     * Get the encapsulation boundary of the stream
     */
    public function getBoundary(): string;
}
