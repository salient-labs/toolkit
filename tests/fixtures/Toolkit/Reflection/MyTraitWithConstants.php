<?php declare(strict_types=1);

namespace Salient\Tests\Reflection;

/**
 * MyTraitWithConstants
 */
trait MyTraitWithConstants
{
    /**
     * MyTraitWithConstants::MY_CONSTANT
     */
    public const MY_CONSTANT = 'my constant';

    /**
     * MyTraitWithConstants::MY_TRAIT_CONSTANT
     */
    public const MY_TRAIT_CONSTANT = 71;
}
