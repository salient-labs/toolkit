<?php declare(strict_types=1);

namespace Salient\Contract\Http\Message;

interface HttpMultipartStreamInterface extends HttpStreamInterface
{
    /**
     * Get the stream's encapsulation boundary
     */
    public function getBoundary(): string;
}
