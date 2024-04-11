<?php declare(strict_types=1);

namespace Salient\Contract\Http;

use Psr\Http\Message\StreamInterface;

/**
 * @api
 */
interface HttpStreamPartInterface
{
    /**
     * Get the field name of the part
     */
    public function getName(): string;

    /**
     * Get the filename of the part
     */
    public function getFilename(): ?string;

    /**
     * Get the ASCII fallback filename of the part
     */
    public function getFallbackFilename(): ?string;

    /**
     * Get the media type of the part
     */
    public function getMediaType(): ?string;

    /**
     * Get the content of the part
     */
    public function getContent(): StreamInterface;
}
