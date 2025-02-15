<?php declare(strict_types=1);

namespace Salient\Core\Exception;

use Salient\Contract\Catalog\MessageLevel as Level;
use Salient\Contract\Console\ConsoleInterface;
use Salient\Contract\Console\ConsoleMessageType as MessageType;
use Salient\Contract\Core\Exception\MultipleErrorException;
use Salient\Utility\Arr;
use Salient\Utility\Format;

/**
 * @api
 *
 * @phpstan-require-implements MultipleErrorException
 */
trait MultipleErrorExceptionTrait
{
    protected string $MessageOnly;
    /** @var string[] */
    protected array $Errors;
    protected bool $HasUnreportedErrors = true;

    /**
     * @api
     */
    public function __construct(string $message = '', string ...$errors)
    {
        $message = rtrim($message, ':');
        $this->MessageOnly = $message;
        $this->Errors = $errors;

        if (!$errors) {
            $this->HasUnreportedErrors = false;
        } elseif (count($errors) === 1) {
            $message = Arr::implode(': ', [$message, $errors[0]], '');
        } else {
            $message = Arr::implode(":\n", [$message, rtrim(Format::list($errors))], '');
        }

        parent::__construct($message);
    }

    /**
     * @inheritDoc
     */
    public function getMessageOnly(): string
    {
        return $this->MessageOnly;
    }

    /**
     * @inheritDoc
     */
    public function getErrors(): array
    {
        return $this->Errors;
    }

    /**
     * @inheritDoc
     */
    public function reportErrors(ConsoleInterface $writer): void
    {
        foreach ($this->Errors as $error) {
            $writer->message('__Error:__', $error, Level::ERROR, MessageType::UNFORMATTED);
        }
        $this->HasUnreportedErrors = false;
    }

    /**
     * @inheritDoc
     */
    public function hasUnreportedErrors(): bool
    {
        return $this->HasUnreportedErrors;
    }
}
