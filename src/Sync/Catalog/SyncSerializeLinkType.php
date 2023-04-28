<?php declare(strict_types=1);

namespace Lkrms\Sync\Catalog;

use Lkrms\Concept\Enumeration;

/**
 * Serialized sync entity link types
 *
 */
final class SyncSerializeLinkType extends Enumeration
{
    /**
     * "@type" and "@id"
     *
     * ```php
     * [
     *   "@type" => "prefix:Entity",
     *   "@id"   => 1,
     * ]
     * ```
     */
    public const DEFAULT = 0;

    /**
     * "@id" only (identifier type not preserved)
     *
     * ```php
     * [
     *   "@id" => "prefix:Entity/1",
     * ]
     * ```
     */
    public const COMPACT = 1;

    /**
     * "@type", "@id", "@name" and "@description" (empty values removed)
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
    public const FRIENDLY = 2;

    /**
     * @internal
     */
    public const INTERNAL = -1;
}
