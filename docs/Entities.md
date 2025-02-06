# Entity interfaces

## `Readable`

If a class implements `Readable`:

- `protected` properties covered by `Readable::getReadableProperties()` can be
  read via magic methods
- if `_get<Property>()` is defined, it is called instead of returning the value
  of `<Property>`
- similarly, if `_isset<Property>()` is defined, it is called instead of
  returning `isset(<Property>)`
- if `_get<Property>()` is defined and `_isset<Property>()` is not,
  `isset(<Property>)` is equivalent to `_get<Property>() === null`
- the existence of `_get<Property>()` makes `<Property>` readable, regardless of
  the return value of `Readable::getReadableProperties()`

## `Writable`

If a class implements `Writable`:

- `protected` properties covered by `Writable::getWritableProperties()` can be
  written via magic methods
- if `_set<Property>()` is defined, it is called instead of assigning `$value`
  to `<Property>`
- similarly, if `_unset<Property>()` is defined, it is called instead of using
  `unset(<Property>)`
- if `_set<Property>()` is defined and `_unset<Property>()` is not,
  `unset(<Property>)` is equivalent to `_set<Property>(null)`
- the existence of `_set<Property>()` makes `<Property>` writable, regardless of
  the return value of `Writable::getWritableProperties()`

## `Relatable`

Example:

```php
<?php

use Salient\Contract\Core\Entity\Relatable;

class User implements Relatable { /* ... */ }

class Tag implements Relatable { /* ... */ }

class Post implements Relatable
{
    // ...

    public User $CreatedBy;
    /** @var iterable<Tag> */
    public iterable $Tags;

    // ...

    /**
     * @inheritDoc
     */
    public static function getRelationships(): array
    {
        return [
            'CreatedBy' => [self::ONE_TO_ONE => User::class],
            'Tags' => [self::ONE_TO_MANY => Tag::class],
        ];
    }
}
```
