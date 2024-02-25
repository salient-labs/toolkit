<?php declare(strict_types=1);

namespace Lkrms\Tests\Support\Introspector;

use Lkrms\Concern\HasNormaliser;
use Lkrms\Concern\TExtensible;
use Salient\Core\Concern\HasWritableProperties;
use Salient\Core\Concern\ReadsProtectedProperties;
use Salient\Core\Contract\Extensible;
use Salient\Core\Contract\NormaliserFactory;
use Salient\Core\Contract\Readable;
use Salient\Core\Contract\Writable;

class A implements Readable, Writable, Extensible, NormaliserFactory
{
    use ReadsProtectedProperties, HasWritableProperties, TExtensible, HasNormaliser;

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
