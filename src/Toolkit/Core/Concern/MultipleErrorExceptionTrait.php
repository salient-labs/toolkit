<?php declare(strict_types=1);

namespace Salient\Core\Concern;

use Salient\Catalog\Console\ConsoleMessageType as MessageType;
use Salient\Catalog\Core\MessageLevel as Level;
use Salient\Contract\Core\MultipleErrorExceptionInterface;
use Salient\Core\Facade\Console;
use Salient\Core\Utility\Format;
use Salient\Core\Utility\Get;

/**
 * Implements MultipleErrorExceptionInterface
 *
 * @see MultipleErrorExceptionInterface
 */
trait MultipleErrorExceptionTrait
{
    protected ?string $MessageWithoutErrors = null;

    /**
     * @var string[]
     */
    protected array $Errors = [];

    protected bool $HasUnreportedErrors = true;

    public function __construct(
        string $message = '',
        string ...$errors
    ) {
        $message = rtrim($message, ':');
        $this->MessageWithoutErrors = $message;
        $this->Errors = $errors;

        switch (count($errors)) {
            case 0:
                break;

            case 1:
                $message .= ': ' . reset($errors);
                break;

            default:
                $message .= ":\n" . rtrim(Format::list($errors));
                break;
        }

        parent::__construct($message);
    }

    public function getMessageWithoutErrors(): string
    {
        return Get::coalesce($this->MessageWithoutErrors, $this->message);
    }

    /**
     * @return string[]
     */
    public function getErrors(): array
    {
        return $this->Errors;
    }

    /**
     * @return $this
     */
    public function reportErrors()
    {
        foreach ($this->Errors as $error) {
            Console::message(Level::ERROR, '__Error:__', $error, MessageType::UNFORMATTED);
        }
        $this->HasUnreportedErrors = false;
        return $this;
    }

    public function hasUnreportedErrors(): bool
    {
        return $this->HasUnreportedErrors;
    }

    /**
     * @return array<string,string>
     */
    public function getDetail(): array
    {
        return [
            'Errors' => Format::list($this->Errors),
        ];
    }
}
