<?php declare(strict_types=1);

namespace Salient\Http;

use Salient\Utility\Date;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * @api
 */
class GenericToken extends GenericCredential
{
    private ?DateTimeImmutable $Expires;

    /**
     * @api
     *
     * @param DateTimeInterface|int|null $expires `null` if the token's lifetime
     * is unknown or unlimited, otherwise a {@see DateTimeInterface} or Unix
     * timestamp representing its expiration time.
     */
    public function __construct(
        string $token,
        string $authenticationScheme,
        $expires = null
    ) {
        if (is_int($expires) && $expires < 0) {
            throw new InvalidArgumentException(
                sprintf('Invalid timestamp: %d', $expires),
            );
        }

        $this->Expires = $expires instanceof DateTimeInterface
            ? Date::immutable($expires)
            : ($expires !== null
                ? new DateTimeImmutable('@' . $expires)
                : null);

        parent::__construct($token, $authenticationScheme);
    }

    /**
     * Get the expiration time of the token, or null if its lifetime is unknown
     * or unlimited
     */
    public function getExpires(): ?DateTimeImmutable
    {
        return $this->Expires;
    }
}
