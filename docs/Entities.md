# Entity interfaces

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
