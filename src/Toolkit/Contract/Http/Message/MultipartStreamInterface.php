<?php declare(strict_types=1);

namespace Salient\Contract\Http\Message;

use Salient\Contract\Http\HasHttpHeader;

interface MultipartStreamInterface extends StreamInterface, HasHttpHeader
{
    /**
     * Get the stream's encapsulation boundary
     */
    public function getBoundary(): string;
}
