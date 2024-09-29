<?php declare(strict_types=1);

namespace Salient\Contract\Core\Entity;

/**
 * @api
 */
interface EntityInterface extends
    Normalisable,
    Constructible,
    Readable,
    Writable,
    Extensible,
    Temporal {}
