<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync;

use Lkrms\Sync\Contract\ISyncClassResolver;

class SyncClassResolver implements ISyncClassResolver
{
    public static function entityToProvider(string $entity): string
    {
        return preg_replace(
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
            preg_replace(
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
