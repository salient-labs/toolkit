<?php declare(strict_types=1);

namespace Salient\Tests\Container;

class IdGenerator
{
    /**
     * @var array<string,int>
     */
    private array $Counters = [];

    public function getNext(string $type): int
    {
        $this->Counters[$type] ??= 100 * (count($this->Counters) + 1);

        return $this->Counters[$type]++;
    }
}
