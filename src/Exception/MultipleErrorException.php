<?php declare(strict_types=1);

namespace Lkrms\Exception;

use Lkrms\Console\Catalog\ConsoleLevel as Level;
use Lkrms\Console\Catalog\ConsoleMessageType as MessageType;
use Lkrms\Facade\Console;

/**
 * Base class for exceptions that represent multiple errors
 *
 */
abstract class MultipleErrorException extends Exception
{
    /**
     * @var string[]
     */
    protected $Errors = [];

    public function __construct(string $message = '', string ...$errors)
    {
        $this->Errors = $errors;

        parent::__construct($message);
    }

    /**
     * Get the exception's errors
     *
     * @return string[]
     */
    public function getErrors(): array
    {
        return $this->Errors;
    }

    /**
     * Use Console::message() to print "Error: <error>" with level ERROR for
     * each of the exception's errors
     *
     * @return $this
     * @see Console::message()
     */
    public function reportErrors()
    {
        foreach ($this->Errors as $error) {
            Console::message(Level::ERROR, '__Error:__', $error, MessageType::UNFORMATTED);
        }
        return $this;
    }

    public function getDetail(): array
    {
        return [
            'Errors' => implode("\n", $this->Errors),
        ];
    }
}
