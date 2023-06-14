<?php declare(strict_types=1);

namespace Lkrms\Tests\Concept\Facade;

use Lkrms\Contract\ReceivesFacade;

class MyUnderlyingClass implements ReceivesFacade
{
    /**
     * @var int|null
     */
    public $LastFuncNumArgs;

    /**
     * @var string|null
     * @phpstan-ignore-next-line
     */
    private $Facade;

    public function setFacade(string $name)
    {
        $this->Facade = $name;

        return $this;
    }
}
