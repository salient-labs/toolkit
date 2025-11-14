<?php declare(strict_types=1);

namespace Salient\Contract;

/**
 * @api
 */
interface HasHmacHashAlgorithm
{
    public const ALGORITHM_SHA1 = 'sha1';
    public const ALGORITHM_SHA256 = 'sha256';
    public const ALGORITHM_SHA512 = 'sha512';
}
