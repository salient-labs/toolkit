<?php declare(strict_types=1);

namespace Salient\Contract\Http;

/**
 * @api
 */
interface OneTimePasswordGeneratorInterface
{
    /**
     * @param string $key Base32-encoded shared key.
     */
    public function getPassword(string $key): string;
}
