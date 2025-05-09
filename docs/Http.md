# HTTP

## Form data

There are several methods in [salient/http][] and [salient/curler][] (see the
[list below][methods]) where data received via `mixed[] $query` and/or
`mixed[]|object $data` parameters is encoded as HTML form data before it is
applied to an HTTP message.

`true` and `false` are encoded as `1` and `0` respectively.

Keys other than `list` keys are preserved by default, but form data flags can be
used to modify this behaviour, e.g. via [Curler::withFormDataFlags()][].

`DateTimeInterface` instances are converted to ISO-8601 strings unless a date
formatter is given, e.g. via [Curler::withDateFormatter()][].

Before recursion, other objects are processed as follows:

1. `Arrayable`: replaced with return value of [toArray()][]
2. `JsonSerializable`: serialized with [Json::encode()][] and deserialized with
   [Json::objectAsArray()][]
3. `Jsonable`: replaced with return value of [toJson()][] after it is
   deserialized with [Json::objectAsArray()][]
4. `Traversable`: passed to `iterator_to_array()` and replaced with its return
   value
5. `object` with at least one public property: replaced with an array that maps
   public property names to values
6. `Stringable`: cast to `string`

> Internally, [FormDataEncoder][] instances are used to recurse into query and
> body data.
>
> If given, a callback is applied to objects other than `DateTimeInterface`
> instances. It must return one of the following:
>
> - `null` to skip the value
> - `false` to process the value as if no callback had been given
> - the value as a `string`
> - an `array` to recurse into
> - an `object` to return without encoding
>
> If a `DateTimeInterface` is returned, it is converted to a string as above.

### Methods that encode HTTP message data

#### `Http`

- [HttpUtil::mergeQuery()][]
- [HttpUtil::replaceQuery()][]
- [Stream::fromData()][]

#### `Curler`

- [Curler::replaceQuery()][]
- [Curler][] request methods: `head()`, `get()`, `post()`, `put()`, `patch()`,
  `delete()`
- Paginated variants: `getP()`, `postP()`, `putP()`, `patchP()`, `deleteP()`
- Raw variants (`$query` only): `postR()`, `putR()`, `patchR()`, `deleteR()`

[Curler]: https://salient-labs.github.io/toolkit/Salient.Curler.Curler.html
[Curler::replaceQuery()]:
  https://salient-labs.github.io/toolkit/Salient.Curler.Curler.html#_replaceQuery
[Curler::withDateFormatter()]:
  https://salient-labs.github.io/toolkit/Salient.Curler.Curler.html#_withDateFormatter
[Curler::withFormDataFlags()]:
  https://salient-labs.github.io/toolkit/Salient.Curler.Curler.html#_withFormDataFlags
[FormDataEncoder]: ../src/Toolkit/Http/Internal/FormDataEncoder.php
[HttpUtil::mergeQuery()]:
  https://salient-labs.github.io/toolkit/Salient.Http.HttpUtil.html#_mergeQuery
[HttpUtil::replaceQuery()]:
  https://salient-labs.github.io/toolkit/Salient.Http.HttpUtil.html#_replaceQuery
[Json::encode()]:
  https://salient-labs.github.io/toolkit/Salient.Utility.Json.html#_encode
[Json::objectAsArray()]:
  https://salient-labs.github.io/toolkit/Salient.Utility.Json.html#_objectAsArray
[methods]: #methods-that-encode-http-message-data
[salient/curler]: https://packagist.org/packages/salient/curler
[salient/http]: https://packagist.org/packages/salient/http
[Stream::fromData()]:
  https://salient-labs.github.io/toolkit/Salient.Http.Message.Stream.html#_fromData
[toArray()]:
  https://salient-labs.github.io/toolkit/Salient.Contract.Core.Arrayable.html#_toArray
[toJson()]:
  https://salient-labs.github.io/toolkit/Salient.Contract.Core.Jsonable.html#_toJson
