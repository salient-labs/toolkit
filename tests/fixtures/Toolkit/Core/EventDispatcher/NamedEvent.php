<?php declare(strict_types=1);

namespace Salient\Tests\Core\EventDispatcher;

use Salient\Core\Contract\HasName;

class NamedEvent extends BaseEvent implements HasName
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
