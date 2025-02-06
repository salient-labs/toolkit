<?php declare(strict_types=1);

namespace Salient\Tests\Core\Introspector;

use Salient\Contract\Core\Entity\Readable;
use Salient\Core\Concern\ReadableTrait;
use Salient\Utility\Get;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;

class D extends C implements Readable
{
    use ReadableTrait;

    public DateTimeInterface $Once;
    public DateTimeImmutable $Then;
    public DateTime $Always;
    private string $Uuid;

    protected function _getNow(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }

    protected function _getUuid(): string
    {
        return $this->Uuid ??= Get::uuid();
    }
}
