<?php declare(strict_types=1);

namespace Salient\Tests\Core\Introspector;

use Salient\Contract\Core\Entity\Extensible;
use Salient\Contract\Core\Entity\Normalisable;
use Salient\Contract\Core\Entity\Readable;
use Salient\Contract\Core\Entity\Writable;
use Salient\Core\Concern\ExtensibleTrait;
use Salient\Core\Concern\HasNormaliser;
use Salient\Core\Concern\HasWritableProperties;
use Salient\Core\Concern\ReadsProtectedProperties;

class A implements Readable, Writable, Extensible, Normalisable
{
    use ReadsProtectedProperties;
    use HasWritableProperties;
    use ExtensibleTrait {
        ExtensibleTrait::__set insteadof HasWritableProperties;
        ExtensibleTrait::__get insteadof ReadsProtectedProperties;
        ExtensibleTrait::__isset insteadof ReadsProtectedProperties;
        ExtensibleTrait::__unset insteadof HasWritableProperties;
    }
    use HasNormaliser;

    /**
     * @inheritDoc
     */
    public static function getWritableProperties(): array
    {
        return [
            'Id',
            'Name',
        ];
    }

    /** @var int|null */
    protected $Id;
    /** @var string|null */
    protected $Name;
    /** @var string */
    protected $NotWritable = 'read-only';
}
