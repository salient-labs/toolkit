<?php declare(strict_types=1);

namespace Salient\Tests\Sync\Support;

use Salient\Contract\Sync\Exception\InvalidFilterExceptionInterface;
use Salient\Contract\Sync\Exception\InvalidFilterSignatureExceptionInterface;
use Salient\Contract\Sync\SyncOperation;
use Salient\Sync\Support\SyncContext;
use Salient\Tests\Sync\Entity\Post;
use Salient\Tests\Sync\Entity\User;
use Salient\Tests\Sync\SyncTestCase;
use Salient\Utility\Arr;
use Closure;

/**
 * @covers \Salient\Sync\Support\SyncContext
 */
final class SyncContextTest extends SyncTestCase
{
    public function testOffline(): void
    {
        $context = $this->Provider->getContext();

        $this->assertNull($context->getOffline());
        $this->assertSame($context, $context->withOffline(null));
        $this->assertNull($context->getOffline());

        $this->assertNotSame($context, $context2 = $context->withOffline(false));
        $this->assertFalse($context2->getOffline());
        $this->assertSame($context2, $context2->withOffline(false));
        $this->assertFalse($context2->getOffline());

        $this->assertNotSame($context2, $context3 = $context2->withOffline(true));
        $this->assertTrue($context3->getOffline());
        $this->assertSame($context3, $context3->withOffline(true));
        $this->assertTrue($context3->getOffline());
    }

    public function testWithArgsWithoutArgs(): void
    {
        $context = $this->Provider->getContext();

        $context = $context->withOperation(SyncOperation::READ_LIST, User::class);
        $this->assertFalse($context->hasFilter());
        $this->assertSame($context, $context->withOperation(SyncOperation::READ_LIST, User::class));

        $context = $context->withOperation(SyncOperation::READ, User::class, 1);
        $this->assertFalse($context->hasFilter());
        $this->assertSame($context, $context->withOperation(SyncOperation::READ, User::class, 1));
    }

    /**
     * @dataProvider withArgsProvider
     *
     * @param array<string,mixed>|string $expected
     * @param array<string,string>|null $expectedFilterKeys
     * @param mixed ...$args
     */
    public function testWithArgs($expected, ?array $expectedFilterKeys, ...$args): void
    {
        $this->maybeExpectException($expected);

        if (count($args) === 1 && $args[0] instanceof Closure) {
            $args = Arr::wrap($args[0]($this));
        }

        $context = $this->Provider->getContext();
        $context2 = $context->withOperation(SyncOperation::READ_LIST, User::class, ...$args);

        $this->assertNotSame($context, $context2);
        $this->assertSame($expected, $context2->getFilters());
        if ($expectedFilterKeys !== null && $context2 instanceof SyncContext) {
            $this->assertSame($expectedFilterKeys, $this->getFilterKeys($context2));
        }
    }

    /**
     * @return array<array{array<string,mixed>|string,array<string,string>|null,...}>
     */
    public static function withArgsProvider(): array
    {
        $user = fn(self $test) => $test->Provider->with(User::class)->get(1);
        $post1 = fn(self $test) => $test->Provider->with(Post::class)->get(11);
        $post2 = fn(self $test) => $test->Provider->with(Post::class)->get(21);

        $exception = sprintf(
            '%s,%s',
            InvalidFilterSignatureExceptionInterface::class,
            'Invalid filter signature for SyncOperation::READ_LIST',
        );

        return [
            'array' => [
                [
                    '__' => __DIR__,
                    '__meta' => null,
                    '_null_ok_' => false,
                    '$top' => 100,
                    'first_name' => 'john',
                    'last_name' => 'smith',
                    'org_unit' => [42, 71],
                ],
                [
                    'first_name_id' => 'first_name',
                    'last_name_id' => 'last_name',
                    'org_unit_id' => 'org_unit',
                ],
                [
                    '__' => __DIR__,
                    '__meta' => null,
                    '_null_ok_' => false,
                    '$top' => 100,
                    'first name' => 'john',
                    'LAST--NAME' => 'smith',
                    'OrgUnit' => [42, 71],
                ],
            ],
            'array + numeric key' => [
                $exception,
                null,
                [null],
            ],
            'array + empty key' => [
                ['' => null],
                [],
                ['' => null],
            ],
            'array + whitespace key' => [
                ['' => null],
                [],
                [' ' => null],
            ],
            'array + numeric string key' => [
                $exception,
                null,
                [' 0 ' => null],
            ],
            'int' => [
                ['id' => [42]],
                [],
                42,
            ],
            'string' => [
                ['id' => ['foo']],
                [],
                'foo',
            ],
            'list of int' => [
                ['id' => [42, 71]],
                [],
                42,
                71,
            ],
            'list of string' => [
                ['id' => [
                    'foo',
                    'bar',
                ]],
                [],
                'foo',
                'bar',
            ],
            'list of int and string' => [
                $exception,
                null,
                42,
                'foo',
            ],
            'entity' => [
                ['user' => [1]],
                ['user_id' => 'user'],
                $user,
            ],
            'list of entity' => [
                ['post' => [11, 21]],
                ['post_id' => 'post'],
                fn(self $test) => [$post1($test), $post2($test)],
            ],
            'list of multiple entity types' => [
                ['post' => [11, 21], 'user' => [1]],
                ['post_id' => 'post', 'user_id' => 'user'],
                fn(self $test) => [$post1($test), $post2($test), $user($test)],
            ],
            'invalid entity' => [
                sprintf(
                    '%s,%s has no identifier',
                    InvalidFilterExceptionInterface::class,
                    User::class,
                ),
                null,
                new User(),
            ],
        ];
    }

    public function testClaimFilterWithIdKey(): void
    {
        // Claim under original key
        $context = $this->Provider->getContext()->withOperation(SyncOperation::READ_LIST, User::class, ['user' => 2]);
        $this->assertSame(['user' => 2], $context->getFilters());
        $this->assertSame(2, $context->getFilter('user_id'));
        $this->assertSame(2, $context->claimFilter('user'));
        $this->assertNull($context->getFilter('user_id'));
        $this->assertNull($context->getFilter('user'));
        $this->assertSame([], $context->getFilters());

        // Claim under alternate key ('_id' removed)
        $context = $this->Provider->getContext()->withOperation(SyncOperation::READ_LIST, User::class, ['user_id' => 2]);
        $this->assertSame(['user_id' => 2], $context->getFilters());
        $this->assertSame(2, $context->getFilter('user_id'));
        $this->assertSame(2, $context->claimFilter('user'));
        $this->assertNull($context->getFilter('user_id'));
        $this->assertNull($context->getFilter('user'));
        $this->assertSame([], $context->getFilters());

        // Claim under alternate key ('_id' added)
        $context = $this->Provider->getContext()->withOperation(SyncOperation::READ_LIST, User::class, ['user' => 2]);
        $this->assertSame(['user' => 2], $context->getFilters());
        $this->assertSame(2, $context->getFilter('user'));
        $this->assertSame(2, $context->claimFilter('user_id'));
        $this->assertNull($context->getFilter('user'));
        $this->assertNull($context->getFilter('user_id'));
        $this->assertSame([], $context->getFilters());

        // Original and alternate keys both used ('_id' second)
        $context = $this->Provider->getContext()->withOperation(SyncOperation::READ_LIST, User::class, ['user' => 2, 'user_id' => 'foo']);
        $this->assertSame(['user' => 2, 'user_id' => 'foo'], $context->getFilters());
        $this->assertSame(2, $context->getFilter('user'));
        $this->assertSame('foo', $context->claimFilter('user_id'));
        $this->assertSame(2, $context->getFilter('user'));
        $this->assertNull($context->getFilter('user_id'));
        $this->assertSame(['user' => 2], $context->getFilters());
        $this->assertSame(2, $context->claimFilter('user'));
        $this->assertNull($context->getFilter('user'));
        $this->assertSame([], $context->getFilters());

        // Original and alternate keys both used ('_id' first)
        $context = $this->Provider->getContext()->withOperation(SyncOperation::READ_LIST, User::class, ['user_id' => 'foo', 'user' => 2]);
        $this->assertSame(['user_id' => 'foo', 'user' => 2], $context->getFilters());
        $this->assertSame(2, $context->getFilter('user'));
        $this->assertSame('foo', $context->claimFilter('user_id'));
        $this->assertSame(2, $context->getFilter('user'));
        $this->assertNull($context->getFilter('user_id'));
        $this->assertSame(['user' => 2], $context->getFilters());
        $this->assertSame(2, $context->claimFilter('user'));
        $this->assertNull($context->getFilter('user'));
        $this->assertSame([], $context->getFilters());
    }

    /**
     * @dataProvider getFilterProvider
     *
     * @param mixed $expected
     * @param mixed[] $args
     * @param array<string,(int|string|float|bool|null)[]|int|string|float|bool|null> $values
     */
    public function testGetFilter(
        $expected,
        array $args,
        string $key,
        array $values = []
    ): void {
        $context = $this
            ->Provider
            ->getContext()
            ->withOperation(SyncOperation::READ_LIST, User::class, ...$args);

        foreach ($values as $name => $value) {
            $context = $context->withValue($name, $value);
        }

        $this->assertSame($expected, $context->getFilter($key));
    }

    /**
     * @return array<array{mixed,mixed[],string,3?:array<string,(int|string|float|bool|null)[]|int|string|float|bool|null>}>
     */
    public static function getFilterProvider(): array
    {
        return [
            'int' => [
                [42],
                [42],
                'id',
            ],
            'key not set' => [
                null,
                [],
                'id',
            ],
            'key not set + fallback value (int)' => [
                42,
                [],
                'value',
                ['value' => 42],
            ],
            'key not set + fallback value (string)' => [
                'foo',
                [],
                'value',
                ['value' => 'foo'],
            ],
            'scalar input (float)' => [
                3.14,
                [['value' => 3.14]],
                'value',
            ],
            'string' => [
                ['foo'],
                ['foo'],
                'id',
            ],
            'list of int' => [
                [42, 71],
                [42, 71],
                'id',
            ],
            'scalar input (int)' => [
                42,
                [['user_id' => 42]],
                'user_id',
            ],
            'list of string' => [
                ['foo', 'bar'],
                ['foo', 'bar'],
                'id',
            ],
            'scalar input (string)' => [
                'foo',
                [['user_id' => 'foo']],
                'user_id',
            ],
            'list of array-key' => [
                [42, 'foo'],
                [['user_id' => [42, 'foo']]],
                'user_id',
            ],
            'list of mixed' => [
                [42, 'foo', 3.14],
                [['value' => [42, 'foo', 3.14]]],
                'value',
            ],
        ];
    }

    /**
     * @dataProvider claimFilterProvider
     *
     * @param mixed $expected
     * @param mixed[] $args
     * @param array<string,(int|string|float|bool|null)[]|int|string|float|bool|null> $values
     */
    public function testClaimFilter(
        $expected,
        array $args,
        string $key,
        bool $checkClaim = true,
        array $values = []
    ): void {
        $context = $this
            ->Provider
            ->getContext()
            ->withOperation(SyncOperation::READ_LIST, User::class, ...$args);

        foreach ($values as $name => $value) {
            $context = $context->withValue($name, $value);
        }

        $this->assertSame($expected, $context->claimFilter($key));
        if ($checkClaim) {
            $this->assertNull($context->claimFilter($key));
        }
    }

    /**
     * @return array<array{mixed,mixed[],string,3?:bool,4?:array<string,(int|string|float|bool|null)[]|int|string|float|bool|null>}>
     */
    public static function claimFilterProvider(): array
    {
        return [
            'int' => [
                [42],
                [42],
                'id',
            ],
            'key not set' => [
                null,
                [],
                'id',
            ],
            'key not set + fallback value (int)' => [
                42,
                [],
                'value',
                false,
                ['value' => 42],
            ],
            'key not set + fallback value (string)' => [
                'foo',
                [],
                'value',
                false,
                ['value' => 'foo'],
            ],
            'scalar input (float)' => [
                3.14,
                [['value' => 3.14]],
                'value',
            ],
            'string' => [
                ['foo'],
                ['foo'],
                'id',
            ],
            'list of int' => [
                [42, 71],
                [42, 71],
                'id',
            ],
            'scalar input (int)' => [
                42,
                [['user_id' => 42]],
                'user_id',
            ],
            'list of string' => [
                ['foo', 'bar'],
                ['foo', 'bar'],
                'id',
            ],
            'scalar input (string)' => [
                'foo',
                [['user_id' => 'foo']],
                'user_id',
            ],
            'list of array-key' => [
                [42, 'foo'],
                [['user_id' => [42, 'foo']]],
                'user_id',
            ],
            'list of mixed' => [
                [42, 'foo', 3.14],
                [['value' => [42, 'foo', 3.14]]],
                'value',
            ],
        ];
    }

    /**
     * @return array<string,string>
     */
    private function getFilterKeys(SyncContext $context): array
    {
        return (function () {
            /** @var SyncContext $this */
            // @phpstan-ignore property.protected
            return $this->FilterKeys;
        })->bindTo($context, $context)();
    }
}
