<?php declare(strict_types=1);

namespace Salient\Sync\Exception;

use Salient\Contract\Sync\Exception\HeartbeatCheckFailedExceptionInterface;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Utility\Format;
use Salient\Utility\Inflect;

/**
 * @internal
 */
class HeartbeatCheckFailedException extends AbstractSyncException implements HeartbeatCheckFailedExceptionInterface
{
    /** @var SyncProviderInterface[] */
    protected array $Providers;

    public function __construct(SyncProviderInterface ...$providers)
    {
        $this->Providers = $providers;

        parent::__construct(Inflect::format(
            $providers,
            '{{#}} sync {{#:provider}} {{#:is}} unreachable',
        ));
    }

    /**
     * @inheritDoc
     */
    public function getMetadata(): array
    {
        return [
            'Providers' => Format::list(array_map(
                fn($provider) => $this->getProviderName($provider),
                $this->Providers,
            )),
        ];
    }
}
