<?php declare(strict_types=1);

namespace Lkrms\Http\Contract;

interface IAccessToken
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
