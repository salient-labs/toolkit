<?php declare(strict_types=1);

namespace Salient\Tests\Core\Introspector;

use Salient\Contract\Core\Entity\Extensible;
use Salient\Contract\Core\Entity\Normalisable;
use Salient\Contract\Core\Entity\Readable;
use Salient\Contract\Core\Entity\Writable;
use Salient\Core\Concern\ExtensibleTrait;
use Salient\Core\Concern\NormalisableTrait;
use Salient\Core\Concern\ReadableProtectedPropertiesTrait;
use Salient\Core\Concern\WritableTrait;

class A implements Readable, Writable, Extensible, Normalisable
{
    use ReadableProtectedPropertiesTrait;
    use WritableTrait;
    use ExtensibleTrait;
    use NormalisableTrait;

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
