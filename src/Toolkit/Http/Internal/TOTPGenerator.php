<?php

namespace Salient\Http\Internal;

use Salient\Contract\Http\OneTimePasswordGeneratorInterface;
use Salient\Contract\HasHmacHashAlgorithm;
use Salient\Utility\Str;

/**
 * An [RFC6238] time-based one-time password generator
 *
 * @internal
 */
final class TOTPGenerator implements
    OneTimePasswordGeneratorInterface,
    HasHmacHashAlgorithm
{
    private int $Digits;
    /** @var self::ALGORITHM_* */
    private string $Algorithm;
    private int $TimeStep;
    private int $StartTime;

    /**
     * @param TOTPGenerator::ALGORITHM_* $algorithm
     */
    public function __construct(
        int $digits = 6,
        string $algorithm = TOTPGenerator::ALGORITHM_SHA1,
        int $timeStep = 30,
        int $startTime = 0
    ) {
        $this->Digits = $digits;
        $this->Algorithm = $algorithm;
        $this->TimeStep = $timeStep;
        $this->StartTime = $startTime;
    }

    /**
     * @inheritDoc
     */
    public function getPassword(string $key): string
    {
        // Get time steps between T0 and now as per [RFC6238] Section 4.2
        $X = $this->TimeStep;
        $T0 = $this->StartTime;
        $T = (int) floor((time() - $T0) / $X);

        /** @var string */
        $T = hex2bin(sprintf('%016s', dechex($T)));
        $K = Str::decodeBase32($key, true);

        $hash = hash_hmac($this->Algorithm, $T, $K, true);

        // Use dynamic truncation as per [RFC4226] Section 5.3
        $offset = ord($hash[-1]) & 0xF;

        $binaryCode = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return sprintf(
            '%0' . $this->Digits . 's',
            substr((string) $binaryCode, -$this->Digits),
        );
    }
}
