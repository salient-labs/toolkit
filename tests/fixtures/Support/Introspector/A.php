<?php declare(strict_types=1);

namespace Lkrms\Tests\Support\Introspector;

use Lkrms\Concern\HasNormaliser;
use Lkrms\Concern\TExtensible;
use Lkrms\Concern\TFullyReadable;
use Lkrms\Concern\TWritable;
use Lkrms\Contract\IExtensible;
use Lkrms\Contract\IReadable;
use Lkrms\Contract\IWritable;
use Lkrms\Contract\ReturnsNormaliser;

class A implements IReadable, IWritable, IExtensible, ReturnsNormaliser
{
    use TFullyReadable, TWritable, TExtensible, HasNormaliser;

    /**
     * @inheritDoc
     */
    public static function getWritable(): array
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
