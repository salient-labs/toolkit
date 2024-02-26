<?php declare(strict_types=1);

namespace Salient\Tests\Sync;

use Salient\Core\Utility\Pcre;
use Salient\Sync\Contract\SyncClassResolverInterface;

class SyncClassResolver implements SyncClassResolverInterface
{
    public static function entityToProvider(string $entity): string
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

    public static function providerToEntity(string $provider): array
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
