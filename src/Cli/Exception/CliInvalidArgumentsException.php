<?php declare(strict_types=1);

namespace Lkrms\Cli\Exception;

use Lkrms\Console\ConsoleLevel as Level;
use Lkrms\Facade\Console;

/**
 * Thrown when an invalid command-line argument is given
 *
 */
class CliInvalidArgumentsException extends \Lkrms\Exception\Exception
{
    /**
     * @var string[]
     */
    private $Errors = [];

    public function __construct(string ...$errors)
    {
        $this->Errors = $errors;

        parent::__construct();
    }

    /**
     * @return string[]
     */
    public function getErrors(): array
    {
        return $this->Errors;
    }

    public function reportErrors(): void
    {
        foreach ($this->Errors as $error) {
            Console::message(Level::ERROR, '__Error:__', $error, null, false, false);
        }
        Console::print('', Level::ERROR, false);
    }

    public function getDetail(): array
    {
        return [
            'Errors' => implode("\n", $this->Errors),
        ];
    }
}
