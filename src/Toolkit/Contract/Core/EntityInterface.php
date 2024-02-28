<?php declare(strict_types=1);

namespace Salient\Core\Contract;

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
