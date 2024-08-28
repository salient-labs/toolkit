<?php declare(strict_types=1);

namespace Salient\Tests\Sync\Support;

use Salient\Contract\Sync\Exception\InvalidFilterExceptionInterface;
use Salient\Contract\Sync\Exception\InvalidFilterSignatureExceptionInterface;
use Salient\Contract\Sync\SyncOperation;
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
        $this->assertSame($context, $context->offlineFirst());
        $this->assertNull($context->getOffline());

        $this->assertNotSame($context, $context2 = $context->online());
        $this->assertFalse($context2->getOffline());
        $this->assertSame($context2, $context2->online());
        $this->assertFalse($context2->getOffline());

        $this->assertNotSame($context2, $context3 = $context2->offline());
        $this->assertTrue($context3->getOffline());
        $this->assertSame($context3, $context3->offline());
        $this->assertTrue($context3->getOffline());
    }

    public function testWithFilterWithoutArgs(): void
    {
        $context = $this->Provider->getContext();

        $this->assertSame($context, $context->withFilter(SyncOperation::READ_LIST));
        $this->assertSame($context, $context->withFilter(SyncOperation::READ, 1));
    }

    /**
     * @dataProvider withFilterProvider
     *
     * @param array<string,mixed>|string $expected
     * @param mixed ...$args
     */
    public function testWithFilter($expected, ...$args): void
    {
        $this->maybeExpectException($expected);

        if (count($args) === 1 && $args[0] instanceof Closure) {
            $args = Arr::wrap($args[0]($this));
        }

        $context = $this->Provider->getContext();
        $context2 = $context->withFilter(SyncOperation::READ_LIST, ...$args);

        $this->assertNotSame($context, $context2);
        $this->assertSame($expected, $context2->getFilter());
    }

    /**
     * @return array<array{array<string,mixed>|string,...}>
     */
    public static function withFilterProvider(): array
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
                [null],
            ],
            'array + empty key' => [
                $exception,
                ['' => null],
            ],
            'int' => [
                ['id' => [42]],
                42,
            ],
            'string' => [
                ['id' => ['foo']],
                'foo',
            ],
            'list of int' => [
                ['id' => [42, 71]],
                42,
                71,
            ],
            'list of string' => [
                ['id' => [
                    'foo',
                    'bar',
                ]],
                'foo',
                'bar',
            ],
            'list of int and string' => [
                $exception,
                42,
                'foo',
            ],
            'entity' => [
                ['user' => [1]],
                $user,
            ],
            'list of entity' => [
                ['post' => [11, 21]],
                fn(self $test) => [$post1($test), $post2($test)],
            ],
            'list of multiple entity types' => [
                ['post' => [11, 21], 'user' => [1]],
                fn(self $test) => [$post1($test), $post2($test), $user($test)],
            ],
            'invalid entity' => [
                sprintf(
                    '%s,%s has no identifier',
                    InvalidFilterExceptionInterface::class,
                    User::class,
                ),
                new User(),
            ],
        ];
    }

    public function testClaimFilterWithIdKey(): void
    {
        // Claim under original key
        $context = $this->Provider->getContext()->withFilter(SyncOperation::READ_LIST, ['user' => 2]);
        $this->assertSame(['user' => 2], $context->getFilter());
        $this->assertSame(2, $context->getFilter('user_id'));
        $this->assertSame(2, $context->claimFilter('user'));
        $this->assertNull($context->getFilter('user_id'));
        $this->assertNull($context->getFilter('user'));
        $this->assertSame([], $context->getFilter());

        // Claim under alternate key ('_id' removed)
        $context = $this->Provider->getContext()->withFilter(SyncOperation::READ_LIST, ['user_id' => 2]);
        $this->assertSame(['user_id' => 2], $context->getFilter());
        $this->assertSame(2, $context->getFilter('user_id'));
        $this->assertSame(2, $context->claimFilter('user'));
        $this->assertNull($context->getFilter('user_id'));
        $this->assertNull($context->getFilter('user'));
        $this->assertSame([], $context->getFilter());

        // Claim under alternate key ('_id' added)
        $context = $this->Provider->getContext()->withFilter(SyncOperation::READ_LIST, ['user' => 2]);
        $this->assertSame(['user' => 2], $context->getFilter());
        $this->assertSame(2, $context->getFilter('user'));
        $this->assertSame(2, $context->claimFilter('user_id'));
        $this->assertNull($context->getFilter('user'));
        $this->assertNull($context->getFilter('user_id'));
        $this->assertSame([], $context->getFilter());

        // Original and alternate keys both used ('_id' second)
        $context = $this->Provider->getContext()->withFilter(SyncOperation::READ_LIST, ['user' => 2, 'user_id' => 'foo']);
        $this->assertSame(['user' => 2, 'user_id' => 'foo'], $context->getFilter());
        $this->assertSame(2, $context->getFilter('user'));
        $this->assertSame('foo', $context->claimFilter('user_id'));
        $this->assertSame(2, $context->getFilter('user'));
        $this->assertNull($context->getFilter('user_id'));
        $this->assertSame(['user' => 2], $context->getFilter());
        $this->assertSame(2, $context->claimFilter('user'));
        $this->assertNull($context->getFilter('user'));
        $this->assertSame([], $context->getFilter());

        // Original and alternate keys both used ('_id' first)
        $context = $this->Provider->getContext()->withFilter(SyncOperation::READ_LIST, ['user_id' => 'foo', 'user' => 2]);
        $this->assertSame(['user_id' => 'foo', 'user' => 2], $context->getFilter());
        $this->assertSame(2, $context->getFilter('user'));
        $this->assertSame('foo', $context->claimFilter('user_id'));
        $this->assertSame(2, $context->getFilter('user'));
        $this->assertNull($context->getFilter('user_id'));
        $this->assertSame(['user' => 2], $context->getFilter());
        $this->assertSame(2, $context->claimFilter('user'));
        $this->assertNull($context->getFilter('user'));
        $this->assertSame([], $context->getFilter());
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
        ?string $method,
        ?string $invalidMethod = null,
        array $values = []
    ): void {
        $context = $this
            ->Provider
            ->getContext()
            ->withFilter(SyncOperation::READ_LIST, ...$args);

        foreach ($values as $name => $value) {
            $context = $context->withValue($name, $value);
        }

        if ($method !== null) {
            $this->assertSame($expected, $context->$method($key));
        }

        if ($invalidMethod !== null) {
            $this->expectException(InvalidFilterExceptionInterface::class);
            $this->expectExceptionMessageMatches('/Invalid (filter|context) value/');
            $context->$invalidMethod($key);
        }
    }

    /**
     * @return array<array{mixed,mixed[],string,string|null,4?:string|null,5?:array<string,mixed>}>
     */
    public static function getFilterProvider(): array
    {
        return [
            'int' => [
                42,
                [42],
                'id',
                'getFilterInt',
                'getFilterString',
            ],
            'int (key not set)' => [
                null,
                [],
                'id',
                'getFilterInt',
            ],
            'int (key not set + fallback value)' => [
                42,
                [],
                'value',
                'getFilterInt',
                null,
                ['value' => 42],
            ],
            'int (key not set + invalid fallback value)' => [
                null,
                [],
                'value',
                null,
                'getFilterInt',
                ['value' => 'foo'],
            ],
            'int from float' => [
                null,
                [['value' => 3.14]],
                'value',
                null,
                'getFilterInt',
            ],
            'string' => [
                'foo',
                ['foo'],
                'id',
                'getFilterString',
                'getFilterInt',
            ],
            'array-key (int)' => [
                42,
                [42],
                'id',
                'getFilterArrayKey',
            ],
            'array-key (string)' => [
                'foo',
                ['foo'],
                'id',
                'getFilterArrayKey',
            ],
            'list of int' => [
                [42, 71],
                [42, 71],
                'id',
                'getFilterIntList',
                'getFilterStringList',
            ],
            'list of int (key not set)' => [
                null,
                [],
                'id',
                'getFilterIntList',
            ],
            'list of int + scalar input' => [
                [42],
                [['user_id' => 42]],
                'user_id',
                'getFilterIntList',
            ],
            'list of string' => [
                ['foo', 'bar'],
                ['foo', 'bar'],
                'id',
                'getFilterStringList',
                'getFilterIntList',
            ],
            'list of string + scalar input' => [
                ['foo'],
                [['user_id' => 'foo']],
                'user_id',
                'getFilterStringList',
            ],
            'list of array-key (int)' => [
                [42, 71],
                [42, 71],
                'id',
                'getFilterArrayKeyList',
            ],
            'list of array-key (string)' => [
                ['foo', 'bar'],
                ['foo', 'bar'],
                'id',
                'getFilterArrayKeyList',
            ],
            'list of array-key (mixed)' => [
                [42, 'foo'],
                [['user_id' => [42, 'foo']]],
                'user_id',
                'getFilterArrayKeyList',
            ],
            'list of array-key (mixed + float)' => [
                null,
                [['value' => [42, 'foo', 3.14]]],
                'value',
                null,
                'getFilterArrayKeyList',
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
        ?string $method,
        ?string $invalidMethod = null,
        bool $checkClaim = true,
        array $values = []
    ): void {
        $context = $this
            ->Provider
            ->getContext()
            ->withFilter(SyncOperation::READ_LIST, ...$args);

        foreach ($values as $name => $value) {
            $context = $context->withValue($name, $value);
        }

        if ($method !== null) {
            $this->assertSame($expected, $context->$method($key));
            if ($checkClaim) {
                $this->assertNull($context->$method($key));
            }
        }

        if ($invalidMethod !== null) {
            if ($method !== null) {
                $context = $context->withFilter(SyncOperation::READ_LIST, ...$args);
            }
            $this->expectException(InvalidFilterExceptionInterface::class);
            $this->expectExceptionMessageMatches('/Invalid (filter|context) value/');
            $context->$invalidMethod($key);
        }
    }

    /**
     * @return array<array{mixed,mixed[],string,string|null,4?:string|null,5?:bool,6?:array<string,mixed>}>
     */
    public static function claimFilterProvider(): array
    {
        return [
            'int' => [
                42,
                [42],
                'id',
                'claimFilterInt',
                'claimFilterString',
            ],
            'int (key not set)' => [
                null,
                [],
                'id',
                'claimFilterInt',
            ],
            'int (key not set + fallback value)' => [
                42,
                [],
                'value',
                'claimFilterInt',
                null,
                false,
                ['value' => 42],
            ],
            'int (key not set + invalid fallback value)' => [
                null,
                [],
                'value',
                null,
                'claimFilterInt',
                false,
                ['value' => 'foo'],
            ],
            'int from float' => [
                null,
                [['value' => 3.14]],
                'value',
                null,
                'claimFilterInt',
            ],
            'string' => [
                'foo',
                ['foo'],
                'id',
                'claimFilterString',
                'claimFilterInt',
            ],
            'array-key (int)' => [
                42,
                [42],
                'id',
                'claimFilterArrayKey',
            ],
            'array-key (string)' => [
                'foo',
                ['foo'],
                'id',
                'claimFilterArrayKey',
            ],
            'list of int' => [
                [42, 71],
                [42, 71],
                'id',
                'claimFilterIntList',
                'claimFilterStringList',
            ],
            'list of int (key not set)' => [
                null,
                [],
                'id',
                'claimFilterIntList',
            ],
            'list of int + scalar input' => [
                [42],
                [['user_id' => 42]],
                'user_id',
                'claimFilterIntList',
            ],
            'list of string' => [
                ['foo', 'bar'],
                ['foo', 'bar'],
                'id',
                'claimFilterStringList',
                'claimFilterIntList',
            ],
            'list of string + scalar input' => [
                ['foo'],
                [['user_id' => 'foo']],
                'user_id',
                'claimFilterStringList',
            ],
            'list of array-key (int)' => [
                [42, 71],
                [42, 71],
                'id',
                'claimFilterArrayKeyList',
            ],
            'list of array-key (string)' => [
                ['foo', 'bar'],
                ['foo', 'bar'],
                'id',
                'claimFilterArrayKeyList',
            ],
            'list of array-key (mixed)' => [
                [42, 'foo'],
                [['user_id' => [42, 'foo']]],
                'user_id',
                'claimFilterArrayKeyList',
            ],
            'list of array-key (mixed + float)' => [
                null,
                [['value' => [42, 'foo', 3.14]]],
                'value',
                null,
                'claimFilterArrayKeyList',
            ],
        ];
    }
}
