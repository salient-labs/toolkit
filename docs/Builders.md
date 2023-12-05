## Builders

### Example

```php
<?php

use Lkrms\Concept\Builder;
use Lkrms\Concern\HasBuilder;
use Lkrms\Contract\Buildable;

/**
 * @implements Buildable<OptionBuilder>
 */
class Option implements Buildable
{
    use HasBuilder;

    protected $Long;
    protected $Short;
    protected $ValueName;
    protected $Type;
    protected $ValueType;
    protected $Description;

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
 * @extends Builder<Option>
 */
class OptionBuilder extends Builder
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
    ->go();
```
