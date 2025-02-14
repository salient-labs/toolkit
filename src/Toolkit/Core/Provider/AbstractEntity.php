<?php declare(strict_types=1);

namespace Salient\Core\Provider;

use Salient\Contract\Core\Entity\ProviderEntityInterface;
use Salient\Core\Concern\ConstructibleTrait;
use Salient\Core\Concern\ExtensibleTrait;
use Salient\Core\Concern\NormalisableTrait;
use Salient\Core\Concern\ProvidableTrait;
use Salient\Core\Concern\ReadableTrait;
use Salient\Core\Concern\WritableTrait;

/**
 * @api
 *
 * @implements ProviderEntityInterface<AbstractProvider,ProviderContext<AbstractProvider,self>>
 */
abstract class AbstractEntity implements ProviderEntityInterface
{
    use ConstructibleTrait;
    use ExtensibleTrait;
    use NormalisableTrait;
    use ReadableTrait;
    use WritableTrait;
    /** @use ProvidableTrait<AbstractProvider,ProviderContext<AbstractProvider,self>> */
    use ProvidableTrait;
}
