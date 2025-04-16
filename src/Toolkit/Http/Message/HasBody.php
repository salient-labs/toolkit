<?php declare(strict_types=1);

namespace Salient\Http\Message;

use Psr\Http\Message\StreamInterface as PsrStreamInterface;
use Salient\Utility\Exception\InvalidArgumentTypeException;
use InvalidArgumentException;

/**
 * @internal
 */
trait HasBody
{
    /**
     * @param PsrStreamInterface|resource|string|null $body
     */
    private function filterBody($body): PsrStreamInterface
    {
        if ($body instanceof PsrStreamInterface) {
            return $body;
        }

        if (is_string($body) || $body === null) {
            return Stream::fromString((string) $body);
        }

        try {
            return new Stream($body);
        } catch (InvalidArgumentException $ex) {
            throw new InvalidArgumentTypeException(
                1,
                'body',
                PsrStreamInterface::class . '|resource|string|null',
                $body,
            );
        }
    }
}
