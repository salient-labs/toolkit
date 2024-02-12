<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility\Get;

class RefClass
{
    /**
     * @var mixed
     */
    public $BindTo;

    /**
     * @param mixed $bindTo
     */
    public function __construct(&$bindTo)
    {
        $this->BindTo = &$bindTo;
    }

    /**
     * @param mixed $value
     */
    public function apply($value): void
    {
        $this->BindTo = $value;
    }
}
