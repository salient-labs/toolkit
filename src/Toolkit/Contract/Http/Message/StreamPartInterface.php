<?php declare(strict_types=1);

namespace Salient\Contract\Http\Message;

use Psr\Http\Message\StreamInterface as PsrStreamInterface;
use Salient\Contract\Core\Immutable;
use Salient\Contract\Http\HasMediaType;

/**
 * @api
 */
interface StreamPartInterface extends Immutable, HasMediaType
{
    /**
     * Get the field name of the part
     */
    public function getName(): string;

    /**
     * Get an instance with the given field name
     *
     * @return static
     */
    public function withName(string $name): StreamPartInterface;

    /**
     * Get the filename of the part
     */
    public function getFilename(): ?string;

    /**
     * Get the ASCII filename of the part
     */
    public function getAsciiFilename(): ?string;

    /**
     * Get the media type of the part
     */
    public function getMediaType(): ?string;

    /**
     * Get the body of the part
     */
    public function getBody(): PsrStreamInterface;
}
