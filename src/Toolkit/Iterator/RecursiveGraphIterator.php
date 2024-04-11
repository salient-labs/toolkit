<?php declare(strict_types=1);

namespace Salient\Iterator;

use Salient\Iterator\Concern\RecursiveGraphIteratorTrait;
use RecursiveIterator;

/**
 * Iterates over the properties of objects and the elements of arrays,
 * descending into them recursively
 *
 * @api
 *
 * @implements RecursiveIterator<array-key,mixed>
 */
class RecursiveGraphIterator extends GraphIterator implements RecursiveIterator
{
    use RecursiveGraphIteratorTrait;
}
