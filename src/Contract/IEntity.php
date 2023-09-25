<?php declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * An arbitrary entity
 */
interface IEntity extends IResolvable, IConstructible, IReadable, IWritable, IExtensible, HasDateProperties
{
    /**
     * Get the plural form of the entity's class name
     *
     * The return value of `Faculty::plural()`, for example, should be
     * `Faculties`.
     */
    public static function plural(): string;
}
