<?php declare(strict_types=1);

namespace Salient\Tests\Core\Introspector;

use Salient\Contract\Core\Extensible;
use Salient\Contract\Core\Normalisable;
use Salient\Contract\Core\NormaliserFactory;
use Salient\Contract\Core\Readable;
use Salient\Contract\Core\Writable;
use Salient\Core\Concern\ExtensibleTrait;
use Salient\Core\Concern\HasNormaliser;
use Salient\Core\Concern\HasWritableProperties;
use Salient\Core\Concern\ReadsProtectedProperties;

class A implements Readable, Writable, Extensible, Normalisable, NormaliserFactory
{
    use ReadsProtectedProperties;
    use HasWritableProperties;
    use ExtensibleTrait;
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
