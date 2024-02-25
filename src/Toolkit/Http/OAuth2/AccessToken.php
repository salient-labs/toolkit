<?php declare(strict_types=1);

namespace Salient\Http\OAuth2;

use Salient\Core\Concern\ReadsProtectedProperties;
use Salient\Core\Contract\Immutable;
use Salient\Core\Contract\Readable;
use Salient\Core\Exception\InvalidArgumentException;
use Salient\Core\Utility\Date;
use Salient\Http\Contract\AccessTokenInterface;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * A token issued by an authorization provider for access to protected resources
 *
 * @property-read string $Token
 * @property-read string $Type
 * @property-read DateTimeImmutable|null $Expires
 * @property-read string[] $Scopes
 * @property-read array<string,mixed> $Claims
 */
final class AccessToken implements AccessTokenInterface, Immutable, Readable
{
    use ReadsProtectedProperties;

    protected string $Token;

    protected string $Type;

    protected ?DateTimeImmutable $Expires;

    /**
     * @var string[]
     */
    protected array $Scopes;

    /**
     * @var array<string,mixed>
     */
    protected array $Claims;

    /**
     * Creates a new AccessToken object
     *
     * @param DateTimeInterface|int|null $expires `null` if the access token's
     * lifetime is unknown, otherwise a {@see DateTimeInterface} or Unix
     * timestamp representing its expiration time.
     * @param string[]|null $scopes
     * @param array<string,mixed>|null $claims
     */
    public function __construct(
        string $token,
        string $type,
        $expires,
        ?array $scopes = null,
        ?array $claims = null
    ) {
        if (is_int($expires) && $expires < 0) {
            throw new InvalidArgumentException(sprintf(
                'Invalid $expires: %d',
                $expires
            ));
        }

        $this->Token = $token;
        $this->Type = $type;
        $this->Expires = $expires instanceof DateTimeInterface
            ? Date::immutable($expires)
            : ($expires === null
                ? null
                : new DateTimeImmutable("@$expires"));
        $this->Scopes = $scopes ?: [];
        $this->Claims = $claims ?: [];
    }

    /**
     * @inheritDoc
     */
    public function getToken(): string
    {
        return $this->Token;
    }

    /**
     * @inheritDoc
     */
    public function getType(): string
    {
        return $this->Type;
    }
}
