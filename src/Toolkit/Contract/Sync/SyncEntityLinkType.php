<?php declare(strict_types=1);

namespace Salient\Contract\Sync;

use Salient\Core\AbstractEnumeration;

/**
 * Serialized sync entity relationship data formats, inspired by JSON-LD
 *
 * @extends AbstractEnumeration<int>
 */
final class SyncEntityLinkType extends AbstractEnumeration
{
    /**
     * "@type" and "@id"
     *
     * ```php
     * <?php
     * [
     *   '@type' => 'prefix:Foo',
     *   '@id' => 42,
     * ]
     * ```
     */
    public const DEFAULT = 0;

    /**
     * "@id" only (identifier type not preserved)
     *
     * ```php
     * <?php
     * [
     *   '@id' => 'prefix:Foo/42',
     * ]
     * ```
     */
    public const COMPACT = 1;

    /**
     * "@type", "@id", "@name" and "@description" (empty values removed)
     *
     * ```php
     * <?php
     * [
     *   '@type' => 'prefix:Foo',
     *   '@id' => 42,
     *   '@name' => 'Foo',
     * ]
     * ```
     */
    public const FRIENDLY = 2;

    /** @internal */
    public const INTERNAL = -1;
}
