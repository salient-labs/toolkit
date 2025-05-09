<?php declare(strict_types=1);

namespace Salient\Http\Server;

use Salient\Core\Concern\ImmutableTrait;
use Salient\Http\Message\Response;
use LogicException;

/**
 * @api
 *
 * @template TReturn = mixed
 */
class ServerResponse extends Response
{
    use ImmutableTrait;

    protected bool $HasReturnValue = false;
    /** @var TReturn */
    protected $ReturnValue;

    /**
     * Check if the response has a return value
     */
    public function hasReturnValue(): bool
    {
        return $this->HasReturnValue;
    }

    /**
     * Get the return value applied to the response
     *
     * @return TReturn
     * @throws LogicException if the response does not have a return value.
     */
    public function getReturnValue()
    {
        if (!$this->HasReturnValue) {
            throw new LogicException('No return value');
        }
        return $this->ReturnValue;
    }

    /**
     * Get an instance with the given return value
     *
     * @template T
     *
     * @param T $value
     * @return static<T>
     */
    public function withReturnValue($value = null)
    {
        /** @var static<T> */
        // @phpstan-ignore varTag.nativeType
        $response = $this;
        // @phpstan-ignore salient.property.type, return.type
        return $response
            ->with('HasReturnValue', true)
            ->with('ReturnValue', $value);
    }
}
