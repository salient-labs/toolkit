<?php declare(strict_types=1);

namespace Lkrms\Auth;

use Lkrms\Concern\TFullyReadable;
use Lkrms\Contract\IImmutable;
use Lkrms\Contract\IReadable;
use Lkrms\Utility\Date;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * A token issued by an authorization provider for access to protected resources
 *
 * @property-read string $Token
 * @property-read string $Type
 * @property-read DateTimeImmutable|null $Expires
 * @property-read string[] $Scopes
 * @property-read array<string,mixed> $Claims
 */
final class AccessToken implements IReadable, IImmutable
{
    use TFullyReadable;

    /**
     * @var string
     */
    protected $Token;

    /**
     * @var string
     */
    protected $Type;

    /**
     * @var DateTimeImmutable|null
     */
    protected $Expires;

    /**
     * @var string[]
     */
    protected $Scopes;

    /**
     * @var array<string,mixed>
     */
    protected $Claims;

    /**
     * Creates a new AccessToken object
     *
     * @param DateTimeInterface|int|null $expires `null` if the access token's
     * lifetime is unknown, otherwise a {@see DateTimeInterface} or Unix
     * timestamp representing its expiration time.
     * @param string[]|null $scopes
     * @param array<string,mixed>|null $claims
     */
    public function __construct(string $token, string $type, $expires, ?array $scopes = null, ?array $claims = null)
    {
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
}
