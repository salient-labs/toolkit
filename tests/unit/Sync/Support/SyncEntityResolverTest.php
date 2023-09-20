<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Support;

use Lkrms\Container\Application;
use Lkrms\Facade\File;
use Lkrms\Facade\Sync;
use Lkrms\Support\Catalog\RegularExpression as Regex;
use Lkrms\Sync\Support\SyncEntityFuzzyResolver;
use Lkrms\Sync\Support\SyncEntityResolver;
use Lkrms\Tests\Sync\Entity\Post;
use Lkrms\Tests\Sync\Entity\User;
use Lkrms\Tests\Sync\Provider\JsonPlaceholderApi;

final class SyncEntityResolverTest extends \Lkrms\Tests\TestCase
{
    public function testGetByName()
    {
        $basePath = File::createTemporaryDirectory();

        $app = (new Application($basePath))
            ->loadCache()
            ->loadSync(__METHOD__, [])
            ->syncNamespace(
                'lkrms-tests',
                'https://lkrms.github.io/php-util/tests/entity',
                'Lkrms\Tests\Sync\Entity'
            )
            // Register JsonPlaceholderApi as the default Post and User provider
            ->service(JsonPlaceholderApi::class);

        $postProvider = Post::defaultProvider($app);
        $postEntityProvider = $postProvider->with(Post::class);
        $posts = $postEntityProvider->getListA();

        $userEntityProvider = User::withDefaultProvider($app);
        // Ensure deferred user entities are resolved
        $userEntityProvider->getListA();

        $post = reset($posts);
        $user = $userEntityProvider->get($post->User->Id);

        $this->assertSame(1, Sync::getRunId(), 'getRunId()');
        $this->assertMatchesRegularExpression(Regex::anchorAndDelimit(Regex::UUID), Sync::getRunUuid(), 'getRunUuid()');
        $this->assertSame('lkrms-tests:User', Sync::getEntityTypeUri(User::class), 'Sync::getEntityTypeUri()');
        $this->assertSame('https://lkrms.github.io/php-util/tests/entity/User', Sync::getEntityTypeUri(User::class, false), 'Sync::getEntityTypeUri(, false)');
        $this->assertSame('Sincere@april.biz', $user->Email);

        $resolvers = [
            'not fuzzy' => new SyncEntityResolver($userEntityProvider, 'Name'),
            'Levenshtein' => new SyncEntityFuzzyResolver($userEntityProvider, 'Name', null, SyncEntityFuzzyResolver::ALGORITHM_LEVENSHTEIN, 0.6),
            'similar text' => new SyncEntityFuzzyResolver($userEntityProvider, 'Name', null, SyncEntityFuzzyResolver::ALGORITHM_SIMILAR_TEXT, 0.6),
        ];

        $names = [
            'Leanne Graham',
            'leanne graham',
            'GRAHAM, leanne',
            'Lee-Anna Graham',
            'leanne graham',
            'GRAHAM, leanne',
            'Lee-Anna Graham',
        ];

        $output = [];

        foreach ($resolvers as $group => $resolver) {
            foreach ($names as $name) {
                if ($resolver instanceof SyncEntityFuzzyResolver) {
                    $uncertainty = null;
                    /** @var User|null */
                    $user = $resolver->getByName($name, $uncertainty);
                    $output[$group][] = [$user->Name ?? null, $uncertainty];
                    continue;
                }
                $user = $resolver->getByName($name);
                $output[$group][] = [$user->Name ?? null];
            }
        }

        $this->assertSame([
            'not fuzzy' => [
                [
                    'Leanne Graham',
                ],
                [
                    null,
                ],
                [
                    null,
                ],
                [
                    null,
                ],
                [
                    null,
                ],
                [
                    null,
                ],
                [
                    null,
                ],
            ],
            'Levenshtein' => [
                [
                    'Leanne Graham',
                    0.0,
                ],
                [
                    'Leanne Graham',
                    0.0,
                ],
                [
                    null,
                    null,
                ],
                [
                    'Leanne Graham',
                    0.2,
                ],
                [
                    'Leanne Graham',
                    0.0,
                ],
                [
                    null,
                    null,
                ],
                [
                    'Leanne Graham',
                    0.2,
                ],
            ],
            'similar text' => [
                [
                    'Leanne Graham',
                    0.0,
                ],
                [
                    'Leanne Graham',
                    0.0,
                ],
                [
                    'Leanne Graham',
                    0.5384615384615384,
                ],
                [
                    'Leanne Graham',
                    0.19999999999999996,
                ],
                [
                    'Leanne Graham',
                    0.0,
                ],
                [
                    'Leanne Graham',
                    0.5384615384615384,
                ],
                [
                    'Leanne Graham',
                    0.19999999999999996,
                ],
            ]
        ], $output);

        $app->unloadSync(true)
            ->unload();

        File::pruneDirectory($basePath);
        rmdir($basePath);
    }
}
