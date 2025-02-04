<?php declare(strict_types=1);

namespace Salient\Core\Exception;

use Salient\Contract\Console\ConsoleMessageType as MessageType;
use Salient\Contract\Console\ConsoleWriterInterface;
use Salient\Contract\Core\Exception\MultipleErrorExceptionInterface;
use Salient\Contract\Core\MessageLevel as Level;
use Salient\Utility\Arr;
use Salient\Utility\Format;

/**
 * @api
 *
 * @phpstan-require-implements MultipleErrorExceptionInterface
 */
trait MultipleErrorExceptionTrait
{
    protected string $MessageOnly;
    /** @var string[] */
    protected array $Errors;
    protected bool $HasUnreportedErrors = true;

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
    public function reportErrors(ConsoleWriterInterface $writer): void
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
