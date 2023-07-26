<?php declare(strict_types=1);

namespace Lkrms\Tests\Support;

use Closure;
use Lkrms\Exception\PipelineResultRejectedException;
use Lkrms\Support\Catalog\ArrayKeyConformity;
use Lkrms\Support\Catalog\ArrayMapperFlag;
use Lkrms\Support\Pipeline;
use Throwable;
use UnexpectedValueException;

final class PipelineTest extends \Lkrms\Tests\TestCase
{
    public function testStream()
    {
        $in = [12, 23, 34, 45, 56, 67, 78, 89, 90];
        $out = [];
        foreach ((new Pipeline())
                ->stream($in)
                ->through(
                    fn($payload, Closure $next) => $next($payload * 3),
                    fn($payload, Closure $next) => $next($payload / 23),
                    fn($payload, Closure $next) => $next(round($payload, 3))
                )
                ->start() as $_out) {
            $out[] = $_out;
        }

        $this->assertSame(
            [1.565, 3.0, 4.435, 5.87, 7.304, 8.739, 10.174, 11.609, 11.739],
            $out
        );
    }

    public function testAfter()
    {
        $in = [12, 23, 34, 45, 56, 67, 78, 89, 90];
        $out = [];
        foreach ((new Pipeline())
                ->stream($in)
                ->after(fn($payload) => $payload * 2)
                ->through(
                    fn($payload, Closure $next) => $next($payload * 3),
                    fn($payload, Closure $next) => $next($payload / 23),
                    fn($payload, Closure $next) => $next(round($payload, 3))
                )
                ->start() as $_out) {
            $out[] = $_out;
        }

        $this->assertSame(
            [3.13, 6.0, 8.87, 11.739, 14.609, 17.478, 20.348, 23.217, 23.478],
            $out
        );
    }

    public function testUnless()
    {
        $in = [12, 23, 34, 45, 56, 67, 78, 89, 90];
        $out = [];
        foreach ((new Pipeline())
                ->stream($in)
                ->through(
                    fn($payload, Closure $next) => $payload % 2 ? null : $next($payload * 3),
                    fn($payload, Closure $next) => $next($payload / 23),
                    fn($payload, Closure $next) => $payload < 11 ? $next(round($payload, 3)) : null,
                )
                ->unless(fn($result) => is_null($result))
                ->start() as $_out) {
            $out[] = $_out;
        }

        $this->assertSame(
            [1.565, 4.435, 7.304, 10.174],
            $out
        );

        $this->expectException(PipelineResultRejectedException::class);
        (new Pipeline())
            ->send(23)
            ->through(
                fn($payload, Closure $next) => $payload % 2 ? null : $next($payload * 3),
                fn($payload, Closure $next) => $next($payload / 23),
                fn($payload, Closure $next) => $payload < 11 ? $next(round($payload, 3)) : null,
            )
            ->unless(fn($result) => is_null($result))
            ->run();
    }

    public function testThroughKeyMap()
    {
        $in = [
            [
                'USER_ID' => 32,
                'FULL_NAME' => 'Greta',
                'MAIL' => 'greta@domain.test',
            ],
            [
                'FULL_NAME' => 'Amir',
                'MAIL' => 'amir@domain.test',
                'URI' => 'https://domain.test/~amir',
            ],
            [
                'USER_ID' => 71,
                'FULL_NAME' => 'Terry',
                'MAIL' => null,
            ],
        ];
        $map = [
            'USER_ID' => 'Id',
            'FULL_NAME' => 'Name',
            'MAIL' => 'Email',
        ];
        $out = [];

        $pipeline = Pipeline::create()
            ->throughKeyMap($map, 0);
        foreach ($in as $_in) {
            $out[] = $pipeline->send($_in)->run();
        }

        $pipeline = Pipeline::create()
            ->throughKeyMap($map, ArrayMapperFlag::ADD_MISSING);
        foreach ($in as $_in) {
            $out[] = $pipeline->send($_in)->run();
        }

        $pipeline = Pipeline::create()
            ->throughKeyMap($map, ArrayMapperFlag::ADD_UNMAPPED);
        foreach ($in as $_in) {
            $out[] = $pipeline->send($_in)->run();
        }

        $pipeline = Pipeline::create()
            ->throughKeyMap($map, ArrayMapperFlag::REMOVE_NULL);
        foreach ($in as $_in) {
            $out[] = $pipeline->send($_in)->run();
        }

        $pipeline = Pipeline::create()
            ->throughKeyMap(
                $map,
                ArrayMapperFlag::ADD_MISSING
                    | ArrayMapperFlag::ADD_UNMAPPED
                    | ArrayMapperFlag::REMOVE_NULL
            );
        foreach ($in as $_in) {
            $out[] = $pipeline->send($_in)->run();
        }

        $mapToMultiple = [
            'USER_ID' => 'Id',
            'FULL_NAME' => 'Name',
            'MAIL' => ['Email', 'UPN'],
        ];
        $pipeline = Pipeline::create()
            ->throughKeyMap($mapToMultiple, ArrayMapperFlag::ADD_MISSING);
        foreach ($in as $_in) {
            $out[] = $pipeline->send($_in)->run();
        }

        $this->assertSame([
            // 1.
            ['Id' => 32, 'Name' => 'Greta', 'Email' => 'greta@domain.test'],
            ['Name' => 'Amir', 'Email' => 'amir@domain.test'],
            ['Id' => 71, 'Name' => 'Terry', 'Email' => null],

            // 2.
            ['Id' => 32, 'Name' => 'Greta', 'Email' => 'greta@domain.test'],
            ['Id' => null, 'Name' => 'Amir', 'Email' => 'amir@domain.test'],
            ['Id' => 71, 'Name' => 'Terry', 'Email' => null],

            // 3.
            ['Id' => 32, 'Name' => 'Greta', 'Email' => 'greta@domain.test'],
            ['Name' => 'Amir', 'Email' => 'amir@domain.test', 'URI' => 'https://domain.test/~amir'],
            ['Id' => 71, 'Name' => 'Terry', 'Email' => null],

            // 4.
            ['Id' => 32, 'Name' => 'Greta', 'Email' => 'greta@domain.test'],
            ['Name' => 'Amir', 'Email' => 'amir@domain.test'],
            ['Id' => 71, 'Name' => 'Terry'],

            // 5.
            ['Id' => 32, 'Name' => 'Greta', 'Email' => 'greta@domain.test'],
            ['Name' => 'Amir', 'Email' => 'amir@domain.test', 'URI' => 'https://domain.test/~amir'],
            ['Id' => 71, 'Name' => 'Terry'],

            // 6.
            ['Id' => 32, 'Name' => 'Greta', 'Email' => 'greta@domain.test', 'UPN' => 'greta@domain.test'],
            ['Id' => null, 'Name' => 'Amir', 'Email' => 'amir@domain.test', 'UPN' => 'amir@domain.test'],
            ['Id' => 71, 'Name' => 'Terry', 'Email' => null, 'UPN' => null],
        ], $out);

        $pipeline = Pipeline::create()
            ->throughKeyMap($map, ArrayMapperFlag::REQUIRE_MAPPED);
        $this->expectException(UnexpectedValueException::class);
        foreach ($in as $_in) {
            $pipeline->send($_in)->run();
        }
    }

    public function testWithConformity()
    {
        $good = [
            [
                'USER_ID' => 32,
                'FULL_NAME' => 'Greta',
                'MAIL' => 'greta@domain.test',
            ],
            [
                'USER_ID' => 53,
                'FULL_NAME' => 'Amir',
                'MAIL' => 'amir@domain.test',
            ],
            [
                'USER_ID' => 71,
                'FULL_NAME' => 'Terry',
                'MAIL' => null,
            ],
        ];
        $bad = [
            [
                'FULL_NAME' => 'Greta',
                'USER_ID' => 32,
                'MAIL' => 'greta@domain.test',
            ],
            [
                'FULL_NAME' => 'Amir',
                'MAIL' => 'amir@domain.test',
                'USER_ID' => 53,
            ],
            [
                'USER_ID' => 71,
                'FULL_NAME' => 'Terry',
                'MAIL' => null,
            ],
        ];
        $ugly = [
            [
                'USER_ID' => 32,
                'FULL_NAME' => 'Greta',
                'MAIL' => 'greta@domain.test',
            ],
            [
                'MAIL' => 'amir@domain.test',
                'URI' => 'https://domain.test/~amir',
            ],
            [
                'USER_ID' => 71,
                'FULL_NAME' => 'Terry',
            ],
        ];
        $map = [
            'USER_ID' => 'Id',
            'FULL_NAME' => 'Name',
            'MAIL' => 'Email',
        ];
        $out = [];
        $err = [];

        foreach ([ArrayKeyConformity::COMPLETE, ArrayKeyConformity::NONE] as $conformity) {
            foreach ([$good, $bad, $ugly] as $i => $in) {
                $pipeline = Pipeline::create()
                    ->stream($in)
                    ->withConformity($conformity)
                    ->throughKeyMap($map, 0);

                if ($i === 2) {
                    try {
                        $output = iterator_to_array($pipeline->start());
                        array_push($out, ...$output);
                    } catch (Throwable $ex) {
                        $err[] = $out[] = [get_class($ex) => $ex->getMessage()];
                    }
                    continue;
                }

                array_push($out, ...iterator_to_array($pipeline->start()));
            }
        }

        $this->assertSame([
            // ArrayKeyConformity::COMPLETE + good
            ['Id' => 32, 'Name' => 'Greta', 'Email' => 'greta@domain.test'],
            ['Id' => 53, 'Name' => 'Amir', 'Email' => 'amir@domain.test'],
            ['Id' => 71, 'Name' => 'Terry', 'Email' => null],

            // ArrayKeyConformity::COMPLETE + bad
            ['Id' => 'Greta', 'Name' => 32, 'Email' => 'greta@domain.test'],
            ['Id' => 'Amir', 'Name' => 'amir@domain.test', 'Email' => 53],
            ['Id' => 71, 'Name' => 'Terry', 'Email' => null],

            // ArrayKeyConformity::COMPLETE + ugly
            $err[0] ?? null,

            // ArrayKeyConformity::NONE + good
            ['Id' => 32, 'Name' => 'Greta', 'Email' => 'greta@domain.test'],
            ['Id' => 53, 'Name' => 'Amir', 'Email' => 'amir@domain.test'],
            ['Id' => 71, 'Name' => 'Terry', 'Email' => null],

            // ArrayKeyConformity::NONE + bad
            ['Id' => 32, 'Name' => 'Greta', 'Email' => 'greta@domain.test'],
            ['Id' => 53, 'Name' => 'Amir', 'Email' => 'amir@domain.test'],
            ['Id' => 71, 'Name' => 'Terry', 'Email' => null],

            // ArrayKeyConformity::NONE + ugly
            ['Id' => 32, 'Name' => 'Greta', 'Email' => 'greta@domain.test'],
            ['Email' => 'amir@domain.test'],
            ['Id' => 71, 'Name' => 'Terry'],
        ], $out);
    }
}
