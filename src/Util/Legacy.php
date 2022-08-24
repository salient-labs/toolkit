<?php

declare(strict_types=1);

namespace Lkrms\Util;

use Lkrms\Concept\Utility;

/**
 * Use the previous names of renamed classes
 *
 */
final class Legacy extends Utility
{
    private const CLASS_ALIASES = [
        \Lkrms\App::class              => \Lkrms\Facade\App::class,
        \Lkrms\App\App::class          => \Lkrms\Facade\App::class,
        \Lkrms\App\AppContainer::class => \Lkrms\Container\AppContainer::class,
        \Lkrms\Assert::class           => \Lkrms\Facade\Assert::class,
        \Lkrms\Cache::class            => \Lkrms\Facade\Cache::class,
        \Lkrms\Cli::class              => \Lkrms\Facade\Cli::class,
        \Lkrms\Cli\Cli::class          => \Lkrms\Facade\Cli::class,
        \Lkrms\Console::class          => \Lkrms\Console\Console::class,
        \Lkrms\Container\DI::class     => \Lkrms\Facade\DI::class,
        \Lkrms\Convert::class          => \Lkrms\Util\Convert::class,
        \Lkrms\Curler::class           => \Lkrms\Curler\Curler::class,
        \Lkrms\Env::class              => \Lkrms\Util\Env::class,
        \Lkrms\Err::class              => \Lkrms\Err\Err::class,
        \Lkrms\File::class             => \Lkrms\Util\File::class,
        \Lkrms\Format::class           => \Lkrms\Util\Format::class,
        \Lkrms\Generate::class         => \Lkrms\Util\Generate::class,
        \Lkrms\Ioc\Ioc::class          => \Lkrms\Facade\DI::class,
        \Lkrms\Reflect::class          => \Lkrms\Util\Reflect::class,
        \Lkrms\Runtime::class          => \Lkrms\Util\Runtime::class,
        \Lkrms\Sql::class              => \Lkrms\Util\Sql::class,
        \Lkrms\Store\Cache::class      => \Lkrms\Facade\Cache::class,
        \Lkrms\Store\Trash::class      => \Lkrms\Facade\Trash::class,
        \Lkrms\Test::class             => \Lkrms\Util\Test::class,
        \Lkrms\Trash::class            => \Lkrms\Facade\Trash::class,
        \Lkrms\Util\Assert::class      => \Lkrms\Facade\Assert::class,

        \Lkrms\Console\ConsoleTarget::class        => \Lkrms\Console\ConsoleTarget\ConsoleTarget::class,
        \Lkrms\Console\ConsoleTarget\Analog::class => \Lkrms\Console\ConsoleTarget\AnalogTarget::class,
        \Lkrms\Console\ConsoleTarget\Logger::class => \Lkrms\Console\ConsoleTarget\LoggerTarget::class,
        \Lkrms\Console\ConsoleTarget\Stream::class => \Lkrms\Console\ConsoleTarget\StreamTarget::class,

        \Lkrms\Core\Contract\IBindable::class               => \Lkrms\Contract\IBindable::class,
        \Lkrms\Core\Contract\IBound::class                  => \Lkrms\Contract\IBound::class,
        \Lkrms\Core\Contract\IConstructible::class          => \Lkrms\Contract\IConstructible::class,
        \Lkrms\Core\Contract\IConvertibleEnumeration::class => \Lkrms\Contract\IConvertibleEnumeration::class,
        \Lkrms\Core\Contract\IEnumeration::class            => \Lkrms\Contract\IEnumeration::class,
        \Lkrms\Core\Contract\IExtensible::class             => \Lkrms\Contract\IExtensible::class,
        \Lkrms\Core\Contract\IGettable::class               => \Lkrms\Contract\IReadable::class,
        \Lkrms\Core\Contract\IProvidable::class             => \Lkrms\Contract\IProvidable::class,
        \Lkrms\Core\Contract\IProvider::class               => \Lkrms\Contract\IProvider::class,
        \Lkrms\Core\Contract\IResolvable::class             => \Lkrms\Contract\IResolvable::class,
        \Lkrms\Core\Contract\ISettable::class               => \Lkrms\Contract\IWritable::class,
        \Lkrms\Core\Contract\ISingular::class               => \Lkrms\Contract\IFacade::class,
        \Lkrms\Core\Mixin\TBound::class                     => \Lkrms\Concern\TBound::class,
        \Lkrms\Core\Mixin\TClassCache::class                => \Lkrms\Concern\HasClassCache::class,
        \Lkrms\Core\Mixin\TConstructible::class             => \Lkrms\Concern\TConstructible::class,
        \Lkrms\Core\Mixin\TExtensible::class                => \Lkrms\Concern\TExtensible::class,
        \Lkrms\Core\Mixin\TFullyGettable::class             => \Lkrms\Concern\TFullyReadable::class,
        \Lkrms\Core\Mixin\TFullySettable::class             => \Lkrms\Concern\TFullyWritable::class,
        \Lkrms\Core\Mixin\TGettable::class                  => \Lkrms\Concern\TReadable::class,
        \Lkrms\Core\Mixin\TPluralClassName::class           => \Lkrms\Concern\ClassNameHasPluralForm::class,
        \Lkrms\Core\Mixin\TProvidable::class                => \Lkrms\Concern\TProvidable::class,
        \Lkrms\Core\Mixin\TResolvable::class                => \Lkrms\Concern\TResolvable::class,
        \Lkrms\Core\Mixin\TSettable::class                  => \Lkrms\Concern\TWritable::class,
        \Lkrms\Core\ConvertibleEnumeration::class           => \Lkrms\Concept\ConvertibleEnumeration::class,
        \Lkrms\Core\Entity\Entity::class                    => \Lkrms\Concept\Entity::class,
        \Lkrms\Core\Entity\ProviderEntity::class            => \Lkrms\Concept\ProviderEntity::class,
        \Lkrms\Core\Enumeration::class                      => \Lkrms\Concept\Enumeration::class,
        \Lkrms\Core\Facade::class                           => \Lkrms\Concept\Facade::class,
        \Lkrms\Core\TypedCollection::class                  => \Lkrms\Concept\TypedCollection::class,
        \Lkrms\Core\Utility::class                          => \Lkrms\Concept\Utility::class,

        \Lkrms\Core\Contract\ConstructorHasNoRequiredParameters::class => \Lkrms\Contract\HasNoRequiredConstructorParameters::class,
    ];

    /**
     * @var bool
     */
    private static $AutoloaderIsRegistered;

    /**
     * Register an autoloader for renamed classes
     *
     * Once registered, the autoloader will create a class alias whenever the
     * previous name of a class is requested, allowing renamed classes to be
     * used without immediately changing code.
     *
     * @return void
     */
    public static function registerAutoloader(): void
    {
        if (self::$AutoloaderIsRegistered)
        {
            return;
        }

        spl_autoload_register([self::class, "autoloader"]);

        self::$AutoloaderIsRegistered = true;
    }

    private static function autoloader(string $className): void
    {
        if ($class = self::CLASS_ALIASES[$className] ?? null)
        {
            class_alias($class, $className);
        }
    }
}
