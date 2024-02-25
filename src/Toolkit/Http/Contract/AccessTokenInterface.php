<?php declare(strict_types=1);

namespace Salient\Http\Contract;

interface AccessTokenInterface
{
    /**
     * Get the object's token string
     */
    public function getToken(): string;

    /**
     * Get the token's type, e.g. "Bearer"
     */
    public function getType(): string;
}
