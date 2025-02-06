## Builders

### Example

```php
<?php

use Salient\Contract\Core\Buildable;
use Salient\Core\Concern\BuildableTrait;
use Salient\Core\AbstractBuilder;

/**
 * @implements Buildable<OptionBuilder>
 */
class Option implements Buildable
{
    /** @use BuildableTrait<OptionBuilder> */
    use BuildableTrait;

    protected $Long;
    protected $Short;
    protected $ValueName;
    protected $Type;
    protected $ValueType;
    protected $Description;

    /**
     * @internal
     */
    public function __construct(
        $long = null,
        $short = null,
        $valueName = null,
        $type = OptionType::FLAG,
        $valueType = ValueType::BOOLEAN,
        $description = null
    ) {
        $this->Long = $long;
        $this->Short = $short;
        $this->ValueName = $valueName;
        $this->Type = $type;
        $this->ValueType = $valueType;
        $this->Description = $description;
    }
}

/**
 * @method $this long($value)
 * @method $this short($value)
 * @method $this valueName($value)
 * @method $this type($value)
 * @method $this valueType($value)
 * @method $this description($value)
 *
 * @extends AbstractBuilder<Option>
 */
class OptionBuilder extends AbstractBuilder
{
    protected static function getService(): string
    {
        return Option::class;
    }
}

// The following statements are equivalent:

$opt = new Option(
    'out',
    'o',
    'FILE',
    OptionType::VALUE,
    ValueType::STRING,
    'Write output to <FILE>'
);

$opt = Option::build()  // or `$opt = (new OptionBuilder())` if `Option` doesn't implement `Buildable`
    ->long('out')
    ->short('o')
    ->valueName('FILE')
    ->type(OptionType::VALUE)
    ->valueType(ValueType::STRING)
    ->description('Write output to <FILE>')
    ->build();
```
