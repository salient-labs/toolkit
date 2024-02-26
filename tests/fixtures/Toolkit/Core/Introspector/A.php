<?php declare(strict_types=1);

namespace Salient\Tests\Core\Introspector;

use Salient\Core\Concern\ExtensibleTrait;
use Salient\Core\Concern\HasNormaliser;
use Salient\Core\Concern\HasWritableProperties;
use Salient\Core\Concern\ReadsProtectedProperties;
use Salient\Core\Contract\Extensible;
use Salient\Core\Contract\Normalisable;
use Salient\Core\Contract\NormaliserFactory;
use Salient\Core\Contract\Readable;
use Salient\Core\Contract\Writable;

class A implements Readable, Writable, Extensible, Normalisable, NormaliserFactory
{
    use ReadsProtectedProperties, HasWritableProperties, ExtensibleTrait, HasNormaliser;

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

    /**
     * @var int|null
     */
    protected $Id;

    /**
     * @var string|null
     */
    protected $Name;

    /**
     * @var string
     */
    protected $NotWritable = 'read-only';
}
