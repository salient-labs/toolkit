<?php declare(strict_types=1);

namespace Salient\Tests\Sync;

use Salient\Contract\Sync\SyncClassResolverInterface;
use Salient\Core\Utility\Pcre;

class SyncClassResolver implements SyncClassResolverInterface
{
    public function entityToProvider(string $entity): string
    {
        return Pcre::replace(
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
            "\\$entity"
        );
    }

    public function providerToEntity(string $provider): array
    {
        return [
            Pcre::replace(
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
