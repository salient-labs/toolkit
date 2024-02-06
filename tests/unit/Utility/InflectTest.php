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

    /**
     * @dataProvider indefiniteProvider
     */
    public function testIndefinite(string $expected, string $word): void
    {
        $this->assertSame($expected, Inflect::indefinite($word));
    }

    /**
     * @return array<array{string,string}>
     */
    public static function indefiniteProvider(): array
    {
        return [
            // Source: https://fastapi.metacpan.org/source/DCONWAY/Lingua-EN-Inflexion-0.002008/t/indefinite.t
            ['an', 'a-th'],
            ['an', 'A.B.C'],
            ['an', 'AGE'],
            ['an', 'agendum'],
            ['an', 'AI'],
            ['an', 'aide-de-camp'],
            ['an', 'albino'],
            ['an', 'Ath'],
            ['a', 'b-th'],
            ['a', 'B.L.T. sandwich'],
            ['a', 'bacterium'],
            ['a', 'BLANK'],
            ['a', 'BMW'],
            ['a', 'Bth'],
            ['a', 'Burmese restaurant'],
            ['a', 'c-th'],
            ['a', 'C.O.'],
            ['a', 'cameo'],
            ['a', 'CAPITAL'],
            ['a', 'CCD'],
            ['a', 'COLON'],
            ['a', 'Cth'],
            ['a', 'd-th'],
            ['a', 'D.S.M.'],
            ['a', 'DINNER'],
            ['a', 'DNR'],
            ['a', 'Dth'],
            ['a', 'dynamo'],
            ['an', 'e-th'],
            ['an', 'E.K.G.'],
            ['an', 'ECG'],
            ['an', 'EGG'],
            ['an', 'embryo'],
            ['an', 'erratum'],
            ['an', 'Eth'],
            ['a', 'eucalyptus'],
            ['an', 'Euler number'],
            ['a', 'eulogy'],
            ['a', 'euphemism'],
            ['a', 'euphoria'],
            ['a', 'ewe'],
            ['a', 'ewer'],
            ['an', 'extremum'],
            ['an', 'eye'],
            ['an', 'f-th'],
            ['an', 'F.A.Q.'],
            ['an', 'F.B.I. agent'],
            ['a', 'FACT'],
            ['a', 'FAQ'],
            ['a', 'fish'],
            ['an', 'FSM'],
            ['an', 'Fth'],
            ['a', 'G-string'],
            ['a', 'g-th'],
            ['a', 'genus'],
            ['a', 'GOD'],
            ['a', 'Governor General'],
            ['a', 'GSM phone'],
            ['a', 'Gth'],
            ['an', 'H-Bomb'],
            ['an', 'h-th'],
            ['an', 'H.A.L. 9000'],
            ['an', 'H.M.S Ark Royal'],
            ['a', 'HAL 9000'],
            ['a', 'has-been'],
            ['a', 'height'],
            ['an', 'heir'],
            ['a', 'honed blade'],
            ['an', 'honest man'],
            ['a', 'honeymoon'],
            ['an', 'honorarium'],
            ['an', 'honorary degree'],
            ['an', 'honoree'],
            ['an', 'honorific'],
            ['a', 'Hough transform'],
            ['a', 'hound'],
            ['an', 'hour'],
            ['an', 'hourglass'],
            ['a', 'houri'],
            ['a', 'house'],
            ['an', 'HSL colour space'],
            ['an', 'Hth'],
            ['an', 'i-th'],
            ['an', 'I.O.U.'],
            ['an', 'IDEA'],
            ['an', 'inferno'],
            ['an', 'Inspector General'],
            ['an', 'IQ'],
            ['an', 'Ith'],
            ['a', 'j-th'],
            ['a', 'Jth'],
            ['a', 'jumbo'],
            ['a', 'k-th'],
            ['a', 'knife'],
            ['a', 'Kth'],
            ['an', 'l-th'],
            ['an', 'L.E.D.'],
            ['a', 'lady in waiting'],
            ['an', 'LCD'],
            ['a', 'leaf'],
            ['a', 'LED'],
            ['an', 'Lth'],
            ['an', 'm-th'],
            ['an', 'M.I.A.'],
            ['a', 'Major General'],
            ['a', 'MIASMA'],
            ['an', 'Mth'],
            ['an', 'MTV channel'],
            ['an', 'n-th'],
            ['an', 'N.C.O.'],
            ['a', 'NATO country'],
            ['an', 'NCO'],
            ['a', 'note'],
            ['an', 'Nth'],
            ['an', 'o-th'],
            ['an', 'O.K.'],
            ['an', 'octavo'],
            ['an', 'octopus'],
            ['an', 'OK'],
            ['an', 'okay'],
            ['an', 'OLE'],
            ['a', 'once-and-future-king'],
            ['an', 'oncologist'],
            ['a', 'one night stand'],
            ['an', 'onerous task'],
            ['an', 'opera'],
            ['an', 'optimum'],
            ['an', 'opus'],
            ['an', 'Oth'],
            ['an', 'ox'],
            ['a', 'p-th'],
            ['a', 'P.E.T. scan'],
            ['a', 'PET'],
            ['a', 'Ph.D.'],
            ['a', 'plateau'],
            ['a', 'Pth'],
            ['a', 'q-th'],
            ['a', 'Qth'],
            ['a', 'quantum'],
            ['an', 'r-th'],
            ['an', 'R.S.V.P.'],
            ['a', 'reindeer'],
            ['a', 'REST'],
            ['an', 'RSVP'],
            ['an', 'Rth'],
            ['an', 's-th'],
            ['an', 'S.O.S.'],
            ['a', 'salmon'],
            ['an', 'SST'],
            ['an', 'Sth'],
            ['a', 'SUM'],
            ['a', 't-th'],
            ['a', 'T.N.T. bomb'],
            ['a', 'TENT'],
            ['a', 'thought'],
            ['a', 'TNT bomb'],
            ['a', 'tomato'],
            ['a', 'Tth'],
            ['a', 'U-boat'],
            ['a', 'u-th'],
            ['a', 'U.F.O.'],
            ['a', 'ubiquity'],
            ['a', 'UFO'],
            ['a', 'UK citizen'],
            ['a', 'UNESCO representative'],
            ['a', 'unicorn'],
            ['an', 'unidentified flying object'],
            ['a', 'uniform'],
            ['a', 'unimodal system'],
            ['an', 'unimpressive record'],
            ['an', 'uninformed opinion'],
            ['an', 'uninvited guest'],
            ['a', 'union'],
            ['a', 'uniplex'],
            ['a', 'uniprocessor'],
            ['a', 'unique opportunity'],
            ['a', 'unisex hairdresser'],
            ['a', 'unison'],
            ['a', 'unit'],
            ['a', 'unitarian'],
            ['a', 'united front'],
            ['a', 'unity'],
            ['a', 'univalent bond'],
            ['a', 'univariate statistic'],
            ['a', 'universe'],
            ['an', 'unordered meal'],
            ['a', 'uranium atom'],
            ['an', 'urban myth'],
            ['an', 'urbane miss'],
            ['an', 'urchin'],
            ['a', 'urea detector'],
            ['a', 'urethane monomer'],
            ['an', 'urge'],
            ['an', 'urgency'],
            ['a', 'urinal'],
            ['an', 'urn'],
            ['a', 'usage'],
            ['a', 'use'],
            ['an', 'usher'],
            ['a', 'usual suspect'],
            ['a', 'usurer'],
            ['a', 'usurper'],
            ['a', 'utensil'],
            ['a', 'Uth'],
            ['a', 'utility'],
            ['an', 'utmost urgency'],
            ['a', 'utopia'],
            ['an', 'utterance'],
            ['a', 'v-th'],
            ['a', 'V.I.P.'],
            ['a', 'viper'],
            ['a', 'VIPER'],
            ['a', 'Vth'],
            ['a', 'w-th'],
            ['a', 'Wth'],
            ['an', 'X-ray'],
            ['an', 'x-th'],
            ['an', 'X.O.'],
            ['a', 'xenophobe'],
            ['an', 'Xth'],
            ['an', 'XY chromosome'],
            ['a', 'XYLAPHONE'],
            ['a', 'Y-shaped pipe'],
            ['a', 'y-th'],
            ['a', 'Y.Z. plane'],
            ['an', 'yblent eye'],
            ['an', 'YBLENT eye'],
            ['an', 'yclad body'],
            ['a', 'yellowing'],
            ['a', 'yield'],
            ['a', 'YMCA'],
            ['a', 'youth'],
            ['a', 'youth'],
            ['an', 'ypsiliform junction'],
            ['a', 'Yth'],
            ['an', 'yttrium atom'],
            ['a', 'z-th'],
            ['a', 'zoo'],
            ['a', 'Zth'],
        ];
    }
}
