<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility;

use Lkrms\Tests\TestCase;
use Lkrms\Utility\File;
use Lkrms\Utility\Inflect;

final class InflectTest extends TestCase
{
    /**
     * @dataProvider formatProvider
     *
     * @param mixed $values
     */
    public function testFormat(
        string $expected0,
        string $expected1,
        string $expected71,
        string $format,
        bool $withSingularZero = false,
        ...$values
    ): void {
        if ($withSingularZero) {
            $this->assertSame($expected0, Inflect::formatWithSingularZero($format, 0, ...$values));
            $this->assertSame($expected1, Inflect::formatWithSingularZero($format, 1, ...$values));
            $this->assertSame($expected71, Inflect::formatWithSingularZero($format, 71, ...$values));
            return;
        }
        $this->assertSame($expected0, Inflect::format($format, 0, ...$values));
        $this->assertSame($expected1, Inflect::format($format, 1, ...$values));
        $this->assertSame($expected71, Inflect::format($format, 71, ...$values));
    }

    /**
     * @return array<array{string,string,string,string,4?:bool,...}>
     */
    public static function formatProvider(): array
    {
        return [
            [
                '0 entries were processed',
                '1 entry was processed',
                '71 entries were processed',
                '{{#}} {{#:entry}} {{#:was}} processed',
            ],
            [
                'no entry was processed',
                '1 entry was processed',
                '71 entries were processed',
                '{{#}} {{#:entry}} {{#:was}} processed',
                true,
            ],
            [
                'no events have been updated',
                'an event has been updated',
                '71 events have been updated',
                '{{#:an}} {{#:event}} {{#:has}} been updated',
            ],
            [
                'no event has been updated',
                'an event has been updated',
                '71 events have been updated',
                '{{#:an}} {{#:event}} {{#:has}} been updated',
                true,
            ],
            [
                '0 puppies are available',
                '1 puppy is available',
                '71 puppies are available',
                '{{#}} {{#:puppy}} {{#:is}} available',
            ],
            [
                'no puppy is available',
                '1 puppy is available',
                '71 puppies are available',
                '{{#}} {{#:puppy}} {{#:is}} available',
                true,
            ],
            [
                'no chickens crossed the road',
                'a chicken crossed the road',
                '71 chickens crossed the road',
                '{{#:a}} {{#:chicken}} crossed the road',
            ],
            [
                'no chicken crossed the road',
                'a chicken crossed the road',
                '71 chickens crossed the road',
                '{{#:a}} {{#:chicken}} crossed the road',
                true,
            ],
            [
                'no lines found in: ' . __METHOD__,
                '1 line found in: ' . __METHOD__,
                '71 lines found in: ' . __METHOD__,
                '{{#:no}} {{#:line}} found in: %s',
                false,
                __METHOD__,
            ],
            [
                'no line found in: ' . __METHOD__,
                '1 line found in: ' . __METHOD__,
                '71 lines found in: ' . __METHOD__,
                '{{#}} {{#:line}} found in: %s',
                true,
                __METHOD__,
            ],
            [
                '0 matrices generated',
                '1 matrix generated',
                '71 matrices generated',
                '{{#}} {{#:matrix:matrices}} generated',
            ],
            [
                'no matrix generated',
                '1 matrix generated',
                '71 matrices generated',
                '{{#}} {{#:matrix:matrices}} generated',
                true,
            ],
        ];
    }

    /**
     * @dataProvider pluralProvider
     *
     * @param array<array{string,string}> $data
     */
    public function testPlural(float $minAccuracy, array $data): void
    {
        $expected = [];
        $actual = [];
        $rows = 0;
        $goodRows = 0;
        foreach ($data as [$word, $expectedPlural]) {
            $rows++;
            $actualPlural = Inflect::plural($word);
            if ($actualPlural === $expectedPlural) {
                $goodRows++;
                continue;
            }
            $expected[$word] = $expectedPlural;
            $actual[$word] = $actualPlural;
        }
        $actualAccuracy = $goodRows * 100 / $rows;
        if ($actualAccuracy < $minAccuracy) {
            $this->assertSame($expected, $actual, sprintf(
                'Accuracy: %.2f%% (%d/%d) < %.2f%%',
                $actualAccuracy,
                $goodRows,
                $rows,
                $minAccuracy,
            ));
        } else {
            $this->assertGreaterThanOrEqual($minAccuracy, $actualAccuracy);
        }
    }

    /**
     * @return array<string,array{float,array<array{string,string}>}>
     */
    public static function pluralProvider(): array
    {
        $dir = self::getFixturesPath(__CLASS__);

        return [
            'legacy' => [
                100.0,
                [
                    ['blitz', 'blitzes'],
                    ['boy', 'boys'],
                    ['bus', 'buses'],
                    ['cat', 'cats'],
                    ['city', 'cities'],
                    ['halo', 'halos'],
                    ['house', 'houses'],
                    ['lunch', 'lunches'],
                    ['marsh', 'marshes'],
                    ['photo', 'photos'],
                    ['piano', 'pianos'],
                    ['potato', 'potatoes'],
                    ['puppy', 'puppies'],
                    ['ray', 'rays'],
                    ['tax', 'taxes'],
                    ['truss', 'trusses'],
                ],
            ],
            'plural-01.csv' => [
                30.0,
                // Source: https://fastapi.metacpan.org/source/DCONWAY/Lingua-EN-Inflexion-0.002008/t/noun_plural.t
                File::readCsv($dir . '/plural-01.csv'),
            ],
            'plural-02.csv' => [
                86.0,
                // Source: https://raw.githubusercontent.com/piotrmurach/strings-inflection/master/spec/fixtures/nounlist
                File::readCsv($dir . '/plural-02.csv'),
            ],
        ];
    }
}
