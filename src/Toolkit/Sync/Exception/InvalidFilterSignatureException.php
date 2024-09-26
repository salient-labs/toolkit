<?php declare(strict_types=1);

namespace Salient\Sync\Exception;

use Salient\Contract\Sync\Exception\InvalidFilterSignatureExceptionInterface;
use Salient\Contract\Sync\SyncOperation;
use Salient\Utility\Format;
use Salient\Utility\Reflect;

/**
 * @internal
 */
class InvalidFilterSignatureException extends InvalidFilterException implements InvalidFilterSignatureExceptionInterface
{
    /** @var mixed[] */
    protected array $Args;

    /**
     * @param SyncOperation::* $operation
     * @param mixed ...$args
     */
    public function __construct(int $operation, ...$args)
    {
        $this->Args = $args;

        parent::__construct(sprintf(
            'Invalid filter signature for SyncOperation::%s',
            Reflect::getConstantName(SyncOperation::class, $operation),
        ));
    }

    /**
     * @inheritDoc
     */
    public function getMetadata(): array
    {
        return [
            'Args' => Format::list($this->Args),
        ];
    }
}
