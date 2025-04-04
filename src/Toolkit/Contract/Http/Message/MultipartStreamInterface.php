<?php declare(strict_types=1);

namespace Salient\Contract\Http\Message;

use Salient\Contract\Http\HasHeader;

interface MultipartStreamInterface extends StreamInterface, HasHeader
{
    /**
     * Get the stream's encapsulation boundary
     */
    public function getBoundary(): string;
}
