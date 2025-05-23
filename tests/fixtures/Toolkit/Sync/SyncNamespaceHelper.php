<?php declare(strict_types=1);

namespace Salient\Tests\Sync;

use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncNamespaceHelperInterface;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Utility\Regex;

class SyncNamespaceHelper implements SyncNamespaceHelperInterface
{
    public function getEntityTypeProvider(string $entityType): string
    {
        /** @var class-string<SyncProviderInterface> */
        return Regex::replace(
            [
                '/(?<=\\\\)Entity(?=\\\\)/i',
                '/(?<=\\\\)([^\\\\]+)$/',
                '/^\\\\+/',
            ],
            [
                'Contract',
                'Provides$1',
                '',
            ],
            "\\$entityType"
        );
    }

    public function getProviderEntityTypes(string $provider): array
    {
        /** @var array<class-string<SyncEntityInterface>> */
        return [
            Regex::replace(
                [
                    '/(?<=\\\\)Contract(?=\\\\)/i',
                    '/(?<=\\\\)Provides([^\\\\]+)$/',
                    '/^\\\\+/',
                ],
                [
                    'Entity',
                    '$1',
                    '',
                ],
                "\\$provider"
            ),
        ];
    }
}
