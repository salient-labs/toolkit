<?php declare(strict_types=1);

namespace Lkrms\Auth;

use DateTimeImmutable;
use DateTimeInterface;
use Lkrms\Concern\TFullyReadable;
use Lkrms\Contract\IReadable;
use Lkrms\Facade\Convert;

/**
 * An immutable access token
 *
 * @property-read string $Token
 * @property-read DateTimeImmutable $Expires
 * @property-read array<string,mixed> $Claims
 * @property-read string|null $Type
 */
final class AccessToken implements IReadable
{
    use TFullyReadable;

    /**
     * @var string
     */
    protected $Token;

    /**
     * @var DateTimeImmutable
     */
    protected $Expires;

    /**
     * @var array<string,mixed>
     */
    protected $Claims;

    /**
     * @var string|null
     */
    protected $Type;

    /**
     * @param DateTimeInterface|int $expires
     * @param array<string,mixed> $claims
     */
    public function __construct(string $token, $expires, array $claims = [], ?string $type = null)
    {
        $this->Token   = $token;
        $this->Expires = $expires instanceof DateTimeInterface
                             ? Convert::toDateTimeImmutable($expires)
                             : ($expires > 0 ? new DateTimeImmutable("@$expires") : 0);
        $this->Claims = $claims;
        $this->Type   = $type;
    }
}
