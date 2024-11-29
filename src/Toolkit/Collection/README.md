# salient/collections

> The collections component of the [Salient toolkit][toolkit]

<p>
  <a href="https://packagist.org/packages/salient/toolkit"><img src="https://poser.pugx.org/salient/toolkit/v" alt="Latest Stable Version" /></a>
  <a href="https://packagist.org/packages/salient/toolkit"><img src="https://poser.pugx.org/salient/toolkit/license" alt="License" /></a>
  <a href="https://github.com/salient-labs/toolkit/actions"><img src="https://github.com/salient-labs/toolkit/actions/workflows/ci.yml/badge.svg" alt="CI Status" /></a>
  <a href="https://codecov.io/gh/salient-labs/toolkit"><img src="https://codecov.io/gh/salient-labs/toolkit/graph/badge.svg?token=Y0l9ZeEtrI" alt="Code Coverage" /></a>
</p>

---

`salient/collections` provides classes and traits that allow collections of
values to be accessed via array-like objects with chainable methods.

- Collections are immutable except when array syntax is used to set or unset
  items[^immutable]
- Use `Collection<TKey of array-key,TValue>` or `ListCollection<TValue>` with
  values of any type, or extend them to add type-specific behaviour
- Remix `CollectionTrait`, `ListCollectionTrait`, `ReadOnlyCollectionTrait` and
  `ReadOnlyArrayAccessTrait` for custom behaviour, e.g. to create a strictly
  immutable collection class

```php
<?php
// The constructor accepts any iterable, including another collection
$foo = new \Salient\Collection\Collection(['foo']);
// Items can be added using array syntax
$foo[] = 'bar';
// Otherwise, the collection is immutable
$foo = $foo->add('baz');
// 'qux' is printed but the collection is unchanged because the result of the
// expression is discarded
$foo->unshift('qux')->sort()->forEach(
    fn($item) =>
        print "- $item" . \PHP_EOL
);
print 'Items in collection: ' . $foo->count() . \PHP_EOL;
```

Output:

```
- bar
- baz
- foo
- qux
Items in collection: 3
```

## Documentation

[API documentation][api-docs] for `salient/collections` tracks the `main` branch
of the toolkit's [GitHub repository][toolkit], where further documentation can
also be found.

[^immutable]:
    Methods other than `offsetSet()` and `offsetUnset()` return a modified
    instance if the state of the collection changes.

[api-docs]:
  https://salient-labs.github.io/toolkit/namespace-Salient.Collection.html
[toolkit]: https://github.com/salient-labs/toolkit
