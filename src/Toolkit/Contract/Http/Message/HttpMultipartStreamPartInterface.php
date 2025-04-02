<?php declare(strict_types=1);

namespace Salient\Contract\Http\Message;

use Psr\Http\Message\StreamInterface;
use Salient\Contract\Core\Immutable;

interface HttpMultipartStreamPartInterface extends Immutable
{
    /**
     * Get an instance with the given field name
     */
    public function withName(string $name): HttpMultipartStreamPartInterface;

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
