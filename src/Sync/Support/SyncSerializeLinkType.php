<?php

declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Concept\Enumeration;

/**
 * Serialized sync entity link types
 *
 */
final class SyncSerializeLinkType extends Enumeration
{
    /**
     * "@id" only
     *
     * ```php
     * [
     *   "@id" => "prefix:Entity/1",
     * ]
     * ```
     */
    public const MINIMAL = 0;

    /**
     * "@type", "@id" and "@name" (preserves identifier type)
     *
     * ```php
     * [
     *   "@type" => "prefix:Entity",
     *   "@id"   => 1,
     *   "@name" => "My Entity",
     * ]
     * ```
     */
    public const STANDARD = 1;

    /**
     * "@type", "@id", "@name" and "@description"
     *
     * ```php
     * [
     *   "@type"        => "prefix:Entity",
     *   "@id"          => 1,
     *   "@name"        => "My Entity",
     *   "@description" => "A description of my entity.",
     * ]
     * ```
     */
    public const DETAILED = 2;

    /**
     * @internal
     */
    public const INTERNAL = -1;

}
