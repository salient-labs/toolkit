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
     * "@type" and "@id" (preserves identifier type)
     *
     * ```php
     * [
     *   "@type" => "prefix:Entity",
     *   "@id"   => 1,
     * ]
     * ```
     */
    public const STANDARD = 1;

    /**
     *
     */
    public const DETAILED = 2;

    /**
     * @internal
     */
    public const INTERNAL = -1;

}
