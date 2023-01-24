<?php declare(strict_types=1);

namespace Lkrms\Cli\Exception;

use Lkrms\Console\ConsoleLevel as Level;
use Lkrms\Facade\Console;
use Lkrms\Facade\Convert;

/**
 * Thrown when invalid command-line arguments are given
 *
 */
class CliArgumentsInvalidException extends \Lkrms\Exception\Exception
{
    /**
     * @var string[]
     */
    private $Errors = [];

    /**
     * @param string|string[] $error
     */
    public function __construct($error = '')
    {
        if ($error) {
            $errors = Convert::toArray($error);
            foreach ($errors as $error) {
                Console::message(Level::ERROR, '__Error:__', $error, null, false, false);
            }
            Console::print('', Level::ERROR, false);
            $this->Errors = $errors;
        }

        parent::__construct();
    }

    public function getDetail(): array
    {
        return [
            'Errors' => implode("\n", $this->Errors),
        ];
    }
}
