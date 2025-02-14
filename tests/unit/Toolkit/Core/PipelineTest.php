<?php declare(strict_types=1);

namespace Salient\Tests\Core;

use Salient\Contract\Core\Exception\InvalidDataException;
use Salient\Contract\Core\Pipeline\ArrayMapperInterface;
use Salient\Contract\Core\Pipeline\PipelineInterface;
use Salient\Core\Pipeline;
use Salient\Tests\TestCase;
use Closure;
use Throwable;

/**
 * @covers \Salient\Core\Pipeline
 */
final class PipelineTest extends TestCase
{
    public function testStream(): void
    {
        $in = [12, 23, 34, 45, 56, 67, 78, 89, 90];
        $out = [];
        /** @var PipelineInterface<int,float,null> */
        $pipeline = Pipeline::create();
        foreach (
            $pipeline
                ->stream($in)
                ->through(fn($payload, Closure $next) => $next($payload * 3))
                ->through(fn($payload, Closure $next) => $next($payload / 23))
                ->through(fn($payload, Closure $next) => $next(round($payload, 3)))
                ->start() as $_out
        ) {
            $out[] = $_out;
        }

        $this->assertSame(
            [1.565, 3.0, 4.435, 5.87, 7.304, 8.739, 10.174, 11.609, 11.739],
            $out
        );
    }

    public function testAfter(): void
    {
        $in = [12, 23, 34, 45, 56, 67, 78, 89, 90];
        $out = [];
        /** @var PipelineInterface<int,float,null> */
        $pipeline = Pipeline::create();
        foreach (
            $pipeline
                ->stream($in)
                ->after(fn($payload) => $payload * 2)
                ->through(fn($payload, Closure $next) => $next($payload * 3))
                ->through(fn($payload, Closure $next) => $next($payload / 23))
                ->through(fn($payload, Closure $next) => $next(round($payload, 3)))
                ->start() as $_out
        ) {
            $out[] = $_out;
        }

        $this->assertSame(
            [3.13, 6.0, 8.87, 11.739, 14.609, 17.478, 20.348, 23.217, 23.478],
            $out
        );
    }

    public function testCc(): void
    {
        $in = [12, 23, 34, 45, 56, 67, 78, 89, 90];
        $out1 = [];
        $out2 = [];
        /** @var Pipeline<int,int,null> */
        $pipeline = Pipeline::create()
            ->stream($in)
            ->through(fn($payload, Closure $next) => $next($payload * 6));
        foreach (
            $pipeline
                ->cc(function ($result) use (&$out1) { $out1[] = round($result / 23, 3); })
                ->start() as $_out
        ) {
            $out2[] = $_out;
        }

        $this->assertSame(
            [3.13, 6.0, 8.87, 11.739, 14.609, 17.478, 20.348, 23.217, 23.478],
            $out1
        );

        $this->assertSame(
            [72, 138, 204, 270, 336, 402, 468, 534, 540],
            $out2
        );
    }

    public function testUnless(): void
    {
        $in = [12, 23, 34, 45, 56, 67, 78, 89, 90];
        $out = [];
        /** @var PipelineInterface<int,float,null> */
        $pipeline = Pipeline::create();
        foreach (
            $pipeline
                ->stream($in)
                ->through(fn($payload, Closure $next) => $payload % 2 ? null : $next($payload * 3))
                ->through(fn($payload, Closure $next) => $next($payload / 23))
                ->through(fn($payload, Closure $next) => $payload < 11 ? $next(round($payload, 3)) : null)
                ->unless(fn($result) => $result === null)
                ->start() as $_out
        ) {
            $out[] = $_out;
        }

        $this->assertSame(
            [1.565, 4.435, 7.304, 10.174],
            $out
        );
    }

    public function testThroughKeyMap(): void
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
            ->throughKeyMap($map, ArrayMapperInterface::ADD_MISSING);
        foreach ($in as $_in) {
            $out[] = $pipeline->send($_in)->run();
        }

        $pipeline = Pipeline::create()
            ->throughKeyMap($map, ArrayMapperInterface::ADD_UNMAPPED);
        foreach ($in as $_in) {
            $out[] = $pipeline->send($_in)->run();
        }

        $pipeline = Pipeline::create()
            ->throughKeyMap($map, ArrayMapperInterface::REMOVE_NULL);
        foreach ($in as $_in) {
            $out[] = $pipeline->send($_in)->run();
        }

        $pipeline = Pipeline::create()
            ->throughKeyMap(
                $map,
                ArrayMapperInterface::ADD_MISSING
                    | ArrayMapperInterface::ADD_UNMAPPED
                    | ArrayMapperInterface::REMOVE_NULL
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
            ->throughKeyMap($mapToMultiple, ArrayMapperInterface::ADD_MISSING);
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
            ->throughKeyMap($map, ArrayMapperInterface::REQUIRE_MAPPED);
        $this->expectException(InvalidDataException::class);
        foreach ($in as $_in) {
            $pipeline->send($_in)->run();
        }
    }

    public function testWithConformity(): void
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

        foreach ([Pipeline::CONFORMITY_COMPLETE, Pipeline::CONFORMITY_NONE] as $conformity) {
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
            // CONFORMITY_COMPLETE + good
            ['Id' => 32, 'Name' => 'Greta', 'Email' => 'greta@domain.test'],
            ['Id' => 53, 'Name' => 'Amir', 'Email' => 'amir@domain.test'],
            ['Id' => 71, 'Name' => 'Terry', 'Email' => null],
            // CONFORMITY_COMPLETE + bad
            ['Id' => 'Greta', 'Name' => 32, 'Email' => 'greta@domain.test'],
            ['Id' => 'Amir', 'Name' => 'amir@domain.test', 'Email' => 53],
            ['Id' => 71, 'Name' => 'Terry', 'Email' => null],
            // CONFORMITY_COMPLETE + ugly
            $err[0] ?? null,
            // CONFORMITY_NONE + good
            ['Id' => 32, 'Name' => 'Greta', 'Email' => 'greta@domain.test'],
            ['Id' => 53, 'Name' => 'Amir', 'Email' => 'amir@domain.test'],
            ['Id' => 71, 'Name' => 'Terry', 'Email' => null],
            // CONFORMITY_NONE + bad
            ['Id' => 32, 'Name' => 'Greta', 'Email' => 'greta@domain.test'],
            ['Id' => 53, 'Name' => 'Amir', 'Email' => 'amir@domain.test'],
            ['Id' => 71, 'Name' => 'Terry', 'Email' => null],
            // CONFORMITY_NONE + ugly
            ['Id' => 32, 'Name' => 'Greta', 'Email' => 'greta@domain.test'],
            ['Email' => 'amir@domain.test'],
            ['Id' => 71, 'Name' => 'Terry'],
        ], $out);
    }
}
