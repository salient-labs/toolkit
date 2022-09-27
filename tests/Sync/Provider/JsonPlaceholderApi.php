<?php

declare(strict_types=1);

namespace Lkrms\Tests\Sync\Provider;

use Lkrms\Curler\CurlerHeaders;
use Lkrms\Support\DateFormatter;
use Lkrms\Sync\Provider\HttpSyncProvider;
use Lkrms\Sync\Support\HttpSyncDefinitionBuilder;
use Lkrms\Sync\SyncOperation as OP;
use Lkrms\Tests\Sync\Entity\Post;
use Lkrms\Tests\Sync\Entity\PostProvider;
use Lkrms\Tests\Sync\Entity\User;
use Lkrms\Tests\Sync\Entity\UserProvider;

class JsonPlaceholderApi extends HttpSyncProvider implements PostProvider, UserProvider
{
    private const JSON_PLACEHOLDER_BASE_URL = "https://jsonplaceholder.typicode.com";

    protected function getBaseUrl(?string $path): string
    {
        return self::JSON_PLACEHOLDER_BASE_URL;
    }

    protected function getCurlerHeaders(?string $path): ?CurlerHeaders
    {
        return null;
    }

    protected function _getBackendIdentifier(): array
    {
        return [self::JSON_PLACEHOLDER_BASE_URL];
    }

    protected function _getDateFormatter(): DateFormatter
    {
        return new DateFormatter();
    }

    protected function getCurlerCacheExpiry(?string $path): ?int
    {
        return 24 * 60 * 60;
    }

    public static function getBindings(): array
    {
        return [
            Post::class => \Lkrms\Tests\Sync\CustomEntity\Post::class,
            User::class => \Lkrms\Tests\Sync\CustomEntity\User::class,
        ];
    }

    protected function getHttpDefinition(string $entity, HttpSyncDefinitionBuilder $define)
    {
        switch ($entity)
        {
            case Post::class:
                return $define->operations([OP::READ, OP::READ_LIST])
                    ->path("/posts");

            case User::class:
                return $define->operations([OP::READ, OP::READ_LIST])
                    ->path("/users");
        }

        return null;
    }

    public function getPosts(): iterable
    {
        $filter   = $this->getListFilter(func_get_args());
        if ($user = $filter["user"] ?? null)
        {
            return Post::provideList($this->getCurler("/users/$user/posts")->getJson(), $this);
        }
        return Post::provideList($this->getCurler("/posts")->getJson(), $this);
    }

}
