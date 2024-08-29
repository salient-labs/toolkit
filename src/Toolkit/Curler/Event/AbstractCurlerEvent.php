<?php declare(strict_types=1);

namespace Salient\Curler\Event;

use Salient\Contract\Curler\Event\CurlerEventInterface;
use Salient\Contract\Curler\CurlerInterface;

/**
 * @internal
 */
abstract class AbstractCurlerEvent implements CurlerEventInterface
{
    protected CurlerInterface $Curler;

    public function __construct(CurlerInterface $curler)
    {
        $this->Curler = $curler;
    }

    public function getCurler(): CurlerInterface
    {
        return $this->Curler;
    }
}
