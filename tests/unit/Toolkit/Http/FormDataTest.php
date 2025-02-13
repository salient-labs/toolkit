<?php declare(strict_types=1);

namespace Salient\Tests\Http;

use Salient\Contract\Core\Arrayable;
use Salient\Contract\Core\Jsonable;
use Salient\Contract\Http\FormDataFlag;
use Salient\Core\Date\DateFormatter;
use Salient\Http\FormData;
use Salient\Tests\TestCase;
use Salient\Utility\Json;
use ArrayIterator;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use JsonSerializable;
use stdClass;
use Stringable;

/**
 * @covers \Salient\Http\FormData
 */
final class FormDataTest extends TestCase
{
    public function testGetQueryWithInvalidValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid value at 'field1': stdClass");
        (new FormData(['field1' => new stdClass()]))->getQuery(0, null, fn($value) => $value);
    }

    public function testGetData(): void
    {
        $this->assertSame([
            'Arrayable' => ['foo', 'bar', 'baz'],
            'JsonSerializable' => [
                'FOO',
                ['foo', 'bar', 'baz'],
            ],
            'Jsonable' => [
                'FOO',
                ['foo', 'bar', 'baz'],
            ],
            'Stringable' => ['foo', 'bar'],
            'Generic' => ['Foo' => 'bar'],
            'Iterator' => ['foo' => 'bar', 'baz' => 1],
        ], (new FormData(self::getDataObjects()))->getData());
    }

    /**
     * @dataProvider formDataProvider
     *
     * @template T of object|mixed[]|string|null
     *
     * @param list<array{string,string|object}> $expected
     * @param mixed[] $data
     * @param int-mask-of<FormDataFlag::*> $flags
     * @param (callable(object): (T|false))|null $callback
     */
    public function testGetValues(
        array $expected,
        string $expectedQuery,
        array $data,
        int $flags = FormDataFlag::PRESERVE_NUMERIC_KEYS | FormDataFlag::PRESERVE_STRING_KEYS,
        ?DateFormatter $dateFormatter = null,
        ?callable $callback = null,
        bool $parse = true
    ): void {
        $formData = new FormData($data);
        $this->assertSame($expected, $formData->getValues($flags, $dateFormatter, $callback));

        $query = $formData->getQuery($flags, $dateFormatter, $callback);
        $this->assertSame($expectedQuery, $query);

        if (!$parse) {
            return;
        }

        array_walk_recursive(
            $data,
            function (&$value) use (&$dateFormatter): void {
                if ($value instanceof DateTimeInterface) {
                    $dateFormatter ??= new DateFormatter();
                    $value = $dateFormatter->format($value);
                } elseif (!is_string($value)) {
                    $value = (string) $value;
                }
            },
        );
        if (is_array($data['fields'] ?? null)) {
            unset($data['fields']['empty']);
        }
        parse_str($query, $parsed);
        $this->assertSame($data, $parsed);
    }

    /**
     * @return array<array{list<array{string,string|object}>,string,mixed[],3?:int-mask-of<FormDataFlag::*>,4?:DateFormatter|null,5?:callable|null,6?:bool}>
     */
    public static function formDataProvider(): array
    {
        $date = new DateTimeImmutable('2021-10-02T17:23:14+10:00');

        $data = [
            'user_id' => 7654,
            'fields' => [
                'email' => 'JWilliams432@gmail.com',
                'notify_by' => [
                    ['email', 'sms'],
                    ['mobile', 'home'],
                ],
                'groups' => ['staff', 'editor'],
                'active' => true,
                'created' => $date,
                'empty' => [],
            ],
        ];

        $lists = [
            'list' => ['a', 'b', 'c'],
            'indexed' => [5 => 'a', 9 => 'b', 2 => 'c'],
            'associative' => ['a' => 5, 'b' => 9, 'c' => 2],
        ];

        $created = new FormDataDateTimeWrapper($date);
        $callbackData = $data;
        $callbackData['fields']['created'] = $created;
        $callback1 = fn(FormDataDateTimeWrapper $value) => $value->DateTime;
        $callback2 = fn(FormDataDateTimeWrapper $value) => $value->DateTime->format('D, d M Y');

        return [
            [
                [
                    ['user_id', '7654'],
                    ['fields[email]', 'JWilliams432@gmail.com'],
                    ['fields[notify_by][0][]', 'email'],
                    ['fields[notify_by][0][]', 'sms'],
                    ['fields[notify_by][1][]', 'mobile'],
                    ['fields[notify_by][1][]', 'home'],
                    ['fields[groups][]', 'staff'],
                    ['fields[groups][]', 'editor'],
                    ['fields[active]', '1'],
                    ['fields[created]', '2021-10-02T17:23:14+10:00'],
                ],
                // user_id=7654&fields[email]=JWilliams432@gmail.com&fields[notify_by][0][]=email&fields[notify_by][0][]=sms&fields[notify_by][1][]=mobile&fields[notify_by][1][]=home&fields[groups][]=staff&fields[groups][]=editor&fields[active]=1&fields[created]=2021-10-02T17:23:14+10:00
                'user_id=7654&fields%5Bemail%5D=JWilliams432%40gmail.com&fields%5Bnotify_by%5D%5B0%5D%5B%5D=email&fields%5Bnotify_by%5D%5B0%5D%5B%5D=sms&fields%5Bnotify_by%5D%5B1%5D%5B%5D=mobile&fields%5Bnotify_by%5D%5B1%5D%5B%5D=home&fields%5Bgroups%5D%5B%5D=staff&fields%5Bgroups%5D%5B%5D=editor&fields%5Bactive%5D=1&fields%5Bcreated%5D=2021-10-02T17%3A23%3A14%2B10%3A00',
                $data,
            ],
            [
                [
                    ['user_id', '7654'],
                    ['fields[email]', 'JWilliams432@gmail.com'],
                    ['fields[notify_by][0][0]', 'email'],
                    ['fields[notify_by][0][1]', 'sms'],
                    ['fields[notify_by][1][0]', 'mobile'],
                    ['fields[notify_by][1][1]', 'home'],
                    ['fields[groups][0]', 'staff'],
                    ['fields[groups][1]', 'editor'],
                    ['fields[active]', '1'],
                    ['fields[created]', '2021-10-02T17:23:14+10:00'],
                ],
                // user_id=7654&fields[email]=JWilliams432@gmail.com&fields[notify_by][0][0]=email&fields[notify_by][0][1]=sms&fields[notify_by][1][0]=mobile&fields[notify_by][1][1]=home&fields[groups][0]=staff&fields[groups][1]=editor&fields[active]=1&fields[created]=2021-10-02T17:23:14+10:00
                'user_id=7654&fields%5Bemail%5D=JWilliams432%40gmail.com&fields%5Bnotify_by%5D%5B0%5D%5B0%5D=email&fields%5Bnotify_by%5D%5B0%5D%5B1%5D=sms&fields%5Bnotify_by%5D%5B1%5D%5B0%5D=mobile&fields%5Bnotify_by%5D%5B1%5D%5B1%5D=home&fields%5Bgroups%5D%5B0%5D=staff&fields%5Bgroups%5D%5B1%5D=editor&fields%5Bactive%5D=1&fields%5Bcreated%5D=2021-10-02T17%3A23%3A14%2B10%3A00',
                $data,
                FormDataFlag::PRESERVE_ALL_KEYS,
            ],
            [
                [
                    ['user_id', '7654'],
                    ['fields[email]', 'JWilliams432@gmail.com'],
                    ['fields[notify_by][0][]', 'email'],
                    ['fields[notify_by][0][]', 'sms'],
                    ['fields[notify_by][1][]', 'mobile'],
                    ['fields[notify_by][1][]', 'home'],
                    ['fields[groups][]', 'staff'],
                    ['fields[groups][]', 'editor'],
                    ['fields[active]', '1'],
                    ['fields[created]', 'Sat, 02 Oct 2021 17:23:14 +1000'],
                ],
                // user_id=7654&fields[email]=JWilliams432@gmail.com&fields[notify_by][0][]=email&fields[notify_by][0][]=sms&fields[notify_by][1][]=mobile&fields[notify_by][1][]=home&fields[groups][]=staff&fields[groups][]=editor&fields[active]=1&fields[created]=Sat, 02 Oct 2021 17:23:14 +1000
                'user_id=7654&fields%5Bemail%5D=JWilliams432%40gmail.com&fields%5Bnotify_by%5D%5B0%5D%5B%5D=email&fields%5Bnotify_by%5D%5B0%5D%5B%5D=sms&fields%5Bnotify_by%5D%5B1%5D%5B%5D=mobile&fields%5Bnotify_by%5D%5B1%5D%5B%5D=home&fields%5Bgroups%5D%5B%5D=staff&fields%5Bgroups%5D%5B%5D=editor&fields%5Bactive%5D=1&fields%5Bcreated%5D=Sat%2C%2002%20Oct%202021%2017%3A23%3A14%20%2B1000',
                $data,
                FormDataFlag::PRESERVE_NUMERIC_KEYS | FormDataFlag::PRESERVE_STRING_KEYS,
                new DateFormatter(DateTimeInterface::RSS),
            ],
            [
                [
                    ['user_id', '7654'],
                    ['fields[email]', 'JWilliams432@gmail.com'],
                    ['fields[notify_by][0][]', 'email'],
                    ['fields[notify_by][0][]', 'sms'],
                    ['fields[notify_by][1][]', 'mobile'],
                    ['fields[notify_by][1][]', 'home'],
                    ['fields[groups][]', 'staff'],
                    ['fields[groups][]', 'editor'],
                    ['fields[active]', '1'],
                    ['fields[created]', '2021-10-02T07:23:14+00:00'],
                ],
                // user_id=7654&fields[email]=JWilliams432@gmail.com&fields[notify_by][0][]=email&fields[notify_by][0][]=sms&fields[notify_by][1][]=mobile&fields[notify_by][1][]=home&fields[groups][]=staff&fields[groups][]=editor&fields[active]=1&fields[created]=2021-10-02T07:23:14+00:00
                'user_id=7654&fields%5Bemail%5D=JWilliams432%40gmail.com&fields%5Bnotify_by%5D%5B0%5D%5B%5D=email&fields%5Bnotify_by%5D%5B0%5D%5B%5D=sms&fields%5Bnotify_by%5D%5B1%5D%5B%5D=mobile&fields%5Bnotify_by%5D%5B1%5D%5B%5D=home&fields%5Bgroups%5D%5B%5D=staff&fields%5Bgroups%5D%5B%5D=editor&fields%5Bactive%5D=1&fields%5Bcreated%5D=2021-10-02T07%3A23%3A14%2B00%3A00',
                $data,
                FormDataFlag::PRESERVE_NUMERIC_KEYS | FormDataFlag::PRESERVE_STRING_KEYS,
                new DateFormatter(DateTimeInterface::ATOM, 'UTC'),
            ],
            [
                [
                    ['user_id', '7654'],
                    ['fields[email]', 'JWilliams432@gmail.com'],
                    ['fields[notify_by][0][]', 'email'],
                    ['fields[notify_by][0][]', 'sms'],
                    ['fields[notify_by][1][]', 'mobile'],
                    ['fields[notify_by][1][]', 'home'],
                    ['fields[groups][]', 'staff'],
                    ['fields[groups][]', 'editor'],
                    ['fields[active]', '1'],
                    ['fields[created]', '2021-10-02T17:23:14+10:00'],
                ],
                // user_id=7654&fields[email]=JWilliams432@gmail.com&fields[notify_by][0][]=email&fields[notify_by][0][]=sms&fields[notify_by][1][]=mobile&fields[notify_by][1][]=home&fields[groups][]=staff&fields[groups][]=editor&fields[active]=1&fields[created]=2021-10-02T17:23:14+10:00
                'user_id=7654&fields%5Bemail%5D=JWilliams432%40gmail.com&fields%5Bnotify_by%5D%5B0%5D%5B%5D=email&fields%5Bnotify_by%5D%5B0%5D%5B%5D=sms&fields%5Bnotify_by%5D%5B1%5D%5B%5D=mobile&fields%5Bnotify_by%5D%5B1%5D%5B%5D=home&fields%5Bgroups%5D%5B%5D=staff&fields%5Bgroups%5D%5B%5D=editor&fields%5Bactive%5D=1&fields%5Bcreated%5D=2021-10-02T17%3A23%3A14%2B10%3A00',
                $callbackData,
                FormDataFlag::PRESERVE_NUMERIC_KEYS | FormDataFlag::PRESERVE_STRING_KEYS,
                null,
                $callback1,
                false,
            ],
            [
                [
                    ['user_id', '7654'],
                    ['fields[email]', 'JWilliams432@gmail.com'],
                    ['fields[notify_by][0][]', 'email'],
                    ['fields[notify_by][0][]', 'sms'],
                    ['fields[notify_by][1][]', 'mobile'],
                    ['fields[notify_by][1][]', 'home'],
                    ['fields[groups][]', 'staff'],
                    ['fields[groups][]', 'editor'],
                    ['fields[active]', '1'],
                    ['fields[created]', 'Sat, 02 Oct 2021'],
                ],
                // user_id=7654&fields[email]=JWilliams432@gmail.com&fields[notify_by][0][]=email&fields[notify_by][0][]=sms&fields[notify_by][1][]=mobile&fields[notify_by][1][]=home&fields[groups][]=staff&fields[groups][]=editor&fields[active]=1&fields[created]=Sat, 02 Oct 2021
                'user_id=7654&fields%5Bemail%5D=JWilliams432%40gmail.com&fields%5Bnotify_by%5D%5B0%5D%5B%5D=email&fields%5Bnotify_by%5D%5B0%5D%5B%5D=sms&fields%5Bnotify_by%5D%5B1%5D%5B%5D=mobile&fields%5Bnotify_by%5D%5B1%5D%5B%5D=home&fields%5Bgroups%5D%5B%5D=staff&fields%5Bgroups%5D%5B%5D=editor&fields%5Bactive%5D=1&fields%5Bcreated%5D=Sat%2C%2002%20Oct%202021',
                $callbackData,
                FormDataFlag::PRESERVE_NUMERIC_KEYS | FormDataFlag::PRESERVE_STRING_KEYS,
                null,
                $callback2,
                false,
            ],
            [
                [
                    ['list[]', 'a'],
                    ['list[]', 'b'],
                    ['list[]', 'c'],
                    ['indexed[5]', 'a'],
                    ['indexed[9]', 'b'],
                    ['indexed[2]', 'c'],
                    ['associative[a]', '5'],
                    ['associative[b]', '9'],
                    ['associative[c]', '2'],
                ],
                // list[]=a&list[]=b&list[]=c&indexed[5]=a&indexed[9]=b&indexed[2]=c&associative[a]=5&associative[b]=9&associative[c]=2
                'list%5B%5D=a&list%5B%5D=b&list%5B%5D=c&indexed%5B5%5D=a&indexed%5B9%5D=b&indexed%5B2%5D=c&associative%5Ba%5D=5&associative%5Bb%5D=9&associative%5Bc%5D=2',
                $lists,
            ],
            [
                [
                    ['list[]', 'a'],
                    ['list[]', 'b'],
                    ['list[]', 'c'],
                    ['indexed[]', 'a'],
                    ['indexed[]', 'b'],
                    ['indexed[]', 'c'],
                    ['associative[]', '5'],
                    ['associative[]', '9'],
                    ['associative[]', '2'],
                ],
                // list[]=a&list[]=b&list[]=c&indexed[]=a&indexed[]=b&indexed[]=c&associative[]=5&associative[]=9&associative[]=2
                'list%5B%5D=a&list%5B%5D=b&list%5B%5D=c&indexed%5B%5D=a&indexed%5B%5D=b&indexed%5B%5D=c&associative%5B%5D=5&associative%5B%5D=9&associative%5B%5D=2',
                $lists,
                0,
                null,
                null,
                false,
            ],
            [
                [
                    ['list[]', 'a'],
                    ['list[]', 'b'],
                    ['list[]', 'c'],
                    ['indexed[]', 'a'],
                    ['indexed[]', 'b'],
                    ['indexed[]', 'c'],
                    ['associative[a]', '5'],
                    ['associative[b]', '9'],
                    ['associative[c]', '2'],
                ],
                // list[]=a&list[]=b&list[]=c&indexed[]=a&indexed[]=b&indexed[]=c&associative[a]=5&associative[b]=9&associative[c]=2
                'list%5B%5D=a&list%5B%5D=b&list%5B%5D=c&indexed%5B%5D=a&indexed%5B%5D=b&indexed%5B%5D=c&associative%5Ba%5D=5&associative%5Bb%5D=9&associative%5Bc%5D=2',
                $lists,
                FormDataFlag::PRESERVE_STRING_KEYS,
                null,
                null,
                false,
            ],
            [
                [
                    ['list[0]', 'a'],
                    ['list[1]', 'b'],
                    ['list[2]', 'c'],
                    ['indexed[5]', 'a'],
                    ['indexed[9]', 'b'],
                    ['indexed[2]', 'c'],
                    ['associative[]', '5'],
                    ['associative[]', '9'],
                    ['associative[]', '2'],
                ],
                // list[0]=a&list[1]=b&list[2]=c&indexed[5]=a&indexed[9]=b&indexed[2]=c&associative[]=5&associative[]=9&associative[]=2
                'list%5B0%5D=a&list%5B1%5D=b&list%5B2%5D=c&indexed%5B5%5D=a&indexed%5B9%5D=b&indexed%5B2%5D=c&associative%5B%5D=5&associative%5B%5D=9&associative%5B%5D=2',
                $lists,
                FormDataFlag::PRESERVE_LIST_KEYS | FormDataFlag::PRESERVE_NUMERIC_KEYS,
                null,
                null,
                false,
            ],
            [
                [
                    ['Arrayable[]', 'foo'],
                    ['Arrayable[]', 'bar'],
                    ['Arrayable[]', 'baz'],
                    ['JsonSerializable[0]', 'FOO'],
                    ['JsonSerializable[1][]', 'foo'],
                    ['JsonSerializable[1][]', 'bar'],
                    ['JsonSerializable[1][]', 'baz'],
                    ['Jsonable[0]', 'FOO'],
                    ['Jsonable[1][]', 'foo'],
                    ['Jsonable[1][]', 'bar'],
                    ['Jsonable[1][]', 'baz'],
                    ['Stringable[]', 'foo'],
                    ['Stringable[]', 'bar'],
                    ['Generic[Foo]', 'bar'],
                    ['Iterator[foo]', 'bar'],
                    ['Iterator[baz]', '1'],
                ],
                // Arrayable[]=foo&Arrayable[]=bar&Arrayable[]=baz&JsonSerializable[0]=FOO&JsonSerializable[1][]=foo&JsonSerializable[1][]=bar&JsonSerializable[1][]=baz&Jsonable[0]=FOO&Jsonable[1][]=foo&Jsonable[1][]=bar&Jsonable[1][]=baz&Stringable[]=foo&Stringable[]=bar&Generic[Foo]=bar&Iterator[foo]=bar&Iterator[baz]=1
                'Arrayable%5B%5D=foo&Arrayable%5B%5D=bar&Arrayable%5B%5D=baz&JsonSerializable%5B0%5D=FOO&JsonSerializable%5B1%5D%5B%5D=foo&JsonSerializable%5B1%5D%5B%5D=bar&JsonSerializable%5B1%5D%5B%5D=baz&Jsonable%5B0%5D=FOO&Jsonable%5B1%5D%5B%5D=foo&Jsonable%5B1%5D%5B%5D=bar&Jsonable%5B1%5D%5B%5D=baz&Stringable%5B%5D=foo&Stringable%5B%5D=bar&Generic%5BFoo%5D=bar&Iterator%5Bfoo%5D=bar&Iterator%5Bbaz%5D=1',
                self::getDataObjects(),
                FormDataFlag::PRESERVE_NUMERIC_KEYS | FormDataFlag::PRESERVE_STRING_KEYS,
                null,
                null,
                false,
            ],
        ];
    }

    /**
     * @return mixed[]
     */
    private static function getDataObjects(): array
    {
        return [
            'Arrayable' => new class implements Arrayable {
                public function toArray(): array
                {
                    return ['foo', 'bar', 'baz'];
                }
            },
            'JsonSerializable' => [
                new class implements JsonSerializable {
                    public function jsonSerialize(): string
                    {
                        return 'FOO';
                    }
                },
                new class implements JsonSerializable {
                    /**
                     * @return string[]
                     */
                    public function jsonSerialize(): array
                    {
                        return ['foo', 'bar', 'baz'];
                    }
                },
            ],
            'Jsonable' => [
                new class implements Jsonable {
                    public function toJson(int $flags = 0): string
                    {
                        return Json::encode('FOO', $flags);
                    }
                },
                new class implements Jsonable {
                    public function toJson(int $flags = 0): string
                    {
                        return Json::encode(['foo', 'bar', 'baz'], $flags);
                    }
                },
            ],
            'Stringable' => [
                new class implements Stringable {
                    public function __toString()
                    {
                        return 'foo';
                    }
                },
                new class {
                    public function __toString()
                    {
                        return 'bar';
                    }
                },
            ],
            'Generic' => new class {
                public string $Foo = 'bar';
                protected int $Baz = 1;
            },
            'Iterator' => new ArrayIterator(['foo' => 'bar', 'baz' => 1]),
        ];
    }
}

class FormDataDateTimeWrapper
{
    public DateTimeInterface $DateTime;

    public function __construct(DateTimeInterface $dateTime)
    {
        $this->DateTime = $dateTime;
    }
}
