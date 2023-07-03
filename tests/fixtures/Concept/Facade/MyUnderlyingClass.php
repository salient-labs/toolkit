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
     */
    private $Facade;

    public function setFacade(string $name)
    {
        $this->Facade = $name;

        return $this;
    }

    public function checkFuncNumArgs(int &$numArgs = null, string $format = '', &...$values): string
    {
        !is_null($this->LastFuncNumArgs =
            $this->Facade
                ? $this->Facade::getFuncNumArgs(__FUNCTION__)
                : null) ||
            $this->LastFuncNumArgs = func_num_args();

        $numArgs = $this->LastFuncNumArgs;
        if ($format) {
            return sprintf($format, ...$values);
        }

        return sprintf('%s called with %d argument(s)', __METHOD__, $numArgs);
    }
}
