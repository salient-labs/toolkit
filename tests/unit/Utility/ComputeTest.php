<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility;

use Lkrms\Tests\TestCase;
use Lkrms\Utility\Compute;

final class ComputeTest extends TestCase
{
    /**
     * @dataProvider textDistanceProvider
     */
    public function testTextDistance(
        float $expected,
        string $string1,
        string $string2,
        bool $normalise = true
    ): void {
        $this->assertSame($expected, Compute::textDistance($string1, $string2, $normalise));
    }

    /**
     * @return array<array{float,string,string,3?:bool}>
     */
    public static function textDistanceProvider(): array
    {
        return [
            [1.0, 'DELIVERY', 'milk delivery', false],
            [0.5, 'DELIVERY', 'milk deliverer'],
            [0.38461538461538464, 'DELIVERY', 'milk delivery'],
            [0.7692307692307693, 'DELIVERY - MILK', 'milk delivery'],
            [0.0, 'DELIVERY', 'delivery'],
            [0.6190476190476191, 'DELIVERY', 'milk delivery service'],
        ];
    }

    /**
     * @dataProvider textSimilarityProvider
     */
    public function testTextSimilarity(
        float $expected,
        string $string1,
        string $string2,
        bool $normalise = true
    ): void {
        $this->assertSame($expected, Compute::textSimilarity($string1, $string2, $normalise));
    }

    /**
     * @return array<array{float,string,string,3?:bool}>
     */
    public static function textSimilarityProvider(): array
    {
        return [
            [0.0, 'DELIVERY', 'milk delivery', false],
            [0.5, 'DELIVERY', 'milk deliverer'],
            [0.6153846153846154, 'DELIVERY', 'milk delivery'],
            [0.6153846153846154, 'DELIVERY - MILK', 'milk delivery'],
            [1.0, 'DELIVERY - MILK', 'delivery milk'],
            [1.0, 'DELIVERY', 'delivery'],
            [0.38095238095238093, 'DELIVERY', 'milk delivery service'],
        ];
    }

    /**
     * @dataProvider ngramSimilarityProvider
     */
    public function testNgramSimilarity(
        float $expected,
        string $string1,
        string $string2,
        bool $normalise = true,
        int $size = 2
    ): void {
        $this->assertSame($expected, Compute::ngramSimilarity($string1, $string2, $normalise, $size));
    }

    /**
     * @return array<array{float,string,string,3?:bool,4?:int}>
     */
    public static function ngramSimilarityProvider(): array
    {
        return [
            [0.0, 'DELIVERY', 'milk delivery', false],
            [0.46153846153846156, 'DELIVERY', 'milk deliverer'],
            [0.5833333333333334, 'DELIVERY', 'milk delivery'],
            [0.8333333333333334, 'DELIVERY - MILK', 'milk delivery'],
            [1.0, 'DELIVERY - MILK', 'delivery milk'],
            [1.0, 'DELIVERY', 'delivery'],
            [0.35, 'DELIVERY', 'milk delivery service'],
            [0.4166666666666667, 'DELIVERY', 'milk deliverer', true, 3],
            [0.5454545454545454, 'DELIVERY', 'milk delivery', true, 3],
            [0.7272727272727273, 'DELIVERY - MILK', 'milk delivery', true, 3],
            [1.0, 'DELIVERY - MILK', 'delivery milk', true, 3],
            [1.0, 'DELIVERY', 'delivery', true, 3],
            [0.3157894736842105, 'DELIVERY', 'milk delivery service', true, 3],
        ];
    }

    /**
     * @dataProvider ngramIntersectionProvider
     */
    public function testNgramIntersection(
        float $expected,
        string $string1,
        string $string2,
        bool $normalise = true,
        int $size = 2
    ): void {
        $this->assertSame($expected, Compute::ngramIntersection($string1, $string2, $normalise, $size));
    }

    /**
     * @return array<array{float,string,string,3?:bool,4?:int}>
     */
    public static function ngramIntersectionProvider(): array
    {
        return [
            [0.0, 'DELIVERY', 'milk delivery', false],
            [0.8571428571428571, 'DELIVERY', 'milk deliverer'],
            [1.0, 'DELIVERY', 'milk delivery'],
            [0.8333333333333334, 'DELIVERY - MILK', 'milk delivery'],
            [1.0, 'DELIVERY - MILK', 'delivery milk'],
            [1.0, 'DELIVERY', 'delivery'],
            [1.0, 'DELIVERY', 'milk delivery service'],
            [0.8333333333333334, 'DELIVERY', 'milk deliverer', true, 3],
            [1.0, 'DELIVERY', 'milk delivery', true, 3],
            [0.7272727272727273, 'DELIVERY - MILK', 'milk delivery', true, 3],
            [1.0, 'DELIVERY - MILK', 'delivery milk', true, 3],
            [1.0, 'DELIVERY', 'delivery', true, 3],
            [1.0, 'DELIVERY', 'milk delivery service', true, 3],
        ];
    }

    /**
     * @dataProvider ngramProvider
     *
     * @param string[] $expected
     */
    public function testNgram(
        array $expected,
        string $string,
        int $size = 2
    ): void {
        $actual = Compute::ngrams($string, $size);
        sort($actual);
        $this->assertSame($expected, $actual);
    }

    /**
     * @return array<array{string[],string,2?:int}>
     */
    public static function ngramProvider(): array
    {
        return [
            [
                [],
                '',
            ],
            [
                [],
                'a',
            ],
            [
                ['ab'],
                'ab',
            ],
            [
                ['ab', 'bc'],
                'abc',
            ],
            [
                ['ab', 'bc', 'cd'],
                'abcd',
            ],
            [
                ['ab', 'bc', 'cd', 'de'],
                'abcde',
            ],
            [
                ['ab', 'bc', 'cd', 'de', 'ef'],
                'abcdef',
            ],
            [
                ['abc', 'bcd', 'cde'],
                'abcde',
                3,
            ],
        ];
    }
}
