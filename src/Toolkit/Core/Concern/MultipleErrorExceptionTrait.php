<?php declare(strict_types=1);

namespace Salient\Core\Concern;

use Salient\Contract\Console\ConsoleMessageType as MessageType;
use Salient\Contract\Core\MessageLevel as Level;
use Salient\Contract\Core\MultipleErrorExceptionInterface;
use Salient\Core\Facade\Console;
use Salient\Utility\Arr;
use Salient\Utility\Format;
use Salient\Utility\Str;

/**
 * @phpstan-require-implements MultipleErrorExceptionInterface
 */
trait MultipleErrorExceptionTrait
{
    protected string $MessageWithoutErrors;
    /** @var string[] */
    protected array $Errors;
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
                $this->HasUnreportedErrors = false;
                break;

            case 1:
                $separator = ': ';
                $append = $errors[0];
                // No break
            default:
                $message = Arr::implode(
                    $separator ?? ":\n",
                    [$message, $append ?? rtrim(Format::list($errors))],
                    ''
                );
                break;
        }

        parent::__construct($message);
    }

    public function getMessageWithoutErrors(): string
    {
        return Str::coalesce($this->MessageWithoutErrors, $this->message);
    }

    /**
     * @return string[]
     */
    public function getErrors(): array
    {
        return $this->Errors;
    }

    /**
     * @inheritDoc
     */
    public function reportErrors()
    {
        foreach ($this->Errors as $error) {
            Console::message(Level::ERROR, '__Error:__', $error, MessageType::UNFORMATTED);
        }
        $this->HasUnreportedErrors = false;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function hasUnreportedErrors(): bool
    {
        return $this->HasUnreportedErrors;
    }

    /**
     * @inheritDoc
     */
    public function getMetadata(): array
    {
        return [
            'Errors' => $this->Errors ? Format::list($this->Errors) : "<none>\n",
        ];
    }
}
