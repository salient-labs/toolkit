<?php declare(strict_types=1);

namespace Salient\Tests\Core\EventDispatcher;

use Salient\Contract\Core\Nameable;

class NamedEvent extends BaseEvent implements Nameable
{
    protected string $Name;

    public function __construct(string $name)
    {
        $this->Name = $name;
    }

    public function name(): string
    {
        return $this->Name;
    }
}
