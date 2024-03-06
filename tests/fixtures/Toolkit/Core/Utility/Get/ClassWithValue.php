<?php declare(strict_types=1);

namespace Salient\Tests\Core\Utility\Get;

class ClassWithValue
{
    /**
     * @var mixed
     */
    public $Value;

    /**
     * @param mixed $value
     */
    public function __construct($value)
    {
        $this->Value = $value;
    }
}
