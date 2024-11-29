<?php declare(strict_types=1);

namespace Salient\Tests\Collection;

use Salient\Contract\Core\Comparable;

class MyComparableClass implements Comparable
{
    public string $Name;

    public function __construct(string $name)
    {
        $this->Name = $name;
    }

    /**
     * @inheritDoc
     */
    public static function compare($a, $b): int
    {
        return strlen($b->Name) <=> strlen($a->Name);
    }
}
