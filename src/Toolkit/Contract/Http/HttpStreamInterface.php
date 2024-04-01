<?php declare(strict_types=1);

namespace Salient\Contract\Http;

use Psr\Http\Message\StreamInterface;
use Stringable;

/**
 * @api
 */
interface HttpStreamInterface extends
    StreamInterface,
    Stringable
{
    /**
     * Get the media type of the stream, or null if the stream has no implicit
     * media type
     */
    public static function getMediaType(): ?string;
}
